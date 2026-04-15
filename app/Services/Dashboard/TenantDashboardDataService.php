<?php

namespace App\Services\Dashboard;

use App\Domain\Delivery\Models\Delivery;
use App\Domain\Estimate\Models\Estimate;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Lead\Models\Lead;
use App\Domain\Notification\Models\Notification;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Payment\Models\Payment;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\Task\Models\Task;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Enums\Estimate\EstimateStatus;
use App\Enums\Tasks\Priority as TaskPriority;
use App\Enums\Tasks\Status as TaskStatus;
use App\Tenancy\CurrentTenantProfile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Aggregates tenant dashboard sections (fixed layout Phase A).
 */
class TenantDashboardDataService
{
    /** @var list<string> */
    public const SECTION_KEYS = ['actionCenter', 'risk', 'revenue', 'operations', 'activity', 'meta'];

    public function __construct(
        private PaymentDashboardMetricsService $paymentMetrics,
        private CurrentTenantProfile $tenantProfile
    ) {}

    /**
     * @return array{
     *   actionCenter: array,
     *   risk: array,
     *   revenue: array,
     *   operations: array,
     *   activity: array,
     *   meta: array
     * }
     */
    public function build(Request $request): array
    {
        $cap = max(1, (int) config('dashboard.list_cap', 10));
        $stalledDays = max(1, (int) config('dashboard.stalled_opportunity_days', 14));
        $ticketStaleDays = max(1, (int) config('dashboard.service_ticket_stale_days', 7));
        $estimateExpiringDays = max(1, (int) config('dashboard.expiring_estimate_days', 14));

        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $weekEnd = $now->copy()->endOfWeek();
        $next7DaysEnd = $now->copy()->addDays(7)->endOfDay();

        $tenantUserId = $this->tenantProfile->profile()?->getKey();

        $paymentBaseQuery = Payment::query();
        $paymentDashboard = $this->paymentMetrics->build($request, $paymentBaseQuery);

        $tasks = Task::query()
            ->with([
                'assigned' => fn ($q) => $q->select(['id', 'display_name', 'first_name', 'last_name', 'email']),
            ])
            ->where('completed', false)
            ->whereNotNull('due_date')
            ->where('due_date', '<=', $todayEnd)
            ->orderBy('due_date')
            ->limit($cap)
            ->get([
                'id',
                'display_name',
                'due_date',
                'has_due_time',
                'due_time',
                'status_id',
                'priority_id',
                'assigned_id',
                'relatable_type',
                'relatable_id',
            ]);

        $followUps = Lead::query()
            ->where('converted', false)
            ->whereNotNull('next_followup_at')
            ->whereDate('next_followup_at', '<=', $now->toDateString())
            ->orderBy('next_followup_at')
            ->limit($cap)
            ->get(['id', 'contact_id', 'next_followup_at']);

        $deliveriesToday = Delivery::query()
            ->whereNull('delivered_at')
            ->whereNotIn('status', ['delivered', 'cancelled'])
            ->whereBetween('scheduled_at', [$todayStart, $todayEnd])
            ->orderBy('scheduled_at')
            ->limit($cap)
            ->get(['id', 'sequence', 'scheduled_at', 'customer_id']);

        $overdueInvoices = Invoice::query()
            ->open()
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->orderBy('due_at')
            ->limit($cap)
            ->get(['id', 'sequence', 'customer_name', 'amount_due', 'due_at', 'uuid']);

        $stalledOpportunities = Opportunity::query()
            ->whereNull('won_at')
            ->whereNull('lost_at')
            ->where('updated_at', '<', $now->copy()->subDays($stalledDays))
            ->orderByDesc('estimated_value')
            ->limit($cap)
            ->get(['id', 'sequence', 'estimated_value', 'updated_at', 'customer_id']);

        $expiringEstimates = Estimate::query()
            ->whereIn('status', [
                EstimateStatus::Draft->id(),
                EstimateStatus::PendingApproval->id(),
            ])
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '>=', $now->toDateString())
            ->whereDate('expiration_date', '<=', $now->copy()->addDays($estimateExpiringDays)->toDateString())
            ->orderBy('expiration_date')
            ->limit($cap)
            ->get(['id', 'sequence', 'customer_name', 'expiration_date', 'status']);

        $staleServiceTickets = ServiceTicket::query()
            ->whereNotIn('status', [6, 7, 8])
            ->where('updated_at', '<', $now->copy()->subDays($ticketStaleDays))
            ->orderBy('updated_at')
            ->limit($cap)
            ->get(['id', 'display_name', 'service_ticket_number', 'updated_at', 'customer_id']);

        $overdueWorkOrders = WorkOrder::query()
            ->whereNotIn('status', [7, 8])
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->orderBy('due_at')
            ->limit($cap)
            ->get(['id', 'display_name', 'due_at', 'customer_id']);

        $pipelineValue = (float) Opportunity::query()
            ->whereNull('won_at')
            ->whereNull('lost_at')
            ->sum('estimated_value');

        $recentPayments = Payment::query()
            ->with(['invoice' => fn ($q) => $q->select(['id', 'sequence', 'customer_name'])])
            ->whereIn('status', ['completed', 'partially_refunded'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'sequence', 'amount', 'paid_at', 'invoice_id', 'status']);

        $openServiceTicketCount = ServiceTicket::query()->whereIn('status', [1, 2, 3])->count();
        $openWorkOrderCount = WorkOrder::query()->whereNotIn('status', [7, 8])->count();

        // Next 7 days forward from today, plus any overdue (scheduled before today) that are still not delivered.
        // Plain whereBetween(today, today+7) excludes past dates — e.g. Apr 17 is hidden once "today" is Apr 18+.
        $deliveriesThisWeek = Delivery::query()
            ->whereNull('delivered_at')
            ->whereNotIn('status', ['delivered', 'cancelled'])
            ->where(function ($q) use ($todayStart, $next7DaysEnd) {
                $q->whereBetween('scheduled_at', [$todayStart, $next7DaysEnd])
                    ->orWhere('scheduled_at', '<', $todayStart);
            })
            ->orderByRaw('CASE WHEN scheduled_at < ? THEN 0 ELSE 1 END', [$todayStart])
            ->orderBy('scheduled_at')
            ->limit($cap)
            ->get(['id', 'sequence', 'scheduled_at']);

        $notifications = $tenantUserId
            ? Notification::query()
                ->where('assigned_to_user_id', $tenantUserId)
                ->whereNull('read_at')
                ->latest()
                ->limit($cap)
                ->get(['id', 'title', 'message', 'type', 'created_at', 'route'])
            : collect();

        $activityItems = $this->mergeActivityFeed($notifications, $recentPayments, $cap);

        return [
            'actionCenter' => [
                'tasks' => $tasks->map(function (Task $t) {
                    $status = $this->resolveTaskStatus($t->status_id);
                    $priority = $this->resolveTaskPriority($t->priority_id);

                    return [
                        'id' => $t->id,
                        'label' => $t->display_name,
                        'due_at' => $t->due_date?->toIso8601String(),
                        'due_time' => $this->formatTaskDueTime($t->has_due_time, $t->due_time),
                        'status_id' => $t->status_id,
                        'status' => $status['label'],
                        'status_color' => $status['color'],
                        'priority_id' => $t->priority_id,
                        'priority' => $priority['label'],
                        'priority_color' => $priority['color'],
                        'assigned' => $this->taskAssignedPayload($t),
                        'href' => $this->safeRoute('tasks.show', ['task' => $t->id]),
                    ];
                })->values()->all(),
                'taskEnums' => [
                    'status' => TaskStatus::options(),
                    'priority' => TaskPriority::options(),
                ],
                'followUps' => $followUps->map(fn (Lead $l) => [
                    'id' => $l->id,
                    'label' => $l->display_name,
                    'due_at' => $l->next_followup_at?->format('Y-m-d'),
                    'href' => $this->safeRoute('leads.show', ['lead' => $l->id]),
                ])->values()->all(),
                'deliveriesToday' => $deliveriesToday->map(fn (Delivery $d) => [
                    'id' => $d->id,
                    'label' => $d->display_name,
                    'scheduled_at' => $d->scheduled_at?->toIso8601String(),
                    'href' => $this->safeRoute('deliveries.show', ['delivery' => $d->id]),
                ])->values()->all(),
            ],
            'risk' => [
                'overdueInvoices' => $overdueInvoices->map(fn (Invoice $i) => [
                    'id' => $i->id,
                    'label' => $i->display_name,
                    'subtitle' => $i->customer_name,
                    'amount_due' => (float) $i->amount_due,
                    'due_at' => $i->due_at?->toIso8601String(),
                    'href' => $this->safeRoute('invoices.show', ['invoice' => $i->id]),
                ])->values()->all(),
                'stalledOpportunities' => $stalledOpportunities->map(fn (Opportunity $o) => [
                    'id' => $o->id,
                    'label' => $o->display_name,
                    'subtitle' => 'No activity in '.$stalledDays.'+ days',
                    'estimated_value' => $o->estimated_value !== null ? (float) $o->estimated_value : null,
                    'updated_at' => $o->updated_at?->toIso8601String(),
                    'href' => $this->safeRoute('opportunities.show', ['opportunity' => $o->id]),
                ])->values()->all(),
                'expiringEstimates' => $expiringEstimates->map(fn (Estimate $e) => [
                    'id' => $e->id,
                    'label' => $e->display_name,
                    'subtitle' => $e->customer_name,
                    'expiration_date' => $e->expiration_date?->format('Y-m-d'),
                    'href' => $this->safeRoute('estimates.show', ['estimate' => $e->id]),
                ])->values()->all(),
                'staleServiceTickets' => $staleServiceTickets->map(fn (ServiceTicket $s) => [
                    'id' => $s->id,
                    'label' => $s->display_name ?: $s->service_ticket_number,
                    'updated_at' => $s->updated_at?->toIso8601String(),
                    'href' => $this->safeRoute('servicetickets.show', ['serviceticket' => $s->id]),
                ])->values()->all(),
                'overdueWorkOrders' => $overdueWorkOrders->map(fn (WorkOrder $w) => [
                    'id' => $w->id,
                    'label' => $w->display_name,
                    'due_at' => $w->due_at?->toIso8601String(),
                    'href' => $this->safeRoute('workorders.show', ['workorder' => $w->id]),
                ])->values()->all(),
                'counts' => [
                    'overdue_invoices' => Invoice::query()->open()->whereNotNull('due_at')->where('due_at', '<', $now)->count(),
                    'stalled_opportunities' => Opportunity::query()
                        ->whereNull('won_at')
                        ->whereNull('lost_at')
                        ->where('updated_at', '<', $now->copy()->subDays($stalledDays))
                        ->count(),
                ],
            ],
            'revenue' => [
                'paymentDashboard' => $paymentDashboard,
                'pipeline_value' => $pipelineValue,
                'recentPayments' => $recentPayments->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'label' => 'Payment #'.$p->sequence,
                    'amount' => (float) $p->amount,
                    'paid_at' => $p->paid_at?->toIso8601String(),
                    'customer' => $p->invoice?->customer_name,
                    'href' => $this->safeRoute('payments.show', ['payment' => $p->id]),
                ])->values()->all(),
            ],
            'operations' => [
                'open_service_ticket_count' => $openServiceTicketCount,
                'open_work_order_count' => $openWorkOrderCount,
                'deliveriesThisWeek' => $deliveriesThisWeek->map(fn (Delivery $d) => [
                    'id' => $d->id,
                    'label' => $d->display_name,
                    'scheduled_at' => $d->scheduled_at?->toIso8601String(),
                    'href' => $this->safeRoute('deliveries.show', ['delivery' => $d->id]),
                ])->values()->all(),
                'links' => [
                    'service_tickets' => $this->safeRoute('servicetickets.index'),
                    'work_orders' => $this->safeRoute('workorders.index'),
                    'deliveries' => $this->safeRoute('deliveries.index'),
                ],
            ],
            'activity' => [
                'items' => $activityItems,
                'links' => [
                    'notifications' => $this->safeRoute('notifications.index'),
                    'payments' => $this->safeRoute('payments.index'),
                ],
            ],
            'meta' => [
                'generated_at' => $now->toIso8601String(),
                'stalled_opportunity_days' => $stalledDays,
                'service_ticket_stale_days' => $ticketStaleDays,
                'expiring_estimate_days' => $estimateExpiringDays,
                'list_cap' => $cap,
            ],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Notification>  $notifications
     * @param  \Illuminate\Support\Collection<int, Payment>  $recentPayments
     * @return list<array{type: string, id: int, title: string, subtitle: ?string, at: ?string, href: ?string}>
     */
    private function mergeActivityFeed($notifications, $recentPayments, int $cap): array
    {
        $rows = [];

        foreach ($notifications as $n) {
            $rows[] = [
                'type' => 'notification',
                'id' => $n->id,
                'title' => $n->title,
                'subtitle' => $n->message,
                'at' => $n->created_at?->toIso8601String(),
                'href' => $this->safeRoute('notifications.redirect', ['id' => $n->id]),
            ];
        }

        foreach ($recentPayments as $p) {
            $rows[] = [
                'type' => 'payment',
                'id' => $p->id,
                'title' => 'Payment received',
                'subtitle' => trim(($p->invoice?->customer_name ?: 'Payment').' · $'.number_format((float) $p->amount, 2)),
                'at' => $p->paid_at?->toIso8601String() ?? $p->created_at?->toIso8601String(),
                'href' => $this->safeRoute('payments.show', ['payment' => $p->id]),
            ];
        }

        usort($rows, function (array $a, array $b) {
            return strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? ''));
        });

        return array_slice($rows, 0, $cap);
    }

    /**
     * @return array{label: ?string, color: string}
     */
    private function resolveTaskStatus(?int $id): array
    {
        if ($id === null) {
            return ['label' => null, 'color' => 'gray'];
        }

        foreach (TaskStatus::cases() as $case) {
            if ($case->id() === $id) {
                return [
                    'label' => $case->label(),
                    'color' => $case->color(),
                ];
            }
        }

        return ['label' => null, 'color' => 'gray'];
    }

    /**
     * @return array{label: ?string, color: string}
     */
    private function resolveTaskPriority(?int $id): array
    {
        if ($id === null) {
            return ['label' => null, 'color' => 'gray'];
        }

        foreach (TaskPriority::cases() as $case) {
            if ($case->id() === $id) {
                return [
                    'label' => $case->label(),
                    'color' => $case->color(),
                ];
            }
        }

        return ['label' => null, 'color' => 'gray'];
    }

    /**
     * Same shape as Task resource JSON for {@see TaskListView} (assigned.display_name).
     *
     * @return array{id: int, display_name: string}|null
     */
    private function taskAssignedPayload(Task $t): ?array
    {
        $u = $t->relationLoaded('assigned') ? $t->assigned : null;
        if ($u === null) {
            return null;
        }

        $display = trim((string) ($u->display_name ?? ''));
        if ($display === '') {
            $display = trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
        }
        if ($display === '') {
            $display = trim((string) ($u->email ?? ''));
        }
        if ($display === '') {
            return null;
        }

        return [
            'id' => (int) $u->id,
            'display_name' => $display,
        ];
    }

    /**
     * Human-readable due time when the task has a specific time set.
     */
    private function formatTaskDueTime(?bool $hasDueTime, mixed $dueTime): ?string
    {
        if (! $hasDueTime || $dueTime === null || $dueTime === '') {
            return null;
        }

        try {
            return Carbon::parse($dueTime)->format('g:i A');
        } catch (\Throwable) {
            return is_string($dueTime) ? $dueTime : null;
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function safeRoute(string $name, array $parameters = []): ?string
    {
        if (! Route::has($name)) {
            return null;
        }

        try {
            return route($name, $parameters, false);
        } catch (\Throwable) {
            return null;
        }
    }
}
