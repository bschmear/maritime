<?php

namespace App\Domain\Delivery\Actions;

use App\Domain\Delivery\Exceptions\DeliveryFleetConflictException;
use App\Domain\Delivery\Models\Delivery as RecordModel;
use App\Domain\Delivery\Models\DeliveryItem;
use App\Domain\Delivery\Support\DeliveryFleetConflictGuard;
use App\Domain\Delivery\Support\DeliveryFleetFieldValidator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdateDelivery
{
    public function __invoke(int $id, array $data): array
    {
        $data = $this->normalizeTravelInput($data);

        $validator = Validator::make($data, [
            'customer_id' => 'sometimes|required|exists:customer_profiles,id',
            'asset_unit_id' => 'nullable|exists:asset_units,id',
            'work_order_id' => 'nullable|exists:work_orders,id',
            'transaction_id' => 'nullable|exists:transactions,id',
            'technician_id' => 'nullable|exists:users,id',
            'subsidiary_id' => 'nullable|exists:subsidiaries,id',
            'location_id' => 'nullable|exists:locations,id',
            'scheduled_at' => 'sometimes|required|date',
            'estimated_arrival_at' => 'nullable|date',
            'status' => 'sometimes|required|in:scheduled,en_route,delivered,cancelled,rescheduled,confirmed',
            'internal_notes' => 'nullable|string|max:5000',
            'customer_notes' => 'nullable|string|max:5000',

            'delivery_to_type' => 'nullable|in:contact_address,delivery_location,custom',
            'delivery_location_id' => 'nullable|exists:delivery_locations,id',
            'contact_address_id' => 'nullable|integer',

            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',

            'time_to_leave_by' => 'nullable|date',
            'estimated_travel_duration_seconds' => 'nullable|integer|min:0|max:864000',

            'fleet_truck_id' => 'nullable|integer|exists:fleets,id',
            'fleet_trailer_id' => 'nullable|integer|exists:fleets,id',
            'delivery_duration_minutes' => 'nullable|integer|min:1|max:32767',
            'swap_with_delivery_id' => 'nullable|integer|exists:deliveries,id',

            'items' => 'nullable|array',
            'items.*.id' => 'nullable|integer',
            'items.*.asset_unit_id' => 'nullable|exists:asset_units,id',
            'items.*.asset_variant_id' => 'nullable|exists:asset_variants,id',
            'items.*.name' => 'nullable|string|max:500',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);
        $validator->after(function ($v) use ($data) {
            DeliveryFleetFieldValidator::validateFleetRows($v, $data);
        });
        $validated = $validator->validate();

        DeliveryAddressFiller::fill($validated);

        $swapWithDeliveryId = isset($validated['swap_with_delivery_id']) ? (int) $validated['swap_with_delivery_id'] : null;
        unset($validated['swap_with_delivery_id']);

        $items = $validated['items'] ?? null;
        unset($validated['items']);

        $skipTravelAutoCompute = $this->hasManualTravelInput($data);

        unset($validated['en_route_at']);

        try {
            return DB::transaction(function () use ($id, $validated, $items, $skipTravelAutoCompute, $swapWithDeliveryId) {
                $record = RecordModel::findOrFail($id);
                $previousTransactionId = $record->transaction_id;
                $previousWorkOrderId = $record->work_order_id;
                $oldStatus = $record->status;

                if (isset($validated['status']) && $validated['status'] === 'en_route' && $oldStatus !== 'en_route') {
                    $validated['en_route_at'] = now();
                    if ($record->estimated_travel_duration_seconds) {
                        $validated['estimated_arrival_at'] = now()->addSeconds((int) $record->estimated_travel_duration_seconds);
                    }
                }

                $record->update($validated);

                if (is_array($items)) {
                    $this->syncItems($record, $items);
                } else {
                    // If the caller toggled the source without sending items, re-sync.
                    $newTx = $record->transaction_id;
                    $newWo = $record->work_order_id;
                    if ($newTx && $newTx !== $previousTransactionId) {
                        (new SyncItemsFromSource)($record, 'transaction', (int) $newTx);
                    } elseif ($newWo && $newWo !== $previousWorkOrderId) {
                        (new SyncItemsFromSource)($record, 'work_order', (int) $newWo);
                    }
                }

                $record->load('items');
                $record->syncStatusFromItems();
                $record->save();

                if (! $skipTravelAutoCompute
                    && ! in_array($record->status, ['en_route', 'delivered', 'cancelled'], true)) {
                    app(\App\Domain\Delivery\Actions\ComputeDeliveryTravelEstimates::class)($record);
                }
                $record->save();

                $onlyQuickStatus = count($validated) === 1 && array_key_exists('status', $validated);
                if (! $onlyQuickStatus) {
                    $record = DeliveryFleetConflictGuard::assertResolved($record->fresh(), $swapWithDeliveryId);
                }

                return [
                    'success' => true,
                    'record' => $record->fresh(),
                ];
            });
        } catch (DeliveryFleetConflictException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'conflicts' => $e->conflicts,
                'record' => null,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateDelivery', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $validated,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdateDelivery', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $validated,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        }
    }

    /**
     * Diff the incoming items against existing ones: update kept rows, create new ones,
     * delete any existing row whose id isn't present in the payload. Preserves delivered_at.
     */
    private function syncItems(RecordModel $record, array $items): void
    {
        $keep = [];

        foreach ($items as $index => $row) {
            $payload = [
                'delivery_id' => $record->id,
                'type' => $row['type'] ?? 'asset',
                'asset_unit_id' => $row['asset_unit_id'] ?? null,
                'asset_variant_id' => $row['asset_variant_id'] ?? null,
                'name' => $row['name'] ?? 'Asset',
                'description' => $row['description'] ?? null,
                'quantity' => $row['quantity'] ?? 1,
                'unit_price' => $row['unit_price'] ?? 0,
                'position' => $index,
            ];

            if (! empty($row['id'])) {
                $existing = DeliveryItem::where('delivery_id', $record->id)
                    ->whereKey($row['id'])
                    ->first();

                if ($existing) {
                    $existing->fill($payload)->save();
                    $keep[] = $existing->id;

                    continue;
                }
            }

            $created = DeliveryItem::create($payload);
            $keep[] = $created->id;
        }

        DeliveryItem::where('delivery_id', $record->id)
            ->whereNotIn('id', $keep)
            ->delete();
    }

    private function normalizeTravelInput(array $data): array
    {
        if (array_key_exists('time_to_leave_by', $data) && $data['time_to_leave_by'] === '') {
            $data['time_to_leave_by'] = null;
        }
        if (array_key_exists('estimated_travel_duration_seconds', $data) && $data['estimated_travel_duration_seconds'] === '') {
            $data['estimated_travel_duration_seconds'] = null;
        }
        foreach (['fleet_truck_id', 'fleet_trailer_id'] as $k) {
            if (array_key_exists($k, $data) && ($data[$k] === '' || $data[$k] === false)) {
                $data[$k] = null;
            }
        }
        if (array_key_exists('delivery_duration_minutes', $data) && ($data['delivery_duration_minutes'] === '' || $data['delivery_duration_minutes'] === null)) {
            $data['delivery_duration_minutes'] = null;
        }

        return $data;
    }

    /**
     * When the user leaves both fields empty, we still run Google auto-compute when possible.
     * If either field is set, do not overwrite with Google.
     */
    private function hasManualTravelInput(array $data): bool
    {
        $tt = $data['time_to_leave_by'] ?? null;
        if (is_string($tt)) {
            $tt = trim($tt) === '' ? null : $tt;
        }
        if ($tt !== null && $tt !== '') {
            return true;
        }
        if (! array_key_exists('estimated_travel_duration_seconds', $data)) {
            return false;
        }
        $sec = $data['estimated_travel_duration_seconds'];
        if ($sec === null || $sec === '') {
            return false;
        }

        return is_numeric($sec);
    }
}
