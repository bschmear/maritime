<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Post;
use App\Models\Tag;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {

        $stats = [
            'posts' => Post::count(),
            'published_posts' => Post::published()->count(),
            'categories' => Category::count(),
            'tags' => Tag::count(),
            'faqs' => Faq::count(),
        ];

        $recentPosts = Post::with(['user', 'category'])
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Kiosk/Dashboard', [
            'stats' => $stats,
            'recentPosts' => $recentPosts,
        ]);
    }
}
