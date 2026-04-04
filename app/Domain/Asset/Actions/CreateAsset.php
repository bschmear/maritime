<?php

namespace App\Domain\Asset\Actions;

use App\Domain\Asset\Models\Asset as RecordModel;
use App\Domain\AssetSpec\Models\AssetSpecValue;
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
                $this->syncSpecsForSpecable($record, $data['specs']);
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

    private function syncSpecsForSpecable(RecordModel $asset, array $specs): void
    {
        $type = $asset->getMorphClass();
        $id = $asset->getKey();

        foreach ($specs as $spec) {
            if (empty($spec['spec_id'])) {
                continue;
            }

            AssetSpecValue::updateOrCreate(
                [
                    'specable_type' => $type,
                    'specable_id' => $id,
                    'asset_spec_definition_id' => $spec['spec_id'],
                ],
                [
                    'value_number' => $spec['value_number'] ?? null,
                    'value_text' => $spec['value_text'] ?? null,
                    'value_boolean' => $spec['value_boolean'] ?? null,
                    'unit' => $spec['unit'] ?? null,
                ]
            );
        }
    }
}
