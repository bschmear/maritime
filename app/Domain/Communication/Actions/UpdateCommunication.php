<?php

declare(strict_types=1);

namespace App\Domain\Communication\Actions;

use App\Domain\Communication\Models\Communication as RecordModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateCommunication
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{success: bool, record?: RecordModel|null, message?: string}
     */
    public function __invoke(int $id, array $data): array
    {
        try {
            $validated = Validator::make($data, [
                'communication_type_id' => ['sometimes', 'integer'],
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

            $record = RecordModel::query()->findOrFail($id);

            if (array_key_exists('next_action_at', $validated)) {
                $validated['next_action_at'] = $validated['next_action_at'] !== null
                    ? Carbon::parse($validated['next_action_at'])->utc()
                    : null;
            }
            if (array_key_exists('date_contacted', $validated) && $validated['date_contacted'] !== null) {
                $validated['date_contacted'] = Carbon::parse($validated['date_contacted'])->utc();
            }

            $record->update($validated);

            return [
                'success' => true,
                'record' => $record->fresh(['user']),
            ];
        } catch (ValidationException|ModelNotFoundException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateCommunication', [
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
            Log::error('Unexpected error in UpdateCommunication', [
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
