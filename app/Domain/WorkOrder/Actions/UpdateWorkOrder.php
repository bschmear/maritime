<?php
namespace App\Domain\WorkOrder\Actions;

use App\Domain\WorkOrder\Models\WorkOrder as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class UpdateWorkOrder
{
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, [
            // Add validation rules here
        ])->validate();

        try {
            // Debug: Log the incoming data
            \Log::info('UpdateWorkOrder data received:', $data);

            $validated = $data; // For now, just pass through all data

            // Ensure cost fields have default values
            $validated['labor_cost'] = $validated['labor_cost'] ?? 0;
            $validated['parts_cost'] = $validated['parts_cost'] ?? 0;
            $validated['total_cost'] = $validated['total_cost'] ?? 0;

            $record = RecordModel::findOrFail($id);
            $record->update($validated);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateWorkOrder', [
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
            Log::error('Unexpected error in UpdateWorkOrder', [
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