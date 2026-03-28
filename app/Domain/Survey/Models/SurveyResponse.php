<?php
// App\Models\Survey\SurveyResponse.php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Scopes\TeamScope;
use App\Models\Communication;

class SurveyResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'team_id',
        'deal_id',
        'owner_id',
        'owner_type',
        'assigned_to',
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

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new TeamScope());
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Team::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Deal::class);
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SurveyResponseAnswer::class, 'response_id');
    }

    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class, 'survey_response_id');
    }

    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(\App\Models\AiSurveyAnalysis::class, 'survey_response_id');
    }

    public function latestAiAnalysis(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\AiSurveyAnalysis::class, 'survey_response_id')->latestOfMany();
    }

    public function hasAiAnalysis(): bool
    {
        return $this->aiAnalyses()->exists();
    }

    public function scheduledFollowupEmail(): BelongsTo
    {
        return $this->belongsTo(\App\Models\EmailSent::class, 'scheduled_followup_email_id');
    }

    public function getSurveyResponse($surveyId): string
    {
        return config('app.crm_url') . '/surveys/survey/response?sid=' . $surveyId . '&rid=' . $this->id;
    }
}
