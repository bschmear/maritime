<?php
namespace App\Domain\WorkOrder\Actions;

use App\Domain\WorkOrder\Models\WorkOrder as RecordModel;
use App\Domain\WorkOrder\Models\WorkOrderServiceItem;
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
            $validated = $data; // For now, just pass through all data

            // Extract service_items before updating
            $serviceItems = $validated['service_items'] ?? null;
            unset($validated['service_items']);

            // Ensure cost fields have default values
            $validated['labor_cost'] = $validated['labor_cost'] ?? 0;
            $validated['parts_cost'] = $validated['parts_cost'] ?? 0;
            $validated['total_cost'] = $validated['total_cost'] ?? 0;

            $record = RecordModel::findOrFail($id);
            $record->update($validated);

            // Sync WorkOrderServiceItem line items (replace all)
            if ($serviceItems !== null) {
                $record->serviceItems()->delete();
                foreach ($serviceItems as $idx => $item) {
                    if (empty($item['display_name'])) {
                        continue;
                    }
                    WorkOrderServiceItem::create([
                        'work_order_id'   => $record->id,
                        'service_item_id' => $item['service_item_id'] ?? null,
                        'display_name'   => $item['display_name'],
                        'description'    => $item['description'] ?? null,
                        'quantity'       => $item['quantity'] ?? 1,
                        'unit_price'     => $item['unit_price'] ?? 0,
                        'unit_cost'      => $item['unit_cost'] ?? null,
                        'estimated_hours'=> $item['estimated_hours'] ?? null,
                        'actual_hours'   => $item['actual_hours'] ?? null,
                        'billable'       => $item['billable'] ?? true,
                        'warranty'       => $item['warranty'] ?? false,
                        'billing_type'   => $item['billing_type'] ?? null,
                        'sort_order'     => $item['sort_order'] ?? $idx,
                    ]);
                }
            }

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