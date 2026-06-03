<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL as UrlGenerator;
use RuntimeException;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class MarketingSitemapGenerator
{
    public const OUTPUT_PATH = 'sitemap.xml';

    /**
     * Build and write public/sitemap.xml. Returns the absolute file path.
     */
    public function generate(): string
    {
        UrlGenerator::forceRootUrl(rtrim((string) config('app.url'), '/'));

        $sitemap = Sitemap::create();

        $this->addStaticPages($sitemap);
        $this->addBlogPosts($sitemap);
        $this->addBlogCategories($sitemap);
        $this->addBlogTags($sitemap);

        $path = public_path(self::OUTPUT_PATH);
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create public directory: {$directory}");
        }

        if (! is_writable($directory)) {
            throw new RuntimeException("Public directory is not writable: {$directory}");
        }

        $tempPath = $directory.'/'.self::OUTPUT_PATH.'.'.getmypid().'.tmp';

        try {
            if (is_file($tempPath)) {
                unlink($tempPath);
            }

            $sitemap->writeToFile($tempPath);

            if (! is_file($tempPath) || filesize($tempPath) === 0) {
                throw new RuntimeException('Sitemap generation produced an empty file.');
            }

            if (is_file($path)) {
                unlink($path);
            }

            rename($tempPath, $path);
        } catch (\Throwable $exception) {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }

            throw $exception;
        }

        $this->updateRobotsTxt();

        return $path;
    }

    private function updateRobotsTxt(): void
    {
        $robotsPath = public_path('robots.txt');
        $sitemapUrl = rtrim((string) config('app.url'), '/').'/'.self::OUTPUT_PATH;
        $line = 'Sitemap: '.$sitemapUrl;

        $contents = is_file($robotsPath) ? (string) file_get_contents($robotsPath) : "User-agent: *\nDisallow:\n";

        if (preg_match('/^Sitemap:\s*.+$/mi', $contents)) {
            $contents = preg_replace('/^Sitemap:\s*.+$/mi', $line, $contents) ?? $contents;
        } else {
            $contents = rtrim($contents)."\n\n".$line."\n";
        }

        file_put_contents($robotsPath, $contents);
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

    private function blogCategoryUrl(string $slug): string
    {
        return route('blogCategory', [], true).'?slug='.rawurlencode($slug);
    }

    private function blogTagUrl(string $slug): string
    {
        return route('blogTag', [], true).'?slug='.rawurlencode($slug);
    }
}
