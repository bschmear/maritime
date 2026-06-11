<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\ContactAddress;
use App\Domain\Customer\Actions\CreateCustomer;
use App\Domain\Customer\Models\Customer;
use App\Domain\Integration\Models\Integration;
use App\Domain\Integration\Support\QuickBooksCustomerAddressMapper;
use App\Domain\Lead\Actions\CreateLead;
use App\Domain\Lead\Models\Lead;
use App\Enums\Integration\IntegrationSyncStatus;
use App\Enums\Integration\IntegrationType;
use App\Services\Payments\QuickBooksOAuthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PullContactsFromQuickBooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var array{skipped_inactive: int, skipped_no_qbo_id: int, skipped_existing_qbo: int, skipped_email: int, skipped_no_names: int, skipped_no_subsidiary: int, created_lead: int, created_customer: int, failed_create: int} */
    private array $importStats = [
        'skipped_inactive' => 0,
        'skipped_no_qbo_id' => 0,
        'skipped_existing_qbo' => 0,
        'skipped_email' => 0,
        'skipped_no_names' => 0,
        'skipped_no_subsidiary' => 0,
        'created_lead' => 0,
        'created_customer' => 0,
        'failed_create' => 0,
    ];

    public function __construct(
        public int $tenantUserProfileId,
        public string $recordType,
    ) {}

    public function handle(
        QuickBooksOAuthService $oauth,
        CreateCustomer $createCustomer,
        CreateLead $createLead,
    ): void {
        $this->recordType = $this->normalizeRecordType($this->recordType);
        $integration = Integration::query()
            ->where('integration_type', IntegrationType::QuickBooks)
            ->first();

        Log::info('QuickBooks customer import: job started', $this->logContext([
            'record_type' => $this->recordType,
            'integration_found' => $integration !== null,
            'has_access_token' => (bool) ($integration?->access_token),
            'has_refresh_token' => (bool) ($integration?->refresh_token),
            'queue_connection' => config('queue.default'),
        ]));

        if (! $integration?->access_token || ! $integration->refresh_token) {
            Log::warning('QuickBooks customer import: integration not connected in job context', $this->logContext());

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
                $sql = 'select * from Customer STARTPOSITION '.$start.' MAXRESULTS '.$pageSize;
                $payload = $oauth->queryAccountingForIntegration($integration, $sql);
                $integration->refresh();

                if (! empty($payload['Fault'])) {
                    $msg = $this->faultMessage($payload['Fault']);
                    throw new \RuntimeException($msg ?: 'QuickBooks returned a fault.');
                }

                $queryResponse = $payload['QueryResponse'] ?? [];
                $customers = $queryResponse['Customer'] ?? [];
                if ($customers !== [] && ! array_is_list($customers)) {
                    $customers = [$customers];
                }

                foreach ($customers as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $this->importOneCustomer($row, $createCustomer, $createLead);
                }

                $count = count($customers);

                Log::info('QuickBooks customer import: fetched customer page from QBO', $this->logContext([
                    'start_position' => $start,
                    'customer_count' => $count,
                    'has_fault' => ! empty($payload['Fault']),
                ]));

                $start += $pageSize;
            } while ($count === $pageSize);

            $integration->refresh();
            $integration->update([
                'sync_status' => IntegrationSyncStatus::Success,
                'last_synced_at' => now(),
                'sync_error_message' => null,
            ]);

            Log::info('QuickBooks customer import: completed', $this->logContext([
                'stats' => $this->importStats,
            ]));
        } catch (\Throwable $e) {
            Log::error('QuickBooks customer import: failed', $this->logContext([
                'error' => $e->getMessage(),
                'stats' => $this->importStats,
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
            'record_type' => $this->recordType,
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function normalizeRecordType(string $recordType): string
    {
        return match ($recordType) {
            'contact' => 'customer',
            default => $recordType,
        };
    }

    private function importOneCustomer(array $row, CreateCustomer $createCustomer, CreateLead $createLead): void
    {
        if (array_key_exists('Active', $row) && $row['Active'] === false) {
            $this->importStats['skipped_inactive']++;

            return;
        }

        $qboId = isset($row['Id']) ? (string) $row['Id'] : '';
        if ($qboId === '') {
            $this->importStats['skipped_no_qbo_id']++;

            return;
        }

        if (Contact::query()->where('quickbooks_customer_id', $qboId)->exists()) {
            $this->importStats['skipped_existing_qbo']++;

            return;
        }

        $emailRaw = $row['PrimaryEmailAddr']['Address'] ?? $row['Email'] ?? null;
        $email = is_string($emailRaw) ? strtolower(trim($emailRaw)) : '';
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = '';
        }

        if ($email !== '') {
            $exists = match ($this->recordType) {
                'lead' => Lead::query()->whereHas('contact', fn ($q) => $q->whereRaw('LOWER(email) = ?', [$email]))->exists(),
                'customer' => Customer::query()->whereHas('contact', fn ($q) => $q->whereRaw('LOWER(email) = ?', [$email]))->exists(),
                default => Contact::query()->whereRaw('LOWER(email) = ?', [$email])->exists(),
            };

            if ($exists) {
                $this->importStats['skipped_email']++;

                return;
            }
        }

        [$first, $last, $company] = $this->resolveNames($row);

        if ($first === '' && $last === '' && $company === '') {
            $this->importStats['skipped_no_names']++;

            return;
        }

        if ($first === '' && $last === '') {
            $first = $company !== '' ? $company : 'QuickBooks';
            $last = 'Customer';
        }

        $phone = $this->firstNonEmptyString([
            $row['PrimaryPhone']['FreeFormNumber'] ?? null,
            $row['Mobile']['FreeFormNumber'] ?? null,
            $row['AlternatePhone']['FreeFormNumber'] ?? null,
        ]);

        if ($this->recordType === 'lead') {
            $result = $createLead([
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone,
                'mobile' => $phone,
                'company' => $company !== '' ? $company : null,
                'assigned_user_id' => $this->tenantUserProfileId,
                'quickbooks_customer_id' => $qboId,
                'source' => 'QuickBooks',
            ]);
            if ($result['success'] ?? false) {
                $this->importStats['created_lead']++;
                $this->syncAddresses((int) ($result['record']->contact_id ?? 0), $row);
            } else {
                $this->importStats['failed_create']++;
                Log::warning('QuickBooks customer import: create lead failed', $this->logContext([
                    'qbo_id' => $qboId,
                    'message' => $result['message'] ?? null,
                ]));
            }

            return;
        }

        $subsidiaryId = Customer::defaultSubsidiaryId();
        if ($subsidiaryId === null) {
            $this->importStats['skipped_no_subsidiary']++;
            Log::warning('QuickBooks customer import: no subsidiary available', $this->logContext([
                'qbo_id' => $qboId,
            ]));

            return;
        }

        $payload = [
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone,
            'mobile' => $phone,
            'company' => $company !== '' ? $company : null,
            'assigned_user_id' => $this->tenantUserProfileId,
            'quickbooks_customer_id' => $qboId,
            'source' => 'QuickBooks',
            'subsidiary_id' => $subsidiaryId,
        ];

        $result = $createCustomer($payload);
        if ($result['success'] ?? false) {
            $this->importStats['created_customer']++;
            $this->syncAddresses((int) ($result['record']->contact_id ?? 0), $row);
        } else {
            $this->importStats['failed_create']++;
            Log::warning('QuickBooks customer import: create customer failed', $this->logContext([
                'qbo_id' => $qboId,
                'message' => $result['message'] ?? null,
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function syncAddresses(int $contactId, array $row): void
    {
        if ($contactId <= 0) {
            return;
        }

        $addresses = QuickBooksCustomerAddressMapper::addressesFromCustomerRow($row);
        if ($addresses === []) {
            return;
        }

        foreach ($addresses as $address) {
            ContactAddress::query()->create(array_merge($address, [
                'contact_id' => $contactId,
            ]));
        }
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolveNames(array $row): array
    {
        $given = $this->normalizeNamePart($row['GivenName'] ?? null);
        $family = $this->normalizeNamePart($row['FamilyName'] ?? null);
        $company = $this->normalizeNamePart($row['CompanyName'] ?? null);
        $display = $this->normalizeNamePart($row['DisplayName'] ?? null);
        $fully = $this->normalizeNamePart($row['FullyQualifiedName'] ?? null);

        if ($given !== '' || $family !== '') {
            return [$given, $family, $company];
        }

        $fallback = $display !== '' ? $display : $fully;
        if ($fallback === '') {
            return ['', '', $company];
        }

        if ($company !== '' && strcasecmp($fallback, $company) === 0) {
            return ['', '', $company];
        }

        $parts = preg_split('/\s+/', $fallback, 2, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return ['', '', $company];
        }
        $first = $parts[0] ?? '';
        $second = $parts[1] ?? '';

        return [$first, $second, $company];
    }

    private function normalizeNamePart(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim($value);
    }

    /**
     * @param  list<string|null>  $candidates
     */
    private function firstNonEmptyString(array $candidates): ?string
    {
        foreach ($candidates as $c) {
            if (is_string($c)) {
                $t = trim($c);
                if ($t !== '') {
                    return $t;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fault
     */
    private function faultMessage(array $fault): string
    {
        $errors = $fault['Error'] ?? [];
        if (! is_array($errors)) {
            return '';
        }
        if ($errors !== [] && ! array_is_list($errors)) {
            $errors = [$errors];
        }
        $parts = [];
        foreach ($errors as $err) {
            if (is_array($err) && ! empty($err['Message'])) {
                $parts[] = (string) $err['Message'];
            }
        }

        return implode('; ', $parts);
    }
}
