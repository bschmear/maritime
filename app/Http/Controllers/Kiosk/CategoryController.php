<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::withCount('posts')
            ->latest()
            ->paginate(15);

        return Inertia::render('Kiosk/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Kiosk/Categories/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        Category::create($validated);

        return redirect()->route('kiosk.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function show(Category $category): Response
    {
        $category->loadCount('posts');
        $category->load(['posts' => function ($query) {
            $query->latest()->take(10);
        }]);

        return Inertia::render('Kiosk/Categories/Show', [
            'category' => $category,
        ]);
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('Kiosk/Categories/Edit', [
            'category' => $category,
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $category->update($validated);

        return redirect()->route('kiosk.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('kiosk.categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
