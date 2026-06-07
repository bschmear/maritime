<?php

namespace App\Domain\Role\Actions;

use App\Domain\Role\Models\Role as RecordModel;
use App\Support\Tenant\TenantPermissionsCache;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdateRole
{
    public function __invoke(int $id, array $data): array
    {
        $record = RecordModel::findOrFail($id);

        $validated = Validator::make($data, [
            'display_name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:roles,slug,'.$id,
            'description' => 'nullable|string|max:1000',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ])->validate();

        if ($record->slug === 'admin' && ($validated['slug'] ?? '') !== 'admin') {
            return [
                'success' => false,
                'message' => 'The administrator role slug cannot be changed.',
                'errors' => ['slug' => ['The administrator role slug cannot be changed.']],
                'record' => null,
            ];
        }

        $permissionIds = $validated['permission_ids'] ?? null;
        unset($validated['permission_ids']);

        try {
            $record->update($validated);

            if (is_array($permissionIds)) {
                $record->permissions()->sync($permissionIds);
                TenantPermissionsCache::bumpVersion();
            }

            return [
                'success' => true,
                'record' => $record->fresh()->load('permissions'),
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateRole', [
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
            Log::error('Unexpected error in UpdateRole', [
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
