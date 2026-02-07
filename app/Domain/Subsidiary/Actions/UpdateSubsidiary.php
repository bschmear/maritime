<?php

namespace App\Domain\Subsidiary\Actions;

use App\Domain\Subsidiary\Models\Subsidiary as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class UpdateSubsidiary
{
    public function __invoke(int $id, array $data): array
    {
        // Define validation rules for known fields
        $validationRules = [
            'display_name'        => ['required', 'string', 'max:255'],
            'legal_name'          => ['nullable', 'string', 'max:255'],
            'code'                => ['nullable', 'string', 'max:50'],
            'inactive'            => ['nullable', 'boolean'],
            'email'               => ['nullable', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:50'],
            'website'             => ['nullable', 'string', 'max:255'],
            'address_line_1'      => ['nullable', 'string', 'max:255'],
            'address_line_2'      => ['nullable', 'string', 'max:255'],
            'city'                => ['nullable', 'string', 'max:100'],
            'state'               => ['nullable', 'string', 'max:100'],
            'postal_code'         => ['nullable', 'string', 'max:20'],
            'country'             => ['nullable', 'string', 'max:100'],
            'latitude'            => ['nullable', 'numeric'],
            'longitude'           => ['nullable', 'numeric'],
            'timezone'            => ['nullable', 'string', 'max:50'],
            'default_labor_rate'  => ['nullable', 'numeric', 'min:0'],
            'work_order_prefix'   => ['nullable', 'string', 'max:10'],
            'next_work_order_number' => ['nullable', 'integer', 'min:1000'],
            'logo'                => ['nullable', 'string', 'max:255'],
            'settings'            => ['nullable', 'array'],
            'notes'               => ['nullable', 'string'],
        ];

        // Only validate fields that exist in the input
        $rulesToUse = array_filter($validationRules, fn($key) => array_key_exists($key, $data), ARRAY_FILTER_USE_KEY);

        $validated = Validator::make($data, $rulesToUse)->validate();

        // Merge validated fields with any extra custom fields
        $updateData = array_merge($validated, array_diff_key($data, $rulesToUse));

        try {
            $record = RecordModel::findOrFail($id);
            $record->update($updateData);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateSubsidiary', [
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
            Log::error('Unexpected error in UpdateSubsidiary', [
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
