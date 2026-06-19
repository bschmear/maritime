<?php

declare(strict_types=1);

namespace App\Services\Financing;

use App\Domain\Financing\Models\Financing;
use App\Enums\Financing\Status as FinancingStatus;
use App\Models\AccountSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FinancingOverviewDataService
{
    private const PAID_OFF_WINDOW_DAYS = 30;

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

            $q = Financing::query();
            $this->applyStatScope($q, $scope);
            $out[$key] = $q->count();
        }

        return $out;
    }

    /**
     * @return array{
     *   paid_off_window_days: int,
     *   days_alert_threshold: int|null,
     *   has_days_alert_threshold: bool,
     *   interest_alert_threshold: float|null,
     *   has_interest_alert_threshold: bool
     * }
     */
    public function buildStatContext(): array
    {
        $settings = AccountSettings::getCurrent();
        $daysThreshold = $this->accountDaysAlertThreshold($settings);
        $interestThreshold = $this->accountInterestAlertThreshold($settings);

        return [
            'paid_off_window_days' => self::PAID_OFF_WINDOW_DAYS,
            'days_alert_threshold' => $daysThreshold,
            'has_days_alert_threshold' => $daysThreshold !== null,
            'interest_alert_threshold' => $interestThreshold,
            'has_interest_alert_threshold' => $interestThreshold !== null,
        ];
    }

    /**
     * @return array{
     *   by_lender_status: array{labels: list<string>, series: list<int>, colors: list<string>},
     *   by_financing_status: array{labels: list<string>, series: list<int>, colors: list<string>},
     *   imported_trend: array{categories: list<string>, series: list<array{name: string, data: list<int>}>, colors: list<string>},
     *   total_current_balance: float
     * }
     */
    public function buildCharts(): array
    {
        $activeBalance = (float) Financing::query()
            ->where('status', FinancingStatus::Active->value)
            ->sum('current_balance');

        return [
            'by_lender_status' => $this->byLenderStatusChart(),
            'by_financing_status' => $this->byFinancingStatusChart(),
            'imported_trend' => $this->importedTrendChart(),
            'total_current_balance' => round($activeBalance, 2),
        ];
    }

    /**
     * Active financings for the dashboard list (highest aging first).
     *
     * @return list<array<string, mixed>>
     */
    public function activeFinancingPreview(int $limit = 25): array
    {
        return Financing::query()
            ->with([
                'assetUnit' => fn ($q) => $q->select(['id', 'serial_number', 'hin', 'sku', 'asset_id'])
                    ->with(['asset:id,display_name']),
                'vendor' => fn ($q) => $q->select(['id', 'display_name']),
            ])
            ->where('status', FinancingStatus::Active->value)
            ->orderByDesc('aging_days')
            ->orderByDesc('current_balance')
            ->limit($limit)
            ->get()
            ->map(fn (Financing $financing) => [
                'id' => $financing->id,
                'display_name' => $financing->display_name,
                'serial_vin' => $financing->serial_vin,
                'lender_invoice_number' => $financing->lender_invoice_number,
                'principal_amount' => (float) ($financing->principal_amount ?? 0),
                'current_balance' => (float) ($financing->current_balance ?? 0),
                'aging_days' => $financing->aging_days,
                'financed_at' => $financing->financed_at?->toDateString(),
                'interest_start_date' => $financing->interest_start_date?->toDateString(),
                'lender_status' => $financing->lender_status,
                'supplier_name' => $financing->supplier_name,
                'model_number' => $financing->model_number,
                'model_year' => $financing->model_year,
                'asset_unit' => $financing->assetUnit ? [
                    'id' => $financing->assetUnit->id,
                    'display_name' => $financing->assetUnit->display_name,
                ] : null,
                'vendor' => $financing->vendor ? [
                    'id' => $financing->vendor->id,
                    'display_name' => $financing->vendor->display_name,
                ] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Builder<Financing>  $query
     */
    private function applyStatScope(Builder $query, string $scope): void
    {
        $settings = AccountSettings::getCurrent();
        $accountDaysThreshold = $this->accountDaysAlertThreshold($settings);

        match ($scope) {
            'active' => $query->where('status', FinancingStatus::Active->value),
            'paid_off' => $query
                ->where('status', FinancingStatus::PaidOff->value)
                ->where('updated_at', '>=', now()->subDays(self::PAID_OFF_WINDOW_DAYS)),
            'in_stock' => $query
                ->where('status', FinancingStatus::Active->value)
                ->whereRaw('LOWER(COALESCE(lender_status, \'\')) = ?', ['in-stock']),
            'high_aging' => $this->applyHighAgingStatScope($query, $accountDaysThreshold),
            default => $query->whereRaw('0 = 1'),
        };
    }

    /**
     * @param  Builder<Financing>  $query
     */
    private function applyHighAgingStatScope(Builder $query, ?int $accountDaysThreshold): void
    {
        $query->where('status', FinancingStatus::Active->value);

        if ($accountDaysThreshold !== null) {
            $query->where(function (Builder $inner) use ($accountDaysThreshold) {
                $inner->where(function (Builder $withOverride) {
                    $withOverride->whereNotNull('days_alert_threshold')
                        ->whereColumn('aging_days', '>=', 'days_alert_threshold');
                })->orWhere(function (Builder $withDefault) use ($accountDaysThreshold) {
                    $withDefault->whereNull('days_alert_threshold')
                        ->where('aging_days', '>=', $accountDaysThreshold);
                });
            });

            return;
        }

        $query->whereNotNull('days_alert_threshold')
            ->whereColumn('aging_days', '>=', 'days_alert_threshold');
    }

    private function accountDaysAlertThreshold(AccountSettings $settings): ?int
    {
        $value = $settings->financing_max_days_in_inventory;

        if ($value === null || (int) $value <= 0) {
            return null;
        }

        return (int) $value;
    }

    private function accountInterestAlertThreshold(AccountSettings $settings): ?float
    {
        $value = $settings->financing_interest_alert_amount;

        if ($value === null || (float) $value <= 0) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @return array{labels: list<string>, series: list<int>, colors: list<string>}
     */
    private function byLenderStatusChart(): array
    {
        $rows = Financing::query()
            ->where('status', FinancingStatus::Active->value)
            ->selectRaw('COALESCE(NULLIF(TRIM(lender_status), \'\'), \'Unknown\') as label, count(*) as aggregate')
            ->groupBy('label')
            ->orderByDesc('aggregate')
            ->get();

        $palette = ['#3b82f6', '#14b8a6', '#22c55e', '#f59e0b', '#a855f7', '#6b7280', '#ef4444'];

        $labels = [];
        $series = [];
        $colors = [];

        foreach ($rows as $index => $row) {
            $labels[] = (string) $row->label;
            $series[] = (int) $row->aggregate;
            $colors[] = $palette[$index % count($palette)];
        }

        return compact('labels', 'series', 'colors');
    }

    /**
     * @return array{labels: list<string>, series: list<int>, colors: list<string>}
     */
    private function byFinancingStatusChart(): array
    {
        $counts = Financing::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $options = array_map(fn (array $opt) => [
            'id' => $opt['value'],
            'name' => $opt['name'],
            'color' => $opt['color'] ?? 'gray',
        ], FinancingStatus::options());

        return $this->chartPayloadFromOptions($options, $counts, 'value');
    }

    /**
     * @return array{categories: list<string>, series: list<array{name: string, data: list<int>}>, colors: list<string>}
     */
    private function importedTrendChart(): array
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
            $data[] = Financing::query()
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->count();
            $cursor->addWeek();
        }

        return [
            'categories' => $categories,
            'series' => [
                ['name' => 'New financings', 'data' => $data],
            ],
            'colors' => ['#3b82f6'],
        ];
    }

    /**
     * @param  list<array{id: string, name: string, color?: string}>  $options
     * @param  Collection<int|string, mixed>  $counts
     * @return array{labels: list<string>, series: list<int>, colors: list<string>}
     */
    private function chartPayloadFromOptions(array $options, Collection $counts, string $countKey = 'id'): array
    {
        $labels = [];
        $series = [];
        $colors = [];

        foreach ($options as $option) {
            $key = $option[$countKey] ?? $option['id'] ?? null;
            if ($key === null) {
                continue;
            }
            $count = (int) ($counts[$key] ?? $counts[(string) $key] ?? 0);
            if ($count === 0) {
                continue;
            }
            $labels[] = $option['name'];
            $series[] = $count;
            $colors[] = $this->colorToHex($option['color'] ?? 'gray');
        }

        return compact('labels', 'series', 'colors');
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
        $path = app_path('Domain/Financing/Schema/table.json');

        return is_readable($path)
            ? (json_decode((string) file_get_contents($path), true) ?: [])
            : [];
    }
}
