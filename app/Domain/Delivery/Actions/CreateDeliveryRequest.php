<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Actions;

use App\Domain\Delivery\Models\Delivery as RecordModel;
use App\Domain\Delivery\Models\DeliveryItem;
use App\Domain\Delivery\Support\DeliveryApproverResolver;
use App\Domain\Delivery\Support\DeliveryFleetFieldValidator;
use App\Domain\SystemLog\Support\LogSystemEvent;
use App\Enums\System\SystemLogAction;
use App\Domain\Location\Models\Location;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class CreateDeliveryRequest
{
    public function __invoke(array $data): array
    {
        $data = $this->normalizeTravelInput($data);
        $data['status'] = 'requested';

        $validator = Validator::make($data, [
            'customer_id' => 'required|exists:customer_profiles,id',
            'asset_unit_id' => 'nullable|exists:asset_units,id',
            'work_order_id' => 'nullable|exists:work_orders,id',
            'transaction_id' => 'nullable|exists:transactions,id',
            'subsidiary_id' => 'nullable|exists:subsidiaries,id',
            'location_id' => 'required|exists:locations,id',
            'technician_id' => 'nullable|exists:users,id',
            'scheduled_at' => 'required|date',
            'estimated_arrival_at' => 'nullable|date',
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
            'estimated_return_travel_duration_seconds' => 'nullable|integer|min:0|max:864000',
            'fleet_truck_id' => 'nullable|integer|exists:fleets,id',
            'fleet_trailer_id' => 'nullable|integer|exists:fleets,id',
            'delivery_duration_minutes' => 'nullable|integer|min:1|max:32767',
            'items' => 'nullable|array',
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

        $location = Location::query()->find((int) $validated['location_id']);
        if (! DeliveryApproverResolver::forLocation($location)) {
            return [
                'success' => false,
                'message' => 'This location has no delivery approver configured. Ask an administrator to assign a manager or delivery approver.',
                'record' => null,
            ];
        }

        DeliveryAddressFiller::fill($validated);

        $items = $validated['items'] ?? [];
        unset($validated['items']);

        $requesterId = current_tenant_user_id();
        if ($requesterId === null) {
            return [
                'success' => false,
                'message' => 'You must be signed in to submit a delivery request.',
                'record' => null,
            ];
        }

        try {
            return DB::transaction(function () use ($validated, $items, $requesterId) {
                $record = RecordModel::create(array_merge($validated, [
                    'uuid' => (string) Str::uuid(),
                    'requested_by_user_id' => $requesterId,
                    'requested_at' => now(),
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

                $record = $record->fresh(['location', 'requestedBy', 'customer']);
                LogSystemEvent::record($record, SystemLogAction::Created);

                return [
                    'success' => true,
                    'record' => $record,
                ];
            });
        } catch (QueryException $e) {
            Log::error('Database query error in CreateDeliveryRequest', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateDeliveryRequest', [
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
        if (array_key_exists('estimated_return_travel_duration_seconds', $data) && $data['estimated_return_travel_duration_seconds'] === '') {
            $data['estimated_return_travel_duration_seconds'] = null;
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
}
