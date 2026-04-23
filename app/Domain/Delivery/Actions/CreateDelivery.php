<?php

namespace App\Domain\Delivery\Actions;

use App\Domain\Delivery\Models\Delivery as RecordModel;
use App\Domain\Delivery\Models\DeliveryItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class CreateDelivery
{
    public function __invoke(array $data): array
    {
        $data = $this->normalizeTravelInput($data);

        $validated = Validator::make($data, [
            'customer_id' => 'required|exists:customer_profiles,id',
            // Legacy single-asset column (optional now that delivery_items exists).
            'asset_unit_id' => 'nullable|exists:asset_units,id',
            'work_order_id' => 'nullable|exists:work_orders,id',
            'transaction_id' => 'nullable|exists:transactions,id',
            'subsidiary_id' => 'nullable|exists:subsidiaries,id',
            'location_id' => 'nullable|exists:locations,id',
            'technician_id' => 'nullable|exists:users,id',
            'scheduled_at' => 'required|date',
            'estimated_arrival_at' => 'nullable|date',
            'status' => 'required|in:scheduled,en_route,delivered,cancelled,rescheduled,confirmed',
            'internal_notes' => 'nullable|string|max:5000',
            'customer_notes' => 'nullable|string|max:5000',

            // Delivery destination
            'delivery_to_type' => 'nullable|in:contact_address,delivery_location,custom',
            'delivery_location_id' => 'nullable|exists:delivery_locations,id',
            'contact_address_id' => 'nullable|integer',

            // Address snapshot
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

            // Items (optional at create; may be synced from source instead)
            'items' => 'nullable|array',
            'items.*.asset_unit_id' => 'nullable|exists:asset_units,id',
            'items.*.asset_variant_id' => 'nullable|exists:asset_variants,id',
            'items.*.name' => 'nullable|string|max:500',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ])->validate();

        DeliveryAddressFiller::fill($validated);

        $items = $validated['items'] ?? [];
        unset($validated['items']);

        $skipTravelAutoCompute = $this->hasManualTravelInput($data);

        unset($validated['en_route_at']);

        try {
            return DB::transaction(function () use ($validated, $items, $skipTravelAutoCompute) {
                $record = RecordModel::create(array_merge($validated, [
                    'uuid' => (string) Str::uuid(),
                ]));

                foreach ($items as $index => $row) {
                    DeliveryItem::create([
                        'delivery_id' => $record->id,
                        'type' => 'asset',
                        'asset_unit_id' => $row['asset_unit_id'] ?? null,
                        'asset_variant_id' => $row['asset_variant_id'] ?? null,
                        'name' => $row['name'] ?? 'Asset',
                        'description' => $row['description'] ?? null,
                        'quantity' => $row['quantity'] ?? 1,
                        'unit_price' => $row['unit_price'] ?? 0,
                        'position' => $index,
                    ]);
                }

                if (empty($items)) {
                    if (! empty($validated['transaction_id'])) {
                        (new SyncItemsFromSource)($record, 'transaction', (int) $validated['transaction_id']);
                    } elseif (! empty($validated['work_order_id'])) {
                        (new SyncItemsFromSource)($record, 'work_order', (int) $validated['work_order_id']);
                    }
                }

                $record->load('items');
                $record->syncStatusFromItems();
                $record->save();

                if (! $skipTravelAutoCompute) {
                    app(\App\Domain\Delivery\Actions\ComputeDeliveryTravelEstimates::class)($record);
                }
                $record->save();

                return [
                    'success' => true,
                    'record' => $record->fresh(),
                ];
            });
        } catch (QueryException $e) {
            Log::error('Database query error in CreateDelivery', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateDelivery', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        }
    }

    private function normalizeTravelInput(array $data): array
    {
        if (array_key_exists('time_to_leave_by', $data) && $data['time_to_leave_by'] === '') {
            $data['time_to_leave_by'] = null;
        }
        if (array_key_exists('estimated_travel_duration_seconds', $data) && $data['estimated_travel_duration_seconds'] === '') {
            $data['estimated_travel_duration_seconds'] = null;
        }

        return $data;
    }

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
