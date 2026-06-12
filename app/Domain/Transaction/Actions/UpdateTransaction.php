<?php

namespace App\Domain\Transaction\Actions;

use App\Domain\AssetOption\Services\PersistAssetOptionSelectionsForLineItem;
use App\Domain\MsoRecord\Support\SyncTransactionMsoFlags;
use App\Domain\Transaction\Models\Transaction as RecordModel;
use App\Domain\Transaction\Models\TransactionItem;
use App\Domain\Transaction\Support\AssertTransactionCanComplete;
use App\Domain\Transaction\Support\AssertTransactionLineItemsEditable;
use App\Domain\Transaction\Support\ComputeTransactionLineTax;
use App\Domain\Transaction\Support\FillTransactionCustomerSnapshot;
use App\Domain\Transaction\Support\RecalculateTransactionTotals;
use App\Domain\Transaction\Support\SyncAssetUnitStatusesFromTransaction;
use App\Domain\Transaction\Support\SyncLinkedDealTaxRate;
use App\Enums\Transaction\TransactionStatus;
use App\Support\TransactionEnumMapper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateTransaction
{
    public function __invoke(int $id, array $data): array
    {
        $updateLinkedInvoiceTax = filter_var($data['update_linked_invoice_tax'] ?? false, FILTER_VALIDATE_BOOLEAN);
        unset($data['update_linked_invoice_tax']);

        $assetUnitStatuses = $data['asset_unit_statuses'] ?? null;
        unset($data['asset_unit_statuses']);

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
            'tax_jurisdiction_code' => ['nullable', 'string', 'max:32'],
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

        if (($payload['status'] ?? null) === TransactionStatus::Completed->value) {
            AssertTransactionCanComplete::validate(
                RecordModel::with(['invoices', 'contract', 'serviceTickets', 'deliveries'])->findOrFail($id)
            );
            $prev = RecordModel::query()->whereKey($id)->first(['won_at', 'closed_at']);
            if ($prev && $prev->won_at === null && ! array_key_exists('won_at', $payload)) {
                $payload['won_at'] = now();
            }
            if (! array_key_exists('closed_at', $payload)) {
                $payload['closed_at'] = now();
            }
        }

        if (($payload['status'] ?? null) === TransactionStatus::Failed->value) {
            $lostAt = RecordModel::query()->whereKey($id)->value('lost_at');
            if ($lostAt === null && ! array_key_exists('lost_at', $payload)) {
                $payload['lost_at'] = now();
            }
        }

        $syncItems = array_key_exists('items', $data) && is_array($data['items']);
        $itemsData = $syncItems ? $data['items'] : [];

        $newStatusValue = $payload['status'] ?? null;
        $terminalStatuses = [
            TransactionStatus::Completed->value,
            TransactionStatus::Failed->value,
            TransactionStatus::Cancelled->value,
        ];
        $shouldSyncUnitStatuses = is_array($assetUnitStatuses)
            && $newStatusValue !== null
            && in_array($newStatusValue, $terminalStatuses, true);

        try {
            $record = null;
            $previousTaxRate = null;
            $previousStatus = null;

            DB::transaction(function () use (
                $id,
                $payload,
                $syncItems,
                $itemsData,
                $assetUnitStatuses,
                $shouldSyncUnitStatuses,
                $newStatusValue,
                $terminalStatuses,
                &$record,
                &$previousTaxRate,
                &$previousStatus,
            ) {
                $record = RecordModel::findOrFail($id);
                $previousTaxRate = (float) ($record->tax_rate ?? 0);
                $previousStatus = $record->status;

                if ($syncItems) {
                    AssertTransactionLineItemsEditable::validate($record);
                }

                if (array_key_exists('tax_rate', $payload)
                    && SyncLinkedDealTaxRate::transactionHasSentInvoice($record)) {
                    $incomingRate = (float) ($payload['tax_rate'] ?? 0);
                    if (abs($incomingRate - $previousTaxRate) > 0.0001) {
                        throw ValidationException::withMessages([
                            'tax_rate' => 'Tax rate cannot be changed after an invoice has been sent to the customer.',
                        ]);
                    }
                }

                $record->update($payload);

                if ($syncItems) {
                    $this->syncItems($record, $itemsData);
                    $record->refresh();
                    RecalculateTransactionTotals::rollupTransaction($record->fresh());
                }

                if (
                    $shouldSyncUnitStatuses
                    && $newStatusValue !== $previousStatus
                    && in_array($newStatusValue, $terminalStatuses, true)
                ) {
                    SyncAssetUnitStatusesFromTransaction::apply($record->fresh(), $assetUnitStatuses);
                }
            });

            $record = $record->fresh();

            if ($record !== null && $record->isCompleted()) {
                SyncTransactionMsoFlags::forTransaction($record);
                $record = $record->fresh();
            }

            if ($updateLinkedInvoiceTax && $record !== null) {
                $newRate = (float) ($record->tax_rate ?? 0);
                if (abs($newRate - (float) $previousTaxRate) > 0.0001) {
                    SyncLinkedDealTaxRate::applyRateToDraftInvoices($record, $newRate);
                    $record = $record->fresh();
                }
            }

            return [
                'success' => true,
                'record' => $record,
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
            $baseSubtotal = max(0, $qty * $price - $discount);
            $itemTaxable = ComputeTransactionLineTax::boolish($itemData['taxable'] ?? true);
            $itemTax = ComputeTransactionLineTax::amount($baseSubtotal, $itemTaxable, $dealRate);

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

            $preliminaryLineTotal = $baseSubtotal + $addonsPreTax + $itemTax + $addonsTaxSum;

            $fill = [
                'type' => $itemData['type'] ?? 'line',
                'itemable_type' => $itemData['itemable_type'] ?? null,
                'itemable_id' => $itemData['itemable_id'] ?? null,
                'name' => $itemData['name'] ?? 'Item',
                'description' => $itemData['description'] ?? null,
                'quantity' => $qty,
                'unit_price' => $price,
                'discount' => $discount,
                'subtotal' => $baseSubtotal,
                'taxable' => $itemTaxable,
                'tax_rate' => $dealRate > 0 ? $dealRate : null,
                'tax_amount' => $itemTax > 0 ? $itemTax : null,
                'total' => $preliminaryLineTotal,
                'position' => $itemData['position'] ?? $position,
                'asset_options_fill_mode' => (($itemData['asset_options_fill_mode'] ?? 'staff') === 'customer') ? 'customer' : 'staff',
            ];

            // Only touch FK columns when the client sent those keys; omitting them avoids overwriting with null
            // when JSON/Inertia drops null keys from the payload.
            if (array_key_exists('asset_variant_id', $itemData)) {
                $fill['asset_variant_id'] = ($itemData['asset_variant_id'] === '' || $itemData['asset_variant_id'] === null)
                    ? null
                    : (int) $itemData['asset_variant_id'];
            }
            if (array_key_exists('asset_unit_id', $itemData)) {
                $fill['asset_unit_id'] = ($itemData['asset_unit_id'] === '' || $itemData['asset_unit_id'] === null)
                    ? null
                    : (int) $itemData['asset_unit_id'];
            }

            $itemId = ! empty($itemData['id']) ? (int) $itemData['id'] : null;

            if ($itemId) {
                $item = TransactionItem::find($itemId);
                if ($item && $item->parent_type === RecordModel::class && (int) $item->parent_id === $record->id) {
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

            if (($itemData['type'] ?? '') === 'asset') {
                $linePayload = [
                    'itemable_type' => $itemData['itemable_type'] ?? null,
                    'itemable_id' => $itemData['itemable_id'] ?? null,
                    'asset_variant_id' => $itemData['asset_variant_id'] ?? null,
                    'asset_options_fill_mode' => (($itemData['asset_options_fill_mode'] ?? 'staff') === 'customer') ? 'customer' : 'staff',
                ];
                app(PersistAssetOptionSelectionsForLineItem::class)(
                    $item,
                    $linePayload,
                    is_array($itemData['selected_asset_options'] ?? null) ? $itemData['selected_asset_options'] : [],
                    null,
                    'Deal line '.(((int) $position) + 1),
                );
            }

            RecalculateTransactionTotals::finalizeLineItem(
                $item,
                $dealRate,
                $baseSubtotal,
                $itemTax,
                $addonsPreTax,
                $addonsTaxSum,
            );
        }
    }
}
