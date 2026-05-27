<?php

namespace App\Domain\Role\Actions;

use App\Domain\Role\Models\Role as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateRole
{
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, [
            'display_name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:roles,slug',
            'description' => 'nullable|string|max:1000',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ])->validate();

        $permissionIds = $validated['permission_ids'] ?? [];
        unset($validated['permission_ids']);

        try {
            $record = RecordModel::create($validated);
            $record->permissions()->sync($permissionIds);

            return [
                'success' => true,
                'record' => $record->load('permissions'),
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateRole', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateRole', [
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
