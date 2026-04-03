<?php

declare(strict_types=1);

namespace App\Domain\Score\Actions;

use App\Domain\Score\Models\Score as RecordModel;
use App\Domain\Score\Support\LatestScoreForScorable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateScore
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{success: bool, record?: RecordModel, message?: string}
     */
    public function __invoke(int $id, array $data): array
    {
        try {
            $validated = Validator::make($data, [
                'score_value' => ['sometimes', 'numeric'],
                'weight' => ['nullable', 'numeric'],
                'meta' => ['nullable', 'array'],
                'notes' => ['nullable', 'string', 'max:250'],
            ])->validate();

            $record = RecordModel::query()->findOrFail($id);
            $record->update($validated);

            $record->load('scorable');

            if ($record->is_current && $record->scorable) {
                LatestScoreForScorable::sync($record->scorable, $record);
            }

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (ValidationException|ModelNotFoundException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateScore', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdateScore', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
