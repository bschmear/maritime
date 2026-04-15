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
use App\Services\Dashboard\PaymentDashboardMetricsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    public function __construct(
        protected PaymentDashboardMetricsService $paymentDashboardMetrics
    ) {
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

        $periodBounds = $this->paymentDashboardMetrics->resolvePaymentListPeriod($request);
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

        $paymentDashboard = $this->paymentDashboardMetrics->build($request, $dashboardQuery);

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
