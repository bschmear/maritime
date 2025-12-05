<?php
namespace Domain\Role\Actions;

use Domain\Role\Models\Role as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class UpdateRole
{
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, [
            'display_name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:roles,slug,' . $id,
            'description' => 'nullable|string|max:1000',
            'permissions' => 'nullable|array',
        ])->validate();

        try {
            $record = RecordModel::findOrFail($id);
            $record->update($validated);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateRole', [
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
            Log::error('Unexpected error in UpdateRole', [
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