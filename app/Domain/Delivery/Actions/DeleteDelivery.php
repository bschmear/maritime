<?php

namespace App\Domain\Delivery\Actions;

use App\Domain\Delivery\Models\Delivery as RecordModel;
use App\Domain\Delivery\Support\SyncTechnicianDeliveryInProgress;
use App\Domain\SystemLog\Support\LogSystemEvent;
use App\Enums\System\SystemLogAction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteDelivery
{
    public function __invoke(int $id): array
    {
        try {
            $record = RecordModel::findOrFail($id);
            $technicianId = $record->technician_id;
            LogSystemEvent::record($record, SystemLogAction::Deleted);
            $record->delete();
            SyncTechnicianDeliveryInProgress::recomputeForUserIds([$technicianId]);

            return [
                'success' => true,
                'message' => 'Record deleted successfully.',
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteDelivery', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteDelivery', [
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
