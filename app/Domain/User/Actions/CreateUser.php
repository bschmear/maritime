<?php

namespace App\Domain\User\Actions;

use App\Domain\User\Models\User as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class CreateUser
{
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'mobile_phone' => 'nullable|string|max:20',
            'office_phone' => 'nullable|string|max:20',
            'position_title' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'avatar' => 'nullable|integer|exists:documents,id',
            'is_technician' => 'sometimes|boolean',
            'current_role' => 'nullable|exists:roles,id',
        ])->validate();

        $displayName = Str::limit(trim(trim($validated['first_name']).' '.trim($validated['last_name'])), 255, '');

        try {
            $record = RecordModel::create([
                'display_name' => $displayName,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'position_title' => $validated['position_title'] ?? null,
                'email' => $validated['email'],
                'mobile_phone' => $validated['mobile_phone'] ?? null,
                'office_phone' => $validated['office_phone'] ?? null,
                'position_title' => $validated['position_title'] ?? null,
                'bio' => $validated['bio'] ?? null,
                'avatar' => $validated['avatar'] ?? null,
                'is_technician' => (bool) ($validated['is_technician'] ?? false),
                'current_role' => $validated['current_role'] ?? null,
            ]);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateUser', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateUser', [
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
