<?php

namespace App\Domain\Invoice\Actions;

use App\Domain\Invoice\Models\Invoice as RecordModel;
use App\Domain\Invoice\Support\InvoicePaymentFields;
use App\Domain\InvoiceItem\Models\InvoiceItem;
use App\Enums\Invoice\Status as InvoiceStatus;
use App\Enums\Payments\Currency as PaymentsCurrency;
use App\Enums\Payments\Terms;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateInvoice
{
    public function __invoke(array $data): array
    {
        // Extract items before validation to avoid unknown-field errors.
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        unset($data['items']);

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
            'billing_address_line1' => ['nullable', 'string', 'max:255'],
            'billing_address_line2' => ['nullable', 'string', 'max:255'],
            'billing_city' => ['nullable', 'string', 'max:255'],
            'billing_state' => ['nullable', 'string', 'max:255'],
            'billing_postal' => ['nullable', 'string', 'max:50'],
            'billing_country' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'fees_total' => ['nullable', 'numeric'],
        ], InvoicePaymentFields::validationRules()))->validate();

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

        $validated['payment_term'] = self::normalizePaymentTerm($validated['payment_term'] ?? null);

        $validated['status'] = InvoiceStatus::tryFromStored($validated['status'] ?? 'draft')?->value ?? 'draft';

        $validated['currency'] = PaymentsCurrency::toStoredValue($validated['currency'] ?? null) ?? 'USD';

        try {
            // Calculate invoice-level totals from line items.
            $subtotal = 0.0;
            $discountTotal = 0.0;
            $taxTotal = 0.0;

            foreach ($items as $item) {
                $qty = (float) ($item['quantity'] ?? 1);
                $price = (float) ($item['unit_price'] ?? 0);
                $discount = (float) ($item['discount'] ?? 0);
                $itemSub = ($qty * $price) - $discount;

                $subtotal += $itemSub;
                $discountTotal += $discount;

                if (! empty($item['taxable']) && ! empty($item['tax_rate'])) {
                    $taxTotal += round($itemSub * ((float) $item['tax_rate'] / 100), 2);
                }
            }

            $feesTotal = (float) ($validated['fees_total'] ?? 0);
            $total = $subtotal + $taxTotal + $feesTotal;

            $payload = array_merge($validated, $paymentNormalized, [
                'status' => $validated['status'] ?? 'draft',
                'currency' => $validated['currency'] ?? 'USD',
                'subtotal' => round($subtotal, 2),
                'tax_total' => round($taxTotal, 2),
                'discount_total' => round($discountTotal, 2),
                'fees_total' => round($feesTotal, 2),
                'total' => round($total, 2),
                'amount_due' => round($total, 2),
            ]);

            $record = RecordModel::create($payload);

            // Create line items — InvoiceItem::booted() auto-calculates subtotal/tax_amount/total.
            foreach ($items as $position => $item) {
                InvoiceItem::create([
                    'invoice_id' => $record->id,
                    'transaction_item_id' => $item['transaction_item_id'] ?? null,
                    'itemable_type' => $item['itemable_type'] ?? null,
                    'itemable_id' => isset($item['itemable_id']) ? (int) $item['itemable_id'] : null,
                    'asset_variant_id' => ! empty($item['asset_variant_id']) ? (int) $item['asset_variant_id'] : null,
                    'asset_unit_id' => ! empty($item['asset_unit_id']) ? (int) $item['asset_unit_id'] : null,
                    'name' => $item['name'] ?? '',
                    'description' => $item['description'] ?? null,
                    'quantity' => (float) ($item['quantity'] ?? 1),
                    'unit_price' => (float) ($item['unit_price'] ?? 0),
                    'cost' => (float) ($item['cost'] ?? 0),
                    'discount' => (float) ($item['discount'] ?? 0),
                    'is_warranty' => (bool) ($item['is_warranty'] ?? false),
                    'warranty_type' => $item['warranty_type'] ?? null,
                    'billable_to' => $item['billable_to'] ?? 'customer',
                    'taxable' => (bool) ($item['taxable'] ?? false),
                    'tax_rate' => (float) ($item['tax_rate'] ?? 0),
                    'position' => $item['position'] ?? $position,
                ]);
            }

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateInvoice', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateInvoice', [
                'error' => $e->getMessage(),
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
