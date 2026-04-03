<?php

namespace App\Domain\Score\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Score extends Model
{
    protected $fillable = [
        'scorable_type',
        'scorable_id',
        'user_id',
        'assigned_id',
        'score_type',
        'score_value',
        'weight',
        'meta',
        'notes',
        'is_current',
    ];

    protected $casts = [
        'score_value' => 'decimal:2',
        'weight' => 'decimal:2',
        'meta' => 'array',
        'is_current' => 'boolean',
        'user_id' => 'integer',
        'assigned_id' => 'integer',
    ];

    public function scorable(): MorphTo
    {
        return $this->morphTo();
    }
}
