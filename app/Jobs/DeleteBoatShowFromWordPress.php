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

class DeleteBoatShowFromWordPress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $boatShowUuid,
    ) {}

    public function handle(WordPressApiService $wordpress): void
    {
        if (! $wordpress->isConnected()) {
            return;
        }

        $result = $wordpress->deleteBoatShow($this->boatShowUuid);
        if (! ($result['success'] ?? false)) {
            Log::warning('DeleteBoatShowFromWordPress failed', [
                'uuid' => $this->boatShowUuid,
                'message' => $result['message'] ?? null,
            ]);
        }
    }
}
