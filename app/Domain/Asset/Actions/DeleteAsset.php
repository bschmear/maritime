<?php

namespace App\Domain\Asset\Actions;

use App\Domain\Asset\Models\Asset as RecordModel;
use App\Domain\SystemLog\Support\LogSystemEvent;
use App\Enums\System\SystemLogAction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteAsset
{
    public function __invoke(int $id): array
    {
        try {
            $record = RecordModel::findOrFail($id);
            LogSystemEvent::record($record, SystemLogAction::Deleted);
            $record->delete();

            return [
                'success' => true,
                'message' => 'Asset deleted successfully.',
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteAsset', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteAsset', [
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
