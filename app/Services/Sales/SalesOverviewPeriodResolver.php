<?php

namespace App\Services\Sales;

use Carbon\Carbon;
use Illuminate\Http\Request;

final class SalesOverviewPeriodResolver
{
    /**
     * @return array{start: Carbon, end: Carbon, key: string, label: string}
     */
    public function resolve(Request $request): array
    {
        $period = strtolower(trim((string) $request->query('period', 'month')));
        $tz = (string) config('app.timezone', 'UTC');
        $now = now($tz);

        $year = (int) $request->query('year', $now->year);

        return match ($period) {
            'week' => [
                'start' => $now->copy()->startOfWeek(),
                'end' => $now->copy()->endOfWeek(),
                'key' => 'week',
                'label' => 'This week',
            ],
            'month' => $this->defaultMonth($now),
            'quarter' => $this->quarterPeriod($now, $tz, $year),
            'year' => $this->yearPeriod($tz, $year),
            'custom' => $this->customPeriod($request, $tz) ?? $this->defaultMonth($now),
            default => $this->defaultMonth($now),
        };
    }

    /**
     * @return array{start: Carbon, end: Carbon, key: string, label: string}
     */
    private function defaultMonth(Carbon $now): array
    {
        return [
            'start' => $now->copy()->startOfMonth(),
            'end' => $now->copy()->endOfMonth(),
            'key' => 'month',
            'label' => $now->format('F Y'),
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon, key: string, label: string}
     */
    private function yearPeriod(string $tz, int $year): array
    {
        $y = max(2000, min(2100, $year));
        $start = Carbon::createFromDate($y, 1, 1, $tz)->startOfDay();
        $end = Carbon::createFromDate($y, 12, 31, $tz)->endOfDay();

        return [
            'start' => $start,
            'end' => $end,
            'key' => 'year',
            'label' => (string) $y,
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon, key: string, label: string}
     */
    private function quarterPeriod(Carbon $now, string $tz, int $year): array
    {
        $y = max(2000, min(2100, $year));
        $quarter = (int) ceil($now->month / 3);
        $startMonth = ($quarter - 1) * 3 + 1;
        $start = Carbon::createFromDate($y, $startMonth, 1, $tz)->startOfDay();
        $end = $start->copy()->addMonths(3)->subDay()->endOfDay();

        return [
            'start' => $start,
            'end' => $end,
            'key' => 'quarter',
            'label' => 'Q'.$quarter.' '.$y,
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon, key: string, label: string}|null
     */
    private function customPeriod(Request $request, string $tz): ?array
    {
        $from = $request->query('date_from');
        $to = $request->query('date_to');
        if (! is_string($from) || ! is_string($to) || $from === '' || $to === '') {
            return null;
        }

        try {
            $start = Carbon::parse($from, $tz)->startOfDay();
            $end = Carbon::parse($to, $tz)->endOfDay();
        } catch (\Throwable) {
            return null;
        }

        if ($start->gt($end)) {
            return null;
        }

        return [
            'start' => $start,
            'end' => $end,
            'key' => 'custom',
            'label' => $start->format('M j, Y').' – '.$end->format('M j, Y'),
        ];
    }
}
