<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\HelpCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HelpCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = HelpCategory::query()->with('parent');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $categories = $query->orderBy('sort_order')->paginate(15);

        return Inertia::render('Kiosk/Help/Categories/Index', [
            'categories' => $categories,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Kiosk/Help/Categories/Create', [
            'parents' => HelpCategory::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:help_categories,id',
            'sort_order' => 'nullable|integer',
            'active' => 'boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        HelpCategory::create($validated);

        return redirect()->route('kiosk.help-categories.index')
            ->with('success', 'Category created.');
    }

    public function edit(HelpCategory $help_category): Response
    {
        return Inertia::render('Kiosk/Help/Categories/Edit', [
            'category' => $help_category,
            'parents' => HelpCategory::query()
                ->where('id', '!=', $help_category->id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, HelpCategory $help_category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:help_categories,id',
            'sort_order' => 'nullable|integer',
            'active' => 'boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $help_category->update($validated);

        return redirect()->route('kiosk.help-categories.index')
            ->with('success', 'Category updated.');
    }

    public function destroy(HelpCategory $help_category): RedirectResponse
    {
        $help_category->delete();

        return redirect()->route('kiosk.help-categories.index')
            ->with('success', 'Category deleted.');
    }
}
