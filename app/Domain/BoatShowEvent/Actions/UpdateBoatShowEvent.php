<?php

namespace App\Domain\BoatShowEvent\Actions;

use App\Domain\BoatShowEvent\Models\BoatShowEvent as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdateBoatShowEvent
{
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, [
            'boat_show_id' => ['sometimes', 'required', 'exists:boat_shows,id'],
            'display_name' => ['sometimes', 'required', 'string', 'max:255'],
            'year' => ['sometimes', 'required', 'integer', 'min:2000', 'max:2100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'venue' => ['nullable', 'string', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'booth' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'meta' => ['nullable', 'array'],
            'auto_followup' => ['sometimes', 'boolean'],
            'delay_amount' => ['sometimes', 'integer', 'min:0'],
            'delay_unit' => ['sometimes', 'string', 'in:minutes,hours,days'],
            'recipient_user_ids' => ['nullable', 'array'],
            'recipient_user_ids.*' => ['integer', 'exists:users,id'],
            'email_template_id' => ['nullable', 'integer', 'exists:email_templates,id'],
        ])->validate();

        if (array_key_exists('recipient_user_ids', $validated)) {
            $ids = array_values(array_unique(array_filter($validated['recipient_user_ids'] ?? [])));
            $validated['recipients'] = $ids === [] ? null : ['user_ids' => $ids];
            unset($validated['recipient_user_ids']);
        }

        try {
            $record = RecordModel::query()->findOrFail($id);
            $record->update($validated);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateBoatShowEvent', [
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
            Log::error('Unexpected error in UpdateBoatShowEvent', [
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
