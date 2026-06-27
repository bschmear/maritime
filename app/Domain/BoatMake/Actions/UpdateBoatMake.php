<?php

namespace App\Domain\BoatMake\Actions;

use App\Domain\BoatMake\Models\BoatMake as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdateBoatMake
{
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, [
            'display_name' => ['sometimes', 'string', 'max:255'],
            'asset_types' => ['sometimes', 'array', 'min:1'],
            'asset_types.*' => ['integer', 'in:1,2,3,4'],
            'is_custom' => ['sometimes', 'boolean'],
            'use_default_logo' => ['sometimes', 'boolean'],
            'default_brand_image' => ['sometimes', 'nullable', 'string', 'max:512'],
            'custom_logo_id' => ['sometimes', 'nullable', 'integer', 'exists:documents,id'],
            'active' => ['sometimes', 'boolean'],
            'brand_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'vendor_id' => ['sometimes', 'nullable', 'integer', 'exists:vendors,id'],
        ])->validate();

        if (array_key_exists('use_default_logo', $validated) && $validated['use_default_logo'] === true) {
            $record = RecordModel::findOrFail($id);
            if (empty($record->default_brand_image) && empty($validated['default_brand_image'])) {
                $validated['use_default_logo'] = false;
            }
        }

        try {
            $record = RecordModel::findOrFail($id);
            $record->update($validated);

            return [
                'success' => true,
                'record' => $record->fresh(),
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateBoatMake', [
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
            Log::error('Unexpected error in UpdateBoatMake', [
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
}
