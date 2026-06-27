<?php

namespace App\Support;

use App\Models\Category;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL as UrlGenerator;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class MarketingSitemapGenerator
{
    public const OUTPUT_PATH = 'sitemap.xml';

    public function cached(): string
    {
        return Cache::remember(
            PublicPageCache::MARKETING_SITEMAP,
            now()->addHours(24),
            fn () => $this->buildSitemap(),
        );
    }

    public static function forgetCache(): void
    {
        PublicPageCache::forgetSitemap();
    }

    /**
     * Build sitemap XML without writing to disk.
     */
    public function buildSitemap(): string
    {
        UrlGenerator::forceRootUrl(rtrim((string) config('app.url'), '/'));

        $sitemap = Sitemap::create();

        $this->addStaticPages($sitemap);
        $this->addBlogPosts($sitemap);
        $this->addBlogCategories($sitemap);
        $this->addBlogTags($sitemap);
        $this->addHelpDocumentation($sitemap);

        return $sitemap->render();
    }

    /**
     * Warm the sitemap cache. Returns the cached XML.
     */
    public function warmCache(): string
    {
        self::forgetCache();

        return $this->cached();
    }

    private function addStaticPages(Sitemap $sitemap): void
    {
        $pages = [
            ['route' => 'home', 'priority' => 1.0, 'frequency' => Url::CHANGE_FREQUENCY_WEEKLY],
            ['route' => 'features', 'priority' => 0.9, 'frequency' => Url::CHANGE_FREQUENCY_MONTHLY],
            ['route' => 'features.boat-shows', 'priority' => 0.8, 'frequency' => Url::CHANGE_FREQUENCY_MONTHLY],
            ['route' => 'features.service-department', 'priority' => 0.8, 'frequency' => Url::CHANGE_FREQUENCY_MONTHLY],
            ['route' => 'features.performance-tracking', 'priority' => 0.8, 'frequency' => Url::CHANGE_FREQUENCY_MONTHLY],
            ['route' => 'features.delivery-system', 'priority' => 0.8, 'frequency' => Url::CHANGE_FREQUENCY_MONTHLY],
            ['route' => 'features.smart-surveys', 'priority' => 0.8, 'frequency' => Url::CHANGE_FREQUENCY_MONTHLY],
            ['route' => 'features.stripe-payments', 'priority' => 0.8, 'frequency' => Url::CHANGE_FREQUENCY_MONTHLY],
            ['route' => 'features.mailchimp', 'priority' => 0.8, 'frequency' => Url::CHANGE_FREQUENCY_MONTHLY],
            ['route' => 'features.quickbooks', 'priority' => 0.8, 'frequency' => Url::CHANGE_FREQUENCY_MONTHLY],
            ['route' => 'about', 'priority' => 0.8, 'frequency' => Url::CHANGE_FREQUENCY_MONTHLY],
            ['route' => 'contact', 'priority' => 0.8, 'frequency' => Url::CHANGE_FREQUENCY_MONTHLY],
            ['route' => 'faq', 'priority' => 0.7, 'frequency' => Url::CHANGE_FREQUENCY_MONTHLY],
            ['route' => 'blog', 'priority' => 0.9, 'frequency' => Url::CHANGE_FREQUENCY_DAILY],
            ['route' => 'checkout.plans', 'priority' => 0.8, 'frequency' => Url::CHANGE_FREQUENCY_MONTHLY],
            ['route' => 'terms', 'priority' => 0.4, 'frequency' => Url::CHANGE_FREQUENCY_YEARLY],
            ['route' => 'privacy-policy', 'priority' => 0.4, 'frequency' => Url::CHANGE_FREQUENCY_YEARLY],
            ['route' => 'login', 'priority' => 0.5, 'frequency' => Url::CHANGE_FREQUENCY_YEARLY],
            ['route' => 'register', 'priority' => 0.5, 'frequency' => Url::CHANGE_FREQUENCY_YEARLY],
        ];

        foreach ($pages as $page) {
            if (! Route::has($page['route'])) {
                continue;
            }

            $sitemap->add(
                Url::create(route($page['route'], [], true))
                    ->setPriority($page['priority'])
                    ->setChangeFrequency($page['frequency'])
            );
        }
    }

    private function addBlogPosts(Sitemap $sitemap): void
    {
        if (! Route::has('blogPostShow')) {
            return;
        }

        Post::query()
            ->published()
            ->orderByDesc('published_at')
            ->get(['slug', 'updated_at', 'published_at', 'created_at'])
            ->each(function (Post $post) use ($sitemap) {
                $lastMod = $post->updated_at ?? $post->published_at ?? $post->created_at;

                $sitemap->add(
                    Url::create(route('blogPostShow', ['slug' => $post->slug], true))
                        ->setLastModificationDate($lastMod)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setPriority(0.7)
                );
            });
    }

    private function addBlogCategories(Sitemap $sitemap): void
    {
        if (! Route::has('blogCategory')) {
            return;
        }

        Category::query()
            ->whereHas('posts', fn ($query) => $query->published())
            ->orderBy('name')
            ->get(['slug', 'updated_at'])
            ->each(function (Category $category) use ($sitemap) {
                $sitemap->add(
                    Url::create($this->blogCategoryUrl($category->slug))
                        ->setLastModificationDate($category->updated_at ?? now())
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setPriority(0.6)
                );
            });
    }

    private function addBlogTags(Sitemap $sitemap): void
    {
        if (! Route::has('blogTag')) {
            return;
        }

        Tag::query()
            ->whereHas('posts', fn ($query) => $query->published())
            ->orderBy('name')
            ->get(['slug', 'updated_at'])
            ->each(function (Tag $tag) use ($sitemap) {
                $sitemap->add(
                    Url::create($this->blogTagUrl($tag->slug))
                        ->setLastModificationDate($tag->updated_at ?? now())
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setPriority(0.5)
                );
            });
    }

    private function addHelpDocumentation(Sitemap $sitemap): void
    {
        $helpPortal = rtrim((string) config('app.help_portal'), '/');

        if ($helpPortal === '') {
            return;
        }

        $sitemap->add(
            Url::create($helpPortal)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                ->setPriority(0.8)
        );

        try {
            HelpCategory::query()
                ->active()
                ->orderBy('sort_order')
                ->get(['slug', 'updated_at'])
                ->each(function (HelpCategory $category) use ($sitemap, $helpPortal) {
                    $sitemap->add(
                        Url::create($helpPortal.'/c/'.$category->slug)
                            ->setLastModificationDate($category->updated_at ?? now())
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setPriority(0.6)
                    );
                });

            HelpArticle::query()
                ->active()
                ->published()
                ->orderBy('sort_order')
                ->get(['slug', 'updated_at', 'published_at'])
                ->each(function (HelpArticle $article) use ($sitemap, $helpPortal) {
                    $lastMod = $article->updated_at ?? $article->published_at ?? now();

                    $sitemap->add(
                        Url::create($helpPortal.'/a/'.$article->slug)
                            ->setLastModificationDate($lastMod)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setPriority(0.7)
                    );
                });
        } catch (\Throwable) {
            // Help docs use a separate Postgres database; skip detail URLs when unavailable.
        }
    }

    private function blogCategoryUrl(string $slug): string
    {
        return route('blogCategory', [], true).'?slug='.rawurlencode($slug);
    }

    private function blogTagUrl(string $slug): string
    {
        return route('blogTag', [], true).'?slug='.rawurlencode($slug);
    }
}
