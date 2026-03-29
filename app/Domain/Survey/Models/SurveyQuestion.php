<?php

namespace App\Domain\Survey\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyQuestion extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'survey_id',
        'type',
        'label',
        'required',
        'order',
        'options',
        'config',
        'conditional_logic',
    ];

    protected $casts = [
        'options' => 'array',
        'config' => 'array',
        'conditional_logic' => 'array',
        'required' => 'boolean',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SurveyResponseAnswer::class, 'survey_question_id');
    }
}
