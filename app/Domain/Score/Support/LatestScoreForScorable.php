<?php

declare(strict_types=1);

namespace App\Domain\Score\Support;

use App\Domain\Customer\Models\Customer;
use App\Domain\Lead\Models\Lead;
use App\Domain\Score\Models\Score;
use Illuminate\Database\Eloquent\Model;

final class LatestScoreForScorable
{
    /**
     * @var list<class-string<Model>>
     */
    private const SUPPORTED_MODELS = [
        Lead::class,
        Customer::class,
    ];

    public static function supports(Model $entity): bool
    {
        return in_array($entity::class, self::SUPPORTED_MODELS, true);
    }

    public static function sync(Model $entity, ?Score $score): void
    {
        if (! $entity->exists || ! self::supports($entity)) {
            return;
        }

        if ($score) {
            $entity->update([
                'latest_score_id' => $score->id,
                'latest_score' => $score->score_value,
            ]);
        } else {
            $entity->update([
                'latest_score_id' => null,
                'latest_score' => null,
            ]);
        }
    }
}
