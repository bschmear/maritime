<?php

namespace App\Domain\SystemLog\Support;

use App\Domain\SystemLog\Models\SystemLog;
use App\Enums\System\SystemLogAction;
use Illuminate\Database\Eloquent\Model;

class LogSystemEvent
{
    public static function record(Model $model, SystemLogAction $action, ?string $actorLabel = null): void
    {
        $actorLabel = trim((string) ($actorLabel ?? '')) ?: null;

        SystemLog::query()->create([
            'loggable_type' => $model->getMorphClass(),
            'loggable_id' => $model->getKey(),
            'action' => $action->value,
            'user_id' => $actorLabel !== null ? null : current_tenant_user_id(),
            'actor_label' => $actorLabel,
            'created_at' => now(),
        ]);
    }
}
