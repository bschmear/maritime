<?php

namespace App\Http\Controllers\Kiosk;

use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Post;
use App\Models\SupportTicket;
use App\Models\Tag;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $totalPosts = Post::count();
        $publishedPosts = Post::published()->count();
        $draftPosts = max(0, $totalPosts - $publishedPosts);

        $categorySlices = Category::query()
            ->withCount('posts')
            ->orderByDesc('posts_count')
            ->orderBy('name')
            ->get()
            ->filter(fn (Category $category) => $category->posts_count > 0)
            ->map(fn (Category $category) => [
                'label' => $category->name,
                'value' => $category->posts_count,
            ])
            ->values();

        $uncategorizedPosts = Post::query()->whereNull('category_id')->count();
        if ($uncategorizedPosts > 0) {
            $categorySlices->push([
                'label' => 'Uncategorized',
                'value' => $uncategorizedPosts,
            ]);
        }

        $statusCounts = SupportTicket::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $ticketSlices = collect(SupportTicketStatus::cases())
            ->map(function (SupportTicketStatus $status) use ($statusCounts) {
                $count = (int) ($statusCounts[$status->value] ?? 0);

                return [
                    'label' => $status->label(),
                    'value' => $count,
                    'color' => $this->statusChartColor($status),
                ];
            })
            ->filter(fn (array $slice) => $slice['value'] > 0)
            ->values();

        $stats = [
            'posts' => $totalPosts,
            'published_posts' => $publishedPosts,
            'categories' => Category::count(),
            'tags' => Tag::count(),
            'faqs' => Faq::count(),
            'support_tickets' => SupportTicket::count(),
        ];

        $charts = [
            'posts' => [
                'labels' => ['Published', 'Draft'],
                'series' => [$publishedPosts, $draftPosts],
                'colors' => ['#22c55e', '#94a3b8'],
                'total' => $totalPosts,
            ],
            'categories' => [
                'labels' => $categorySlices->pluck('label')->all(),
                'series' => $categorySlices->pluck('value')->all(),
                'colors' => [],
                'total' => Category::count(),
            ],
            'supportTickets' => [
                'labels' => $ticketSlices->pluck('label')->all(),
                'series' => $ticketSlices->pluck('value')->all(),
                'colors' => $ticketSlices->pluck('color')->all(),
                'total' => SupportTicket::count(),
            ],
        ];

        $recentPosts = Post::with(['user', 'category'])
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Kiosk/Dashboard', [
            'stats' => $stats,
            'charts' => $charts,
            'recentPosts' => $recentPosts,
        ]);
    }

    private function statusChartColor(SupportTicketStatus $status): string
    {
        return match ($status) {
            SupportTicketStatus::Open => '#3b82f6',
            SupportTicketStatus::InProgress => '#eab308',
            SupportTicketStatus::WaitingOnCustomer => '#f97316',
            SupportTicketStatus::Resolved => '#22c55e',
            SupportTicketStatus::Closed => '#64748b',
        };
    }
}
