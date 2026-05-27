<?php

namespace App\Domain\User\Actions;

use App\Domain\User\Models\User as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdateUser
{
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, [
            'display_name' => 'nullable|string|max:255',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$id,
            'mobile_phone' => 'nullable|string|max:20',
            'office_phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string|max:1000',
            'avatar' => 'sometimes|nullable|integer|exists:documents,id',
            'is_technician' => 'sometimes|boolean',
            'current_role' => 'nullable|exists:roles,id',
        ])->validate();

        $displayName = trim((string) ($validated['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim($validated['first_name'].' '.$validated['last_name']);
        }

        try {
            $record = RecordModel::findOrFail($id);

            $payload = [
                'display_name' => $displayName,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'mobile_phone' => $validated['mobile_phone'] ?? null,
                'office_phone' => $validated['office_phone'] ?? null,
                'bio' => $validated['bio'] ?? null,
                'is_technician' => array_key_exists('is_technician', $validated)
                    ? (bool) $validated['is_technician']
                    : $record->is_technician,
                'current_role' => array_key_exists('current_role', $validated)
                    ? $validated['current_role']
                    : $record->current_role,
            ];

            if (array_key_exists('avatar', $validated)) {
                $payload['avatar'] = $validated['avatar'];
            }

            $record->update($payload);

            return [
                'success' => true,
                'record' => $record->fresh(),
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateUser', [
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
            Log::error('Unexpected error in UpdateUser', [
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
