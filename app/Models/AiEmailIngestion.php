<?php

namespace App\Models;

use App\Enums\InboundEmail\IngestionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiEmailIngestion extends Model
{
    protected $connection = 'pgsql';

    protected $fillable = [
        'tenant_id',
        'email_route_id',
        'status',
        'from_email',
        'to_email',
        'subject',
        'raw_payload',
        'parsed_data',
        'error',
        'processed_at',
    ];

    protected $casts = [
        'status' => IngestionStatus::class,
        'raw_payload' => 'array',
        'parsed_data' => 'array',
        'processed_at' => 'datetime',
    ];

    public function emailRoute(): BelongsTo
    {
        return $this->belongsTo(EmailRoute::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function markProcessing(): void
    {
        $this->update(['status' => IngestionStatus::Processing]);
    }

    /**
     * @param  array<string, mixed>  $parsedData
     */
    public function markCompleted(array $parsedData): void
    {
        $this->update([
            'status' => IngestionStatus::Completed,
            'parsed_data' => $parsedData,
            'processed_at' => now(),
            'error' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => IngestionStatus::Failed,
            'error' => $error,
            'processed_at' => now(),
        ]);
    }
}
