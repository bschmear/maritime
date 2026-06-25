<?php

namespace App\Domain\Subsidiary\Actions;

use App\Domain\Subsidiary\Models\Subsidiary as RecordModel;
use App\Domain\Subsidiary\Support\GoogleReviewUrl;
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
            'inactive'            => ['nullable', 'boolean'],
            'email'               => ['nullable', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:50'],
            'website'               => ['nullable', 'string', 'max:255'],
            'google_review_url'     => GoogleReviewUrl::validationRules(),
            'prompt_google_review_on_transaction_close' => ['nullable', 'boolean'],
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
            'settings'            => ['nullable', 'array'],
            'notes'               => ['nullable', 'string'],
            'logo'                => ['nullable', 'integer'],
        ];

        // Only validate fields that exist in the input
        $rulesToUse = array_filter($validationRules, fn ($key) => array_key_exists($key, $data), ARRAY_FILTER_USE_KEY);

        if (array_key_exists('google_review_url', $data)) {
            $data['google_review_url'] = GoogleReviewUrl::normalize($data['google_review_url']);
        }

        $validated = Validator::make($data, $rulesToUse)->validate();

        // Merge validated fields with any extra custom fields
        $updateData = array_merge($validated, array_diff_key($data, $rulesToUse));

        if (array_key_exists('prompt_google_review_on_transaction_close', $updateData)) {
            $updateData['prompt_google_review_on_transaction_close'] = filter_var(
                $updateData['prompt_google_review_on_transaction_close'],
                FILTER_VALIDATE_BOOLEAN
            );
        }

        if (array_key_exists('google_review_url', $updateData)) {
            $updateData['google_review_url'] = GoogleReviewUrl::normalize($updateData['google_review_url']);
        }

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
