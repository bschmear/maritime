<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\BillPayment\Actions\CreateBillPayment;
use App\Domain\BillPayment\Actions\StoreBillPayment;
use App\Domain\BillPayment\Actions\UpdateBillPayment;
use App\Domain\BillPayment\Models\BillPayment;
use App\Domain\Integration\Models\Integration;
use App\Domain\Integration\Support\QuickBooksSettings;
use App\Enums\Integration\IntegrationSyncStatus;
use App\Enums\Integration\IntegrationType;
use App\Jobs\Concerns\MarksQuickBooksImportFailure;
use App\Services\Payments\QuickBooksOAuthService;
use App\Support\QuickBooks\QuickBooksBillPaymentMapper;
use App\Support\QuickBooks\QuickBooksImportDateRange;
use App\Support\QuickBooks\QuickBooksRowMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PullBillPaymentsFromQuickBooks implements ShouldQueue
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
        CreateBillPayment $createBillPayment,
        UpdateBillPayment $updateBillPayment,
        StoreBillPayment $storeBillPayment,
    ): void {
        if (! QuickBooksSettings::forCurrentTenant()->isSyncBillPaymentsEnabled()) {
            return;
        }

        $integration = Integration::query()
            ->where('integration_type', IntegrationType::QuickBooks)
            ->first();

        if (! $integration?->access_token || ! $integration->refresh_token) {
            return;
        }

        $integration->update([
            'sync_status' => IntegrationSyncStatus::Syncing,
            'sync_error_message' => null,
        ]);

        try {
            $start = 1;
            $pageSize = 100;

            do {
                $sql = $this->txnDateFrom && $this->txnDateTo
                    ? QuickBooksImportDateRange::billPaymentQuery($this->txnDateFrom, $this->txnDateTo, $start, $pageSize)
                    : QuickBooksImportDateRange::allBillPaymentsQuery($start, $pageSize);
                $payload = $oauth->queryAccountingForIntegration($integration, $sql);
                $integration->refresh();

                if (! empty($payload['Fault'])) {
                    throw new \RuntimeException(QuickBooksRowMapper::faultMessage($payload['Fault']) ?: 'QuickBooks returned a fault.');
                }

                $rows = QuickBooksRowMapper::normalizeList($payload['QueryResponse']['BillPayment'] ?? []);

                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $this->importOnePayment($row, $createBillPayment, $updateBillPayment, $storeBillPayment);
                }

                $count = count($rows);
                $start += $pageSize;
            } while ($count === $pageSize);

            $integration->refresh();
            $integration->update([
                'sync_status' => IntegrationSyncStatus::Success,
                'last_synced_at' => now(),
                'sync_error_message' => null,
            ]);
        } catch (\Throwable $e) {
            $integration->refresh();
            $integration->update([
                'sync_status' => IntegrationSyncStatus::Failed,
                'sync_error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importOnePayment(
        array $row,
        CreateBillPayment $createBillPayment,
        UpdateBillPayment $updateBillPayment,
        StoreBillPayment $storeBillPayment,
    ): void {
        $qboId = QuickBooksRowMapper::refValue(['value' => $row['Id'] ?? null]);
        if ($qboId === '') {
            return;
        }

        $payload = QuickBooksBillPaymentMapper::mapBillPaymentRow($row);
        $existing = BillPayment::query()->where('quickbooks_bill_payment_id', $qboId)->first();

        if ($existing !== null) {
            $updateBillPayment((int) $existing->id, $payload);
            $existing->refresh();
            $storeBillPayment->applyFromPayment($existing);

            return;
        }

        $result = $createBillPayment($payload);
        if (($result['record'] ?? null) instanceof BillPayment) {
            $storeBillPayment->applyFromPayment($result['record']);
        }
    }
}
