<?php
namespace App\Domain\WorkOrder\Actions;

use App\Domain\WorkOrder\Models\WorkOrder as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class CreateWorkOrder
{
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, [
            // Add validation rules here
        ])->validate();

        try {
            // Debug: Log the incoming data
            \Log::info('CreateWorkOrder data received:', $data);

            $validated = $data; // For now, just pass through all data

            // Ensure cost fields have default values
            $validated['labor_cost'] = $validated['labor_cost'] ?? 0;
            $validated['parts_cost'] = $validated['parts_cost'] ?? 0;
            $validated['total_cost'] = $validated['total_cost'] ?? 0;

            // Auto-generate work_order_number if not provided
            if (empty($validated['work_order_number'])) {
                $lastWorkOrder = RecordModel::orderBy('work_order_number', 'desc')->first();
                $validated['work_order_number'] = $lastWorkOrder ? $lastWorkOrder->work_order_number + 1 : 1000;
            }

            $record = RecordModel::create($validated);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateWorkOrder', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateWorkOrder', [
                'error' => $e->getMessage(),
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