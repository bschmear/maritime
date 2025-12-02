<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    /**
     * Display a listing of blog posts
     */
    public function index(Request $request): Response
    {
        
        $query = Post::with(['user', 'category', 'tags'])
            ->where('published', true);

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->has('category') && $request->category) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Filter by tag
        if ($request->has('tag') && $request->tag) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('slug', $request->tag);
            });
        }

        $posts = $query->orderBy('published_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(12)
            ->through(function ($post) {
                return $this->formatPost($post);
            });

        return Inertia::render('Blog/Index', [
            'posts' => $posts,
            'categories' => Category::withCount('posts')->get(),
            'tags' => Tag::withCount('posts')->get(),
            'filters' => $request->only(['search', 'category', 'tag']),
        ]);
    }

    /**
     * Display posts by category
     */
    public function category(Request $request): Response
    {
        $categorySlug = $request->query('slug');
        
        if (!$categorySlug) {
            return redirect()->route('blog');
        }

        $category = Category::where('slug', $categorySlug)->firstOrFail();

        $posts = Post::with(['user', 'category', 'tags'])
            ->where('published', true)
            ->where('category_id', $category->id)
            ->orderBy('published_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(12)
            ->through(function ($post) {
                return $this->formatPost($post);
            });

        return Inertia::render('Blog/Category', [
            'category' => $category,
            'posts' => $posts,
            'categories' => Category::withCount('posts')->get(),
            'tags' => Tag::withCount('posts')->get(),
        ]);
    }

    /**
     * Display posts by tag
     */
    public function tag(Request $request): Response
    {
        $tagSlug = $request->query('slug');
        
        if (!$tagSlug) {
            return redirect()->route('blog');
        }

        $tag = Tag::where('slug', $tagSlug)->firstOrFail();

        $posts = Post::with(['user', 'category', 'tags'])
            ->where('published', true)
            ->whereHas('tags', function ($q) use ($tag) {
                $q->where('tags.id', $tag->id);
            })
            ->orderBy('published_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(12)
            ->through(function ($post) {
                return $this->formatPost($post);
            });

        return Inertia::render('Blog/Tag', [
            'tag' => $tag,
            'posts' => $posts,
            'categories' => Category::withCount('posts')->get(),
            'tags' => Tag::withCount('posts')->get(),
        ]);
    }

    /**
     * Display a single blog post
     */
    public function post(string $slug): Response
    {
        $post = Post::with(['user', 'category', 'tags'])
            ->where('slug', $slug)
            ->where('published', true)
            ->firstOrFail();

        // Get related posts (same category, excluding current post)
        $relatedPosts = Post::with(['user', 'category'])
            ->where('published', true)
            ->where('category_id', $post->category_id)
            ->where('id', '!=', $post->id)
            ->orderBy('published_at', 'desc')
            ->take(3)
            ->get()
            ->map(function ($relatedPost) {
                return $this->formatPost($relatedPost);
            });

        return Inertia::render('Blog/Show', [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'body' => $post->body,
                'short_description' => $post->short_description,
                'cover_image' => $post->cover_image,
                'published_at' => $post->published_at ? $post->published_at->format('F j, Y') : $post->created_at->format('F j, Y'),
                'author' => [
                    'name' => $post->user->name ?? 'Anonymous',
                    'avatar' => $post->user->avatar ?? null,
                ],
                'category' => $post->category ? [
                    'name' => $post->category->name,
                    'slug' => $post->category->slug,
                ] : null,
                'tags' => $post->tags->map(function ($tag) {
                    return [
                        'name' => $tag->name,
                        'slug' => $tag->slug,
                    ];
                }),
                'read_time' => $this->calculateReadTime($post->body),
            ],
            'relatedPosts' => $relatedPosts,
        ]);
    }

    /**
     * Format a post for display
     */
    private function formatPost($post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->short_description ?? Str::limit(strip_tags($post->body ?? ''), 150),
            'cover_image' => $post->cover_image ?: 'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=800&h=500&fit=crop',
            'published_at' => $post->published_at ? $post->published_at->format('F j, Y') : $post->created_at->format('F j, Y'),
            'author' => $post->user->name ?? 'Anonymous',
            'category' => $post->category ? [
                'name' => $post->category->name,
                'slug' => $post->category->slug,
            ] : null,
            'tags' => $post->tags->map(function ($tag) {
                return [
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ];
            }),
            'read_time' => $this->calculateReadTime($post->body),
        ];
    }

    /**
     * Calculate reading time
     */
    private function calculateReadTime(?string $content): string
    {
        $wordCount = str_word_count(strip_tags($content ?? ''));
        $readTime = $wordCount > 0 ? ceil($wordCount / 200) : 1;
        return $readTime . ' min read';
    }
}
