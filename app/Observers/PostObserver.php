<?php

namespace App\Observers;

use App\Models\Post;
use App\Support\MarketingSitemapGenerator;
use Illuminate\Support\Facades\Log;
use Throwable;

class PostObserver
{
    public function saved(Post $post): void
    {
        $this->regenerate();
    }

    public function deleted(Post $post): void
    {
        $this->regenerate();
    }

    public function restored(Post $post): void
    {
        $this->regenerate();
    }

    public function forceDeleted(Post $post): void
    {
        $this->regenerate();
    }

    private function regenerate(): void
    {
        try {
            app(MarketingSitemapGenerator::class)->generate();
        } catch (Throwable $exception) {
            Log::warning('Failed to regenerate marketing sitemap.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
