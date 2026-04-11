<?php

namespace App\Domain\AssetUnit\Actions;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetUnit\Models\AssetUnit as RecordModel;
use App\Domain\AssetVariant\Models\AssetVariant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class CreateAssetUnit
{
    public function __invoke(array $data): array
    {
        $validator = Validator::make($data, [
            'asset_id' => [
                'required',
                'integer',
                Rule::exists('assets', 'id')->where(function ($query) {
                    $query->where('inactive', false)->orWhereNull('inactive');
                }),
            ],
            'serial_number' => 'nullable|string|max:255',
            'hin' => 'nullable|string|max:255|unique:asset_units,hin',
            'sku' => 'nullable|string|max:255',
            'condition' => 'nullable|integer|in:1,2,3',
            'status' => 'nullable|integer|in:1,2,3,4,5,6,7',
            'inactive' => 'nullable|boolean',
            'is_customer_owned' => 'nullable|boolean',
            'is_consignment' => 'nullable|boolean',
            'engine_hours' => 'nullable|numeric|min:0',
            'last_service_at' => 'nullable|date',
            'warranty_expires_at' => 'nullable|date',
            'cost' => 'nullable|numeric|min:0',
            'asking_price' => 'nullable|numeric|min:0',
            'sold_price' => 'nullable|numeric|min:0',
            'vendor_id' => 'nullable|integer|exists:vendors,id',
            'customer_id' => 'nullable|integer|exists:customer_profiles,id',
            'location_id' => 'nullable|integer|exists:locations,id',
            'subsidiary_id' => 'nullable|integer|exists:subsidiaries,id',
            'in_service_at' => 'nullable|date',
            'out_of_service_at' => 'nullable|date',
            'sold_at' => 'nullable|date',
            'attributes' => 'nullable|array',
            'notes' => 'nullable|string',
            'asset_variant_id' => 'nullable|integer|exists:asset_variants,id',
        ]);

        $validator->after(function ($v) use ($data): void {
            $assetId = $data['asset_id'] ?? null;
            if (! $assetId) {
                return;
            }
            $asset = Asset::query()->find($assetId);
            if (! $asset) {
                return;
            }
            if (! $asset->has_variants) {
                return;
            }
            $vid = $data['asset_variant_id'] ?? null;
            if (! $vid) {
                $v->errors()->add('asset_variant_id', 'Select a variant for this unit.');

                return;
            }
            if (! AssetVariant::query()->where('asset_id', $asset->id)->whereKey($vid)->exists()) {
                $v->errors()->add('asset_variant_id', 'The selected variant does not belong to this asset.');
            }
        });

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray(),
                'record' => null,
            ];
        }

        try {
            // Start with all validated data
            $recordData = $validator->validated();
            $asset = Asset::query()->find($recordData['asset_id']);
            if ($asset && ! $asset->has_variants) {
                unset($recordData['asset_variant_id']);
            }

            // Add any additional non-validated fields that should be saved
            $additionalFields = ['price_history'];
            foreach ($additionalFields as $field) {
                if (array_key_exists($field, $data)) {
                    $recordData[$field] = $data[$field];
                }
            }

            $record = RecordModel::create($recordData);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateAssetUnit', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateAssetUnit', [
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
