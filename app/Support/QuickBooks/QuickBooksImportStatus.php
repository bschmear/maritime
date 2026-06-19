<?php

declare(strict_types=1);

namespace App\Support\QuickBooks;

use App\Domain\Integration\Models\Integration;
use App\Enums\Integration\IntegrationSyncStatus;
use App\Enums\Integration\IntegrationType;

final class QuickBooksImportStatus
{
    public static function integration(): ?Integration
    {
        return Integration::query()
            ->where('integration_type', IntegrationType::QuickBooks)
            ->first();
    }

    public static function markSyncing(?Integration $integration = null): void
    {
        $integration ??= self::integration();
        if ($integration === null) {
            return;
        }

        $integration->update([
            'sync_status' => IntegrationSyncStatus::Syncing,
            'sync_error_message' => null,
        ]);
    }

    public static function markSuccess(?Integration $integration = null): void
    {
        $integration ??= self::integration();
        if ($integration === null) {
            return;
        }

        $integration->update([
            'sync_status' => IntegrationSyncStatus::Success,
            'last_synced_at' => now(),
            'sync_error_message' => null,
        ]);
    }

    public static function markFailed(?string $message = null, ?Integration $integration = null): void
    {
        $integration ??= self::integration();
        if ($integration === null) {
            return;
        }

        $integration->update([
            'sync_status' => IntegrationSyncStatus::Failed,
            'sync_error_message' => $message ?: 'Import failed.',
        ]);
    }

    public static function clearActiveImport(?Integration $integration = null): void
    {
        $integration ??= self::integration();
        if ($integration === null) {
            return;
        }

        $metadata = is_array($integration->metadata) ? $integration->metadata : [];
        unset($metadata['sync_operation']);

        $integration->update([
            'sync_status' => IntegrationSyncStatus::Success,
            'sync_error_message' => null,
            'metadata' => $metadata,
        ]);
    }
}
