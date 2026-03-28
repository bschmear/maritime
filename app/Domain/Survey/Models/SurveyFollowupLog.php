<?php

namespace App\Domain\Survey\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyFollowupLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'response_id',
        'followup_id',
        'sent_at',
        'status',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function response()
    {
        return $this->belongsTo(SurveyResponse::class, 'response_id');
    }

    public function followup()
    {
        return $this->belongsTo(SurveyFollowup::class, 'followup_id');
    }

}
