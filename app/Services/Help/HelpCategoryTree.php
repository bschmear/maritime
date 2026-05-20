<?php

namespace App\Services\Help;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Support\Collection;

class HelpCategoryTree
{
    /**
     * @return Collection<int, HelpCategory>
     */
    public static function forPortal(): Collection
    {
        $categories = HelpCategory::query()
            ->active()
            ->with([
                'children' => fn ($q) => $q->active()->orderBy('sort_order'),
                'articles' => fn ($q) => $q->active()->published()->orderBy('sort_order'),
            ])
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return $categories;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function toNavArray(Collection $categories): array
    {
        return $categories->map(function (HelpCategory $category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'children' => $category->children->map(fn (HelpCategory $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                ])->values()->all(),
                'articles' => $category->articles->map(fn (HelpArticle $article) => [
                    'id' => $article->id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                ])->values()->all(),
            ];
        })->values()->all();
    }
}
