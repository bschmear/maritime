<?php

namespace App\Domain\SystemLog\Support;

use App\Domain\SystemLog\Models\SystemLog;
use App\Enums\System\SystemLogAction;
use Illuminate\Database\Eloquent\Model;

class LogSystemEvent
{
    public static function record(Model $model, SystemLogAction $action): void
    {
        SystemLog::query()->create([
            'loggable_type' => $model->getMorphClass(),
            'loggable_id' => $model->getKey(),
            'action' => $action->value,
            'user_id' => current_tenant_user_id(),
            'created_at' => now(),
        ]);
    }
}
