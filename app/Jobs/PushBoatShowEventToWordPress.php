<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\BoatShowEvent\Models\BoatShowEvent;
use App\Services\Integrations\WordPressApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PushBoatShowEventToWordPress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $boatShowEventId,
    ) {}

    public function handle(WordPressApiService $wordpress): void
    {
        if (! $wordpress->isConnected()) {
            return;
        }

        $event = BoatShowEvent::query()->find($this->boatShowEventId);
        if ($event === null) {
            return;
        }

        $result = $wordpress->pushBoatShowEvent($event);
        if (! ($result['success'] ?? false)) {
            Log::warning('PushBoatShowEventToWordPress failed', [
                'boat_show_event_id' => $this->boatShowEventId,
                'message' => $result['message'] ?? null,
            ]);
        }
    }
}
