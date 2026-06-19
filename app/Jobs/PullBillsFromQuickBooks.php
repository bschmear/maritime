<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Bill\Actions\CreateBill;
use App\Domain\Bill\Actions\UpdateBill;
use App\Domain\Bill\Models\Bill;
use App\Domain\Integration\Models\Integration;
use App\Domain\Integration\Support\QuickBooksSettings;
use App\Domain\Vendor\Models\Vendor;
use App\Enums\Integration\IntegrationType;
use App\Jobs\Concerns\MarksQuickBooksImportFailure;
use App\Services\Payments\QuickBooksOAuthService;
use App\Support\QuickBooks\QuickBooksBillMapper;
use App\Support\QuickBooks\QuickBooksBillVendorLinker;
use App\Support\QuickBooks\QuickBooksImportDateRange;
use App\Support\QuickBooks\QuickBooksImportStatus;
use App\Support\QuickBooks\QuickBooksRowMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PullBillsFromQuickBooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use MarksQuickBooksImportFailure;

    public function __construct(
        public int $tenantUserProfileId,
        public ?string $txnDateFrom = null,
        public ?string $txnDateTo = null,
    ) {}

    public function handle(
        QuickBooksOAuthService $oauth,
        QuickBooksBillVendorLinker $vendorLinker,
        CreateBill $createBill,
        UpdateBill $updateBill,
    ): void {
        if (! QuickBooksSettings::forCurrentTenant()->isSyncBillsEnabled()) {
            QuickBooksImportStatus::clearActiveImport();

            return;
        }

        $integration = Integration::query()
            ->where('integration_type', IntegrationType::QuickBooks)
            ->first();

        if (! $integration?->access_token || ! $integration->refresh_token) {
            QuickBooksImportStatus::clearActiveImport();

            return;
        }

        QuickBooksImportStatus::markSyncing($integration);

        try {
            $start = 1;
            $pageSize = 100;

            do {
                $sql = $this->txnDateFrom && $this->txnDateTo
                    ? QuickBooksImportDateRange::billQuery($this->txnDateFrom, $this->txnDateTo, $start, $pageSize)
                    : QuickBooksImportDateRange::allBillsQuery($start, $pageSize);
                $payload = $oauth->queryAccountingForIntegration($integration, $sql);
                $integration->refresh();

                if (! empty($payload['Fault'])) {
                    throw new \RuntimeException(QuickBooksRowMapper::faultMessage($payload['Fault']) ?: 'QuickBooks returned a fault.');
                }

                $rows = QuickBooksRowMapper::normalizeList($payload['QueryResponse']['Bill'] ?? []);

                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $this->importOneBill($row, $integration, $vendorLinker, $createBill, $updateBill);
                }

                $count = count($rows);
                $start += $pageSize;
            } while ($count === $pageSize);

            Vendor::refreshAllOverdueBalances();

            QuickBooksImportStatus::markSuccess($integration);
        } catch (\Throwable $e) {
            QuickBooksImportStatus::markFailed($e->getMessage(), $integration);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importOneBill(
        array $row,
        Integration $integration,
        QuickBooksBillVendorLinker $vendorLinker,
        CreateBill $createBill,
        UpdateBill $updateBill,
    ): void {
        $qboId = QuickBooksRowMapper::refValue(['value' => $row['Id'] ?? null]);
        if ($qboId === '') {
            return;
        }

        $payload = QuickBooksBillMapper::mapBillRow($row);

        if (($payload['vendor_id'] ?? null) === null && ! empty($payload['quickbooks_vendor_id'])) {
            $payload = $vendorLinker->enrichPayload($integration, $payload, $row);
        }

        $existing = Bill::query()->where('quickbooks_bill_id', $qboId)->first();

        if ($existing !== null) {
            $updateBill((int) $existing->id, $payload);

            return;
        }

        $createBill($payload);
    }
}
