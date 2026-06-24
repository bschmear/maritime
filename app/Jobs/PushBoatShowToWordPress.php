<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\BoatShow\Models\BoatShow;
use App\Services\Integrations\WordPressApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PushBoatShowToWordPress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $boatShowId,
    ) {}

    public function handle(WordPressApiService $wordpress): void
    {
        if (! $wordpress->isConnected()) {
            return;
        }

        $show = BoatShow::query()->find($this->boatShowId);
        if ($show === null) {
            return;
        }

        $result = $wordpress->pushBoatShow($show);
        if (! ($result['success'] ?? false)) {
            Log::warning('PushBoatShowToWordPress failed', [
                'boat_show_id' => $this->boatShowId,
                'message' => $result['message'] ?? null,
            ]);
        }
    }
}
