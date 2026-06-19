<?php

declare(strict_types=1);

namespace App\Support\QuickBooks;

use App\Domain\Integration\Models\Integration;
use App\Services\Payments\QuickBooksOAuthService;

/**
 * Resolves {@see vendor_id} on bill import payloads when QuickBooks VendorRef alone is insufficient.
 */
final class QuickBooksBillVendorLinker
{
    public function __construct(
        private readonly QuickBooksOAuthService $oauth,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  Bill mapper output (for_import)
     * @param  array<string, mixed>  $billRow  Raw QuickBooks Bill entity
     * @return array<string, mixed>
     */
    public function enrichPayload(Integration $integration, array $payload, array $billRow): array
    {
        if (($payload['vendor_id'] ?? null) !== null) {
            return $payload;
        }

        $vendorRef = is_array($billRow['VendorRef'] ?? null) ? $billRow['VendorRef'] : null;
        $qboVendorId = $payload['quickbooks_vendor_id']
            ?? QuickBooksVendorResolver::quickbooksVendorIdFromRef($vendorRef);

        $qboVendorRow = null;
        if (is_string($qboVendorId) && $qboVendorId !== '') {
            try {
                $qboVendorRow = $this->oauth->readVendorForIntegration($integration, $qboVendorId);
            } catch (\Throwable) {
                // Fall back to VendorRef name / id matching only.
            }
        }

        $vendorId = QuickBooksVendorResolver::resolveLocalVendorId($vendorRef, $qboVendorRow);
        if ($vendorId === null) {
            return $payload;
        }

        $payload['vendor_id'] = $vendorId;

        if (is_string($qboVendorId) && $qboVendorId !== '') {
            $payload['quickbooks_vendor_id'] = $qboVendorId;
            QuickBooksVendorResolver::backfillQuickbooksIdOnVendor($vendorId, $qboVendorId);
        }

        return $payload;
    }
}
