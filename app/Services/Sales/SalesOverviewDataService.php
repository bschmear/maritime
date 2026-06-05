<?php

namespace App\Services\Sales;

use App\Domain\Customer\Models\Customer;
use App\Domain\Estimate\Models\Estimate;
use App\Domain\Lead\Models\Lead;
use App\Domain\Location\Models\Location;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\User\Models\User;
use App\Enums\Estimate\EstimateStatus;
use App\Enums\Opportunity\Status as OpportunityStatus;
use App\Enums\Transaction\TransactionStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class SalesOverviewDataService
{
    public function __construct(
        private SalesOverviewPeriodResolver $periodResolver,
    ) {}

    /**
     * @return array{
     *     summary: list<array<string, mixed>>,
     *     charts: array<string, mixed>,
     *     quickLinks: list<array<string, mixed>>,
     *     filters: array<string, mixed>,
     *     period_label: string,
     *     salespeople: list<array{id: int, label: string}>,
     *     locations: list<array{id: int, label: string}>
     * }
     */
    public function build(Request $request): array
    {
        $period = $this->periodResolver->resolve($request);
        $salespersonId = $this->resolveSalespersonId($request);
        $locationId = $this->resolveLocationId($request);
        $periodLabel = $period['label'];

        $openOpportunityStatusId = OpportunityStatus::Open->id();
        $openOpportunitiesQuery = $this->scopeOpportunity(Opportunity::query(), $salespersonId, $locationId)
            ->where('status', $openOpportunityStatusId)
            ->whereNull('won_at')
            ->whereNull('lost_at');

        $pipelineValue = (float) (clone $openOpportunitiesQuery)->sum('estimated_value');
        $openOpportunitiesCount = (clone $openOpportunitiesQuery)->count();

        $activeLeadsCount = $this->scopeLead(Lead::query(), $salespersonId, $locationId)
            ->where('converted', false)
            ->count();

        $pendingEstimatesCount = $this->scopeEstimate(Estimate::query(), $salespersonId, $locationId)
            ->where('status', EstimateStatus::PendingApproval->id())
            ->count();

        $activeDealStatuses = [
            TransactionStatus::Pending->value,
            TransactionStatus::Processing->value,
            'pending',
            'processing',
            'active',
            'open',
        ];

        $activeDealsCount = $this->scopeTransaction(Transaction::query(), $salespersonId, $locationId)
            ->whereIn('status', $activeDealStatuses)
            ->count();

        $wonInPeriodCount = $this->scopeOpportunity(Opportunity::query(), $salespersonId, $locationId)
            ->where('status', OpportunityStatus::Won->id())
            ->whereBetween('won_at', [$period['start'], $period['end']])
            ->count();

        $closedInPeriodCount = $this->scopeTransaction(Transaction::query(), $salespersonId, $locationId)
            ->where('status', TransactionStatus::Completed->value)
            ->whereBetween('closed_at', [$period['start'], $period['end']])
            ->count();

        $opportunityChartQuery = $this->scopeOpportunity(Opportunity::query(), $salespersonId, $locationId)
            ->whereBetween('created_at', [$period['start'], $period['end']]);

        $estimateChartQuery = $this->scopeEstimate(Estimate::query(), $salespersonId, $locationId)
            ->whereBetween('created_at', [$period['start'], $period['end']]);

        $leadsCreatedInPeriod = $this->scopeLead(Lead::query(), $salespersonId, $locationId)
            ->whereBetween('created_at', [$period['start'], $period['end']]);

        $convertedInPeriod = (clone $leadsCreatedInPeriod)->where('converted', true)->count();
        $activeInPeriod = (clone $leadsCreatedInPeriod)->where('converted', false)->count();

        return [
            'period_label' => $periodLabel,
            'filters' => [
                'period' => $period['key'],
                'year' => (int) $request->query('year', now()->year),
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to'),
                'salesperson_id' => $salespersonId,
                'location_id' => $locationId,
            ],
            'salespeople' => $this->salespersonOptions(),
            'locations' => $this->locationOptions(),
            'summary' => [
                [
                    'key' => 'pipeline',
                    'label' => 'Open pipeline',
                    'value' => $openOpportunitiesCount,
                    'subvalue' => $pipelineValue,
                    'subvalue_format' => 'currency',
                    'hint' => 'Open opportunities now',
                    'icon' => 'trending_up',
                    'color' => 'blue',
                    'href' => $this->safeRoute('opportunities.index'),
                ],
                [
                    'key' => 'leads',
                    'label' => 'Active leads',
                    'value' => $activeLeadsCount,
                    'hint' => 'Not yet converted',
                    'icon' => 'person_search',
                    'color' => 'amber',
                    'href' => $this->safeRoute('leads.index'),
                ],
                [
                    'key' => 'quotes',
                    'label' => 'Pending quotes',
                    'value' => $pendingEstimatesCount,
                    'hint' => 'Awaiting customer approval',
                    'icon' => 'request_quote',
                    'color' => 'indigo',
                    'href' => $this->safeRoute('estimates.index'),
                ],
                [
                    'key' => 'deals',
                    'label' => 'Active deals',
                    'value' => $activeDealsCount,
                    'hint' => 'Transactions in progress',
                    'icon' => 'handshake',
                    'color' => 'purple',
                    'href' => $this->safeRoute('transactions.index'),
                ],
                [
                    'key' => 'won',
                    'label' => 'Won ('.$periodLabel.')',
                    'value' => $wonInPeriodCount,
                    'hint' => 'Opportunities marked won',
                    'icon' => 'emoji_events',
                    'color' => 'green',
                    'href' => $this->safeRoute('opportunities.index'),
                ],
                [
                    'key' => 'closed',
                    'label' => 'Closed ('.$periodLabel.')',
                    'value' => $closedInPeriodCount,
                    'hint' => 'Completed transactions',
                    'icon' => 'check_circle',
                    'color' => 'emerald',
                    'href' => $this->safeRoute('transactions.index'),
                ],
            ],
            'charts' => [
                'opportunities_by_status' => $this->statusChartPayload(
                    $opportunityChartQuery,
                    'status',
                    OpportunityStatus::options(),
                ),
                'estimates_by_status' => $this->statusChartPayload(
                    $estimateChartQuery,
                    'status',
                    EstimateStatus::options(),
                ),
                'leads_overview' => [
                    'labels' => ['Active (new)', 'Converted (new)'],
                    'series' => [$activeInPeriod, $convertedInPeriod],
                    'colors' => ['#eab308', '#22c55e'],
                ],
                'activity_trend' => $this->activityTrendChart($period, $salespersonId, $locationId),
            ],
            'quickLinks' => [
                [
                    'title' => 'Sales process map',
                    'description' => 'Walk through the full flow from contact to close.',
                    'href' => $this->safeRoute('sales.flow'),
                    'icon' => 'account_tree',
                    'color' => 'primary',
                ],
                [
                    'title' => 'Opportunities',
                    'description' => 'Pipeline and deal stages.',
                    'href' => $this->safeRoute('opportunities.index'),
                    'icon' => 'trending_up',
                    'color' => 'blue',
                ],
                [
                    'title' => 'Estimates',
                    'description' => 'Quotes and customer approvals.',
                    'href' => $this->safeRoute('estimates.index'),
                    'icon' => 'request_quote',
                    'color' => 'indigo',
                ],
            ],
        ];
    }

    private function resolveSalespersonId(Request $request): ?int
    {
        $id = $request->query('salesperson_id');
        if ($id === null || $id === '' || $id === 'all') {
            return null;
        }

        $id = (int) $id;

        return $id > 0 ? $id : null;
    }

    private function resolveLocationId(Request $request): ?int
    {
        $id = $request->query('location_id');
        if ($id === null || $id === '' || $id === 'all') {
            return null;
        }

        $id = (int) $id;

        return $id > 0 ? $id : null;
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function locationOptions(): array
    {
        return Location::query()
            ->orderBy('display_name')
            ->get(['id', 'display_name'])
            ->map(fn (Location $location) => [
                'id' => (int) $location->id,
                'label' => trim((string) ($location->display_name ?: 'Location #'.$location->id)),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function opportunityIdsForLocation(int $locationId): array
    {
        return Estimate::query()
            ->where('location_id', $locationId)
            ->whereNotNull('opportunity_id')
            ->distinct()
            ->pluck('opportunity_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function salespersonOptions(): array
    {
        $ids = Opportunity::query()->whereNotNull('user_id')->distinct()->pluck('user_id')
            ->merge(Estimate::query()->whereNotNull('user_id')->distinct()->pluck('user_id'))
            ->merge(Lead::query()->whereNotNull('assigned_user_id')->distinct()->pluck('assigned_user_id'))
            ->merge(Transaction::query()->whereNotNull('user_id')->distinct()->pluck('user_id'))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('display_name')
            ->orderBy('first_name')
            ->get(['id', 'display_name', 'first_name', 'last_name', 'email'])
            ->map(fn (User $user) => [
                'id' => (int) $user->id,
                'label' => $this->userLabel($user),
            ])
            ->values()
            ->all();
    }

    private function userLabel(User $user): string
    {
        $name = trim((string) ($user->display_name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $full = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
        if ($full !== '') {
            return $full;
        }

        return (string) ($user->email ?? 'User #'.$user->id);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function scopeOpportunity(Builder $query, ?int $salespersonId, ?int $locationId): Builder
    {
        if ($salespersonId) {
            $query->where('user_id', $salespersonId);
        }

        if ($locationId) {
            $opportunityIds = $this->opportunityIdsForLocation($locationId);
            $query->whereIn('id', $opportunityIds !== [] ? $opportunityIds : [-1]);
        }

        return $query;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function scopeEstimate(Builder $query, ?int $salespersonId, ?int $locationId): Builder
    {
        if ($salespersonId) {
            $query->where('user_id', $salespersonId);
        }

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        return $query;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function scopeTransaction(Builder $query, ?int $salespersonId, ?int $locationId): Builder
    {
        if ($salespersonId) {
            $query->where('user_id', $salespersonId);
        }

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        return $query;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function scopeLead(Builder $query, ?int $salespersonId, ?int $locationId): Builder
    {
        if ($salespersonId) {
            $query->where('assigned_user_id', $salespersonId);
        }

        if ($locationId) {
            $customerIds = Estimate::query()
                ->where('location_id', $locationId)
                ->whereNotNull('customer_id')
                ->distinct()
                ->pluck('customer_id');

            $contactIds = Customer::query()
                ->whereIn('id', $customerIds)
                ->whereNotNull('contact_id')
                ->distinct()
                ->pluck('contact_id');

            $query->where(function (Builder $inner) use ($customerIds, $contactIds) {
                if ($customerIds->isNotEmpty() && $contactIds->isNotEmpty()) {
                    $inner->whereIn('converted_customer_id', $customerIds)
                        ->orWhereIn('contact_id', $contactIds);
                } elseif ($customerIds->isNotEmpty()) {
                    $inner->whereIn('converted_customer_id', $customerIds);
                } elseif ($contactIds->isNotEmpty()) {
                    $inner->whereIn('contact_id', $contactIds);
                } else {
                    $inner->whereRaw('0 = 1');
                }
            });
        }

        return $query;
    }

    /**
     * @param  array{start: Carbon, end: Carbon, key: string, label: string}  $period
     * @return array{categories: list<string>, series: list<array{name: string, data: list<float>}>, colors: list<string>}
     */
    private function activityTrendChart(array $period, ?int $salespersonId, ?int $locationId): array
    {
        $start = $period['start']->copy();
        $end = $period['end']->copy();
        $days = (int) $start->diffInDays($end) + 1;

        $categories = [];
        $pipelineSeries = [];
        $dealsSeries = [];

        if ($days <= 14) {
            $cursor = $start->copy()->startOfDay();
            while ($cursor->lte($end)) {
                $bucketStart = $cursor->copy()->startOfDay();
                $bucketEnd = $cursor->copy()->endOfDay();
                $categories[] = $cursor->format('M j');
                $pipelineSeries[] = $this->sumPipelineInRange($bucketStart, $bucketEnd, $salespersonId, $locationId);
                $dealsSeries[] = $this->sumDealsInRange($bucketStart, $bucketEnd, $salespersonId, $locationId);
                $cursor->addDay();
            }
        } elseif ($days <= 120) {
            $cursor = $start->copy()->startOfWeek();
            while ($cursor->lte($end)) {
                $bucketStart = $cursor->copy()->max($start);
                $bucketEnd = $cursor->copy()->endOfWeek()->min($end);
                $categories[] = $bucketStart->format('M j');
                $pipelineSeries[] = $this->sumPipelineInRange($bucketStart, $bucketEnd, $salespersonId, $locationId);
                $dealsSeries[] = $this->sumDealsInRange($bucketStart, $bucketEnd, $salespersonId, $locationId);
                $cursor->addWeek();
            }
        } else {
            $cursor = $start->copy()->startOfMonth();
            while ($cursor->lte($end)) {
                $bucketStart = $cursor->copy()->max($start);
                $bucketEnd = $cursor->copy()->endOfMonth()->min($end);
                $categories[] = $bucketStart->format('M Y');
                $pipelineSeries[] = $this->sumPipelineInRange($bucketStart, $bucketEnd, $salespersonId, $locationId);
                $dealsSeries[] = $this->sumDealsInRange($bucketStart, $bucketEnd, $salespersonId, $locationId);
                $cursor->addMonth();
            }
        }

        return [
            'categories' => $categories,
            'series' => [
                ['name' => 'Pipeline added', 'data' => $pipelineSeries],
                ['name' => 'Deal value opened', 'data' => $dealsSeries],
            ],
            'colors' => ['#3b82f6', '#8b5cf6'],
        ];
    }

    private function sumPipelineInRange(Carbon $start, Carbon $end, ?int $salespersonId, ?int $locationId): float
    {
        return round((float) $this->scopeOpportunity(Opportunity::query(), $salespersonId, $locationId)
            ->whereBetween('created_at', [$start, $end])
            ->sum('estimated_value'), 2);
    }

    private function sumDealsInRange(Carbon $start, Carbon $end, ?int $salespersonId, ?int $locationId): float
    {
        return round((float) $this->scopeTransaction(Transaction::query(), $salespersonId, $locationId)
            ->whereBetween('created_at', [$start, $end])
            ->sum('total'), 2);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<array{id: int, name: string, color?: string}>  $statusOptions
     * @return array{labels: list<string>, series: list<int>, colors: list<string>}
     */
    private function statusChartPayload(Builder $query, string $statusColumn, array $statusOptions): array
    {
        $counts = (clone $query)
            ->selectRaw("{$statusColumn}, count(*) as aggregate")
            ->groupBy($statusColumn)
            ->pluck('aggregate', $statusColumn);

        $labels = [];
        $series = [];
        $colors = [];

        foreach ($statusOptions as $option) {
            $id = (int) $option['id'];
            $count = (int) ($counts[$id] ?? $counts[(string) $id] ?? 0);
            if ($count === 0) {
                continue;
            }
            $labels[] = $option['name'];
            $series[] = $count;
            $colors[] = $this->tailwindColorToHex($option['color'] ?? 'gray');
        }

        return [
            'labels' => $labels,
            'series' => $series,
            'colors' => $colors,
        ];
    }

    private function tailwindColorToHex(string $color): string
    {
        return match ($color) {
            'blue' => '#3b82f6',
            'indigo' => '#6366f1',
            'yellow' => '#eab308',
            'gray' => '#6b7280',
            'red' => '#ef4444',
            'green' => '#22c55e',
            'slate' => '#64748b',
            'orange' => '#f97316',
            'purple' => '#a855f7',
            default => '#6b7280',
        };
    }

    private function safeRoute(string $name, array $parameters = []): ?string
    {
        return Route::has($name) ? route($name, $parameters) : null;
    }
}
