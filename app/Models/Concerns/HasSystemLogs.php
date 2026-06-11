<?php

namespace App\Models\Concerns;

use App\Domain\SystemLog\Models\SystemLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasSystemLogs
{
    public function systemLogs(): MorphMany
    {
        return $this->morphMany(SystemLog::class, 'loggable')
            ->with(['user' => fn ($query) => $query->select(['id', 'display_name'])])
            ->orderByDesc('created_at');
    }
}
