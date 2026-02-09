<?php
namespace App\Domain\Asset\Actions;

use App\Domain\Asset\Models\Asset as RecordModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class DeleteAsset
{
    public function __invoke(int $id): array
    {
        try {
            $record = RecordModel::findOrFail($id);
            $record->delete();

            return [
                'success' => true,
                'message' => 'Asset deleted successfully.',
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteAsset', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteAsset', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}