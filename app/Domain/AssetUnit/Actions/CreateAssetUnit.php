<?php
namespace App\Domain\AssetUnit\Actions;

use App\Domain\AssetUnit\Models\AssetUnit as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
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
                })
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
            'customer_id' => 'nullable|integer|exists:customers,id',
            'location_id' => 'nullable|integer|exists:locations,id',
            'subsidiary_id' => 'nullable|integer|exists:subsidiaries,id',
            'in_service_at' => 'nullable|date',
            'out_of_service_at' => 'nullable|date',
            'sold_at' => 'nullable|date',
            'attributes' => 'nullable|array',
            'notes' => 'nullable|string',
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
            // Start with all validated data
            $recordData = $validator->validated();

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
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateAssetUnit', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        }
    }
}