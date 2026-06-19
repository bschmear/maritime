<?php

declare(strict_types=1);

namespace App\Support\QuickBooks;

/**
 * Optional QuickBooks vendor import diagnostics (disabled by default).
 */
final class QuickBooksVendorImportDiagnostics
{
    public const CHANNEL = 'quickbooks_vendor_import';

    /**
     * @param  array<string, mixed>  $context
     */
    public static function logJobStarted(array $context = []): void
    {
        // Disabled — enable via QUICKBOOKS_VENDOR_IMPORT_DIAGNOSTICS=true if needed.
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function logJobFinished(array $context = []): void
    {
        // Disabled — enable via QUICKBOOKS_VENDOR_IMPORT_DIAGNOSTICS=true if needed.
    }

    /**
     * @param  array<string, mixed>  $qboPayload
     */
    public static function logQboPayload(
        array $qboPayload,
        ?int $localVendorId = null,
        ?string $importAction = null,
    ): void {
        // Disabled — enable via QUICKBOOKS_VENDOR_IMPORT_DIAGNOSTICS=true if needed.
    }
}
