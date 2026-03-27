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
            'logo' => ['sometimes', 'nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ])->validate();

        try {
            $record = RecordModel::findOrFail($id);
            $record->update($validated);

            return [
                'success' => true,
                'record' => $record,
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
