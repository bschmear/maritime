<?php

declare(strict_types=1);

namespace App\Domain\Score\Support;

use App\Domain\Score\Models\Score;
use Illuminate\Database\Eloquent\Model;

final class LatestScoreForScorable
{
    public static function sync(Model $entity, ?Score $score): void
    {
        if (! $entity->exists) {
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
