<?php

namespace App\Services\Dashboard;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\Payment\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Payment list dashboard metrics (hero, AR context, period stats).
 * Shared by {@see \App\Http\Controllers\Tenant\PaymentController} and tenant dashboard.
 */
final class PaymentDashboardMetricsService
{
    /**
     * @return array{start: Carbon, end: Carbon, key: string, label: string}|null
     */
    public function resolvePaymentListPeriod(Request $request): ?array
    {
        $period = strtolower(trim((string) $request->query('period', 'all')));
        if ($period === '' || $period === 'all') {
            return null;
        }

        $tz = (string) config('app.timezone', 'UTC');
        $now = now($tz);

        return match ($period) {
            'mtd' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
                'key' => 'mtd',
                'label' => 'Month to date',
            ],
            'last_30' => [
                'start' => $now->copy()->subDays(29)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
                'key' => 'last_30',
                'label' => 'Last 30 days',
            ],
            'last_90' => [
                'start' => $now->copy()->subDays(89)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
                'key' => 'last_90',
                'label' => 'Last 90 days',
            ],
            'q1', 'q2', 'q3', 'q4' => $this->paymentQuarterPeriod(
                $period,
                (int) $request->query('year', $now->year),
                $tz
            ),
            'custom' => $this->paymentCustomPeriod($request, $tz),
            default => null,
        };
    }

    /**
     * @param  Builder<Payment>  $filteredPaymentQuery  Query after period, search, and filters (not paginated).
     * @return array<string, mixed>
     */
    public function build(Request $request, Builder $filteredPaymentQuery): array
    {
        $tz = (string) config('app.timezone', 'UTC');
        $now = now($tz);

        $completedStatuses = ['completed', 'partially_refunded'];

        $collectedBase = Payment::query()->whereIn('payments.status', $completedStatuses);

        $totalCollectedAllTime = (float) (clone $collectedBase)->sum('payments.amount');

        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $collectedThisMonth = (float) (clone $collectedBase)
            ->whereRaw(
                'COALESCE(payments.paid_at, payments.created_at) BETWEEN ? AND ?',
                [$monthStart->toDateTimeString(), $monthEnd->toDateTimeString()]
            )
            ->sum('payments.amount');

        $lastMonthStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonthNoOverflow()->endOfMonth();
        $collectedLastMonth = (float) (clone $collectedBase)
            ->whereRaw(
                'COALESCE(payments.paid_at, payments.created_at) BETWEEN ? AND ?',
                [$lastMonthStart->toDateTimeString(), $lastMonthEnd->toDateTimeString()]
            )
            ->sum('payments.amount');

        $collectedMomPct = null;
        if ($collectedLastMonth > 0) {
            $collectedMomPct = round((($collectedThisMonth - $collectedLastMonth) / $collectedLastMonth) * 100, 1);
        }

        $outstandingBalance = (float) Invoice::query()->open()->sum('amount_due');
        $openReceivableCount = Invoice::query()->open()->count();

        $overdueBase = Invoice::query()
            ->open()
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now);
        $overdueAmount = (float) (clone $overdueBase)->sum('amount_due');
        $overdueInvoiceCount = (clone $overdueBase)->count();

        $partialInvoiceCount = Invoice::query()->where('status', 'partial')->count();
        $partialOutstanding = (float) Invoice::query()->where('status', 'partial')->sum('amount_due');

        $last30Start = $now->copy()->subDays(29)->startOfDay();
        $last30End = $now->copy()->endOfDay();
        $largestPaymentLast30 = (float) ((clone $collectedBase)
            ->whereRaw(
                'COALESCE(payments.paid_at, payments.created_at) BETWEEN ? AND ?',
                [$last30Start->toDateTimeString(), $last30End->toDateTimeString()]
            )
            ->max('payments.amount') ?? 0);

        $periodCompleted = (clone $filteredPaymentQuery)->whereIn('payments.status', $completedStatuses);
        $periodCollected = (float) (clone $periodCompleted)->sum('payments.amount');
        $periodPaymentCount = (clone $filteredPaymentQuery)->count();
        $periodCompletedCount = (clone $periodCompleted)->count();
        $periodAvgPayment = $periodCompletedCount > 0
            ? round($periodCollected / $periodCompletedCount, 2)
            : 0.0;
        $periodLargestPayment = (float) ((clone $periodCompleted)->max('payments.amount') ?? 0);

        $topMethodRow = (clone $periodCompleted)
            ->reorder()
            ->selectRaw('payments.payment_method_code, SUM(payments.amount) as method_sum')
            ->groupBy('payments.payment_method_code')
            ->orderByDesc('method_sum')
            ->first();

        $topPaymentMethod = null;
        if ($topMethodRow !== null) {
            $code = (string) $topMethodRow->payment_method_code;
            $sum = (float) $topMethodRow->method_sum;
            $topPaymentMethod = [
                'code' => $code,
                'total' => $sum,
                'pct' => $periodCollected > 0 ? (int) round(($sum / $periodCollected) * 100) : null,
            ];
        }

        $periodBounds = $this->resolvePaymentListPeriod($request);
        $periodLabel = $periodBounds['label'] ?? 'All time';
        $periodKey = $periodBounds !== null ? $periodBounds['key'] : 'all';

        $requestedPeriod = strtolower(trim((string) $request->query('period', 'all'))) ?: 'all';
        if ($requestedPeriod === 'custom' && $periodBounds === null) {
            $requestedPeriod = 'all';
        }

        return [
            'hero' => [
                'total_collected_all_time' => $totalCollectedAllTime,
                'collected_this_month' => $collectedThisMonth,
                'collected_last_month' => $collectedLastMonth,
                'collected_mom_pct' => $collectedMomPct,
                'outstanding_balance' => $outstandingBalance,
                'open_receivable_count' => $openReceivableCount,
                'overdue_amount' => $overdueAmount,
                'overdue_invoice_count' => $overdueInvoiceCount,
            ],
            'context' => [
                'partial_invoice_count' => $partialInvoiceCount,
                'partial_outstanding' => $partialOutstanding,
                'largest_payment_last_30' => $largestPaymentLast30,
            ],
            'period' => [
                'label' => $periodLabel,
                'key' => $periodKey,
                'collected' => $periodCollected,
                'payment_count' => $periodPaymentCount,
                'completed_count' => $periodCompletedCount,
                'avg_payment' => $periodAvgPayment,
                'largest_payment' => $periodLargestPayment,
                'top_method' => $topPaymentMethod,
            ],
            'filters' => [
                'period' => $requestedPeriod,
                'year' => (int) $request->query('year', $now->year),
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to'),
            ],
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon, key: string, label: string}
     */
    private function paymentQuarterPeriod(string $quarter, int $year, string $tz): array
    {
        $y = max(2000, min(2100, $year));
        $startMonth = match ($quarter) {
            'q1' => 1,
            'q2' => 4,
            'q3' => 7,
            'q4' => 10,
            default => 1,
        };
        $start = Carbon::createFromDate($y, $startMonth, 1, $tz)->startOfDay();
        $end = $start->copy()->addMonths(3)->subDay()->endOfDay();

        return [
            'start' => $start,
            'end' => $end,
            'key' => $quarter,
            'label' => strtoupper($quarter).' '.$y,
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon, key: string, label: string}|null
     */
    private function paymentCustomPeriod(Request $request, string $tz): ?array
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
