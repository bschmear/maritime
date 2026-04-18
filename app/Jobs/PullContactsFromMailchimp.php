<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Contact\Actions\CreateContact;
use App\Domain\Contact\Models\Contact;
use App\Domain\Integration\Models\Integration;
use App\Domain\Lead\Actions\CreateLead;
use App\Domain\Lead\Models\Lead;
use App\Enums\Integration\IntegrationSyncStatus;
use App\Enums\Integration\IntegrationType;
use App\Services\MailchimpOAuthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PullContactsFromMailchimp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $tenantUserId,
        public string $recordType,
        public ?int $typeId,
        public string $listId,
        public ?string $segmentId = null,
    ) {}

    public function handle(
        MailchimpOAuthService $oauth,
        CreateContact $createContact,
        CreateLead $createLead,
    ): void {
        $integration = Integration::query()
            ->where('integration_type', IntegrationType::MailChimp)
            ->first();

        if (! $integration) {
            Log::warning('PullContactsFromMailchimp: no Mailchimp integration');

            return;
        }

        $integration->update([
            'sync_status' => IntegrationSyncStatus::Syncing,
            'sync_error_message' => null,
        ]);

        try {
            $serverPrefix = $integration->metadata['dc'] ?? null;
            if (! $serverPrefix) {
                throw new \RuntimeException('Mailchimp server prefix not found');
            }

            $accessToken = $integration->access_token;
            if ($integration->token_expires_at && now()->isAfter($integration->token_expires_at)) {
                $accessToken = $oauth->refreshAccessToken($integration);
            }

            $url = "https://{$serverPrefix}.api.mailchimp.com/3.0/lists/{$this->listId}";
            $url .= $this->segmentId ? "/segments/{$this->segmentId}/members" : '/members';

            $allMembers = [];
            $offset = 0;
            $count = 500;

            do {
                $response = Http::withToken($accessToken)->get($url, [
                    'count' => $count,
                    'offset' => $offset,
                ]);

                if ($response->failed()) {
                    Log::error('Mailchimp API fetch failed', [
                        'url' => $url,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    $integration->update([
                        'sync_status' => IntegrationSyncStatus::Failed,
                        'sync_error_message' => 'Mailchimp API fetch failed: '.$response->status(),
                    ]);

                    return;
                }

                $data = $response->json();
                $members = $data['members'] ?? [];
                $totalItems = (int) ($data['total_items'] ?? count($members));

                $allMembers = array_merge($allMembers, $members);
                $offset += $count;
            } while (count($members) === $count && $offset < $totalItems);

            foreach ($allMembers as $member) {
                if (($member['status'] ?? '') !== 'subscribed') {
                    continue;
                }

                $email = strtolower(trim((string) ($member['email_address'] ?? '')));
                if ($email === '') {
                    continue;
                }

                $exists = $this->recordType === 'lead'
                    ? Lead::query()->whereHas('contact', fn ($q) => $q->whereRaw('LOWER(email) = ?', [$email]))->exists()
                    : Contact::query()->whereRaw('LOWER(email) = ?', [$email])->exists();

                if ($exists) {
                    continue;
                }

                $fname = trim((string) ($member['merge_fields']['FNAME'] ?? ''));
                $lname = trim((string) ($member['merge_fields']['LNAME'] ?? ''));
                $phone = trim((string) ($member['merge_fields']['PHONE'] ?? ''));

                if ($fname === '' && $lname === '') {
                    $local = strstr($email, '@', true) ?: 'import';
                    $fname = ucfirst($local);
                    $lname = 'Mailchimp';
                }

                if ($this->recordType === 'lead') {
                    $result = $createLead([
                        'first_name' => $fname,
                        'last_name' => $lname,
                        'email' => $email,
                        'phone' => $phone !== '' ? $phone : null,
                        'mobile' => $phone !== '' ? $phone : null,
                        'assigned_user_id' => $this->tenantUserId,
                    ]);
                    if (! ($result['success'] ?? false)) {
                        Log::warning('PullContactsFromMailchimp: CreateLead failed', [
                            'email' => $email,
                            'message' => $result['message'] ?? null,
                        ]);
                    }
                } else {
                    $payload = [
                        'first_name' => $fname,
                        'last_name' => $lname,
                        'email' => $email,
                        'phone' => $phone !== '' ? $phone : null,
                        'mobile' => $phone !== '' ? $phone : null,
                        'assigned_user_id' => $this->tenantUserId,
                        'source' => 'Mailchimp',
                    ];
                    $result = $createContact($payload);
                    if (! ($result['success'] ?? false)) {
                        Log::warning('PullContactsFromMailchimp: CreateContact failed', [
                            'email' => $email,
                            'message' => $result['message'] ?? null,
                        ]);
                    }
                }
            }

            $integration->update([
                'sync_status' => IntegrationSyncStatus::Success,
                'last_synced_at' => now(),
                'sync_error_message' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Mailchimp pullContacts job failed', [
                'tenant_user_id' => $this->tenantUserId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $integration->update([
                'sync_status' => IntegrationSyncStatus::Failed,
                'sync_error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
