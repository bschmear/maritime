<?php
namespace App\Domain\Delivery\Actions;

use App\Domain\Delivery\Models\Delivery as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class UpdateDelivery
{
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, [
            'customer_id'          => 'required|exists:customers,id',
            'asset_unit_id'        => 'required|exists:asset_units,id',
            'work_order_id'        => 'nullable|exists:work_orders,id',
            'technician_id'        => 'nullable|exists:users,id',
            'subsidiary_id'        => 'nullable|exists:subsidiaries,id',
            'location_id'          => 'nullable|exists:locations,id',
            'scheduled_at'         => 'required|date',
            'estimated_arrival_at' => 'nullable|date|after:scheduled_at',
            'status'               => 'required|in:scheduled,en_route,delivered,cancelled,rescheduled,confirmed',
            'internal_notes'       => 'nullable|string|max:5000',
            'customer_notes'       => 'nullable|string|max:5000',
            'address_line_1'       => 'nullable|string|max:255',
            'address_line_2'       => 'nullable|string|max:255',
            'city'                 => 'nullable|string|max:100',
            'state'                => 'nullable|string|max:100',
            'postal_code'          => 'nullable|string|max:20',
            'country'              => 'nullable|string|max:100',
            'latitude'             => 'nullable|numeric',
            'longitude'            => 'nullable|numeric',
        ])->validate();

        try {
            $record = RecordModel::findOrFail($id);
            $record->update($validated);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateDelivery', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data
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
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        }
    }
}