<?php

namespace App\Domain\BoatShowLayout\Actions;

use App\Domain\BoatShowLayout\Models\BoatShowLayout as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateBoatShowLayout
{
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, [
            'boat_show_event_id' => ['required', 'exists:boat_show_events,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'width_ft' => ['required', 'integer', 'min:1', 'max:500'],
            'height_ft' => ['required', 'integer', 'min:1', 'max:500'],
            'grid_size' => ['nullable', 'integer', 'min:1', 'max:10'],
            'scale' => ['nullable', 'integer', 'min:1', 'max:50'],
            'meta' => ['nullable', 'array'],
        ])->validate();

        if (! array_key_exists('grid_size', $validated)) {
            $validated['grid_size'] = 1;
        }
        if (! array_key_exists('scale', $validated)) {
            $validated['scale'] = 10;
        }

        try {
            $record = RecordModel::query()->create($validated);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateBoatShowLayout', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateBoatShowLayout', [
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
