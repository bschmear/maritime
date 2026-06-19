<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Bill\Actions\UpdateBill;
use App\Domain\Bill\Models\Bill;
use App\Domain\Integration\Models\Integration;
use App\Domain\Integration\Support\QuickBooksSettings;
use App\Enums\Integration\IntegrationType;
use App\Services\Payments\QuickBooksOAuthService;
use App\Support\QuickBooks\QuickBooksBillMapper;
use App\Support\QuickBooks\QuickBooksBillVendorLinker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PullSingleBillFromQuickBooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $tenantUserProfileId,
        public int $billId,
    ) {}

    public function handle(
        QuickBooksOAuthService $oauth,
        QuickBooksBillVendorLinker $vendorLinker,
        UpdateBill $updateBill,
    ): void {
        if (! QuickBooksSettings::forCurrentTenant()->isSyncBillsEnabled()) {
            return;
        }

        $bill = Bill::query()->find($this->billId);
        if ($bill === null || ! $bill->quickbooks_bill_id) {
            return;
        }

        $integration = Integration::query()
            ->where('integration_type', IntegrationType::QuickBooks)
            ->first();

        if (! $integration?->access_token || ! $integration->refresh_token) {
            return;
        }

        $row = $oauth->readBillForIntegration($integration, (string) $bill->quickbooks_bill_id);
        $payload = QuickBooksBillMapper::mapBillRow($row);
        $payload = $vendorLinker->enrichPayload($integration, $payload, $row);

        $updateBill((int) $bill->id, $payload);
    }
}
