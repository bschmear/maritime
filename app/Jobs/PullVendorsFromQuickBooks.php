<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Integration\Models\Integration;
use App\Domain\Vendor\Actions\CreateVendor;
use App\Domain\Vendor\Actions\UpdateVendor;
use App\Domain\Vendor\Models\Vendor;
use App\Enums\Integration\IntegrationSyncStatus;
use App\Enums\Integration\IntegrationType;
use App\Jobs\Concerns\MarksQuickBooksImportFailure;
use App\Services\Payments\QuickBooksOAuthService;
use App\Support\QuickBooks\QuickBooksRowMapper;
use App\Support\QuickBooks\QuickBooksVendorContactLinker;
use App\Support\QuickBooks\QuickBooksVendorImportDiagnostics;
use App\Support\QuickBooks\QuickBooksVendorMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PullVendorsFromQuickBooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use MarksQuickBooksImportFailure;

    /** @var array{created: int, updated: int, skipped: int, failed: int, contacts_linked: int} */
    private array $importStats = [
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'failed' => 0,
        'contacts_linked' => 0,
    ];

    public function __construct(
        public int $tenantUserProfileId,
        public bool $updateExisting = true,
    ) {}

    public function handle(
        QuickBooksOAuthService $oauth,
        CreateVendor $createVendor,
        UpdateVendor $updateVendor,
    ): void {
        $integration = Integration::query()
            ->where('integration_type', IntegrationType::QuickBooks)
            ->first();

        if (! $integration?->access_token || ! $integration->refresh_token) {
            Log::warning('QuickBooks vendor import: integration not connected', [
                'tenant_id' => tenancy()->initialized ? tenancy()->tenant?->getTenantKey() : null,
            ]);

            return;
        }

        QuickBooksVendorImportDiagnostics::logJobStarted([
            'update_existing' => $this->updateExisting,
            'tenant_user_profile_id' => $this->tenantUserProfileId,
        ]);

        $integration->update([
            'sync_status' => IntegrationSyncStatus::Syncing,
            'sync_error_message' => null,
        ]);

        try {
            $start = 1;
            $pageSize = 100;

            do {
                $sql = "select * from Vendor STARTPOSITION {$start} MAXRESULTS {$pageSize}";
                $payload = $oauth->queryAccountingForIntegration($integration, $sql);
                $integration->refresh();

                if (! empty($payload['Fault'])) {
                    throw new \RuntimeException(QuickBooksRowMapper::faultMessage($payload['Fault']) ?: 'QuickBooks returned a fault.');
                }

                $rows = QuickBooksRowMapper::normalizeList($payload['QueryResponse']['Vendor'] ?? []);

                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $qboId = QuickBooksRowMapper::refValue(['value' => $row['Id'] ?? null]);
                    if ($qboId !== '') {
                        try {
                            $readRow = $oauth->readVendorForIntegration($integration, $qboId);
                            $row = QuickBooksVendorMapper::mergeReadRow($row, $readRow);
                        } catch (\Throwable $e) {
                            Log::debug('QuickBooks vendor import: vendor read skipped', [
                                'quickbooks_id' => $qboId,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    $this->importOneVendor($row, $createVendor, $updateVendor);
                }

                $count = count($rows);
                $start += $pageSize;
            } while ($count === $pageSize);

            Vendor::refreshAllOverdueBalances();

            $integration->refresh();
            $integration->update([
                'sync_status' => IntegrationSyncStatus::Success,
                'last_synced_at' => now(),
                'sync_error_message' => null,
            ]);

            QuickBooksVendorImportDiagnostics::logJobFinished([
                'tenant_id' => tenancy()->initialized ? tenancy()->tenant?->getTenantKey() : null,
                'update_existing' => $this->updateExisting,
                'stats' => $this->importStats,
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
     * @param  array<string, mixed>  $row  Merged QuickBooks vendor payload (list + read).
     */
    private function importOneVendor(
        array $row,
        CreateVendor $createVendor,
        UpdateVendor $updateVendor,
    ): void {
        try {
            if (array_key_exists('Active', $row) && $row['Active'] === false) {
                return;
            }

            $payload = QuickBooksVendorMapper::mapVendorRow($row);
            if (($payload['quickbooks_id'] ?? '') === '' || ($payload['display_name'] ?? '') === '') {
                return;
            }

            $existing = $this->findExistingVendor($payload);

            if ($existing !== null) {
                if (! $this->updateExisting) {
                    $this->importStats['skipped']++;
                    QuickBooksVendorImportDiagnostics::logQboPayload($row, (int) $existing->id, 'skipped_existing');

                    return;
                }

                QuickBooksVendorImportDiagnostics::logQboPayload($row, (int) $existing->id, 'update');

                $payloadForUpdate = QuickBooksVendorMapper::preserveSensitiveFieldsWhenAbsent($payload);
                $result = $updateVendor((int) $existing->id, $payloadForUpdate, fromQuickBooksImport: true);
                if ($result['success'] ?? false) {
                    $this->importStats['updated']++;
                    $this->linkContactIfPresent($result['record'] ?? $existing, $payload);
                } else {
                    $this->importStats['failed']++;
                    Log::warning('QuickBooks vendor import: update failed', [
                        'vendor_id' => $existing->id,
                        'quickbooks_id' => $payload['quickbooks_id'],
                        'message' => $result['message'] ?? 'Unknown error',
                    ]);
                }

                return;
            }

            QuickBooksVendorImportDiagnostics::logQboPayload($row, null, 'create');

            $result = $createVendor($payload, fromQuickBooksImport: true);
            if ($result['success'] ?? false) {
                $this->importStats['created']++;
                if ($result['record'] ?? null) {
                    $this->linkContactIfPresent($result['record'], $payload);
                }
            } else {
                $this->importStats['failed']++;
                Log::warning('QuickBooks vendor import: create failed', [
                    'quickbooks_id' => $payload['quickbooks_id'],
                    'display_name' => $payload['display_name'],
                    'message' => $result['message'] ?? 'Unknown error',
                ]);
            }
        } catch (ValidationException $e) {
            $this->importStats['failed']++;
            Log::warning('QuickBooks vendor import: validation failed', [
                'quickbooks_id' => $row['Id'] ?? null,
                'display_name' => $row['DisplayName'] ?? null,
                'errors' => $e->errors(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findExistingVendor(array $payload): ?Vendor
    {
        $qboId = (string) $payload['quickbooks_id'];

        $byQboId = Vendor::query()->where('quickbooks_id', $qboId)->first();
        if ($byQboId !== null) {
            return $byQboId;
        }

        $displayName = trim((string) ($payload['display_name'] ?? ''));
        if ($displayName === '') {
            return null;
        }

        return Vendor::query()
            ->whereNull('quickbooks_id')
            ->whereRaw('LOWER(TRIM(display_name)) = ?', [mb_strtolower($displayName)])
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function linkContactIfPresent(Vendor $vendor, array $payload): void
    {
        $contact = QuickBooksVendorContactLinker::resolveContact($payload);
        if ($contact === null) {
            return;
        }

        QuickBooksVendorContactLinker::link($vendor, $contact);
        $this->importStats['contacts_linked']++;
    }
}
