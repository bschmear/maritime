<?php

namespace App\Services;

use App\Domain\Notification\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public function isEnabled(): bool
    {
        return (bool) config('webpush.enabled');
    }

    /**
     * @return array{sent: int, failed: int, removed: int}
     */
    public function sendToUser(int $userId, string $title, string $body, string $url, ?string $tag = null): array
    {
        if (! $this->isEnabled()) {
            return ['sent' => 0, 'failed' => 0, 'removed' => 0];
        }

        $subscriptions = PushSubscription::query()
            ->where('user_id', $userId)
            ->get();

        if ($subscriptions->isEmpty()) {
            return ['sent' => 0, 'failed' => 0, 'removed' => 0];
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'tag' => $tag,
        ], JSON_THROW_ON_ERROR);

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('webpush.subject'),
                'publicKey' => config('webpush.public_key'),
                'privateKey' => config('webpush.private_key'),
            ],
        ]);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                $subscription->toWebPushSubscription(),
                $payload,
            );
        }

        $sent = 0;
        $failed = 0;
        $removed = 0;

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();

            if ($report->isSuccess()) {
                $sent++;
                PushSubscription::query()
                    ->where('endpoint', $endpoint)
                    ->update(['last_used_at' => now()]);

                continue;
            }

            $failed++;
            $statusCode = $report->getResponse()?->getStatusCode();

            Log::warning('Web push delivery failed', [
                'endpoint' => $endpoint,
                'reason' => $report->getReason(),
                'status_code' => $statusCode,
            ]);

            if (in_array($statusCode, [404, 410], true)) {
                PushSubscription::query()->where('endpoint', $endpoint)->delete();
                $removed++;
            }
        }

        return compact('sent', 'failed', 'removed');
    }
}
