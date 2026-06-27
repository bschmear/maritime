<?php

namespace App\Observers;

use App\Models\Post;
use App\Support\MarketingSitemapGenerator;

class PostObserver
{
    public function saved(Post $post): void
    {
        MarketingSitemapGenerator::forgetCache();
    }

    public function deleted(Post $post): void
    {
        MarketingSitemapGenerator::forgetCache();
    }

    public function restored(Post $post): void
    {
        MarketingSitemapGenerator::forgetCache();
    }

    public function forceDeleted(Post $post): void
    {
        MarketingSitemapGenerator::forgetCache();
    }
}
