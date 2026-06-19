<?php

namespace App\Domain\Survey\Models;

use App\Domain\Contact\Models\Contact;
use App\Domain\User\Models\User;
use App\Enums\Surveys\InvitationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyInvitation extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'survey_id',
        'record_type',
        'record_id',
        'contact_id',
        'recipient_email',
        'recipient_mobile',
        'assigned_to_user_id',
        'sent_by_user_id',
        'sent_by_web_user_id',
        'scheduled_at',
        'sent_at',
        'status',
        'send_email',
        'send_sms',
        'error_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'send_email' => 'boolean',
        'send_sms' => 'boolean',
        'status' => InvitationStatus::class,
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function isCancellable(): bool
    {
        return $this->status === InvitationStatus::Scheduled;
    }

    public function isDeletable(): bool
    {
        return $this->status === InvitationStatus::Cancelled;
    }
}
