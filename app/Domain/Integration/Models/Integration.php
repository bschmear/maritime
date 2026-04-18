<?php

declare(strict_types=1);

namespace App\Domain\Integration\Models;

use App\Domain\User\Models\User;
use App\Enums\Integration\IntegrationSyncStatus;
use App\Enums\Integration\IntegrationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Integration extends Model
{
    protected $connection = 'tenant';

    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
        'metadata' => 'array',
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'integration_type' => IntegrationType::class,
        'sync_status' => IntegrationSyncStatus::class,
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'sync_token' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
