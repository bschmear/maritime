<?php
namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(): Response
    {
        $tags = Tag::withCount('posts')
            ->latest()
            ->paginate(15);

        return Inertia::render('Kiosk/Tags/Index', [
            'tags' => $tags,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Kiosk/Tags/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:tags,name',
        ]);

        // Generate slug automatically
        $validated['slug'] = Str::slug($validated['name']);

        Tag::create($validated);

        return redirect()->route('kiosk.tags.index')
            ->with('success', 'Tag created successfully.');
    }

    public function show(Tag $tag): Response
    {
        $tag->loadCount('posts');
        $tag->load(['posts' => function ($query) {
            $query->latest()->take(10);
        }]);

        return Inertia::render('Kiosk/Tags/Show', [
            'tag' => $tag,
        ]);
    }

    public function edit(Tag $tag): Response
    {
        // Load posts count so it displays in the edit page sidebar
        $tag->loadCount('posts');

        return Inertia::render('Kiosk/Tags/Edit', [
            'tag' => $tag,
        ]);
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:tags,name,' . $tag->id,
        ]);

        // Regenerate slug when name changes
        $validated['slug'] = Str::slug($validated['name']);

        $tag->update($validated);

        return redirect()->route('kiosk.tags.index')
            ->with('success', 'Tag updated successfully.');
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        return redirect()->route('kiosk.tags.index')
            ->with('success', 'Tag deleted successfully.');
    }
}
