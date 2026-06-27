<?php

namespace Tests\Unit;

use App\Support\MarketingSitemapGenerator;
use App\Support\PublicPageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MarketingSitemapGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        PublicPageCache::forgetSitemap();

        parent::tearDown();
    }

    public function test_build_sitemap_includes_marketing_home_url(): void
    {
        $xml = app(MarketingSitemapGenerator::class)->buildSitemap();

        $this->assertStringContainsString(route('home', [], true), $xml);
        $this->assertStringContainsString(route('blog', [], true), $xml);
        $this->assertStringContainsString('<loc>', $xml);
    }

    public function test_cached_sitemap_is_stored_and_reused(): void
    {
        Cache::flush();

        $generator = app(MarketingSitemapGenerator::class);

        $first = $generator->cached();
        $second = $generator->cached();

        $this->assertSame($first, $second);
        $this->assertTrue(Cache::has(PublicPageCache::MARKETING_SITEMAP));
    }

    public function test_forget_cache_clears_cached_sitemap(): void
    {
        $generator = app(MarketingSitemapGenerator::class);

        $generator->cached();
        MarketingSitemapGenerator::forgetCache();

        $this->assertFalse(Cache::has(PublicPageCache::MARKETING_SITEMAP));
    }

    public function test_warm_cache_rebuilds_after_invalidation(): void
    {
        $generator = app(MarketingSitemapGenerator::class);

        $original = $generator->cached();
        MarketingSitemapGenerator::forgetCache();
        $warmed = $generator->warmCache();

        $this->assertSame(substr_count($original, '<loc>'), substr_count($warmed, '<loc>'));
        $this->assertTrue(Cache::has(PublicPageCache::MARKETING_SITEMAP));
    }
}
