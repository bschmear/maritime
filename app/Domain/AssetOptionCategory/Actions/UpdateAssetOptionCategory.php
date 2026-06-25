<?php

declare(strict_types=1);

namespace App\Domain\AssetOptionCategory\Actions;

use App\Domain\AssetOptionCategory\Models\AssetOptionCategory as RecordModel;
use App\Domain\AssetOptionCategory\Validation\AssetOptionCategoryInputRules;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdateAssetOptionCategory
{
    /**
     * @return array{success: true, record: RecordModel}|array{success: false, message: string, record: null}
     */
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, AssetOptionCategoryInputRules::update())->validate();

        try {
            $record = RecordModel::findOrFail($id);

            if (array_key_exists('name', $validated)) {
                $validated['slug'] = RecordModel::uniqueSlugForName($validated['name'], (int) $record->id);
            }

            $record->update($validated);

            return [
                'success' => true,
                'record' => $record->fresh(),
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateAssetOptionCategory', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdateAssetOptionCategory', [
                'error' => $e->getMessage(),
                'id' => $id,
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
