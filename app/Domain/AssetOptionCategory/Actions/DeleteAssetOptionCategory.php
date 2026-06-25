<?php

declare(strict_types=1);

namespace App\Domain\AssetOptionCategory\Actions;

use App\Domain\AssetOptionCategory\Models\AssetOptionCategory as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteAssetOptionCategory
{
    /**
     * @return array{success: true}|array{success: false, message: string}
     */
    public function __invoke(int $id): array
    {
        try {
            $record = RecordModel::findOrFail($id);
            $record->delete();

            return ['success' => true];
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteAssetOptionCategory', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteAssetOptionCategory', [
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
