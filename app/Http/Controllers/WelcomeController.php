<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\Plan;
use App\Models\Post;
use App\Support\PublicPageCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    public function index(): Response
    {
        PublicPageCache::forgetWelcomeBlogPosts();
        $blogPosts = Cache::remember(PublicPageCache::WELCOME_BLOG_POSTS, now()->addHours(12), function () {
            $posts = Post::with(['user', 'category'])
                ->published()
                ->featured()
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->take(3)
                ->get();

            return $posts->map(function ($post) {
                $wordCount = str_word_count(strip_tags($post->body ?? ''));
                $readTime = $wordCount > 0 ? ceil($wordCount / 200) : 1;

                return [
                    'id' => $post->id,
                    'slug' => $post->slug,
                    'title' => $post->title,
                    'excerpt' => $post->short_description ?? Str::limit(strip_tags($post->body ?? ''), 150),
                    'date' => $post->published_at ? $post->published_at->format('F j, Y') : $post->created_at->format('F j, Y'),
                    'author' => $post->user->name ?? 'Anonymous',
                    'category' => $post->category->name ?? 'Uncategorized',
                    'image' => $post->cover_image ?: 'https://images.unsplash.com/vector-1753704660095-8788aa5fa966?w=800&h=500&fit=crop',
                    'readTime' => $readTime.' min read',
                ];
            })->values()->all();
        });

        $plans = Cache::remember(PublicPageCache::WELCOME_PRICING_PLANS, now()->addHours(12), function () {
            return Plan::where('active', true)
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
                        'cta' => $plan->popular ? 'Start '.$plan->name.' Trial' : 'Get '.$plan->name,
                        'ctaLink' => route('checkout.plans', ['plan' => $plan->id, 'billing' => 'monthly']),
                        'popular' => $plan->popular,
                        'seatLimit' => $plan->seat_limit,
                    ];
                })
                ->values()
                ->all();
        });

        $faqs = Cache::remember(PublicPageCache::WELCOME_FAQS_FEATURED, now()->addHours(12), function () {
            return Faq::where('featured', true)
                ->orderBy('created_at')
                ->get()
                ->map(function ($faq) {
                    return [
                        'id' => $faq->id,
                        'question' => $faq->question,
                        'answer' => $faq->answer,
                        'featured' => $faq->featured,
                    ];
                })
                ->values()
                ->all();
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
