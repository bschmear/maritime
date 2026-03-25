<?php

namespace App\Domain\BoatShow\Actions;

use App\Domain\BoatShow\Models\BoatShow as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class CreateBoatShow
{
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, [
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'integer'],
            'banner' => ['nullable', 'integer'],
            'meta' => ['nullable', 'array'],
        ])->validate();

        // if (empty($validated['slug'])) {
        $base = Str::slug($validated['display_name']) ?: 'boat-show';
        $slug = $base;
        $n = 0;
        while (RecordModel::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }
        $validated['slug'] = $slug;
        // }

        try {
            $record = RecordModel::query()->create($validated);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateBoatShow', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateBoatShow', [
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
