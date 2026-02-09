<?php
namespace App\Domain\Asset\Actions;

use App\Domain\Asset\Models\Asset as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
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
            'model' => 'nullable|string|max:255',
            'year' => 'nullable|string|max:4',
            'length' => 'nullable|string|max:255',
            'beam' => 'nullable|string|max:255',
            'persons' => 'nullable|integer|min:0',
            'minimum_power' => 'nullable|integer|min:0',
            'maximum_power' => 'nullable|integer|min:0',
            'fuel_tank' => 'nullable|string|max:255',
            'engine_shaft' => 'nullable|string|max:255',
            'water_tank' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'engine_details' => 'nullable|string',
            'default_cost' => 'nullable|numeric|min:0',
            'default_price' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'attributes' => 'nullable|array',
            'description' => 'nullable|string',
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

            // Add all non-validated fields from the request that aren't already validated
            $validatedKeys = array_keys($recordData);
            foreach ($data as $key => $value) {
                if (!in_array($key, $validatedKeys) && !in_array($key, ['tenant_account'])) {
                    $recordData[$key] = $value;
                }
            }

            $record = RecordModel::findOrFail($id);
            $record->update($recordData);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateAsset', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdateAsset', [
                'error' => $e->getMessage(),
                'id' => $id,
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
