<?php

namespace App\Services\Leads;

use App\Domain\Lead\Models\Lead;
use App\Enums\Entity\Source;
use App\Enums\Leads\Status as LeadStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LeadOverviewDataService
{
    /** @var list<int> */
    private const ACTIVE_STATUS_IDS = [1, 2, 3];

    /**
     * @return array<string, int>
     */
    public function buildStats(): array
    {
        $schema = $this->tableSchema();
        $defs = is_array($schema['stats'] ?? null) ? $schema['stats'] : [];

        $out = [];
        foreach ($defs as $def) {
            $key = $def['key'] ?? null;
            if (! is_string($key) || $key === '') {
                continue;
            }

            $scope = $def['scope'] ?? $key;
            if (! is_string($scope) || $scope === '') {
                $scope = $key;
            }

            $q = $this->pipelineBaseQuery();
            $this->applyStatScope($q, $scope);
            $out[$key] = $q->count();
        }

        return $out;
    }

    /**
     * @return array{
     *   by_status: array{labels: list<string>, series: list<int>, colors: list<string>},
     *   by_source: array{labels: list<string>, series: list<int>, colors: list<string>},
     *   created_trend: array{categories: list<string>, series: list<array{name: string, data: list<int>}>, colors: list<string>}
     * }
     */
    public function buildCharts(): array
    {
        return [
            'by_status' => $this->byStatusChart(),
            'by_source' => $this->bySourceChart(),
            'created_trend' => $this->createdTrendChart(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openLeadsPreview(int $limit = 25): array
    {
        return $this->fetchOpenLeads($limit);
    }

    /**
     * Open leads for priority kanban (higher cap).
     *
     * @return list<array<string, mixed>>
     */
    public function kanbanLeads(int $limit = 100): array
    {
        return $this->fetchOpenLeads($limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchOpenLeads(int $limit): array
    {
        $table = (new Lead)->getTable();

        $leads = Lead::query()
            ->with([
                'assigned_user' => fn ($q) => $q->select(['id', 'display_name', 'first_name', 'last_name', 'email']),
            ])
            ->join('contacts', 'contacts.id', '=', $table.'.contact_id')
            ->where($table.'.converted', false)
            ->whereIn($table.'.status_id', self::ACTIVE_STATUS_IDS)
            ->select($table.'.*')
            ->orderByRaw($table.'.next_followup_at IS NULL')
            ->orderBy($table.'.next_followup_at')
            ->orderByDesc($table.'.created_at')
            ->limit($limit)
            ->get();

        return $leads->map(fn (Lead $lead) => $this->serializeLeadRow($lead))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLeadRow(Lead $lead): array
    {
        $assigned = $lead->assigned_user;

        return [
            'id' => $lead->id,
            'contact_id' => $lead->contact_id,
            'display_name' => $lead->display_name,
            'email' => $lead->email,
            'status_id' => $lead->status_id,
            'source_id' => $lead->source_id,
            'priority_id' => $lead->priority_id,
            'assigned_user_id' => $lead->assigned_user_id,
            'next_followup_at' => $lead->next_followup_at?->format('Y-m-d'),
            'assigned_user' => $assigned ? [
                'id' => $assigned->id,
                'display_name' => $assigned->display_name
                    ?? trim(($assigned->first_name ?? '').' '.($assigned->last_name ?? ''))
                    ?: ($assigned->email ?? 'User #'.$assigned->id),
            ] : null,
        ];
    }

    /**
     * @return Builder<Lead>
     */
    public function pipelineBaseQuery(): Builder
    {
        return Lead::query()
            ->where('converted', false)
            ->whereIn('status_id', self::ACTIVE_STATUS_IDS);
    }

    /**
     * @param  Builder<Lead>  $query
     */
    public function applyStatScope(Builder $query, string $scope): void
    {
        $today = now()->startOfDay()->toDateString();

        match ($scope) {
            'open' => $query->where('status_id', LeadStatus::Open->id()),
            'contacted' => $query->where('status_id', LeadStatus::Contacted->id()),
            'qualified' => $query->where('status_id', LeadStatus::Qualified->id()),
            'follow_up_due' => $query
                ->whereNotNull('next_followup_at')
                ->whereDate('next_followup_at', '<=', $today),
            default => $query->whereRaw('0 = 1'),
        };
    }

    /**
     * @return array{labels: list<string>, series: list<int>, colors: list<string>}
     */
    private function byStatusChart(): array
    {
        $activeOptions = array_values(array_filter(
            LeadStatus::options(),
            fn (array $opt) => in_array((int) $opt['id'], self::ACTIVE_STATUS_IDS, true),
        ));

        $counts = (clone $this->pipelineBaseQuery())
            ->selectRaw('status_id, count(*) as aggregate')
            ->groupBy('status_id')
            ->pluck('aggregate', 'status_id');

        return $this->chartPayloadFromOptions($activeOptions, $counts);
    }

    /**
     * @return array{labels: list<string>, series: list<int>, colors: list<string>}
     */
    private function bySourceChart(): array
    {
        $counts = (clone $this->pipelineBaseQuery())
            ->selectRaw('source_id, count(*) as aggregate')
            ->groupBy('source_id')
            ->pluck('aggregate', 'source_id');

        return $this->chartPayloadFromOptions($this->sourceChartOptions(), $counts);
    }

    /**
     * @return list<array{id: int, value: string, name: string, color: string}>
     */
    private function sourceChartOptions(): array
    {
        $colorById = [
            Source::Referral->id() => 'purple',
            Source::Website->id() => 'blue',
            Source::WalkIn->id() => 'teal',
            Source::Ad->id() => 'orange',
            Source::BoatShow->id() => 'indigo',
            Source::Manufacturer->id() => 'green',
            Source::Other->id() => 'gray',
        ];

        return array_map(
            fn (array $opt) => array_merge($opt, [
                'color' => $colorById[(int) $opt['id']] ?? 'gray',
            ]),
            Source::options(),
        );
    }

    /**
     * @return array{categories: list<string>, series: list<array{name: string, data: list<int>}>, colors: list<string>}
     */
    private function createdTrendChart(): array
    {
        $weeks = 12;
        $end = now()->endOfWeek();
        $start = now()->copy()->subWeeks($weeks - 1)->startOfWeek();

        $categories = [];
        $data = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $weekStart = $cursor->copy()->startOfWeek();
            $weekEnd = $cursor->copy()->endOfWeek();
            $categories[] = $cursor->format('M j');
            $data[] = Lead::query()
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->count();
            $cursor->addWeek();
        }

        return [
            'categories' => $categories,
            'series' => [
                ['name' => 'New leads', 'data' => $data],
            ],
            'colors' => ['#3b82f6'],
        ];
    }

    /**
     * @param  list<array{id: int, name: string, color?: string}>  $options
     * @param  Collection<int|string, mixed>  $counts
     * @return array{labels: list<string>, series: list<int>, colors: list<string>}
     */
    private function chartPayloadFromOptions(array $options, Collection $counts): array
    {
        $labels = [];
        $series = [];
        $colors = [];

        foreach ($options as $option) {
            $id = (int) $option['id'];
            $count = (int) ($counts[$id] ?? $counts[(string) $id] ?? 0);
            if ($count === 0) {
                continue;
            }
            $labels[] = $option['name'];
            $series[] = $count;
            $colors[] = $this->colorToHex($option['color'] ?? 'gray');
        }

        return [
            'labels' => $labels,
            'series' => $series,
            'colors' => $colors,
        ];
    }

    private function colorToHex(string $color): string
    {
        return match ($color) {
            'blue' => '#3b82f6',
            'teal' => '#14b8a6',
            'green' => '#22c55e',
            'yellow' => '#eab308',
            'amber' => '#f59e0b',
            'gray' => '#6b7280',
            'purple' => '#a855f7',
            'red' => '#ef4444',
            'indigo' => '#6366f1',
            'orange' => '#f97316',
            default => '#6b7280',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function tableSchema(): array
    {
        $path = app_path('Domain/Lead/Schema/table.json');
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
