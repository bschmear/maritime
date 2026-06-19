<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\ChartOfAccount\Actions\CreateChartOfAccount;
use App\Domain\ChartOfAccount\Actions\UpdateChartOfAccount;
use App\Domain\ChartOfAccount\Models\ChartOfAccount;
use App\Domain\Integration\Models\Integration;
use App\Enums\Integration\IntegrationSyncStatus;
use App\Enums\Integration\IntegrationType;
use App\Jobs\Concerns\MarksQuickBooksImportFailure;
use App\Services\Payments\QuickBooksOAuthService;
use App\Support\QuickBooks\QuickBooksChartOfAccountsMapper;
use App\Support\QuickBooks\QuickBooksRowMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PullChartOfAccountsFromQuickBooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use MarksQuickBooksImportFailure;

    /** @var array{created: int, updated: int, parent_links_updated: int, skipped_no_id: int, skipped_no_name: int, failed_create: int, failed_update: int} */
    private array $importStats = [
        'created' => 0,
        'updated' => 0,
        'parent_links_updated' => 0,
        'skipped_no_id' => 0,
        'skipped_no_name' => 0,
        'failed_create' => 0,
        'failed_update' => 0,
    ];

    /** @var array<string, int> */
    private array $idMap = [];

    /** @var list<array<string, mixed>> */
    private array $rawRows = [];

    public function __construct(public int $tenantUserProfileId) {}

    public function handle(
        QuickBooksOAuthService $oauth,
        CreateChartOfAccount $createChartOfAccount,
        UpdateChartOfAccount $updateChartOfAccount,
    ): void {
        $integration = Integration::query()
            ->where('integration_type', IntegrationType::QuickBooks)
            ->first();

        Log::info('QuickBooks chart of accounts import: job started', $this->logContext([
            'integration_found' => $integration !== null,
            'has_access_token' => (bool) ($integration?->access_token),
            'has_refresh_token' => (bool) ($integration?->refresh_token),
            'queue_connection' => config('queue.default'),
        ]));

        if (! $integration?->access_token || ! $integration->refresh_token) {
            Log::warning('QuickBooks chart of accounts import: integration not connected in job context', $this->logContext());

            return;
        }

        $integration->update([
            'sync_status' => IntegrationSyncStatus::Syncing,
            'sync_error_message' => null,
        ]);

        try {
            $this->fetchAccountsFromQuickBooks($oauth, $integration);

            foreach ($this->rawRows as $row) {
                $this->importOneAccount($row, $createChartOfAccount, $updateChartOfAccount);
            }

            $this->linkParentAccounts($updateChartOfAccount);

            if (count($this->rawRows) === 0) {
                Log::warning('QuickBooks chart of accounts import: no accounts returned from QuickBooks', $this->logContext([
                    'guidance' => 'Verify the connected QuickBooks company has a chart of accounts, then re-run sync.',
                ]));
            }

            $integration->refresh();
            $integration->update([
                'sync_status' => IntegrationSyncStatus::Success,
                'last_synced_at' => now(),
                'sync_error_message' => null,
            ]);

            Log::info('QuickBooks chart of accounts import: completed', $this->logContext([
                'stats' => $this->importStats,
                'raw_row_count' => count($this->rawRows),
            ]));
        } catch (\Throwable $e) {
            Log::error('QuickBooks chart of accounts import: failed', $this->logContext([
                'error' => $e->getMessage(),
                'stats' => $this->importStats,
                'raw_row_count' => count($this->rawRows),
                'trace' => $e->getTraceAsString(),
            ]));

            $integration->refresh();
            $integration->update([
                'sync_status' => IntegrationSyncStatus::Failed,
                'sync_error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function fetchAccountsFromQuickBooks(QuickBooksOAuthService $oauth, Integration $integration): void
    {
        $start = 1;
        $pageSize = 100;

        do {
            $sql = "select * from Account STARTPOSITION {$start} MAXRESULTS {$pageSize}";
            $payload = $oauth->queryAccountingForIntegration($integration, $sql);
            $integration->refresh();

            Log::info('QuickBooks chart of accounts import: QBO query response', $this->logContext([
                'sql' => $sql,
                'start_position' => $start,
                'has_fault' => ! empty($payload['Fault']),
                'query_response_keys' => array_keys($payload['QueryResponse'] ?? []),
                'fault' => $payload['Fault'] ?? null,
                'sample_rows' => array_slice(
                    QuickBooksRowMapper::normalizeList($payload['QueryResponse']['Account'] ?? []),
                    0,
                    3,
                ),
            ]));

            if (! empty($payload['Fault'])) {
                throw new \RuntimeException(QuickBooksRowMapper::faultMessage($payload['Fault']) ?: 'QuickBooks returned a fault.');
            }

            $rows = QuickBooksRowMapper::normalizeList($payload['QueryResponse']['Account'] ?? []);

            foreach ($rows as $row) {
                if (is_array($row)) {
                    $this->rawRows[] = $row;
                }
            }

            $count = count($rows);

            Log::info('QuickBooks chart of accounts import: fetched account page from QBO', $this->logContext([
                'start_position' => $start,
                'account_count' => $count,
            ]));

            $start += $pageSize;
        } while ($count === $pageSize);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importOneAccount(
        array $row,
        CreateChartOfAccount $createChartOfAccount,
        UpdateChartOfAccount $updateChartOfAccount,
    ): void {
        $qboId = QuickBooksChartOfAccountsMapper::extractQboId($row);
        if ($qboId === '') {
            $this->importStats['skipped_no_id']++;

            return;
        }

        $accountPayload = QuickBooksChartOfAccountsMapper::mapAccountRow($row);
        if (($accountPayload['name'] ?? '') === '') {
            $this->importStats['skipped_no_name']++;

            return;
        }

        $existing = ChartOfAccount::query()->where('quickbooks_account_id', $qboId)->first();
        if ($existing !== null) {
            $result = $updateChartOfAccount((int) $existing->id, $accountPayload);
            if (($result['success'] ?? false) === true && ($result['record'] ?? null) !== null) {
                $this->importStats['updated']++;
                $this->idMap[$qboId] = (int) $result['record']->id;

                return;
            }

            $this->importStats['failed_update']++;

            return;
        }

        $result = $createChartOfAccount($accountPayload);
        if (($result['success'] ?? false) === true && ($result['record'] ?? null) !== null) {
            $this->importStats['created']++;
            $this->idMap[$qboId] = (int) $result['record']->id;

            return;
        }

        $this->importStats['failed_create']++;
    }

    private function linkParentAccounts(UpdateChartOfAccount $updateChartOfAccount): void
    {
        foreach ($this->rawRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $qboId = QuickBooksChartOfAccountsMapper::extractQboId($row);
            $parentQboId = QuickBooksChartOfAccountsMapper::parentQboId($row);
            if ($qboId === '' || $parentQboId === '') {
                continue;
            }

            $localId = $this->idMap[$qboId] ?? ChartOfAccount::query()->where('quickbooks_account_id', $qboId)->value('id');
            $parentId = $this->idMap[$parentQboId] ?? ChartOfAccount::query()->where('quickbooks_account_id', $parentQboId)->value('id');
            if ($localId === null || $parentId === null) {
                continue;
            }

            $existing = ChartOfAccount::query()->find($localId);
            if ($existing === null || (int) $existing->parent_id === (int) $parentId) {
                continue;
            }

            $result = $updateChartOfAccount((int) $localId, ['parent_id' => (int) $parentId]);
            if (($result['success'] ?? false) === true) {
                $this->importStats['parent_links_updated']++;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function logContext(array $extra = []): array
    {
        return array_merge([
            'tenant_id' => tenancy()->initialized ? tenancy()->tenant?->getTenantKey() : null,
            'tenancy_initialized' => tenancy()->initialized,
            'tenant_user_profile_id' => $this->tenantUserProfileId,
        ], $extra);
    }
}
