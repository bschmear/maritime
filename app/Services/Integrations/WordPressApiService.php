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
     * @return array{success: bool, message?: string}
     */
    public function ping(): array
    {
        try {
            $response = $this->client()->get($this->endpoint('/status'));

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => $response->json('message') ?? 'WordPress connection failed.',
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
            throw new RuntimeException('WordPress API key is missing.');
        }

        return Http::timeout(30)
            ->acceptJson()
            ->withToken($apiKey);
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
