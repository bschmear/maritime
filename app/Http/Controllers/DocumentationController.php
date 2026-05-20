<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentationController extends Controller
{
    public function index(Request $request): Response
    {
        $featured = HelpArticle::query()
            ->active()
            ->published()
            ->featured()
            ->with('category')
            ->orderBy('sort_order')
            ->limit(6)
            ->get();

        $categories = HelpCategory::query()
            ->active()
            ->whereNull('parent_id')
            ->withCount(['articles' => fn ($q) => $q->active()->published()])
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Documentation/Index', [
            'featured' => $featured,
            'categories' => $categories,
            'search' => $request->input('search'),
        ]);
    }

    public function category(HelpCategory $category): Response
    {
        abort_unless($category->active, 404);

        $category->load([
            'children' => fn ($q) => $q->active()->orderBy('sort_order'),
            'articles' => fn ($q) => $q->active()->published()->orderBy('sort_order'),
        ]);

        return Inertia::render('Documentation/Category', [
            'category' => $category,
        ]);
    }

    public function show(HelpArticle $article): Response
    {
        abort_unless($article->active, 404);
        abort_unless(
            $article->published_at === null || $article->published_at->lte(now()),
            404
        );

        $article->load('category');

        $siblings = HelpArticle::query()
            ->active()
            ->published()
            ->where('category_id', $article->category_id)
            ->orderBy('sort_order')
            ->get(['id', 'title', 'slug']);

        $currentIndex = $siblings->search(fn ($a) => $a->id === $article->id);
        $prev = $currentIndex > 0 ? $siblings[$currentIndex - 1] : null;
        $next = $currentIndex !== false && $currentIndex < $siblings->count() - 1
            ? $siblings[$currentIndex + 1]
            : null;

        return Inertia::render('Documentation/Show', [
            'article' => $article,
            'prev' => $prev,
            'next' => $next,
        ]);
    }
}
