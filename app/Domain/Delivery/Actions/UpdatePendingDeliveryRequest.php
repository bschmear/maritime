<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Actions;

use App\Domain\Delivery\Models\Delivery as RecordModel;
use App\Domain\Delivery\Models\DeliveryItem;
use App\Domain\Delivery\Support\DeliveryFleetFieldValidator;
use App\Domain\SystemLog\Support\LogSystemEvent;
use App\Enums\System\SystemLogAction;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdatePendingDeliveryRequest
{
    public function __invoke(RecordModel $delivery, array $data): array
    {
        if (! $delivery->pending_request && $delivery->review_decision !== ReviewDeliveryRequest::DECISION_DENIED) {
            return [
                'success' => false,
                'message' => 'Only pending or denied delivery requests can be updated.',
                'record' => $delivery,
            ];
        }

        $userId = current_tenant_user_id();
        if ($userId === null) {
            return [
                'success' => false,
                'message' => 'You must be signed in to update a delivery request.',
                'record' => $delivery,
            ];
        }

        $isRequester = (int) $delivery->requested_by_user_id === (int) $userId;
        $isAdmin = current_tenant_role_slug() === 'admin';
        if (! $isRequester && ! $isAdmin) {
            return [
                'success' => false,
                'message' => 'Only the original requester can update this delivery request.',
                'record' => $delivery,
            ];
        }

        $data = $this->normalizeTravelInput($data);

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

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray(),
                'record' => $delivery,
            ];
        }

        $validated = $validator->validated();
        DeliveryAddressFiller::fill($validated);

        $items = $validated['items'] ?? [];
        unset($validated['items']);

        try {
            return DB::transaction(function () use ($delivery, $validated, $items) {
                $incomingScheduled = Carbon::parse($validated['scheduled_at']);
                $scheduledChanged = ! $delivery->scheduled_at
                    || ! $delivery->scheduled_at->equalTo($incomingScheduled);

                $updatePayload = array_merge($validated, [
                    'pending_request' => true,
                    'status' => 'requested',
                    'review_decision' => null,
                    'review_notes' => null,
                    'reviewed_by_user_id' => null,
                    'reviewed_at' => null,
                    'proposed_scheduled_at' => null,
                    'requested_at' => now(),
                ]);

                if ($scheduledChanged) {
                    $updatePayload['time_to_leave_by'] = null;
                }

                $delivery->update($updatePayload);

                $keep = [];
                foreach ($items as $index => $row) {
                    $payload = [
                        'delivery_id' => $delivery->id,
                        'type' => 'asset',
                        'asset_unit_id' => $row['asset_unit_id'] ?? null,
                        'asset_variant_id' => $row['asset_variant_id'] ?? null,
                        'name' => $row['name'] ?? 'Asset',
                        'description' => $row['description'] ?? null,
                        'quantity' => $row['quantity'] ?? 1,
                        'unit_price' => $row['unit_price'] ?? 0,
                        'position' => $index,
                    ];

                    if (! empty($row['id'])) {
                        $existing = DeliveryItem::query()
                            ->where('delivery_id', $delivery->id)
                            ->where('id', $row['id'])
                            ->first();
                        if ($existing) {
                            $existing->update($payload);
                            $keep[] = $existing->id;

                            continue;
                        }
                    }

                    $created = DeliveryItem::create($payload);
                    $keep[] = $created->id;
                }

                if ($items !== []) {
                    DeliveryItem::query()
                        ->where('delivery_id', $delivery->id)
                        ->whereNotIn('id', $keep)
                        ->delete();
                } elseif (! empty($validated['transaction_id'])) {
                    (new SyncItemsFromSource)($delivery->fresh(), 'transaction', (int) $validated['transaction_id']);
                } elseif (! empty($validated['work_order_id'])) {
                    (new SyncItemsFromSource)($delivery->fresh(), 'work_order', (int) $validated['work_order_id']);
                }

                $record = $delivery->fresh(['location', 'requestedBy', 'customer', 'items']);
                LogSystemEvent::record($record, SystemLogAction::Updated);

                return [
                    'success' => true,
                    'record' => $record,
                ];
            });
        } catch (QueryException $e) {
            Log::error('Database query error in UpdatePendingDeliveryRequest', [
                'error' => $e->getMessage(),
                'delivery_id' => $delivery->id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => $delivery,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdatePendingDeliveryRequest', [
                'error' => $e->getMessage(),
                'delivery_id' => $delivery->id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => $delivery,
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
