<?php

namespace App\Models;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SupportTicket extends Model
{
    protected $connection = 'pgsql';

    protected $fillable = [
        'uid',
        'ticket_number',
        'user_id',
        'tenant_id',
        'category',
        'message',
        'date_submitted',
        'escalated',
        'priority',
        'completed',
        'time_completed',
        'reopened',
        'solved',
        'agent',
        'subject',
        'status',
        'satisfaction',
    ];

    protected $appends = [
        'status_label',
        'status_color',
        'category_label',
    ];

    protected $casts = [
        'date_submitted' => 'datetime',
        'time_completed' => 'datetime',
        'escalated' => 'boolean',
        'completed' => 'boolean',
        'reopened' => 'boolean',
        'solved' => 'boolean',
        'category' => SupportTicketCategory::class,
        'priority' => SupportTicketPriority::class,
        'status' => SupportTicketStatus::class,
    ];

    public function getStatusLabelAttribute(): string
    {
        return $this->resolveStatus()->label();
    }

    public function getStatusColorAttribute(): string
    {
        return $this->resolveStatus()->color();
    }

    public function getCategoryLabelAttribute(): string
    {
        $category = $this->category;

        if ($category instanceof SupportTicketCategory) {
            return $category->label();
        }

        if ($category !== null) {
            return SupportTicketCategory::from((int) $category)->label();
        }

        return SupportTicketCategory::General->label();
    }

    public function resolveStatus(): SupportTicketStatus
    {
        $status = $this->status;

        if ($status instanceof SupportTicketStatus) {
            return $status;
        }

        return SupportTicketStatus::from((int) ($status ?? $this->attributes['status'] ?? 1));
    }

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket) {
            if (empty($ticket->uid)) {
                $ticket->uid = (string) Str::uuid();
            }
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = 'TKT-'.strtoupper(Str::random(8));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(TicketResponse::class)->orderBy('created_at');
    }

    public function publicResponses(): HasMany
    {
        return $this->responses()->where('internal', false);
    }
}
