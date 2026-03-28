<?php

namespace App\Domain\Survey\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyFollowup extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'email_subject',
        'email_body',
        'wait_time',
        'send_after_submission',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class);
    }

    public function logs()
    {
        return $this->hasMany(SurveyFollowupLog::class, 'followup_id');
    }
}
