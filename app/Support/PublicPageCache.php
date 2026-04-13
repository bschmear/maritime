<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Keys and invalidation for public marketing pages (Welcome, FAQ, etc.).
 */
final class PublicPageCache
{
    public const WELCOME_PRICING_PLANS = 'welcome.pricing_plans';

    public const WELCOME_FAQS_FEATURED = 'welcome.faqs_featured';

    public const FAQS_ALL = 'faqs.all';

    public const WELCOME_BLOG_POSTS = 'welcome.blog_posts';

    public static function forgetPricingPlans(): void
    {
        Cache::forget(self::WELCOME_PRICING_PLANS);
    }

    public static function forgetFaqs(): void
    {
        Cache::forget(self::WELCOME_FAQS_FEATURED);
        Cache::forget(self::FAQS_ALL);
    }

    public static function forgetWelcomeBlogPosts(): void
    {
        Cache::forget(self::WELCOME_BLOG_POSTS);
    }
}
