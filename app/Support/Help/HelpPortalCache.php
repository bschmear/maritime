<?php

declare(strict_types=1);

namespace App\Support\Help;

use App\Services\Help\HelpArticleSearch;
use App\Services\Help\HelpCategoryTree;
use App\Support\PublicPageCache;
use Illuminate\Support\Facades\Cache;

/**
 * Cached navigation and search index for the public documentation portal.
 */
final class HelpPortalCache
{
    private const TTL_SECONDS = 43200;

    private const STORE = 'redis';

    private const NAV_KEY = 'help_portal.nav';

    private const SEARCH_KEY = 'help_portal.search_index';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function nav(): array
    {
        return Cache::store(self::STORE)->remember(
            self::NAV_KEY,
            now()->addSeconds(self::TTL_SECONDS),
            fn () => HelpCategoryTree::toNavArray(HelpCategoryTree::forPortal()),
        );
    }

    /**
     * @return list<array{title: string, slug: string, excerpt: string, category: string|null, category_slug: string|null, url: string}>
     */
    public static function searchIndex(): array
    {
        return Cache::store(self::STORE)->remember(
            self::SEARCH_KEY,
            now()->addSeconds(self::TTL_SECONDS),
            fn () => HelpArticleSearch::index(),
        );
    }

    public static function forget(): void
    {
        Cache::store(self::STORE)->forget(self::NAV_KEY);
        Cache::store(self::STORE)->forget(self::SEARCH_KEY);
        PublicPageCache::forgetSitemap();
    }
}
