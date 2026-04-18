<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Contact\Models\Contact;
use App\Domain\Integration\Models\Integration;
use App\Domain\Lead\Models\Lead;
use App\Enums\Integration\IntegrationSyncStatus;
use App\Enums\Integration\IntegrationType;
use App\Services\MailchimpOAuthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use MailchimpMarketing\ApiClient;

class PushContactsToMailchimp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int, int>  $selectedIds
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public string $listId,
        public string $type,
        public int $tenantUserId,
        public string $applyScope = 'all',
        public array $selectedIds = [],
        public array $filters = [],
        public ?string $segmentId = null,
    ) {}

    public function handle(MailchimpOAuthService $oauth): void
    {
        $integration = Integration::query()
            ->where('integration_type', IntegrationType::MailChimp)
            ->first();

        if (! $integration) {
            Log::warning('PushContactsToMailchimp: no Mailchimp integration');

            return;
        }

        $integration->update([
            'sync_status' => IntegrationSyncStatus::Syncing,
            'sync_error_message' => null,
        ]);

        try {
            $client = $this->buildClient($integration, $oauth);

            if ($this->type === 'lead') {
                $query = Lead::query()
                    ->with(['contact'])
                    ->whereHas('contact', function ($q): void {
                        $q->whereNotNull('email')->where('email', '!=', '');
                    });
                if ($this->applyScope === 'selected' && $this->selectedIds !== []) {
                    $query->whereIn('id', $this->selectedIds);
                } elseif ($this->applyScope === 'filtered') {
                    $this->applyLeadFilters($query);
                }
                $records = $query->get();
            } else {
                $query = Contact::query()
                    ->whereNotNull('email')
                    ->where('email', '!=', '');
                if ($this->applyScope === 'selected' && $this->selectedIds !== []) {
                    $query->whereIn('id', $this->selectedIds);
                } elseif ($this->applyScope === 'filtered') {
                    $this->applyContactFilters($query);
                }
                $records = $query->get();
            }

            if ($records->isEmpty()) {
                Log::info('PushContactsToMailchimp: no records to export', ['type' => $this->type]);
                $integration->update([
                    'sync_status' => IntegrationSyncStatus::Success,
                    'last_synced_at' => now(),
                ]);

                return;
            }

            $operations = [];

            foreach ($records as $record) {
                if ($this->type === 'lead') {
                    /** @var Lead $record */
                    $email = strtolower(trim((string) ($record->contact?->email ?? '')));
                    $first = (string) ($record->contact?->first_name ?? '');
                    $last = (string) ($record->contact?->last_name ?? '');
                    $phone = (string) ($record->contact?->mobile ?? $record->contact?->phone ?? '');
                } else {
                    /** @var Contact $record */
                    $email = strtolower(trim((string) ($record->email ?? '')));
                    $first = (string) ($record->first_name ?? '');
                    $last = (string) ($record->last_name ?? '');
                    $phone = (string) ($record->mobile ?? $record->phone ?? '');
                }

                if ($email === '') {
                    continue;
                }

                $subscriberHash = md5($email);

                $operations[] = [
                    'method' => 'PUT',
                    'path' => "lists/{$this->listId}/members/{$subscriberHash}",
                    'body' => json_encode([
                        'email_address' => $email,
                        'status_if_new' => 'subscribed',
                        'merge_fields' => [
                            'FNAME' => $first,
                            'LNAME' => $last,
                            'PHONE' => $phone,
                        ],
                    ]),
                ];

                if ($this->segmentId) {
                    $operations[] = [
                        'method' => 'POST',
                        'path' => "lists/{$this->listId}/segments/{$this->segmentId}/members",
                        'body' => json_encode([
                            'email_address' => $email,
                        ]),
                    ];
                }
            }

            if ($operations !== []) {
                $response = $client->batches->start([
                    'operations' => $operations,
                ]);

                Log::info('Mailchimp batch started', [
                    'batch_id' => $response->id ?? null,
                    'total_operations' => count($operations),
                    'records_count' => $records->count(),
                    'segment_id' => $this->segmentId,
                ]);
            }

            $integration->update([
                'sync_status' => IntegrationSyncStatus::Success,
                'last_synced_at' => now(),
                'sync_error_message' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Mailchimp batch job error', [
                'list_id' => $this->listId,
                'segment_id' => $this->segmentId,
                'error' => $e->getMessage(),
            ]);

            $integration->update([
                'sync_status' => IntegrationSyncStatus::Failed,
                'sync_error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Lead>  $query
     */
    protected function applyLeadFilters($query): void
    {
        $filters = $this->filters;
        if (! empty($filters['statuses'])) {
            $query->whereIn('status_id', $filters['statuses']);
        }
        if (! empty($filters['sources'])) {
            $query->whereIn('source_id', $filters['sources']);
        }
        if (! empty($filters['priorities'])) {
            $query->whereIn('priority_id', $filters['priorities']);
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Contact>  $query
     */
    protected function applyContactFilters($query): void
    {
        $filters = $this->filters;
        if (! empty($filters['statuses'])) {
            $query->whereIn('contacts.status', array_map('strval', $filters['statuses']));
        }
        if (! empty($filters['sources'])) {
            $query->whereIn('contacts.source', array_map('strval', $filters['sources']));
        }
        if (! empty($filters['types'])) {
            $query->whereIn('contacts.type', array_map('strval', $filters['types']));
        }
    }

    protected function buildClient(Integration $integration, MailchimpOAuthService $oauth): ApiClient
    {
        $serverPrefix = $integration->metadata['dc'] ?? null;
        if (! $serverPrefix) {
            throw new \RuntimeException('Mailchimp server prefix not found');
        }

        $accessToken = $integration->access_token;
        if ($integration->token_expires_at && now()->isAfter($integration->token_expires_at)) {
            $accessToken = $oauth->refreshAccessToken($integration);
        }

        if (! $accessToken) {
            throw new \RuntimeException('Mailchimp access token not found');
        }

        $client = new ApiClient;
        $client->setConfig([
            'accessToken' => $accessToken,
            'server' => $serverPrefix,
        ]);

        return $client;
    }
}
