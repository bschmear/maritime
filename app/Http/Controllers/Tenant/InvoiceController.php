<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Invoice\Actions\ApplyManualInvoicePayment;
use App\Domain\Invoice\Actions\BuildInvoicePrefillFromTransaction;
use App\Domain\Invoice\Actions\BuildInvoicePrefillFromWorkOrder;
use App\Domain\Invoice\Actions\CreateInvoice as CreateAction;
use App\Domain\Invoice\Actions\DeleteInvoice as DeleteAction;
use App\Domain\Invoice\Actions\UpdateInvoice as UpdateAction;
use App\Domain\Invoice\Models\Invoice as RecordModel;
use App\Domain\Payment\Models\PaymentConfiguration;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\WarrantyClaim\Support\InvoiceManufacturerWarrantyCloseEligibility;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Mail\InvoiceViewRequest;
use App\Models\AccountSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class InvoiceController extends RecordController
{
    protected $recordType = 'Invoice';

    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'invoices',
            'Invoice',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            $this->recordType // Domain name for schema lookup
        );
    }

    public function index(Request $request)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();
        $schema = $this->getTableSchema();
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        $this->domainName = 'Invoice';
        $this->recordModel = new RecordModel;

        $query = RecordModel::query()->with($relationships);
        $activeFilters = $this->resolveFiltersFromRequest($request);

        if ($activeFilters !== []) {
            $query = $this->applyFilters($query, $activeFilters, $fieldsSchema);
        }

        $table = (new RecordModel)->getTable();
        $sortKey = $request->get('sort');
        $sortDir = strtolower((string) $request->get('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortableKeys = collect($schema['columns'] ?? [])
            ->filter(fn ($c) => ($c['sortable'] ?? true) !== false)
            ->pluck('key')
            ->all();
        $dbColumns = \Schema::connection((new RecordModel)->getConnectionName())->getColumnListing($table);

        if ($sortKey && in_array($sortKey, $sortableKeys, true) && in_array($sortKey, $dbColumns, true)) {
            $query->orderBy($table.'.'.$sortKey, $sortDir);
        } else {
            $query->orderBy($table.'.created_at', 'desc');
        }

        $perPage = (int) $request->get('per_page', 15);
        $records = $query->paginate($perPage)->withQueryString();
        $stats = $this->indexTableStats($request, clone $query, $schema);

        return inertia('Tenant/Invoice/Index', [
            'records' => $records,
            'schema' => $schema,
            'formSchema' => $this->getFormSchema(),
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'stats' => $stats,
        ]);
    }

    private function resolveFiltersFromRequest(Request $request): array
    {
        $filtersParam = $request->get('filters');
        if ($filtersParam === null || $filtersParam === '') {
            return [];
        }

        try {
            $decoded = json_decode(urldecode((string) $filtersParam), true) ?? [];
        } catch (\Throwable) {
            $decoded = [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    protected function appendShowRelationships(array &$relationships): void
    {
        foreach (RecordModel::documentEagerLoads() as $key => $callback) {
            $relationships[$key] = $callback;
        }

        $relationships['payments'] = fn ($q) => $q
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->with([
                'recordedBy' => fn ($rq) => $rq->select(['id', 'display_name']),
                // `display_name` on Invoice is an accessor (INV-{sequence}); load columns it needs.
                'invoice' => fn ($iq) => $iq->select(['id', 'sequence']),
            ]);
    }

    public function applyManualPayment(Request $request, $invoice): RedirectResponse
    {
        $id = $invoice instanceof RecordModel ? $invoice->getKey() : (int) $invoice;
        $model = RecordModel::query()->findOrFail($id);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method_code' => ['required', 'string', 'in:check,cash,wire,ach'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'memo' => ['nullable', 'string', 'max:2000'],
        ]);

        (new ApplyManualInvoicePayment)($model, $validated, Auth::id());

        return redirect()
            ->route('invoices.show', $model)
            ->with('success', 'Payment recorded.');
    }

    protected function enabledPaymentMethodsForInertia(): array
    {
        return PaymentConfiguration::enabledStripeMethodOptionsForCurrentAccount();
    }

    protected function editPageExtraProps($record): array
    {
        return array_merge(
            [
                'enabledPaymentMethods' => $this->enabledPaymentMethodsForInertia(),
            ],
            $this->warrantyCloseProps($record)
        );
    }

    protected function showPageExtraProps($record): array
    {
        return array_merge(
            [
                'enabledPaymentMethods' => $this->enabledPaymentMethodsForInertia(),
            ],
            $this->warrantyCloseProps($record)
        );
    }

    /**
     * @param  \App\Domain\Invoice\Models\Invoice  $record
     * @return array{manufacturerWarrantyCloseBlocked: bool, manufacturerWarrantyCloseMessage: ?string}
     */
    private function warrantyCloseProps($record): array
    {
        $reason = app(InvoiceManufacturerWarrantyCloseEligibility::class)->reasonIfBlocked($record);
        $isClosed = in_array($record->status, ['paid', 'void'], true);

        return [
            'manufacturerWarrantyCloseBlocked' => $reason !== null && ! $isClosed,
            'manufacturerWarrantyCloseMessage' => $isClosed ? null : $reason,
        ];
    }

    protected function inertiaUpdateSuccessRedirect(Request $request, int|string $id): RedirectResponse
    {
        return redirect()
            ->route('invoices.show', $id)
            ->with('success', 'Invoice updated successfully.');
    }

    /**
     * {@inheritdoc}
     *
     * Stat keys are declared in {@see RecordModel} table schema {@code stats} (see table.json).
     *
     * Supported fields per stat:
     * - aggregate: "count" (default) or "sum"
     * - column: DB column to sum when aggregate is sum (alias: sum_column)
     * - scope: filter preset; defaults to {@code key} if omitted (draft, pending, outstanding, overdue, paid, paid_mtd, void)
     */
    protected function indexTableStats(Request $request, $query, ?array $schema): array
    {
        if (! is_array($schema) || empty($schema['stats']) || ! is_array($schema['stats'])) {
            return [];
        }

        $sumColumns = ['total', 'amount_due', 'amount_paid', 'subtotal', 'tax_total', 'fees_total', 'discount_total'];

        $out = [];
        foreach ($schema['stats'] as $def) {
            $key = $def['key'] ?? null;
            if (! is_string($key) || $key === '') {
                continue;
            }

            $scope = $def['scope'] ?? $key;
            if (! is_string($scope) || $scope === '') {
                $scope = $key;
            }

            $q = clone $query;
            $this->applyInvoiceStatScope($q, $scope);

            $aggregate = strtolower((string) ($def['aggregate'] ?? 'count'));
            if ($aggregate === 'sum') {
                $column = (string) ($def['column'] ?? $def['sum_column'] ?? 'total');
                if (! in_array($column, $sumColumns, true)) {
                    $out[$key] = 0.0;

                    continue;
                }
                $out[$key] = round((float) $q->sum($column), 2);
            } else {
                $out[$key] = $q->count();
            }
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Domain\Invoice\Models\Invoice>  $query
     */
    private function applyInvoiceStatScope($query, string $scope): void
    {
        match ($scope) {
            'draft' => $query->where('status', 'draft'),
            'pending', 'outstanding' => $query->whereIn('status', ['sent', 'viewed', 'partial']),
            'overdue' => $query->overdue(),
            'paid' => $query->where('status', 'paid'),
            'paid_mtd' => $query->where('status', 'paid')
                ->whereRaw('COALESCE(paid_at, updated_at) >= ?', [now()->startOfMonth()->startOfDay()]),
            'void' => $query->where('status', 'void'),
            default => $query->whereRaw('0 = 1'),
        };
    }

    public function show(Request $request, $id)
    {
        return parent::show($request, $id);
    }

    public function prefillFromTransaction($transaction)
    {
        $id = $transaction instanceof Transaction ? $transaction->getKey() : (int) $transaction;
        $model = Transaction::query()->findOrFail($id);

        return response()->json((new BuildInvoicePrefillFromTransaction)($model));
    }

    public function create()
    {
        $req = request();
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();
        $account = \App\Models\AccountSettings::getCurrent();

        $initialData = [];
        $transactionData = null;
        $workOrderData = null;

        if ($transactionId = $req->query('transaction_id')) {
            $transaction = Transaction::query()->find((int) $transactionId);

            if ($transaction) {
                $initialData = array_merge($initialData, (new BuildInvoicePrefillFromTransaction)($transaction));

                if ($req->query('contact_id')) {
                    $initialData['contact_id'] = (int) $req->query('contact_id');
                }

                $transaction->loadMissing(['subsidiary', 'location', 'contract']);

                if ($transaction->contract) {
                    $initialData['contract'] = [
                        'id' => $transaction->contract->id,
                        'display_name' => $transaction->contract->display_name,
                    ];
                }

                $transactionData = [
                    'id' => $transaction->id,
                    'display_name' => $transaction->display_name,
                    'subsidiary' => $transaction->subsidiary
                        ? ['id' => $transaction->subsidiary->id, 'display_name' => $transaction->subsidiary->display_name]
                        : null,
                    'location' => $transaction->location
                        ? ['id' => $transaction->location->id, 'display_name' => $transaction->location->display_name]
                        : null,
                ];
            }
        } elseif ($workOrderId = $req->query('work_order_id')) {
            $workOrder = WorkOrder::query()->find((int) $workOrderId);
            if ($workOrder) {
                $initialData = array_merge($initialData, (new BuildInvoicePrefillFromWorkOrder)($workOrder));
                $workOrderData = [
                    'id' => $workOrder->id,
                    'work_order_number' => $workOrder->work_order_number,
                    'display_name' => $workOrder->display_name,
                ];
            }

            if (empty($initialData['contact_id'])) {
                return redirect()
                    ->route('workorders.show', (int) $workOrderId)
                    ->with('error', 'Work order customer is missing a bill-to contact. Add a customer contact before creating an invoice.');
            }
        } elseif ($cid = $req->query('contact_id')) {
            $initialData['contact_id'] = (int) $cid;
            $contactRow = Contact::query()
                ->select(['id', 'display_name', 'first_name', 'last_name', 'email', 'phone', 'mobile'])
                ->find((int) $cid);
            if ($contactRow) {
                $initialData['contact'] = [
                    'id' => $contactRow->id,
                    'display_name' => $contactRow->display_name,
                    'first_name' => $contactRow->first_name,
                    'last_name' => $contactRow->last_name,
                    'email' => $contactRow->email,
                    'phone' => $contactRow->phone,
                    'mobile' => $contactRow->mobile,
                ];
            }
        } elseif ($custId = $req->query('customer_id')) {
            $c = Customer::query()->select(['id', 'contact_id'])->find((int) $custId);
            if ($c?->contact_id) {
                $initialData['contact_id'] = $c->contact_id;
                $contactRow = Contact::query()
                    ->select(['id', 'display_name', 'first_name', 'last_name', 'email', 'phone', 'mobile'])
                    ->find($c->contact_id);
                if ($contactRow) {
                    $initialData['contact'] = [
                        'id' => $contactRow->id,
                        'display_name' => $contactRow->display_name,
                        'first_name' => $contactRow->first_name,
                        'last_name' => $contactRow->last_name,
                        'email' => $contactRow->email,
                        'phone' => $contactRow->phone,
                        'mobile' => $contactRow->mobile,
                    ];
                }
            }
        }

        return inertia('Tenant/Invoice/Create', [
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => \App\Enums\Timezone::options(),
            'initialData' => $initialData,
            'transaction' => $transactionData,
            'workOrder' => $workOrderData,
            'enabledPaymentMethods' => PaymentConfiguration::enabledStripeMethodOptionsForCurrentAccount(),
        ]);
    }

    /**
     * Show a specific user with role relationship loaded.
     */
    // public function edit()
    // {
    //     return inertia('Tenant/Invoice/Edit');
    // }

    // public function store(Request $request)
    // {

    // }

    // public function update(Request $request, int $contract)
    // {
    //     $settings = AccountSettings::getCurrent();
    //     $existing = Contract::query()
    //         ->where('account_settings_id', $settings->id)
    //         ->findOrFail($contract);

    //     if ($this->isLocked($existing)) {
    //         return redirect()->route('contracts.show', $contract)
    //             ->with('error', 'This contract cannot be updated because it has been signed.');
    //     }

    //     $result = (new UpdateContract)($contract, $request->all());

    //     if (! $result['success'] || empty($result['record'])) {
    //         return back()
    //             ->withErrors(['message' => $result['message'] ?? 'Could not update contract.'])
    //             ->withInput();
    //     }

    //     return redirect()->route('contracts.show', $contract);
    // }

    public function sendToCustomer(Request $request, int $invoice)
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email'],
        ]);

        $settings = AccountSettings::getCurrent();
        $record = RecordModel::query()
            ->with([
                'contact' => fn ($q) => $q->select(['id', 'email', 'display_name']),
                'transaction' => fn ($q) => $q->select(['id', 'subsidiary_id', 'location_id']),
                'transaction.subsidiary' => fn ($q) => $q->select(['id', 'display_name']),
                'transaction.location' => fn ($q) => $q->select([
                    'id',
                    'display_name',
                    'phone',
                    'email',
                    'address_line_1',
                    'city',
                    'state',
                    'postal_code',
                ]),
            ])
            ->findOrFail($invoice);

        $to = $validated['email'] ?? $record->customer_email ?? $record->contact?->email;
        if (! $to) {
            return back()->with('error', 'No customer email found for this invoice.');
        }

        $viewUrl = route('invoices.view', $record->uuid);
        $record->markAsSent();

        $record = RecordModel::query()
            ->whereKey($record->id)
            ->with([
                'contact' => fn ($q) => $q->select(['id', 'email', 'display_name']),
                'transaction' => fn ($q) => $q->select(['id', 'subsidiary_id', 'location_id']),
                'transaction.subsidiary' => fn ($q) => $q->select(['id', 'display_name']),
                'transaction.location' => fn ($q) => $q->select([
                    'id',
                    'display_name',
                    'phone',
                    'email',
                    'address_line_1',
                    'city',
                    'state',
                    'postal_code',
                ]),
            ])
            ->firstOrFail();

        Mail::to($to)->send(new InvoiceViewRequest($record, $settings, $viewUrl));

        return back()->with('success', 'Invoice link sent to '.$to);
    }

    // public function destroy(int $invoice)
    // {
    // $settings = AccountSettings::getCurrent();
    // Contract::query()
    //     ->where('account_settings_id', $settings->id)
    //     ->findOrFail($contract);

    // $result = (new DeleteContract)($contract);

    // if (! $result['success']) {
    //     return redirect()->route('contracts.index')
    //         ->with('error', $result['message'] ?? 'Could not delete contract.');
    // }

    // return redirect()->route('contracts.index')
    //     ->with('success', $result['message'] ?? 'Contract deleted.');
    // }

}
