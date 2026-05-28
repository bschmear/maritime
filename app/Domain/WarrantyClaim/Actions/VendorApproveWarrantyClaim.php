<?php

declare(strict_types=1);

namespace App\Domain\WarrantyClaim\Actions;

use App\Domain\User\Models\User;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Enums\WarrantyClaim\Status;
use App\Mail\WarrantyClaimVendorApprovedCreator;
use App\Models\AccountSettings;
use App\Services\Mail\TenantMailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class VendorApproveWarrantyClaim
{
    public function __invoke(WarrantyClaim $claim, int $vendorContactId): array
    {
        $status = $claim->status instanceof Status ? $claim->status : Status::tryFrom((string) $claim->getRawOriginal('status')) ?? Status::Draft;
        if ($status !== Status::Submitted) {
            return ['success' => false, 'message' => 'This claim cannot be approved in its current status.'];
        }

        try {
            DB::transaction(function () use ($claim, $vendorContactId) {
                $now = now();
                $claim->update([
                    'status' => Status::Approved->value,
                    'approved_at' => $now,
                    'approved_by_vendor' => true,
                    'vendor_approved_at' => $now,
                    'vendor_approved_by_contact_id' => $vendorContactId,
                ]);
            });

            $claim->refresh();
            $this->notifyCreator($claim);

            return ['success' => true, 'record' => $claim->fresh(['lineItems.workOrderServiceItem', 'vendor'])];
        } catch (Throwable $e) {
            Log::error('VendorApproveWarrantyClaim failed', [
                'claim_id' => $claim->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function notifyCreator(WarrantyClaim $claim): void
    {
        if (! $claim->created_by_user_id) {
            return;
        }

        $user = User::query()->find((int) $claim->created_by_user_id);
        if (! $user || ! $user->email) {
            return;
        }

        $account = AccountSettings::getCurrent();

        try {
            app(TenantMailService::class)->send($user->email, new WarrantyClaimVendorApprovedCreator($claim, $account, $user));
        } catch (Throwable $e) {
            Log::error('Failed to email warranty claim creator after vendor approval', [
                'claim_id' => $claim->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
