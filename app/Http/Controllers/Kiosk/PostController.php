<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Support\PostCoverImageStorage;
use App\Support\PublicPageCache;
use Illuminate\Http\JsonResponse;
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

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->integer('category'));
        }

        if ($request->filled('tag')) {
            $tagId = $request->integer('tag');
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));
        }

        if ($request->filled('author')) {
            $query->where('user_id', $request->integer('author'));
        }

        if ($request->has('published')) {
            $published = $request->boolean('published');
            $query->where('published', $published);
        }

        $posts = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Kiosk/Posts/Index', [
            'posts' => $posts,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name']),
            'authors' => User::query()
                ->whereHas('posts')
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => $request->only(['search', 'category', 'tag', 'author', 'published']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Kiosk/Posts/Create', [
            'categories' => Category::all(),
            'tags' => Tag::all(),
        ]);
    }

    public function uploadCover(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cover_image_file' => 'required|image|mimes:jpeg,jpg,png,webp,gif|max:10240',
            'previous_cover' => PostCoverImageStorage::storedPublicPathRules(),
        ]);

        $path = PostCoverImageStorage::store(
            $request->file('cover_image_file'),
            $validated['previous_cover'] ?? null,
        );

        return response()->json(['cover_image' => $path]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePost($request);

        $validated['user_id'] = auth()->id();
        $validated['slug'] = Str::slug($validated['title']);

        $this->ensurePublishedAtWhenPublished($validated);
        $this->applyCoverImageUpload($request, $validated);

        $post = Post::create($validated);

        if (isset($validated['tags'])) {
            $post->tags()->sync($validated['tags']);
        }

        PublicPageCache::forgetWelcomeBlogPosts();
        PublicPageCache::forgetSitemap();

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
        $validated = $this->validatePost($request);

        $validated['slug'] = Str::slug($validated['title']);

        $this->ensurePublishedAtWhenPublished($validated);
        $this->applyCoverImageUpload($request, $validated, $post->cover_image);

        $post->update($validated);

        if (isset($validated['tags'])) {
            $post->tags()->sync($validated['tags']);
        }

        PublicPageCache::forgetWelcomeBlogPosts();
        PublicPageCache::forgetSitemap();

        return redirect()->route('kiosk.posts.index')
            ->with('success', 'Post updated successfully.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        PostCoverImageStorage::deleteIfStored($post->cover_image);

        $post->delete();

        PublicPageCache::forgetWelcomeBlogPosts();
        PublicPageCache::forgetSitemap();

        return redirect()->route('kiosk.posts.index')
            ->with('success', 'Post deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePost(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'short_description' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'cover_image' => PostCoverImageStorage::storedPublicPathRules(),
            'cover_image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:10240',
            'featured' => 'boolean',
            'published' => 'boolean',
            'published_at' => 'nullable|date',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyCoverImageUpload(Request $request, array &$validated, ?string $previousCover = null): void
    {
        if ($request->hasFile('cover_image_file')) {
            $validated['cover_image'] = PostCoverImageStorage::store(
                $request->file('cover_image_file'),
                $previousCover,
            );
        } elseif (! empty($validated['cover_image'] ?? null)) {
            $path = (string) $validated['cover_image'];
            if ($previousCover && $path !== $previousCover) {
                PostCoverImageStorage::deleteIfStored($previousCover);
            }
        } else {
            unset($validated['cover_image']);
        }

        unset($validated['cover_image_file']);
    }

    /**
     * When marking a post published without a schedule, default published_at to now.
     *
     * @param  array<string, mixed>  $validated
     */
    private function ensurePublishedAtWhenPublished(array &$validated): void
    {
        if (! ($validated['published'] ?? false)) {
            return;
        }

        if (! empty($validated['published_at'])) {
            return;
        }

        $validated['published_at'] = now();
    }
}
