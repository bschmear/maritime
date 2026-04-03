<?php

namespace App\Domain\Communication\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Communication extends Model
{
    protected $fillable = [
        'user_id',
        'communicable_type',
        'communicable_id',
        'communication_type_id',
        'direction',
        'subject',
        'notes',
        'status_id',
        'priority_id',
        'outcome_id',
        'channel_id',
        'assigned_to',
        'tags',
        'is_private',
        'next_action_type_id',
        'next_action_at',
        'date_contacted',
        'calendar_id',
        'event_id',
    ];

    protected $casts = [
        'tags' => 'array',
        'needs_follow_up' => 'boolean',
        'is_private' => 'boolean',
        'next_action_at' => 'datetime',
        'date_contacted' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function communicable(): MorphTo
    {
        return $this->morphTo();
    }
}
