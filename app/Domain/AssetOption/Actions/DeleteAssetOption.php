<?php

namespace App\Domain\AssetOption\Actions;

use App\Domain\AssetOption\Models\AssetOption as RecordModel;
use App\Domain\AssetOption\Support\AssetOptionLineUsageGuard;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteAssetOption
{
    public function __construct(
        private readonly AssetOptionLineUsageGuard $usageGuard = new AssetOptionLineUsageGuard,
    ) {}

    public function __invoke(int $id): array
    {
        try {
            $record = RecordModel::findOrFail($id);

            if ($this->usageGuard->isUsedOnLineItems($id)) {
                return [
                    'success' => false,
                    'offer_inactive' => true,
                    'message' => AssetOptionLineUsageGuard::MESSAGE,
                ];
            }

            $record->delete();

            return [
                'success' => true,
                'message' => 'Record deleted successfully.',
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteAssetOption', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteAssetOption', [
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
