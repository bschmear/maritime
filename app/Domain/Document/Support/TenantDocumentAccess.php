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
    public static function tenantCanDownload(Document $document): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $path = (string) $document->file;
        if ($path === '' || str_contains($path, '..')) {
            return false;
        }

        $tenantId = tenant()?->id;
        if ($tenantId !== null) {
            $expectedPrefix = 'documents/'.$tenantId.'/';
            if (! str_starts_with($path, $expectedPrefix)) {
                return false;
            }
        }

        return Storage::disk('s3')->exists($path);
    }
}
