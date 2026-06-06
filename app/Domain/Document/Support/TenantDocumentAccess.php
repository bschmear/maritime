<?php

declare(strict_types=1);

namespace App\Domain\Document\Support;

use App\Domain\Document\Models\Document;
use Illuminate\Support\Facades\Storage;

/**
 * Tenant-scoped document download checks (authenticated staff).
 */
final class TenantDocumentAccess
{
    /**
     * @return list<string>
     */
    public static function tenantPathPrefixes(?string $tenantId): array
    {
        if ($tenantId === null) {
            return [
                'documents/uploads/',
                'private/documents/',
            ];
        }

        return [
            "documents/{$tenantId}/",
            "private/{$tenantId}/documents/",
        ];
    }

    public static function pathBelongsToTenant(string $path, ?string $tenantId): bool
    {
        if ($path === '' || str_contains($path, '..')) {
            return false;
        }

        foreach (self::tenantPathPrefixes($tenantId) as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function fileExists(string $path): bool
    {
        return $path !== '' && Storage::disk('s3')->exists($path);
    }

    public static function tenantCanDownload(Document $document): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $path = (string) $document->file;

        if (! self::pathBelongsToTenant($path, tenant()?->id)) {
            return false;
        }

        return self::fileExists($path);
    }
}
