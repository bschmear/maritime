<?php

declare(strict_types=1);

namespace App\Domain\Score\Actions;

use App\Domain\Score\Models\Score as RecordModel;
use App\Domain\Score\Support\LatestScoreForScorable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteScore
{
    /**
     * @return array{success: bool, message?: string}
     */
    public function __invoke(int $id): array
    {
        try {
            $record = RecordModel::query()->findOrFail($id);
            $wasCurrentScore = (bool) $record->is_current;
            $entityClass = (string) $record->scorable_type;
            $entityId = (int) $record->scorable_id;

            $entity = $this->resolveScorable($entityClass, $entityId);

            if ($entity !== null && LatestScoreForScorable::supports($entity)) {
                if ((int) $entity->getAttribute('latest_score_id') === $record->getKey()) {
                    LatestScoreForScorable::sync($entity, null);
                }
            }

            $record->delete();

            if ($wasCurrentScore && $entity !== null && LatestScoreForScorable::supports($entity)) {
                $nextScore = RecordModel::query()
                    ->where('scorable_type', $entityClass)
                    ->where('scorable_id', $entityId)
                    ->orderByDesc('created_at')
                    ->first();

                $entity = $this->resolveScorable($entityClass, $entityId);

                if ($nextScore) {
                    $nextScore->update(['is_current' => true]);
                    LatestScoreForScorable::sync($entity, $nextScore);
                } else {
                    LatestScoreForScorable::sync($entity, null);
                }
            }

            return [
                'success' => true,
                'message' => 'Score deleted successfully.',
            ];
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteScore', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteScore', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  class-string<Model>|string  $entityClass
     */
    private function resolveScorable(string $entityClass, int $entityId): ?Model
    {
        if (! class_exists($entityClass) || ! is_subclass_of($entityClass, Model::class)) {
            return null;
        }

        /** @var class-string<Model> $entityClass */
        return $entityClass::query()
            ->setEagerLoads([])
            ->find($entityId);
    }
}
