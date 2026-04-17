<?php

namespace App\Domain\Transaction\Actions;

use App\Domain\Transaction\Models\Transaction as RecordModel;
use App\Domain\Transaction\Models\TransactionItem;
use App\Domain\Transaction\Support\ComputeTransactionLineTax;
use App\Domain\Transaction\Support\FillTransactionCustomerSnapshot;
use App\Support\TransactionEnumMapper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdateTransaction
{
    public function __invoke(int $id, array $data): array
    {
        $validator = Validator::make($data, [
            'customer_id' => ['sometimes', 'required', 'integer', 'exists:customer_profiles,id'],
            'user_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
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
        $validator->after(function ($validator) use ($data, $id) {
            $effectiveSub = array_key_exists('subsidiary_id', $data)
                ? $data['subsidiary_id']
                : RecordModel::query()->whereKey($id)->value('subsidiary_id');
            $effectiveLoc = array_key_exists('location_id', $data)
                ? $data['location_id']
                : RecordModel::query()->whereKey($id)->value('location_id');
            if (! $effectiveLoc || ! $effectiveSub) {
                return;
            }
            $ok = DB::table('location_subsidiary')
                ->where('location_id', $effectiveLoc)
                ->where('subsidiary_id', $effectiveSub)
                ->exists();
            if (! $ok) {
                $validator->errors()->add('location_id', 'The selected location is not linked to this subsidiary.');
            }
        });
        $validated = $validator->validate();

        $payload = FillTransactionCustomerSnapshot::merge($validated);
        unset(
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

        if (array_key_exists('status', $payload)) {
            $payload['status'] = TransactionEnumMapper::statusToValue($payload['status']);
        }

        $syncItems = array_key_exists('items', $data) && is_array($data['items']);
        $itemsData = $syncItems ? $data['items'] : [];

        try {
            $record = null;

            DB::transaction(function () use ($id, $payload, $syncItems, $itemsData, &$record) {
                $record = RecordModel::findOrFail($id);
                $record->update($payload);

                if ($syncItems) {
                    $this->syncItems($record, $itemsData);
                }
            });

            return [
                'success' => true,
                'record' => $record->fresh(),
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateTransaction', [
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
            Log::error('Unexpected error in UpdateTransaction', [
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

    private function syncItems(RecordModel $record, array $items): void
    {
        $dealRate = floatval($record->tax_rate ?? 0);

        $submittedIds = collect($items)
            ->pluck('id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->toArray();

        // Delete items that were removed (cascade deletes their addons)
        $record->items()->whereNotIn('id', $submittedIds)->delete();

        foreach ($items as $position => $itemData) {
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
                $addonsPreTax += $aBase;
                $addonsTaxSum += ComputeTransactionLineTax::amount($aBase, $aTaxable, $dealRate);
            }

            $lineTotal = $subtotal + $addonsPreTax + $itemTax + $addonsTaxSum;

            $fill = [
                'type' => $itemData['type'] ?? 'line',
                'itemable_type' => $itemData['itemable_type'] ?? null,
                'itemable_id' => $itemData['itemable_id'] ?? null,
                'asset_variant_id' => ! empty($itemData['asset_variant_id']) ? (int) $itemData['asset_variant_id'] : null,
                'asset_unit_id' => ! empty($itemData['asset_unit_id']) ? (int) $itemData['asset_unit_id'] : null,
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
            ];

            $itemId = ! empty($itemData['id']) ? (int) $itemData['id'] : null;

            if ($itemId) {
                $item = TransactionItem::find($itemId);
                if ($item && $item->transaction_id === $record->id) {
                    $item->update($fill);
                } else {
                    $item = $record->items()->create($fill);
                }
            } else {
                $item = $record->items()->create($fill);
            }

            // Replace addons for this item
            $item->addons()->delete();
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
    }
}
