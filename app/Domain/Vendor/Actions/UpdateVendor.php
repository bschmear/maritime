<?php

namespace App\Domain\Vendor\Actions;

use App\Domain\Vendor\Models\Vendor as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateVendor
{
    /**
     * @return array{success: bool, record?: RecordModel, message?: string}
     *
     * @throws ValidationException
     */
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, [
            'display_name' => ['required', 'string', 'max:255'],
            'vendor_type' => ['nullable'],
            'industry' => ['nullable', 'string', 'max:255'],
            'vendor_code' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable'],
            'secondary_email' => ['nullable', 'email', 'max:255'],
            'secondary_phone' => ['nullable', 'string', 'max:50'],
            'preferred_contact_method' => ['nullable'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'status_id' => ['nullable', 'integer'],
            'status_reason' => ['nullable', 'string', 'max:255'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'payment_terms' => ['nullable'],
            'credit_limit' => ['nullable', 'numeric'],
            'website' => ['nullable', 'string', 'max:255'],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'contract_start' => ['nullable', 'date'],
            'contract_end' => ['nullable', 'date'],
            'contract_status' => ['nullable'],
            'is_verified' => ['nullable', 'boolean'],
            'primary_contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
        ])->validate();

        try {
            $vendor = RecordModel::query()->findOrFail($id);

            $merged = array_merge($data, $validated);
            unset($merged['id'], $merged['created_at'], $merged['updated_at']);

            $payload = $this->onlyVendorAttributes($merged);
            $payload['tags'] = $this->normalizeTags($payload['tags'] ?? null);
            if (array_key_exists('is_verified', $payload)) {
                $payload['is_verified'] = filter_var($payload['is_verified'], FILTER_VALIDATE_BOOLEAN);
            }

            $vendor->update($payload);

            $vendor->refresh();
            $vendor->syncPrimaryContactPivot();

            return [
                'success' => true,
                'record' => $vendor->fresh(),
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateVendor', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdateVendor', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function onlyVendorAttributes(array $data): array
    {
        $keys = [
            'display_name', 'vendor_type', 'industry', 'vendor_code', 'tags',
            'primary_contact_id',
            'secondary_email', 'secondary_phone', 'preferred_contact_method',
            'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country',
            'latitude', 'longitude',
            'status_id', 'status_reason', 'assigned_user_id', 'notes', 'rating',
            'payment_terms', 'credit_limit', 'website', 'linkedin', 'facebook',
            'contract_start', 'contract_end', 'contract_status', 'is_verified',
        ];

        return Arr::only($data, $keys);
    }

    private function normalizeTags(mixed $tags): ?array
    {
        if ($tags === null || $tags === '') {
            return null;
        }
        if (is_array($tags)) {
            return $tags === [] ? null : $tags;
        }
        if (is_string($tags)) {
            $decoded = json_decode($tags, true);

            return is_array($decoded) ? ($decoded === [] ? null : $decoded) : null;
        }

        return null;
    }
}
