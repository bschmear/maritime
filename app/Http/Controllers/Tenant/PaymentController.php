<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\Payment\Actions\StoreRecordedPayment;
use App\Domain\Payment\Actions\UpdateRecordedPayment;
use App\Domain\Payment\Models\Payment;
use App\Enums\Invoice\Status as InvoiceStatus;
use App\Enums\Payments\PaymentMethod;
use App\Enums\Payments\PaymentProcessor;
use App\Http\Controllers\Concerns\HasSchemaSupport;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    use AuthorizesRequests, HasSchemaSupport, ValidatesRequests;

    protected string $domainName = 'Payment';

    /** @var Payment Used by {@see HasSchemaSupport} helpers that expect a model instance. */
    protected Payment $recordModel;

    public function __construct()
    {
        $this->middleware(['auth', 'tenant.access']);
        $this->recordModel = new Payment;
    }

    public function index(Request $request): JsonResponse|\Inertia\Response
    {
        $schema = $this->getTableSchema() ?? [];
        $formSchema = $this->getFormSchema();
        $fieldsSchemaRaw = $this->getFieldsSchema();
        $fieldsSchema = isset($fieldsSchemaRaw['fields']) && is_array($fieldsSchemaRaw['fields'])
            ? $fieldsSchemaRaw['fields']
            : (array) $fieldsSchemaRaw;
        $enumOptions = $this->getEnumOptions();

        $query = Payment::query()->with([
            'invoice' => fn ($q) => $q->select(['id', 'sequence', 'uuid', 'customer_name']),
            'recorded_by' => fn ($q) => $q->select(['id', 'display_name']),
        ]);

        $periodBounds = $this->resolvePaymentListPeriod($request);
        if ($periodBounds !== null) {
            $query->whereRaw(
                'COALESCE(payments.paid_at, payments.created_at) BETWEEN ? AND ?',
                [$periodBounds['start']->toDateTimeString(), $periodBounds['end']->toDateTimeString()]
            );
        }

        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            $term = '%'.strtolower($search).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(CAST(payments.id AS TEXT)) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(CAST(payments.sequence AS TEXT)) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(payments.reference_number, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(payments.payment_method_code, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(payments.processor_transaction_id, \'\')) LIKE ?', [$term])
                    ->orWhereHas('invoice', function ($iq) use ($term) {
                        $iq->whereRaw('LOWER(CAST(invoices.sequence AS TEXT)) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(COALESCE(invoices.customer_name, \'\')) LIKE ?', [$term]);
                    });
            });
        }

        $filtersParam = $request->get('filters');
        if ($filtersParam) {
            try {
                $filters = json_decode(urldecode((string) $filtersParam), true);
                if (is_array($filters)) {
                    $query = $this->applyFilters($query, $filters, $fieldsSchema);
                }
            } catch (\Throwable) {
            }
        }

        $allowedSort = ['id', 'sequence', 'paid_at', 'amount', 'status', 'payment_method_code', 'processor', 'created_at'];
        $sortKey = $request->get('sort');
        $sortDir = strtolower((string) $request->get('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (is_string($sortKey) && in_array($sortKey, $allowedSort, true)) {
            $query->orderBy($sortKey, $sortDir);
        } else {
            $query->orderByDesc('paid_at')->orderByDesc('id');
        }

        $perPage = min(100, max(1, (int) $request->get('per_page', 15)));
        $dashboardQuery = clone $query;
        /** @var LengthAwarePaginator<int, Payment> $records */
        $records = $query->paginate($perPage)->withQueryString();

        $paymentDashboard = $this->buildPaymentDashboard($request, $dashboardQuery);

        if ($request->ajax() && ! $request->header('X-Inertia')) {
            return response()->json([
                'records' => $records->items(),
                'schema' => $schema,
                'fieldsSchema' => $fieldsSchema,
                'paymentDashboard' => $paymentDashboard,
                'meta' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ],
            ]);
        }

        return inertia('Tenant/Payment/Index', [
            'records' => $records,
            'recordType' => 'payments',
            'recordTitle' => 'Payment',
            'pluralTitle' => 'Payments',
            'schema' => $schema,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'paymentDashboard' => $paymentDashboard,
        ]);
    }

    /**
     * Date range applied to the payment list (paid_at, else created_at).
     *
     * @return array{start: Carbon, end: Carbon, key: string, label: string}|null
     */
    protected function resolvePaymentListPeriod(Request $request): ?array
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
     * @return array{start: Carbon, end: Carbon, key: string, label: string}
     */
    protected function paymentQuarterPeriod(string $quarter, int $year, string $tz): array
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
    protected function paymentCustomPeriod(Request $request, string $tz): ?array
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

    /**
     * @param  Builder<Payment>  $filteredPaymentQuery  Query after period, search, and filters (not paginated).
     * @return array<string, mixed>
     */
    protected function buildPaymentDashboard(Request $request, Builder $filteredPaymentQuery): array
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

    public function show(Request $request, Payment $payment): JsonResponse|\Inertia\Response
    {
        $payment->load([
            'invoice' => fn ($q) => $q->select([
                'id', 'sequence', 'uuid', 'status', 'total', 'amount_due', 'customer_name', 'contact_id',
            ]),
            'recorded_by' => fn ($q) => $q->select(['id', 'display_name', 'email']),
        ]);

        if ($request->ajax() && ! $request->header('X-Inertia')) {
            return response()->json([
                'record' => $payment,
                'recordType' => 'payments',
                'recordTitle' => 'Payment',
                'domainName' => 'Payment',
            ]);
        }

        return inertia('Tenant/Payment/Show', [
            'payment' => $payment,
        ]);
    }

    public function create(Request $request): \Inertia\Response
    {
        $fieldsSchemaRaw = $this->getFieldsSchema();
        $fieldsSchema = isset($fieldsSchemaRaw['fields']) && is_array($fieldsSchemaRaw['fields'])
            ? $fieldsSchemaRaw['fields']
            : (array) $fieldsSchemaRaw;

        $prefillInvoice = null;
        if ($request->filled('invoice_id')) {
            $inv = Invoice::query()
                ->open()
                ->whereKey((int) $request->query('invoice_id'))
                ->select(['id', 'sequence', 'customer_name', 'status', 'total', 'amount_paid', 'amount_due', 'currency', 'due_at'])
                ->first();
            if ($inv !== null) {
                $prefillInvoice = $this->invoiceSummaryForPaymentCreate($inv);
            }
        }

        return inertia('Tenant/Payment/Create', [
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $this->getEnumOptions(),
            'invoiceEnumOptions' => InvoiceStatus::options(),
            'prefillInvoice' => $prefillInvoice,
        ]);
    }

    /**
     * Paginated invoices eligible for logging a payment (sent, viewed, partial).
     */
    public function eligibleInvoicesForCreate(Request $request): JsonResponse
    {
        $search = trim((string) $request->get('search', ''));

        $query = Invoice::query()
            ->open()
            ->select(['id', 'sequence', 'customer_name', 'status', 'total', 'amount_paid', 'amount_due', 'currency', 'due_at', 'created_at']);

        if ($search !== '') {
            $term = '%'.strtolower($search).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(CAST(invoices.sequence AS TEXT)) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(invoices.customer_name, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(CAST(invoices.id AS TEXT)) LIKE ?', [$term]);
            });
        }

        $query->orderByDesc('invoices.created_at');

        $perPage = min(50, max(5, (int) $request->get('per_page', 12)));
        $paginator = $query->paginate($perPage);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Invoice $inv) => $this->invoiceSummaryForPaymentCreate($inv))->values()
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function invoiceSummaryForPaymentCreate(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'display_name' => $invoice->display_name,
            'sequence' => $invoice->sequence,
            'customer_name' => $invoice->customer_name,
            'status' => $invoice->status,
            'total' => (string) $invoice->total,
            'amount_paid' => (string) $invoice->amount_paid,
            'amount_due' => (string) $invoice->amount_due,
            'currency' => $invoice->currency ?? 'USD',
            'due_at' => $invoice->due_at?->toIso8601String(),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method_code' => ['required', 'string', Rule::in(PaymentMethod::codes())],
            'processor' => ['required', 'string', Rule::in(PaymentProcessor::codes())],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'memo' => ['nullable', 'string', 'max:2000'],
            'paid_at' => ['nullable', 'date'],
        ]);
        $validated['apply_to_invoice'] = $request->boolean('apply_to_invoice', true);

        $payment = (new StoreRecordedPayment)($validated, Auth::id());

        return redirect()
            ->route('payments.show', $payment)
            ->with('success', 'Payment recorded.');
    }

    public function edit(Payment $payment): \Inertia\Response
    {
        $payment->load([
            'invoice' => fn ($q) => $q->select(['id', 'sequence', 'uuid', 'customer_name']),
        ]);

        $fieldsSchemaRaw = $this->getFieldsSchema();
        $fieldsSchema = isset($fieldsSchemaRaw['fields']) && is_array($fieldsSchemaRaw['fields'])
            ? $fieldsSchemaRaw['fields']
            : (array) $fieldsSchemaRaw;

        return inertia('Tenant/Payment/Edit', [
            'payment' => $payment,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $this->getEnumOptions(),
        ]);
    }

    public function update(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method_code' => ['required', 'string', Rule::in(PaymentMethod::codes())],
            'processor' => ['required', 'string', Rule::in(PaymentProcessor::codes())],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'memo' => ['nullable', 'string', 'max:2000'],
            'paid_at' => ['nullable', 'date'],
        ]);

        (new UpdateRecordedPayment)($payment, $validated);

        return redirect()
            ->route('payments.show', $payment)
            ->with('success', 'Payment updated.');
    }
}
