<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\Plan;
use App\Models\PricingSetting;
use App\Models\Post;
use App\Support\BlogPlaceholder;
use App\Support\PlanFeatureList;
use App\Support\PlanSeatPolicy;
use App\Support\PublicPageCache;
use App\Support\PublicPageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->isPwa()) {
            if (Auth::guest()) {
                session()->put('url.intended', route('dashboard', absolute: false).'?pwa=1');

                return redirect()->route('login', ['pwa' => 1]);
            }

            if (Auth::user()->email_verified_at === null) {
                return redirect()->route('verification.notice');
            }

            return redirect()->route('dashboard', ['pwa' => 1]);
        }

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
                    'link' => route('blogPostShow', $post->slug),
                    'title' => $post->title,
                    'excerpt' => $post->short_description ?? Str::limit(strip_tags($post->body ?? ''), 150),
                    'date' => $post->published_at ? $post->published_at->format('F j, Y') : $post->created_at->format('F j, Y'),
                    'author' => $post->user->name ?? 'Anonymous',
                    'category' => $post->category->name ?? 'Uncategorized',
                    'image' => BlogPlaceholder::coverImage($post->cover_image),
                    'readTime' => $readTime.' min read',
                ];
            })->values()->all();
        });

        $plans = Cache::remember(PublicPageCache::WELCOME_PRICING_PLANS, now()->addHours(12), function () {
            return Plan::where('active', true)
                ->orderBy('monthly_price')
                ->get()
                ->map(fn (Plan $plan) => PlanFeatureList::toWelcomeArray($plan))
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

        return Inertia::render('Welcome', array_merge(
            PublicPageMeta::home(),
            [
                'blogPosts' => $blogPosts,
                'pricingPlans' => $plans,
                'allTiers' => PricingSetting::allTiersSection(),
                'seatPolicy' => PlanSeatPolicy::forMarketing(),
                'faqs' => $faqs,
            ],
        ));
    }
}
