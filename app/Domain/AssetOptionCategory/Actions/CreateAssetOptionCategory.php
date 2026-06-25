<?php

declare(strict_types=1);

namespace App\Domain\AssetOptionCategory\Actions;

use App\Domain\AssetOptionCategory\Models\AssetOptionCategory as RecordModel;
use App\Domain\AssetOptionCategory\Validation\AssetOptionCategoryInputRules;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateAssetOptionCategory
{
    /**
     * @return array{success: true, record: RecordModel}|array{success: false, message: string, record: null}
     */
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, AssetOptionCategoryInputRules::create())->validate();

        try {
            $validated['slug'] = RecordModel::uniqueSlugForName($validated['name']);
            $validated['active'] = $validated['active'] ?? true;

            $record = RecordModel::create($validated);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateAssetOptionCategory', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateAssetOptionCategory', [
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
