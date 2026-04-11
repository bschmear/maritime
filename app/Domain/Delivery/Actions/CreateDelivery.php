<?php

namespace App\Domain\Delivery\Actions;

use App\Domain\Delivery\Models\Delivery as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class CreateDelivery
{
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, [
            'customer_id' => 'required|exists:customer_profiles,id',
            'asset_unit_id' => 'required|exists:asset_units,id',
            'work_order_id' => 'nullable|exists:work_orders,id',
            'subsidiary_id' => 'nullable|exists:subsidiaries,id',
            'location_id' => 'nullable|exists:locations,id',
            'technician_id' => 'nullable|exists:users,id',
            'scheduled_at' => 'required|date',
            'estimated_arrival_at' => 'nullable|date|after:scheduled_at',
            'status' => 'required|in:scheduled,en_route,rescheduled,confirmed',
            'internal_notes' => 'nullable|string|max:5000',
            'customer_notes' => 'nullable|string|max:5000',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ])->validate();

        try {
            $record = RecordModel::create(array_merge($validated, [
                'uuid' => (string) Str::uuid(),
            ]));

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateDelivery', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateDelivery', [
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
