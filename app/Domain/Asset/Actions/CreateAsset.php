<?php

namespace App\Domain\Asset\Actions;

use App\Domain\Asset\Models\Asset as RecordModel;
use App\Domain\Asset\Support\SyncAssetSpecValues;
use App\Enums\Inventory\BoatType;
use App\Enums\Inventory\HullMaterial;
use App\Enums\Inventory\HullType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateAsset
{
    public function __invoke(array $data): array
    {
        $validator = Validator::make($data, [
            'type' => 'required|integer|in:1,2,3,4',
            'display_name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'inactive' => 'nullable|boolean',
            'has_variants' => 'nullable|boolean',
            'make_id' => 'nullable|integer',
            'model' => 'nullable|string|max:255',
            'year' => 'nullable|string|max:4',
            'length' => 'nullable|integer|min:0|max:10000000',
            'beam' => 'nullable|string|max:255',
            'width' => 'nullable|integer|min:0|max:10000000',
            'hull_type' => ['nullable', 'integer', 'min:1', 'max:'.count(HullType::cases())],
            'hull_material' => ['nullable', 'integer', 'min:1', 'max:'.count(HullMaterial::cases())],
            'boat_type' => ['nullable', 'integer', 'min:1', 'max:'.count(BoatType::cases())],
            'default_cost' => 'nullable|numeric|min:0',
            'default_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'attributes' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray(),
                'record' => null,
            ];
        }

        try {
            $record = RecordModel::create($validator->validated());

            if (! empty($data['specs']) && is_array($data['specs']) && ! $record->has_variants) {
                SyncAssetSpecValues::forSpecable($record, (int) $record->type, $data['specs']);
            }

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateAsset', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage(), 'record' => null];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateAsset', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage(), 'record' => null];
        }
    }
}
