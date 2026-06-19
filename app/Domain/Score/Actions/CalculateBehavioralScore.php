<?php

declare(strict_types=1);

namespace App\Domain\Score\Actions;

use App\Domain\Score\Models\Score;
use App\Domain\Score\Support\BehavioralScoreCalculator;
use App\Domain\Score\Support\ScorableTypeResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class CalculateBehavioralScore
{
    public function __construct(
        private BehavioralScoreCalculator $calculator,
        private CreateScore $createScore,
        private UpdateScore $updateScore,
    ) {}

    /**
     * @return array{success: bool, record?: Score, message?: string}
     */
    public function __invoke(
        string $scorableType,
        int $scorableId,
        ?int $userId = null,
        bool $updateCurrent = false,
    ): array {
        $entityClass = ScorableTypeResolver::toClass($scorableType);
        if ($entityClass === null) {
            throw ValidationException::withMessages([
                'scorable_type' => ['The selected scorable type is invalid.'],
            ]);
        }

        /** @var Model $entity */
        $entity = $entityClass::query()->findOrFail($scorableId);

        $calculated = $this->calculator->calculate($entity);

        if ($updateCurrent) {
            $existing = Score::query()
                ->where('scorable_type', $entityClass)
                ->where('scorable_id', $entity->getKey())
                ->where('score_type', 'behavior')
                ->where('is_current', true)
                ->first();

            if ($existing) {
                $result = ($this->updateScore)($existing->id, [
                    'score_value' => $calculated['score'],
                    'meta' => $calculated['meta'],
                ]);

                if (! ($result['success'] ?? false) || ! isset($result['record'])) {
                    return [
                        'success' => false,
                        'message' => $result['message'] ?? 'Could not update behavioral score.',
                    ];
                }

                return [
                    'success' => true,
                    'record' => $result['record'],
                ];
            }
        }

        $result = ($this->createScore)([
            'scorable_type' => $scorableType,
            'scorable_id' => $scorableId,
            'user_id' => $userId,
            'score_type' => 'behavior',
            'score_value' => $calculated['score'],
            'meta' => $calculated['meta'],
            'notes' => 'Behavioral score',
        ]);

        if (! ($result['success'] ?? false) || ! isset($result['record'])) {
            return [
                'success' => false,
                'message' => $result['message'] ?? 'Could not create behavioral score.',
            ];
        }

        return [
            'success' => true,
            'record' => $result['record'],
        ];
    }
}
