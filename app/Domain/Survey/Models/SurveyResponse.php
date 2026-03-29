<?php

namespace App\Domain\Survey\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SurveyResponse extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'survey_id',
        'assigned_to',
        'sourceable_id',
        'sourceable_type',
        'owner_id',
        'owner_type',
        'first_name',
        'last_name',
        'email',
        'converted',
        'tasks_applied',
        'submitted_at',
        'ip_address',
        'user_agent',
        'scheduled_followup_email_id',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'converted' => 'boolean',
        'tasks_applied' => 'boolean',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SurveyResponseAnswer::class, 'response_id');
    }
}
