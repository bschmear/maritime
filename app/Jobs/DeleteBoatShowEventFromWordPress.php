<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Integrations\WordPressApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeleteBoatShowEventFromWordPress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $boatShowEventUuid,
    ) {}

    public function handle(WordPressApiService $wordpress): void
    {
        if (! $wordpress->isConnected()) {
            return;
        }

        $result = $wordpress->deleteBoatShowEvent($this->boatShowEventUuid);
        if (! ($result['success'] ?? false)) {
            Log::warning('DeleteBoatShowEventFromWordPress failed', [
                'uuid' => $this->boatShowEventUuid,
                'message' => $result['message'] ?? null,
            ]);
        }
    }
}
