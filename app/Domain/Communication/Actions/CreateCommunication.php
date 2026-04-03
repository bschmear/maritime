<?php

declare(strict_types=1);

namespace App\Domain\Communication\Actions;

use App\Domain\Communication\Models\Communication as RecordModel;
use App\Domain\Communication\Support\CommunicableTypeResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateCommunication
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{success: bool, record?: RecordModel, message?: string}
     */
    public function __invoke(array $data): array
    {
        try {
            $validated = Validator::make($data, [
                'communicable_type' => ['required', 'string'],
                'communicable_id' => ['required', 'integer'],
                'user_id' => ['required', 'integer', 'exists:users,id'],
                'communication_type_id' => ['required', 'integer'],
                'direction' => ['nullable', 'string', 'in:inbound,outbound'],
                'subject' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string'],
                'needs_follow_up' => ['sometimes', 'boolean'],
                'is_private' => ['sometimes', 'boolean'],
                'status_id' => ['sometimes', 'integer'],
                'channel_id' => ['nullable', 'integer'],
                'priority_id' => ['sometimes', 'integer'],
                'tags' => ['nullable', 'array'],
                'tags.*' => ['string', 'max:100'],
                'outcome_id' => ['nullable', 'integer'],
                'next_action_type_id' => ['nullable', 'integer'],
                'next_action_at' => ['nullable', 'date'],
                'date_contacted' => ['nullable', 'date'],
                'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
                'calendar_id' => ['nullable', 'string', 'max:255'],
                'event_id' => ['nullable', 'string', 'max:255'],
            ])->validate();

            $entityClass = CommunicableTypeResolver::toClass($validated['communicable_type']);
            if ($entityClass === null) {
                throw ValidationException::withMessages([
                    'communicable_type' => ['The selected communicable type is invalid.'],
                ]);
            }

            CommunicableTypeResolver::findCommunicable($entityClass, (int) $validated['communicable_id']);

            $nextActionAt = isset($validated['next_action_at'])
                ? Carbon::parse($validated['next_action_at'])->utc()
                : null;
            $dateContacted = isset($validated['date_contacted'])
                ? Carbon::parse($validated['date_contacted'])->utc()
                : Carbon::now('UTC');

            $record = RecordModel::query()->create([
                'user_id' => $validated['user_id'],
                'communicable_type' => $entityClass,
                'communicable_id' => $validated['communicable_id'],
                'communication_type_id' => $validated['communication_type_id'],
                'direction' => $validated['direction'] ?? null,
                'subject' => $validated['subject'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'needs_follow_up' => (bool) ($validated['needs_follow_up'] ?? false),
                'is_private' => (bool) ($validated['is_private'] ?? false),
                'status_id' => (int) ($validated['status_id'] ?? 1),
                'channel_id' => $validated['channel_id'] ?? null,
                'priority_id' => (int) ($validated['priority_id'] ?? 2),
                'tags' => $validated['tags'] ?? null,
                'outcome_id' => $validated['outcome_id'] ?? null,
                'next_action_type_id' => $validated['next_action_type_id'] ?? null,
                'next_action_at' => $nextActionAt,
                'date_contacted' => $dateContacted,
                'assigned_to' => $validated['assigned_to'] ?? null,
                'calendar_id' => $validated['calendar_id'] ?? null,
                'event_id' => $validated['event_id'] ?? null,
            ]);

            return [
                'success' => true,
                'record' => $record->fresh(['user']),
            ];
        } catch (ValidationException|ModelNotFoundException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database query error in CreateCommunication', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateCommunication', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
