<?php

namespace App\Domain\Invoice\Actions;

use App\Domain\Invoice\Models\Invoice as RecordModel;
use App\Domain\Invoice\Support\FlattenTransactionItemsForInvoice;
use App\Domain\Invoice\Support\InvoiceBillingAddressRules;
use App\Domain\Invoice\Support\InvoicePaymentFields;
use App\Domain\Invoice\Support\ReplaceInvoiceLineItems;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Support\SyncLinkedDealTaxRate;
use App\Enums\Invoice\Status as InvoiceStatus;
use App\Enums\Payments\Currency as PaymentsCurrency;
use App\Enums\Payments\Terms;
use App\Services\Payments\QuickBooksAccountingService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateInvoice
{
    public function __invoke(int $id, array $data): array
    {
        $updateQuickbooks = filter_var($data['update_quickbooks'] ?? false, FILTER_VALIDATE_BOOLEAN);
        unset($data['update_quickbooks']);

        $updateLinkedTransactionTax = filter_var($data['update_linked_transaction_tax'] ?? false, FILTER_VALIDATE_BOOLEAN);
        unset($data['update_linked_transaction_tax']);

        $items = is_array($data['items'] ?? null) ? $data['items'] : null;
        unset($data['items']);
        unset($data['subtotal'], $data['tax_total'], $data['total']);

        if (array_key_exists('due_at', $data) && $data['due_at'] === '') {
            $data['due_at'] = null;
        }

        $validated = Validator::make($data, array_merge([
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'transaction_id' => ['nullable', 'integer'],
            'contract_id' => ['nullable', 'integer'],
            'work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
            'subsidiary_id' => ['nullable', 'integer', 'exists:subsidiaries,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'status' => ['nullable'],
            'currency' => ['nullable', 'string', 'max:3'],
            'payment_term' => ['nullable'],
            'due_at' => ['nullable', 'date'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'fees_total' => ['nullable', 'numeric'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_jurisdiction' => ['nullable', 'string', 'max:255'],
            'tax_jurisdiction_code' => ['nullable', 'string', 'max:32'],
        ], InvoicePaymentFields::validationRules(), InvoiceBillingAddressRules::rules()), InvoiceBillingAddressRules::messages())
            ->validate();

        $paymentNormalized = InvoicePaymentFields::normalizeForPersistence(
            [
                'allowed_methods' => $validated['allowed_methods'] ?? null,
                'surcharge_percent' => $validated['surcharge_percent'] ?? null,
                'allow_partial_payment' => $validated['allow_partial_payment'] ?? null,
                'minimum_partial_amount' => $validated['minimum_partial_amount'] ?? null,
            ],
            $data
        );

        foreach (['allowed_methods', 'surcharge_percent', 'allow_partial_payment', 'minimum_partial_amount'] as $k) {
            unset($validated[$k]);
        }

        try {
            $record = RecordModel::with('items')->findOrFail($id);

            if (SyncLinkedDealTaxRate::invoiceIsTaxLocked($record) && $items !== null) {
                $oldItems = $record->items->keyBy('id');
                foreach ($items as $item) {
                    $itemId = $item['id'] ?? null;
                    if (! $itemId || ! $oldItems->has($itemId)) {
                        continue;
                    }
                    $old = $oldItems->get($itemId);
                    $newRate = (float) ($item['tax_rate'] ?? 0);
                    $oldRate = (float) ($old->tax_rate ?? 0);
                    if (abs($newRate - $oldRate) > 0.0001) {
                        throw ValidationException::withMessages([
                            'tax_rate' => 'Tax cannot be changed after this invoice has been sent to the customer.',
                        ]);
                    }
                }
            }

            $validated['payment_term'] = self::normalizePaymentTerm($validated['payment_term'] ?? null);

            $incomingStatus = InvoiceStatus::tryFromStored($validated['status'] ?? null);
            $validated['status'] = $incomingStatus?->value ?? $record->status;

            $validated['currency'] = PaymentsCurrency::toStoredValue($validated['currency'] ?? null)
                ?? $record->currency
                ?? 'USD';

            if ($items !== null) {
                ReplaceInvoiceLineItems::apply($record, $items);
                $record->load('items');
            }

            $feesTotal = array_key_exists('fees_total', $validated)
                ? (float) $validated['fees_total']
                : (float) $record->fees_total;

            if ($items !== null) {
                $totals = FlattenTransactionItemsForInvoice::rollupTotals($items, $feesTotal);
                $subtotal = $totals['subtotal'];
                $discountTotal = $totals['discount_total'];
                $taxTotal = $totals['tax_total'];
                $total = $totals['total'];
            } else {
                $subtotal = 0.0;
                $discountTotal = 0.0;
                $taxTotal = 0.0;

                foreach ($record->items as $item) {
                    $qty = (float) $item->quantity;
                    $price = (float) $item->unit_price;
                    $discount = (float) $item->discount;
                    $itemSub = ($qty * $price) - $discount;
                    $subtotal += $itemSub;
                    $discountTotal += $discount;

                    if ($item->taxable && $item->tax_rate) {
                        $taxTotal += round($itemSub * ((float) $item->tax_rate / 100), 2);
                    }
                }

                $total = round($subtotal + $taxTotal + $feesTotal, 2);
            }

            $amountPaid = (float) $record->amount_paid;
            $amountDue = round(max(0, $total - $amountPaid), 2);

            $validated['fees_total'] = round($feesTotal, 2);
            $validated['subtotal'] = round($subtotal, 2);
            $validated['tax_total'] = round($taxTotal, 2);
            $validated['discount_total'] = round($discountTotal, 2);
            $validated['total'] = $total;
            $validated['amount_due'] = $amountDue;

            $record->update(array_merge($validated, $paymentNormalized));

            $record = $record->fresh(['items', 'contact']);

            $qboWarning = null;
            if ($updateQuickbooks && $record->quickbooks_invoice_id) {
                $qboResult = app(QuickBooksAccountingService::class)->updateInvoice($record);
                if (! ($qboResult['success'] ?? false)) {
                    $qboWarning = $qboResult['message'] ?? 'QuickBooks update failed.';
                }
            }

            if ($qboWarning) {
                session()->flash('invoice_qbo_warning', $qboWarning);
            }

            if ($updateLinkedTransactionTax && $record->transaction_id) {
                $rate = SyncLinkedDealTaxRate::resolveRateFromInvoiceItems($record);
                $transaction = Transaction::query()->find($record->transaction_id);
                if ($transaction !== null) {
                    SyncLinkedDealTaxRate::applyRateToTransaction($transaction, $rate);
                }
            }

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateInvoice', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdateInvoice', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        }
    }

    private static function normalizePaymentTerm(mixed $value): string
    {
        if ($value instanceof Terms) {
            return $value->value;
        }

        if ($value === null || $value === '') {
            return Terms::DueOnReceipt->value;
        }

        if (is_numeric($value)) {
            $id = (int) $value;
            foreach (Terms::cases() as $case) {
                if ($case->id() === $id) {
                    return $case->value;
                }
            }
        }

        $str = is_string($value) ? trim($value) : (string) $value;

        return Terms::tryFrom($str)?->value ?? Terms::DueOnReceipt->value;
    }
}
