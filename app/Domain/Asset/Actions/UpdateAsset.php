<?php

namespace App\Domain\Asset\Actions;

use App\Domain\Asset\Models\Asset as RecordModel;
use App\Domain\Asset\Support\SyncAssetSpecValues;
use App\Domain\AssetSpec\Models\AssetSpecValue;
use App\Enums\Inventory\BoatType;
use App\Enums\Inventory\HullMaterial;
use App\Enums\Inventory\HullType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdateAsset
{
    public function __invoke(int $id, array $data): array
    {
        $validator = Validator::make($data, [
            'type' => 'sometimes|required|integer|in:1,2,3,4',
            'display_name' => 'sometimes|required|string|max:255',
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

        $validated = $validator->validated();

        if (array_key_exists('has_variants', $validated) && ! $validated['has_variants']) {
            $pre = RecordModel::query()->find($id);
            if ($pre && $pre->variants()->exists()) {
                return [
                    'success' => false,
                    'message' => 'Remove or reassign all variants before turning off “This asset has variants”.',
                    'errors' => ['has_variants' => ['Remove or reassign all variants first.']],
                    'record' => null,
                ];
            }
        }

        try {
            $record = RecordModel::findOrFail($id);
            $incoming = $validated;

            $willHaveVariants = array_key_exists('has_variants', $incoming)
                ? (bool) $incoming['has_variants']
                : (bool) $record->has_variants;

            if ($willHaveVariants) {
                AssetSpecValue::query()
                    ->where('specable_type', $record->getMorphClass())
                    ->where('specable_id', $record->id)
                    ->delete();
            }

            $record->update($incoming);

            $record->refresh();

            if (! empty($data['specs']) && is_array($data['specs']) && ! $record->has_variants) {
                SyncAssetSpecValues::forSpecable($record, (int) $record->type, $data['specs']);
            }

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateAsset', ['error' => $e->getMessage(), 'id' => $id]);

            return ['success' => false, 'message' => $e->getMessage(), 'record' => null];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdateAsset', ['error' => $e->getMessage(), 'id' => $id]);

            return ['success' => false, 'message' => $e->getMessage(), 'record' => null];
        }
    }
}
