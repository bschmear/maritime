<?php

namespace App\Domain\Survey\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
// use App\Scopes\TeamScope;

class SurveyQuestion extends Model
{
    protected $fillable = [
        'survey_id', 'team_id', 'type', 'label', 'required', 'order',
        'options', 'config', 'conditional_logic'
    ];

    protected $casts = [
        'options' => 'array',
        'config' => 'array',
        'conditional_logic' => 'array',
        'required' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        // static::addGlobalScope(new TeamScope());
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Team::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SurveyResponseAnswer::class, 'survey_question_id');
    }
}

