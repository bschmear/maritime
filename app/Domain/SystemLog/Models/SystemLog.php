<?php

namespace App\Domain\SystemLog\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SystemLog extends Model
{
    public const UPDATED_AT = null;

    protected $connection = 'tenant';

    protected $fillable = [
        'loggable_type',
        'loggable_id',
        'action',
        'user_id',
        'actor_label',
        'created_at',
    ];

    protected $casts = [
        'action' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
