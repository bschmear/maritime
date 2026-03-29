<?php

namespace App\Domain\Survey\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyResponseAnswer extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'response_id',
        'survey_question_id',
        'answer',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(SurveyResponse::class, 'response_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }
}
