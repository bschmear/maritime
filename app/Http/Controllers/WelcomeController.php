<?php
namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\Plan;
use App\Models\Post;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    public function index(): Response
    {

        // Get featured posts first, then latest posts to fill up to 3 total
        $featuredPosts = Post::with(['user', 'category'])
            ->where('published', true)
            ->where('featured', true)
            ->orderBy('published_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get();

        $remainingCount = 3 - $featuredPosts->count();

        if ($remainingCount > 0) {
            $latestPosts = Post::with(['user', 'category'])
                ->where('published', true)
                ->where('featured', false)
                ->orderBy('published_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->take($remainingCount)
                ->get();

            $posts = $featuredPosts->merge($latestPosts);
        } else {
            $posts = $featuredPosts;
        }

        $blogPosts = $posts->map(function ($post) {
            // Calculate read time (average reading speed: 200 words per minute)
            $wordCount = str_word_count(strip_tags($post->body ?? ''));
            $readTime = $wordCount > 0 ? ceil($wordCount / 200) : 1;

            return [
                'id' => $post->id,
                'title' => $post->title,
                'excerpt' => $post->short_description ?? Str::limit(strip_tags($post->body ?? ''), 150),
                'date' => $post->published_at ? $post->published_at->format('F j, Y') : $post->created_at->format('F j, Y'),
                'author' => $post->user->name ?? 'Anonymous',
                'category' => $post->category->name ?? 'Uncategorized',
                'image' => $post->cover_image ?: 'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=800&h=500&fit=crop',
                'readTime' => $readTime . ' min read',
                'link' => '/blog/' . $post->slug,
            ];
        });

        // Get active plans ordered by popular first, then by monthly price
        $plans = Plan::where('active', true)
            ->orderBy('monthly_price')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'price' => [
                        'monthly' => $plan->monthly_price ?? 0,
                        'annual' => $plan->yearly_price ?? 0,
                    ],
                    'features' => $plan->included ?? [],
                    'cta' => $plan->popular ? 'Start ' . $plan->name . ' Trial' : 'Get ' . $plan->name,
                    'ctaLink' => route('checkout.plans', ['plan' => $plan->id, 'billing' => 'monthly']),
                    'popular' => $plan->popular,
                    'seatLimit' => $plan->seat_limit,
                ];
            });

        // Get FAQs - featured first, then by creation order
        $faqs = Faq::where('featured', true)
            ->orderBy('created_at')
            ->get()
            ->map(function ($faq) {
                return [
                    'id' => $faq->id,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'featured' => $faq->featured,
                ];
            });

        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'blogPosts' => $blogPosts,
            'pricingPlans' => $plans,
            'faqs' => $faqs,
        ]);
    }
}
