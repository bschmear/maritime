<?php

declare(strict_types=1);

namespace App\Domain\Financing\Actions;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Financing\Models\Financing as RecordModel;
use App\Enums\Financing\Status;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteFinancing
{
    public function __invoke(int $id): array
    {
        try {
            $record = RecordModel::findOrFail($id);
            $assetUnitId = $record->asset_unit_id;
            $record->delete();

            if ($assetUnitId !== null) {
                $stillFinanced = RecordModel::query()
                    ->where('asset_unit_id', $assetUnitId)
                    ->where('status', Status::Active->value)
                    ->exists();

                if (! $stillFinanced) {
                    AssetUnit::query()
                        ->whereKey($assetUnitId)
                        ->update(['is_financed' => false]);
                }
            }

            return ['success' => true];
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteFinancing', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteFinancing', [
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
