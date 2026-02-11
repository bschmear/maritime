<?php
namespace App\Domain\ServiceItem\Actions;

use App\Domain\ServiceItem\Models\ServiceItem as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class CreateServiceItem
{
    public function __invoke(array $data): array
    {
        $validator = Validator::make($data, [
            'display_name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'billing_type' => 'required|integer|in:1,2,3',
            'default_rate' => 'nullable|numeric|min:0',
            'default_cost' => 'nullable|numeric|min:0',
            'default_hours' => 'nullable|numeric|min:0',
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

            // Ensure cost and rate fields have default values
            $recordData['default_rate'] = $recordData['default_rate'] ?? 0;
            $recordData['default_cost'] = $recordData['default_cost'] ?? 0;
            $recordData['default_hours'] = $recordData['default_hours'] ?? 0;

            // Add any additional non-validated fields that should be saved
            $additionalFields = ['subsidiary_id', 'attributes'];
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
            Log::error('Database query error in CreateServiceItem', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateServiceItem', [
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