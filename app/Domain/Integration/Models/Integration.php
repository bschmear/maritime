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
        'external_id' => 'encrypted',
    ];

    protected static function booted(): void
    {
        static::saving(function (Integration $integration): void {
            if (! $integration->isDirty('external_id')) {
                return;
            }

            $externalId = $integration->external_id;
            $integration->external_id_hash = filled($externalId)
                ? static::hashExternalId((string) $externalId)
                : null;
        });
    }

    public static function hashExternalId(string $externalId): string
    {
        return hash('sha256', $externalId);
    }

    /**
     * Lookup attributes for updateOrCreate / firstOrCreate keyed by a third-party id.
     *
     * @return array{external_id_hash: string}
     */
    public static function attributesForExternalId(string $externalId): array
    {
        return ['external_id_hash' => static::hashExternalId($externalId)];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
