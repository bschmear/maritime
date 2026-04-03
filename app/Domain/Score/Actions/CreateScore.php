<?php

declare(strict_types=1);

namespace App\Domain\Score\Actions;

use App\Domain\Score\Models\Score as RecordModel;
use App\Domain\Score\Support\LatestScoreForScorable;
use App\Domain\Score\Support\ScorableTypeResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateScore
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{success: bool, record?: RecordModel, message?: string}
     */
    public function __invoke(array $data): array
    {
        try {
            $validated = Validator::make($data, [
                'scorable_type' => ['required', 'string'],
                'scorable_id' => ['required', 'integer'],
                'user_id' => ['nullable', 'integer', 'exists:users,id'],
                'score_type' => ['required', 'string', 'in:manual,behavior'],
                'score_value' => ['nullable', 'numeric'],
                'weight' => ['nullable', 'numeric'],
                'meta' => ['nullable', 'array'],
                'meta.breakdown' => ['nullable', 'array'],
                'meta.reason' => ['nullable', 'string'],
                'meta.stage' => ['nullable', 'string'],
                'meta.model_version' => ['nullable', 'string'],
                'meta.auto_generated' => ['nullable', 'boolean'],
                'meta.confidence' => ['nullable', 'numeric', 'between:0,1'],
                'meta.event_id' => ['nullable', 'integer'],
                'notes' => ['nullable', 'string', 'max:250'],
            ])->validate();

            $entityClass = ScorableTypeResolver::toClass($validated['scorable_type']);
            if ($entityClass === null) {
                throw ValidationException::withMessages([
                    'scorable_type' => ['The selected scorable type is invalid.'],
                ]);
            }

            /** @var Model $entity */
            $entity = $entityClass::query()->findOrFail($validated['scorable_id']);

            $scoreValue = $validated['score_value'] ?? null;
            $meta = $validated['meta'] ?? [];

            if ($scoreValue === null && isset($meta['breakdown']) && is_array($meta['breakdown'])) {
                $scoreValue = $this->calculateScoreFromBreakdown($meta['breakdown']);
            }

            if ($scoreValue !== null) {
                $scoreValue = min((float) $scoreValue, 100);
            }

            $meta = array_merge([
                'breakdown' => [],
                'reason' => '',
                'stage' => '',
                'model_version' => '1.0',
                'auto_generated' => false,
                'confidence' => null,
                'event_id' => null,
            ], $meta);

            RecordModel::query()
                ->where('scorable_type', $entityClass)
                ->where('scorable_id', $entity->getKey())
                ->update(['is_current' => false]);

            $totalScores = RecordModel::query()
                ->where('scorable_type', $entityClass)
                ->where('scorable_id', $entity->getKey())
                ->where('score_type', $validated['score_type'])
                ->count();

            if ($totalScores >= 5) {
                RecordModel::query()
                    ->where('scorable_type', $entityClass)
                    ->where('scorable_id', $entity->getKey())
                    ->where('score_type', $validated['score_type'])
                    ->where('is_current', false)
                    ->orderBy('created_at', 'asc')
                    ->first()
                    ?->delete();
            }

            $assignedId = $entity->getAttribute('assigned_id')
                ?? $entity->getAttribute('assigned_user_id');

            $record = RecordModel::query()->create([
                'user_id' => $validated['user_id'] ?? null,
                'assigned_id' => $assignedId,
                'scorable_type' => $entityClass,
                'scorable_id' => $entity->getKey(),
                'score_type' => $validated['score_type'],
                'score_value' => $scoreValue ?? 0,
                'weight' => $validated['weight'] ?? null,
                'meta' => $meta,
                'notes' => $validated['notes'] ?? null,
                'is_current' => true,
            ]);

            LatestScoreForScorable::sync($entity, $record);

            return [
                'success' => true,
                'record' => $record->load('scorable'),
            ];
        } catch (ValidationException|ModelNotFoundException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database query error in CreateScore', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateScore', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  list<array{component?: string, value?: mixed}>  $breakdown
     */
    protected function calculateScoreFromBreakdown(array $breakdown): float
    {
        $total = 0.0;

        foreach ($breakdown as $component) {
            if (isset($component['value']) && is_numeric($component['value'])) {
                $total += (float) $component['value'];
            }
        }

        return round(min($total, 100), 2);
    }
}
