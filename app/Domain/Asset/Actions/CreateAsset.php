<?php

namespace App\Domain\Asset\Actions;

use App\Domain\Asset\Models\Asset as RecordModel;
use App\Domain\AssetSpec\Models\AssetSpecValue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class CreateAsset
{
    public function __invoke(array $data): array
    {
        $validator = Validator::make($data, [
            'type'          => 'required|integer|in:1,2,3,4',
            'display_name'  => 'required|string|max:255',
            'slug'          => 'nullable|string|max:255',
            'inactive'      => 'nullable|boolean',
            'make_id'       => 'nullable|integer',
            'model'         => 'nullable|string|max:255',
            'year'          => 'nullable|string|max:4',
            'default_cost'  => 'nullable|numeric|min:0',
            'default_price' => 'nullable|numeric|min:0',
            'description'   => 'nullable|string',
            'attributes'    => 'nullable|array',
            // specs validated separately below
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors()->toArray(),
                'record'  => null,
            ];
        }

        try {
            $record = RecordModel::create($validator->validated());

            // Sync spec values if included in the payload
            if (!empty($data['specs']) && is_array($data['specs'])) {
                $this->syncSpecs($record->id, $data['specs']);
            }

            return [
                'success' => true,
                'record'  => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateAsset', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage(), 'record' => null];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateAsset', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage(), 'record' => null];
        }
    }

    private function syncSpecs(int $assetId, array $specs): void
    {
        foreach ($specs as $spec) {
            if (empty($spec['spec_id'])) continue;

            AssetSpecValue::updateOrCreate(
                [
                    'asset_id'                  => $assetId,
                    'asset_spec_definition_id'  => $spec['spec_id'],
                ],
                [
                    'value_number'  => $spec['value_number']  ?? null,
                    'value_text'    => $spec['value_text']    ?? null,
                    'value_boolean' => $spec['value_boolean'] ?? null,
                    'unit'          => $spec['unit']          ?? null,
                ]
            );
        }
    }
}