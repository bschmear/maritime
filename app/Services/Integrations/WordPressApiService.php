<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Domain\BoatShow\Models\BoatShow;
use App\Domain\BoatShow\Support\BoatShowWordPressPayload;
use App\Domain\BoatShowEvent\Models\BoatShowEvent;
use App\Domain\Integration\Models\Integration;
use App\Domain\Integration\Support\WordPressIntegrationSettings;
use App\Enums\Integration\IntegrationSyncStatus;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WordPressApiService
{
    public function isConnected(): bool
    {
        return WordPressIntegrationSettings::forCurrentTenant()->isConnected();
    }

    /**
     * @return array{success: bool, message?: string, blocking?: bool}
     */
    public function syncWordPressApiKeyToSite(?string $wordpressApiKey = null): array
    {
        if (! WordPressIntegrationSettings::forCurrentTenant()->wordpressUrl()) {
            return [
                'success' => false,
                'blocking' => true,
                'message' => 'WordPress site URL is not configured.',
            ];
        }

        $integration = WordPressIntegrationSettings::integration();
        $apiKey = $wordpressApiKey ?? $integration?->access_token;
        if (! is_string($apiKey) || $apiKey === '') {
            return [
                'success' => false,
                'blocking' => true,
                'message' => 'Save a WordPress API key in Helmful first.',
            ];
        }

        if ($this->helmfulIntegrationKey() === null) {
            return [
                'success' => false,
                'blocking' => true,
                'message' => 'Replace your Helmful integration key, paste it into WordPress under Settings → Helmful Sync, then try again.',
            ];
        }

        try {
            $response = $this->helmfulClient()->post($this->endpoint('/api-key'), [
                'api_key' => $apiKey,
            ]);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => $this->responseMessage($response->json(), 'Could not register the WordPress API key on your site.'),
                ];
            }

            return [
                'success' => true,
                'message' => $response->json('message') ?? 'WordPress API key registered.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, message?: string}
     */
    public function ping(): array
    {
        try {
            $response = $this->client()->get($this->endpoint('/status'));

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => $this->responseMessage(
                        $response->json(),
                        'WordPress connection failed.',
                    ),
                ];
            }

            return [
                'success' => true,
                'message' => $response->json('message') ?? 'Connected to WordPress.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, message?: string}
     */
    public function pushBoatShow(BoatShow $show): array
    {
        return $this->post('/sync/boat-show', BoatShowWordPressPayload::forShow($show));
    }

    /**
     * @return array{success: bool, message?: string}
     */
    public function pushBoatShowEvent(BoatShowEvent $event): array
    {
        $event->loadMissing('show');

        return $this->post('/sync/boat-show-event', BoatShowWordPressPayload::forEvent($event));
    }

    /**
     * @return array{success: bool, message?: string}
     */
    public function deleteBoatShow(string $uuid): array
    {
        return $this->delete('/sync/boat-show/'.$uuid);
    }

    /**
     * @return array{success: bool, message?: string}
     */
    public function deleteBoatShowEvent(string $uuid): array
    {
        return $this->delete('/sync/boat-show-event/'.$uuid);
    }

    /**
     * @return array{success: bool, shows_synced: int, events_synced: int, errors: list<string>, message?: string}
     */
    public function pushAll(): array
    {
        $integration = WordPressIntegrationSettings::integration();
        if ($integration === null || ! WordPressIntegrationSettings::from($integration)->isConnected()) {
            throw new RuntimeException('WordPress integration is not connected.');
        }

        $this->markSyncing($integration);

        $showsSynced = 0;
        $eventsSynced = 0;
        $errors = [];

        $shows = BoatShow::query()->orderBy('display_name')->get();
        foreach ($shows as $show) {
            $result = $this->pushBoatShow($show);
            if ($result['success'] ?? false) {
                $showsSynced++;
            } else {
                $errors[] = 'Show '.$show->display_name.': '.($result['message'] ?? 'Push failed.');
            }
        }

        $events = BoatShowEvent::query()->with('show')->orderByDesc('year')->get();
        foreach ($events as $event) {
            $result = $this->pushBoatShowEvent($event);
            if ($result['success'] ?? false) {
                $eventsSynced++;
            } else {
                $errors[] = 'Event '.$event->display_name.': '.($result['message'] ?? 'Push failed.');
            }
        }

        $success = $errors === [];
        $this->markSyncResult($integration, $success, $errors[0] ?? null, [
            'last_pushed_at' => now()->toIso8601String(),
        ]);

        return [
            'success' => $success,
            'shows_synced' => $showsSynced,
            'events_synced' => $eventsSynced,
            'errors' => $errors,
            'message' => $success
                ? "Pushed {$showsSynced} shows and {$eventsSynced} events to WordPress."
                : 'Push completed with errors.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message?: string}
     */
    private function post(string $path, array $payload): array
    {
        try {
            $response = $this->client()->post($this->endpoint($path), $payload);

            if (! $response->successful()) {
                Log::warning('WordPressApiService POST failed', [
                    'path' => $path,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => $response->json('message') ?? 'WordPress request failed.',
                ];
            }

            return ['success' => true];
        } catch (\Throwable $e) {
            Log::warning('WordPressApiService POST exception', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, message?: string}
     */
    private function delete(string $path): array
    {
        try {
            $response = $this->client()->delete($this->endpoint($path));

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => $response->json('message') ?? 'WordPress delete failed.',
                ];
            }

            return ['success' => true];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function client(): PendingRequest
    {
        $integration = WordPressIntegrationSettings::integration();
        $apiKey = $integration?->access_token;
        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('WordPress API key is missing in Helmful.');
        }

        return Http::timeout(30)
            ->acceptJson()
            ->withToken($apiKey);
    }

    private function helmfulClient(): PendingRequest
    {
        $helmfulKey = $this->helmfulIntegrationKey();
        if ($helmfulKey === null) {
            throw new RuntimeException('Helmful integration key is not available.');
        }

        return Http::timeout(30)
            ->acceptJson()
            ->withToken($helmfulKey);
    }

    private function helmfulIntegrationKey(): ?string
    {
        $integration = WordPressIntegrationSettings::integration();
        $token = $integration?->sync_token;

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function responseMessage(?array $body, string $fallback): string
    {
        if (! is_array($body)) {
            return $fallback;
        }

        $message = (string) ($body['message'] ?? '');
        if ($message === '') {
            return $fallback;
        }

        if (($body['code'] ?? '') === 'helmful_not_configured') {
            return 'WordPress does not have an API key yet. Save settings in Helmful after pasting your Helmful integration key into WordPress, or generate a key in WordPress under Settings → Helmful Sync.';
        }

        if (($body['code'] ?? '') === 'helmful_helmful_not_configured') {
            return 'Paste your Helmful integration key into WordPress under Settings → Helmful Sync, then test again.';
        }

        return $message;
    }

    private function endpoint(string $path): string
    {
        $base = WordPressIntegrationSettings::forCurrentTenant()->wordpressUrl();
        if ($base === null) {
            throw new RuntimeException('WordPress site URL is not configured.');
        }

        return $base.'/wp-json/helmful/v1'.($path === '/' ? '' : $path);
    }

    /**
     * @param  array<string, mixed>  $settingsPatch
     */
    private function markSyncing(Integration $integration): void
    {
        $integration->update([
            'sync_status' => IntegrationSyncStatus::Syncing,
            'sync_error_message' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $settingsPatch
     */
    private function markSyncResult(Integration $integration, bool $success, ?string $error, array $settingsPatch = []): void
    {
        $settings = array_merge($integration->settings ?? [], $settingsPatch);

        $integration->update([
            'sync_status' => $success ? IntegrationSyncStatus::Success : IntegrationSyncStatus::Failed,
            'sync_error_message' => $success ? null : $error,
            'last_synced_at' => now(),
            'settings' => $settings,
        ]);
    }
}
