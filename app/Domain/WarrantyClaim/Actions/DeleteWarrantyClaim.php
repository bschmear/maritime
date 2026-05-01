<?php

namespace App\Domain\WarrantyClaim\Actions;

use App\Domain\WarrantyClaim\Models\WarrantyClaim as RecordModel;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Domain\WorkOrder\Support\SyncWorkOrderWarrantyFlags;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteWarrantyClaim
{
    public function __invoke(int $id): array
    {
        try {
            $record = RecordModel::findOrFail($id);
            $workOrderId = $record->work_order_id;
            $record->delete();

            if ($workOrderId !== null) {
                $wo = WorkOrder::query()->find((int) $workOrderId);
                if ($wo) {
                    (app(SyncWorkOrderWarrantyFlags::class))($wo);
                }
            }

            return [
                'success' => true,
                'message' => 'Record deleted successfully.',
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteWarrantyClaim', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteWarrantyClaim', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
