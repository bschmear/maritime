<?php

namespace App\Jobs;

use App\Services\WebPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWebPushNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public string $url,
        public ?string $tag = null,
    ) {}

    public function handle(WebPushService $webPushService): void
    {
        $webPushService->sendToUser(
            $this->userId,
            $this->title,
            $this->body,
            $this->url,
            $this->tag,
        );
    }
}
