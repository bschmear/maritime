<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Contact\Actions\CreateContact;
use App\Domain\Contact\Models\Contact;
use App\Domain\Lead\Actions\CreateLead;
use App\Domain\Lead\Models\Lead;
use App\Domain\Payment\Models\PaymentConfiguration;
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

    public function __construct(
        public int $tenantUserProfileId,
        public string $recordType,
    ) {}

    public function handle(
        QuickBooksOAuthService $oauth,
        CreateContact $createContact,
        CreateLead $createLead,
    ): void {
        $config = PaymentConfiguration::forQuickbooks();

        if (! $config->quickbooksConnected()) {
            Log::warning('PullContactsFromQuickBooks: QuickBooks not connected');

            return;
        }

        $config->update([
            'meta' => array_merge($config->meta ?? [], [
                'qbo_import_status' => 'syncing',
                'qbo_import_error' => null,
            ]),
        ]);

        try {
            $start = 1;
            $pageSize = 100;

            do {
                $sql = 'select * from Customer STARTPOSITION '.$start.' MAXRESULTS '.$pageSize;
                $payload = $oauth->queryAccounting($config, $sql);
                $config->refresh();

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
                    $this->importOneCustomer($row, $createContact, $createLead);
                }

                $count = count($customers);
                $start += $pageSize;
            } while ($count === $pageSize);

            $config->refresh();
            $config->update([
                'meta' => array_merge($config->meta ?? [], [
                    'qbo_import_status' => 'success',
                    'qbo_import_error' => null,
                    'qbo_import_completed_at' => now()->toIso8601String(),
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::error('PullContactsFromQuickBooks failed', [
                'tenant_user_profile_id' => $this->tenantUserProfileId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $config->refresh();
            $config->update([
                'meta' => array_merge($config->meta ?? [], [
                    'qbo_import_status' => 'failed',
                    'qbo_import_error' => $e->getMessage(),
                ]),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importOneCustomer(array $row, CreateContact $createContact, CreateLead $createLead): void
    {
        if (array_key_exists('Active', $row) && $row['Active'] === false) {
            return;
        }

        $qboId = isset($row['Id']) ? (string) $row['Id'] : '';
        if ($qboId === '') {
            return;
        }

        if (Contact::query()->where('quickbooks_customer_id', $qboId)->exists()) {
            return;
        }

        $emailRaw = $row['PrimaryEmailAddr']['Address'] ?? $row['Email'] ?? null;
        $email = is_string($emailRaw) ? strtolower(trim($emailRaw)) : '';
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = '';
        }

        if ($email !== '') {
            $exists = $this->recordType === 'lead'
                ? Lead::query()->whereHas('contact', fn ($q) => $q->whereRaw('LOWER(email) = ?', [$email]))->exists()
                : Contact::query()->whereRaw('LOWER(email) = ?', [$email])->exists();

            if ($exists) {
                return;
            }
        }

        [$first, $last, $company] = $this->resolveNames($row);

        if ($first === '' && $last === '' && $company === '') {
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
            if (! ($result['success'] ?? false)) {
                Log::warning('PullContactsFromQuickBooks: CreateLead failed', [
                    'qbo_id' => $qboId,
                    'message' => $result['message'] ?? null,
                ]);
            }

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
        ];

        $result = $createContact($payload);
        if (! ($result['success'] ?? false)) {
            Log::warning('PullContactsFromQuickBooks: CreateContact failed', [
                'qbo_id' => $qboId,
                'message' => $result['message'] ?? null,
            ]);
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
