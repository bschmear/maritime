<?php

namespace App\Domain\BoatShowLayout\Actions;

use App\Domain\BoatShowLayout\Models\BoatShowLayout as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdateBoatShowLayout
{
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, [
            'boat_show_event_id' => ['sometimes', 'required', 'exists:boat_show_events,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'width_ft' => ['sometimes', 'required', 'integer', 'min:1', 'max:500'],
            'height_ft' => ['sometimes', 'required', 'integer', 'min:1', 'max:500'],
            'grid_size' => ['nullable', 'integer', 'min:1', 'max:10'],
            'scale' => ['nullable', 'integer', 'min:1', 'max:50'],
            'meta' => ['nullable', 'array'],
        ])->validate();

        try {
            $record = RecordModel::query()->findOrFail($id);
            $record->update($validated);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateBoatShowLayout', [
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
            Log::error('Unexpected error in UpdateBoatShowLayout', [
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
