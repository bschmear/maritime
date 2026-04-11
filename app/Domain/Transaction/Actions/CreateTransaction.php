<?php

namespace App\Domain\Transaction\Actions;

use App\Domain\Transaction\Models\Transaction as RecordModel;
use App\Domain\Transaction\Support\ComputeTransactionLineTax;
use App\Domain\Transaction\Support\FillTransactionCustomerSnapshot;
use App\Support\TransactionEnumMapper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateTransaction
{
    public function __invoke(array $data): array
    {
        $validator = Validator::make($data, [
            'customer_id' => ['required', 'integer', 'exists:customer_profiles,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'estimate_id' => ['nullable', 'integer', 'exists:estimates,id'],
            'opportunity_id' => ['nullable', 'integer', 'exists:opportunities,id'],
            'subsidiary_id' => ['nullable', 'integer', 'exists:subsidiaries,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'status' => ['nullable'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:255'],
            'billing_address_line1' => ['nullable', 'string', 'max:255'],
            'billing_address_line2' => ['nullable', 'string', 'max:255'],
            'billing_city' => ['nullable', 'string', 'max:255'],
            'billing_state' => ['nullable', 'string', 'max:255'],
            'billing_postal' => ['nullable', 'string', 'max:255'],
            'billing_country' => ['nullable', 'string', 'max:255'],
            'billing_latitude' => ['nullable', 'numeric'],
            'billing_longitude' => ['nullable', 'numeric'],
            'title' => ['nullable', 'string', 'max:255'],
            'subtotal' => ['nullable', 'numeric'],
            'tax_rate' => ['nullable', 'numeric'],
            'tax_jurisdiction' => ['nullable', 'string', 'max:255'],
            'tax_total' => ['nullable', 'numeric'],
            'discount_total' => ['nullable', 'numeric'],
            'fees_total' => ['nullable', 'numeric'],
            'total' => ['nullable', 'numeric'],
            'currency' => ['nullable', 'string', 'max:3'],
            'notes' => ['nullable', 'string'],
            'loss_reason_category' => ['nullable', 'string', 'max:255'],
            'loss_reason' => ['nullable', 'string'],
            'needs_contract' => ['sometimes', 'boolean'],
            'needs_delivery' => ['sometimes', 'boolean'],
        ]);
        $validator->after(function ($validator) use ($data) {
            $locId = $data['location_id'] ?? null;
            $subId = $data['subsidiary_id'] ?? null;
            if (! $locId || ! $subId) {
                return;
            }
            $ok = DB::table('location_subsidiary')
                ->where('location_id', $locId)
                ->where('subsidiary_id', $subId)
                ->exists();
            if (! $ok) {
                $validator->errors()->add('location_id', 'The selected location is not linked to this subsidiary.');
            }
        });
        $validated = $validator->validate();

        $payload = FillTransactionCustomerSnapshot::merge($validated);
        $payload['status'] = TransactionEnumMapper::statusToValue($payload['status'] ?? null);
        $payload['currency'] = $payload['currency'] ?? 'USD';

        unset(
            $payload['id'],
            $payload['uuid'],
            $payload['sequence'],
            $payload['created_at'],
            $payload['updated_at'],
            $payload['customer'],
            $payload['user'],
            $payload['estimate'],
            $payload['opportunity'],
            $payload['subsidiary'],
            $payload['location'],
        );

        $itemsData = is_array($data['items'] ?? null) ? $data['items'] : [];

        try {
            $record = DB::transaction(function () use ($payload, $itemsData) {
                $transaction = RecordModel::create($payload);

                $dealRate = floatval($transaction->tax_rate ?? 0);

                foreach ($itemsData as $position => $itemData) {
                    $qty = floatval($itemData['quantity'] ?? 1);
                    $price = floatval($itemData['unit_price'] ?? 0);
                    $discount = floatval($itemData['discount'] ?? 0);
                    $subtotal = max(0, $qty * $price - $discount);
                    $itemTaxable = ComputeTransactionLineTax::boolish($itemData['taxable'] ?? true);
                    $itemTax = ComputeTransactionLineTax::amount($subtotal, $itemTaxable, $dealRate);

                    $addonsPreTax = 0.0;
                    $addonsTaxSum = 0.0;

                    foreach ($itemData['addons'] ?? [] as $addonData) {
                        $aQty = floatval($addonData['quantity'] ?? 1);
                        $aPrice = floatval($addonData['price'] ?? 0);
                        $aBase = $aQty * $aPrice;
                        $aTaxable = ComputeTransactionLineTax::boolish($addonData['taxable'] ?? true);
                        $aTax = ComputeTransactionLineTax::amount($aBase, $aTaxable, $dealRate);
                        $addonsPreTax += $aBase;
                        $addonsTaxSum += $aTax;
                    }

                    $lineTotal = $subtotal + $addonsPreTax + $itemTax + $addonsTaxSum;

                    $item = $transaction->items()->create([
                        'type' => $itemData['type'] ?? 'line',
                        'itemable_type' => $itemData['itemable_type'] ?? null,
                        'itemable_id' => $itemData['itemable_id'] ?? null,
                        'name' => $itemData['name'] ?? 'Item',
                        'description' => $itemData['description'] ?? null,
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'discount' => $discount,
                        'subtotal' => $subtotal,
                        'taxable' => $itemTaxable,
                        'tax_rate' => $dealRate > 0 ? $dealRate : null,
                        'tax_amount' => $itemTax > 0 ? $itemTax : null,
                        'total' => $lineTotal,
                        'position' => $itemData['position'] ?? $position,
                    ]);

                    foreach ($itemData['addons'] ?? [] as $addonData) {
                        $aQty = floatval($addonData['quantity'] ?? 1);
                        $aPrice = floatval($addonData['price'] ?? 0);
                        $aBase = $aQty * $aPrice;
                        $aTaxable = ComputeTransactionLineTax::boolish($addonData['taxable'] ?? true);
                        $aTax = ComputeTransactionLineTax::amount($aBase, $aTaxable, $dealRate);

                        $item->addons()->create([
                            'addon_id' => $addonData['addon_id'] ?? null,
                            'name' => $addonData['name'] ?? null,
                            'price' => $addonData['price'] ?? 0,
                            'quantity' => (int) ($addonData['quantity'] ?? 1),
                            'taxable' => $aTaxable,
                            'tax_rate' => $dealRate > 0 ? $dealRate : null,
                            'tax_amount' => $aTax > 0 ? $aTax : null,
                            'notes' => $addonData['notes'] ?? null,
                        ]);
                    }
                }

                return $transaction;
            });

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateTransaction', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateTransaction', [
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
}
