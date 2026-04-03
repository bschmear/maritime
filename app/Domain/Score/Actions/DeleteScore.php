<?php

declare(strict_types=1);

namespace App\Domain\Score\Actions;

use App\Domain\Score\Models\Score as RecordModel;
use App\Domain\Score\Support\LatestScoreForScorable;
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
            $entity = $record->scorable;
            $wasCurrentScore = (bool) $record->is_current;
            $entityClass = $record->scorable_type;
            $entityId = $record->scorable_id;

            $record->delete();

            if ($wasCurrentScore && $entity) {
                $nextScore = RecordModel::query()
                    ->where('scorable_type', $entityClass)
                    ->where('scorable_id', $entityId)
                    ->orderByDesc('created_at')
                    ->first();

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
}
