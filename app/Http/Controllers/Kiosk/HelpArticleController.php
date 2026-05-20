<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HelpArticleController extends Controller
{
    public function index(Request $request): Response
    {
        $query = HelpArticle::query()->with('category');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->input('category'));
        }

        $articles = $query->latest()->paginate(15);

        return Inertia::render('Kiosk/Help/Articles/Index', [
            'articles' => $articles,
            'categories' => HelpCategory::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'category']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Kiosk/Help/Articles/Create', [
            'categories' => HelpCategory::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'category_id' => 'nullable|exists:help_categories,id',
            'excerpt' => 'nullable|string',
            'video_url' => 'nullable|string|max:2048',
            'article_type' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
            'featured' => 'boolean',
            'active' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        $validated['slug'] = Str::slug($validated['title']);
        $this->ensurePublishedAt($validated);

        $article = HelpArticle::create($validated);
        $this->applySortOrderInCategory(
            $article->category_id,
            $article->id,
            (int) ($validated['sort_order'] ?? 0),
        );

        return redirect()->route('kiosk.help-articles.index')
            ->with('success', 'Article created.');
    }

    public function edit(HelpArticle $help_article): Response
    {
        return Inertia::render('Kiosk/Help/Articles/Edit', [
            'article' => $help_article,
            'categories' => HelpCategory::query()->orderBy('name')->get(['id', 'name']),
            'categoryArticles' => $this->articlesInCategory($help_article->category_id),
        ]);
    }

    public function siblings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:help_categories,id',
        ]);

        return response()->json([
            'articles' => $this->articlesInCategory((int) $validated['category_id']),
        ]);
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:help_categories,id',
            'order' => 'required|array|min:1',
            'order.*' => 'integer|exists:help_articles,id',
        ]);

        $categoryId = (int) $validated['category_id'];
        $order = array_map('intval', $validated['order']);

        $inCategory = HelpArticle::query()
            ->where('category_id', $categoryId)
            ->whereIn('id', $order)
            ->count();

        if ($inCategory !== count($order)) {
            abort(422, 'One or more articles do not belong to this category.');
        }

        foreach ($order as $index => $id) {
            HelpArticle::query()->whereKey($id)->update(['sort_order' => $index]);
        }

        return back();
    }

    public function update(Request $request, HelpArticle $help_article): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'category_id' => 'nullable|exists:help_categories,id',
            'excerpt' => 'nullable|string',
            'video_url' => 'nullable|string|max:2048',
            'article_type' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
            'featured' => 'boolean',
            'active' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        $validated['slug'] = Str::slug($validated['title']);
        $this->ensurePublishedAt($validated);

        $help_article->update($validated);
        $help_article->refresh();
        $this->applySortOrderInCategory(
            $help_article->category_id,
            $help_article->id,
            (int) ($validated['sort_order'] ?? $help_article->sort_order),
        );

        return redirect()->route('kiosk.help-articles.index')
            ->with('success', 'Article updated.');
    }

    public function destroy(HelpArticle $help_article): RedirectResponse
    {
        $help_article->delete();

        return redirect()->route('kiosk.help-articles.index')
            ->with('success', 'Article deleted.');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function ensurePublishedAt(array &$validated): void
    {
        if (($validated['active'] ?? false) && empty($validated['published_at'])) {
            $validated['published_at'] = now();
        }
    }

    private function applySortOrderInCategory(?int $categoryId, int $articleId, int $position): void
    {
        if (! $categoryId) {
            return;
        }

        $ids = HelpArticle::query()
            ->where('category_id', $categoryId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $ids = array_values(array_filter($ids, fn (int $id) => $id !== $articleId));
        $position = max(0, min($position, count($ids)));
        array_splice($ids, $position, 0, $articleId);

        foreach ($ids as $index => $id) {
            HelpArticle::query()->whereKey($id)->update(['sort_order' => $index]);
        }
    }

    /**
     * @return list<array{id: int, title: string, sort_order: int}>
     */
    private function articlesInCategory(?int $categoryId): array
    {
        if (! $categoryId) {
            return [];
        }

        return HelpArticle::query()
            ->where('category_id', $categoryId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'title', 'sort_order'])
            ->map(fn (HelpArticle $article) => [
                'id' => $article->id,
                'title' => $article->title,
                'sort_order' => $article->sort_order,
            ])
            ->values()
            ->all();
    }
}
