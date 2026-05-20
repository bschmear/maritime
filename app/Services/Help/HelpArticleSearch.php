<?php

namespace App\Services\Help;

use App\Models\HelpArticle;
use Illuminate\Support\Str;

class HelpArticleSearch
{
    /**
     * Compact index for client-side fuzzy search on the documentation portal.
     *
     * @return list<array{title: string, slug: string, excerpt: string, category: string|null, category_slug: string|null, url: string}>
     */
    public static function index(): array
    {
        return HelpArticle::query()
            ->active()
            ->published()
            ->with('category:id,name,slug')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'excerpt', 'category_id'])
            ->map(fn (HelpArticle $article) => [
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => Str::limit(strip_tags($article->excerpt ?? ''), 160),
                'category' => $article->category?->name,
                'category_slug' => $article->category?->slug,
                'url' => route('docs.article', $article->slug),
            ])
            ->values()
            ->all();
    }
}
