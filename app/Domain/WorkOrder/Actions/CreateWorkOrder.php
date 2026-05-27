<?php

namespace App\Domain\WorkOrder\Actions;

use App\Domain\WorkOrder\Models\WorkOrder as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class CreateWorkOrder
{
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, [
            // Add validation rules here
        ])->validate();

        try {
            $validated = $data; // For now, just pass through all data

            // service_items are persisted by WorkOrderController::store via createServiceItems()
            // (recalculateLineItem / recalculateWorkOrder). Do not insert them here or they duplicate.
            unset($validated['service_items']);
            unset($validated['display_name']);

            // Generate UUID if not provided
            if (empty($validated['uuid'])) {
                $validated['uuid'] = (string) Str::uuid();
            }

            // Ensure cost fields have default values
            $validated['labor_cost'] = $validated['labor_cost'] ?? 0;
            $validated['parts_cost'] = $validated['parts_cost'] ?? 0;
            $validated['total_cost'] = $validated['total_cost'] ?? 0;

            // Auto-generate work_order_number if not provided.
            // Soft-deleted work orders still hold their number (unique constraint), so the next
            // number must be based on max across all rows including trashed.
            if (empty($validated['work_order_number'])) {
                $maxNumber = RecordModel::withTrashed()->max('work_order_number');
                $validated['work_order_number'] = $maxNumber !== null
                    ? ((int) $maxNumber) + 1
                    : 1000;
            }

            // Remove timestamp fields to let Laravel handle them automatically
            unset($validated['created_at'], $validated['updated_at']);

            $record = RecordModel::create($validated);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateWorkOrder', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateWorkOrder', [
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
