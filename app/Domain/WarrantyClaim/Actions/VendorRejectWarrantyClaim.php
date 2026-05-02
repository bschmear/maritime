<?php

declare(strict_types=1);

namespace App\Domain\WarrantyClaim\Actions;

use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Enums\WarrantyClaim\Status;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class VendorRejectWarrantyClaim
{
    /**
     * @param  array{rejection_reason?: string|null, vendor_notes?: string|null}  $payload
     */
    public function __invoke(WarrantyClaim $claim, int $vendorContactId, array $payload): array
    {
        $status = $claim->status instanceof Status ? $claim->status : Status::tryFrom((string) $claim->getRawOriginal('status')) ?? Status::Draft;
        if ($status !== Status::Submitted) {
            return ['success' => false, 'message' => 'This claim cannot be rejected in its current status.'];
        }

        try {
            DB::transaction(function () use ($claim, $vendorContactId, $payload) {
                $incoming = isset($payload['vendor_notes']) ? trim((string) $payload['vendor_notes']) : '';
                $existing = trim((string) ($claim->vendor_notes ?? ''));
                $merged = $existing === ''
                    ? ($incoming !== '' ? $incoming : null)
                    : ($incoming !== '' ? $existing."\n\n".$incoming : $existing);

                $claim->update([
                    'status' => Status::Rejected->value,
                    'rejection_reason' => isset($payload['rejection_reason']) ? (string) $payload['rejection_reason'] : null,
                    'vendor_notes' => $merged,
                    'vendor_rejected_at' => now(),
                    'vendor_rejected_by_contact_id' => $vendorContactId,
                ]);
            });

            return ['success' => true, 'record' => $claim->fresh(['lineItems.workOrderServiceItem', 'vendor'])];
        } catch (Throwable $e) {
            Log::error('VendorRejectWarrantyClaim failed', [
                'claim_id' => $claim->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
