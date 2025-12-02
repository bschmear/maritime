<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Post::with(['user', 'category', 'tags']);

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if ($request->has('category')) {
            $query->where('category_id', $request->input('category'));
        }

        if ($request->has('published')) {
            $published = $request->boolean('published');
            $query->where('published', $published);
        }

        $posts = $query->latest()->paginate(15);

        return Inertia::render('Kiosk/Posts/Index', [
            'posts' => $posts,
            'filters' => $request->only(['search', 'category', 'published']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Kiosk/Posts/Create', [
            'categories' => Category::all(),
            'tags' => Tag::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'short_description' => 'nullable|string|max:255',
            'cover_image' => 'nullable|string',
            'featured' => 'boolean',
            'published' => 'boolean',
            'published_at' => 'nullable|date',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['slug'] = Str::slug($validated['title']);

        $post = Post::create($validated);

        if (isset($validated['tags'])) {
            $post->tags()->sync($validated['tags']);
        }

        return redirect()->route('kiosk.posts.index')
            ->with('success', 'Post created successfully.');
    }

    public function show(Post $post): Response
    {
        $post->load(['user', 'category', 'tags']);

        return Inertia::render('Kiosk/Posts/Show', [
            'post' => $post,
        ]);
    }

    public function edit(Post $post): Response
    {
        $post->load('tags');

        return Inertia::render('Kiosk/Posts/Edit', [
            'post' => $post,
            'categories' => Category::all(),
            'tags' => Tag::all(),
        ]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'short_description' => 'nullable|string|max:255',
            'cover_image' => 'nullable|string',
            'featured' => 'boolean',
            'published' => 'boolean',
            'published_at' => 'nullable|date',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
        ]);

        $validated['slug'] = Str::slug($validated['title']);

        $post->update($validated);

        if (isset($validated['tags'])) {
            $post->tags()->sync($validated['tags']);
        }

        return redirect()->route('kiosk.posts.index')
            ->with('success', 'Post updated successfully.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        return redirect()->route('kiosk.posts.index')
            ->with('success', 'Post deleted successfully.');
    }
}
