<?php

namespace App\Domain\Invoice\Actions;

use App\Domain\Invoice\Models\Invoice as RecordModel;
use App\Enums\Invoice\Status as InvoiceStatus;
use App\Enums\Payments\Currency as PaymentsCurrency;
use App\Enums\Payments\Terms;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdateInvoice
{
    public function __invoke(int $id, array $data): array
    {
        unset($data['items']);
        unset($data['tax_rate'], $data['subtotal'], $data['tax_total'], $data['total']);

        if (array_key_exists('due_at', $data) && $data['due_at'] === '') {
            $data['due_at'] = null;
        }

        $validated = Validator::make($data, [
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'transaction_id' => ['nullable', 'integer'],
            'contract_id' => ['nullable', 'integer'],
            'status' => ['nullable'],
            'currency' => ['nullable', 'string', 'max:3'],
            'payment_term' => ['nullable'],
            'due_at' => ['nullable', 'date'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'billing_address_line1' => ['nullable', 'string', 'max:255'],
            'billing_address_line2' => ['nullable', 'string', 'max:255'],
            'billing_city' => ['nullable', 'string', 'max:255'],
            'billing_state' => ['nullable', 'string', 'max:255'],
            'billing_postal' => ['nullable', 'string', 'max:50'],
            'billing_country' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'fees_total' => ['nullable', 'numeric'],
        ])->validate();

        try {
            $record = RecordModel::with('items')->findOrFail($id);

            $validated['payment_term'] = self::normalizePaymentTerm($validated['payment_term'] ?? null);

            $incomingStatus = InvoiceStatus::tryFromStored($validated['status'] ?? null);
            $validated['status'] = $incomingStatus?->value ?? $record->status;

            $validated['currency'] = PaymentsCurrency::toStoredValue($validated['currency'] ?? null)
                ?? $record->currency
                ?? 'USD';

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

            $feesTotal = array_key_exists('fees_total', $validated)
                ? (float) $validated['fees_total']
                : (float) $record->fees_total;

            $total = round($subtotal + $taxTotal + $feesTotal, 2);
            $amountPaid = (float) $record->amount_paid;
            $amountDue = round(max(0, $total - $amountPaid), 2);

            $validated['fees_total'] = round($feesTotal, 2);
            $validated['subtotal'] = round($subtotal, 2);
            $validated['tax_total'] = round($taxTotal, 2);
            $validated['discount_total'] = round($discountTotal, 2);
            $validated['total'] = $total;
            $validated['amount_due'] = $amountDue;

            $record->update($validated);

            return [
                'success' => true,
                'record' => $record->fresh(),
            ];
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
