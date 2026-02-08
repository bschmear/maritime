<?php
namespace App\Domain\ServiceItem\Actions;

use App\Domain\ServiceItem\Models\ServiceItem as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class UpdateServiceItem
{
    public function __invoke(int $id, array $data): array
    {
        $validator = Validator::make($data, [
            'display_name' => 'sometimes|required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'billing_type' => 'sometimes|required|integer|in:1,2,3',
            'default_rate' => 'nullable|numeric|min:0',
            'default_cost' => 'nullable|numeric|min:0',
            'taxable' => 'nullable|boolean',
            'billable' => 'nullable|boolean',
            'warranty_eligible' => 'nullable|boolean',
            'inactive' => 'nullable|boolean',
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

            // Ensure cost and rate fields have default values if they are being updated
            if (array_key_exists('default_rate', $recordData) && $recordData['default_rate'] === null) {
                $recordData['default_rate'] = 0;
            }
            if (array_key_exists('default_cost', $recordData) && $recordData['default_cost'] === null) {
                $recordData['default_cost'] = 0;
            }

            // Add any additional non-validated fields that should be saved
            $additionalFields = ['subsidiary_id', 'attributes'];
            foreach ($additionalFields as $field) {
                if (array_key_exists($field, $data)) {
                    $recordData[$field] = $data[$field];
                }
            }

            $record = RecordModel::findOrFail($id);
            $record->update($recordData);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateServiceItem', [
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
            Log::error('Unexpected error in UpdateServiceItem', [
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