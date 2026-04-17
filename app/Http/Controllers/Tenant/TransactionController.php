<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Customer\Models\Customer;
use App\Domain\Estimate\Models\Estimate;
use App\Domain\Transaction\Actions\CreateTransaction;
use App\Domain\Transaction\Actions\DeleteTransaction;
use App\Domain\Transaction\Actions\UpdateTransaction;
use App\Domain\Transaction\Models\Transaction;
use App\Enums\Timezone;
use App\Enums\Transaction\TransactionStatus;
use App\Http\Controllers\Concerns\HasSchemaSupport;
use App\Models\AccountSettings;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TransactionController extends BaseController
{
    use AuthorizesRequests, HasSchemaSupport, ValidatesRequests;

    protected string $domainName = 'Transaction';

    protected Transaction $recordModel;

    public function __construct(
        protected CreateTransaction $createTransaction,
        protected UpdateTransaction $updateTransaction,
        protected DeleteTransaction $deleteTransaction,
    ) {
        $this->middleware('auth');
        $this->recordModel = new Transaction;
    }

    protected function getUnwrappedFieldsSchema(): array
    {
        $raw = $this->getFieldsSchema();
        if (! $raw) {
            return [];
        }

        return isset($raw['fields']) ? $raw['fields'] : $raw;
    }

    /**
     * Estimate has no display_name column — load id + sequence so the accessor works.
     */
    protected function getEnumOptions(): array
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = [];

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            $fieldType = $fieldDef['type'] ?? 'text';

            if (! empty($fieldDef['enum'])) {
                $enumClass = $fieldDef['enum'];
                if (class_exists($enumClass) && method_exists($enumClass, 'options')) {
                    $enumOptions[$enumClass] = $enumClass::options();
                }
            }

            if ($fieldType === 'record' && isset($fieldDef['typeDomain'])) {
                $domainName = $fieldDef['typeDomain'];

                if ($domainName === 'Estimate') {
                    try {
                        $records = Estimate::query()->select(['id', 'sequence'])->orderBy('sequence', 'desc')->limit(500)->get();
                        $enumOptions[$fieldKey] = $records->map(fn ($r) => [
                            'id' => $r->id,
                            'name' => $r->display_name,
                            'value' => $r->id,
                        ])->toArray();
                    } catch (\Throwable $e) {
                        \Log::warning('Failed to load estimate options: '.$e->getMessage());
                        $enumOptions[$fieldKey] = [];
                    }

                    continue;
                }

                $modelClass = "App\\Domain\\{$domainName}\\Models\\{$domainName}";
                if (class_exists($modelClass)) {
                    try {
                        $records = $domainName === 'Customer'
                            ? Customer::queryOrderedByContactDisplayName()->limit(500)->get()
                            : $modelClass::query()->select(['id', 'display_name'])->orderBy('display_name')->limit(500)->get();
                        $enumOptions[$fieldKey] = $records->map(fn ($r) => [
                            'id' => $r->id,
                            'name' => $r->display_name,
                            'value' => $r->id,
                        ])->toArray();
                    } catch (\Throwable $e) {
                        \Log::warning("Failed to load record options for {$domainName}: ".$e->getMessage());
                        $enumOptions[$fieldKey] = [];
                    }
                }
            }
        }

        return $enumOptions;
    }

    public function index(Request $request)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $schema = $this->getTableSchema() ?? ['columns' => []];
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();

        $columns = array_map(
            fn ($column) => is_array($column) ? ($column['key'] ?? $column) : $column,
            $schema['columns'] ?? []
        );

        $dbColumns = Schema::connection($this->recordModel->getConnectionName())
            ->getColumnListing($this->recordModel->getTable());

        $actualColumns = [];
        foreach ($columns as $column) {
            if (str_contains((string) $column, '.')) {
                continue;
            }
            if (in_array($column, $dbColumns, true)) {
                $actualColumns[] = $column;
            }
        }
        if (! in_array('id', $actualColumns, true)) {
            $actualColumns[] = 'id';

        }
        $actualColumns[] = 'sequence';

        $query = Transaction::query()
            ->select($actualColumns)
            ->with([
                'customer' => Customer::eagerWithContactSelect(),
                'user' => fn ($q) => $q->select(['id', 'display_name']),
                'estimate' => fn ($q) => $q->select(['id', 'sequence']),
                'opportunity' => fn ($q) => $q->select(['id', 'display_name']),
            ]);

        $searchQuery = $request->get('search');
        if ($searchQuery && trim($searchQuery) !== '') {
            $term = '%'.strtolower(trim($searchQuery)).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(COALESCE(title, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('CAST(sequence AS TEXT) LIKE ?', [$term])
                    ->orWhereRaw('CAST(id AS TEXT) LIKE ?', [$term]);
            });
        }

        $filtersParam = $request->get('filters');
        if ($filtersParam) {
            try {
                $filters = json_decode(urldecode($filtersParam), true);
                if (is_array($filters)) {
                    $query = $this->applyFilters($query, $filters, $fieldsSchema);
                }
            } catch (\Throwable) {
            }
        }

        $query->orderBy('created_at', 'desc');
        $perPage = (int) $request->get('per_page', 15);
        $records = $query->paginate($perPage);

        return inertia('Tenant/Transaction/Index', [
            'records' => $records,
            'recordType' => 'transactions',
            'recordTitle' => 'Transaction',
            'pluralTitle' => 'Transactions',
            'schema' => $schema,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
        ]);
    }

    public function create(Request $request)
    {
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();
        $account = AccountSettings::getCurrent();

        $user = $request->user();
        $initialData = [
            'user_id' => $user->id,
            'user' => [
                'id' => $user->id,
                'display_name' => $user->display_name ?? $user->name ?? '',
            ],
            'status' => TransactionStatus::Active->id(),
        ];

        $estimateId = $request->query('estimate_id');

        if ($estimateId) {
            $estimate = Estimate::query()
                ->with(['primaryVersion.lineItems.addons', 'customer', 'user', 'opportunity'])
                ->find($estimateId);

            if ($estimate) {
                $pv = $estimate->primaryVersion;
                $initialData['estimate_id'] = $estimate->id;
                $initialData['estimate'] = [
                    'id' => $estimate->id,
                    'display_name' => $estimate->display_name,
                ];
                $initialData['customer_id'] = $estimate->customer_id;
                if ($estimate->customer) {
                    $initialData['customer'] = [
                        'id' => $estimate->customer->id,
                        'display_name' => $estimate->customer->display_name,
                    ];
                    $initialData['customer_name'] = $estimate->customer_name ?? $estimate->customer->display_name;
                    $initialData['customer_email'] = $estimate->customer_email ?? $estimate->customer->email;
                    $initialData['customer_phone'] = $estimate->customer_phone ?? $estimate->customer->phone;
                }

                $initialData['billing_address_line1'] = $estimate->billing_address_line1;
                $initialData['billing_address_line2'] = $estimate->billing_address_line2;
                $initialData['billing_city'] = $estimate->billing_city;
                $initialData['billing_state'] = $estimate->billing_state;
                $initialData['billing_postal'] = $estimate->billing_postal;
                $initialData['billing_country'] = $estimate->billing_country;
                $initialData['billing_latitude'] = $estimate->billing_latitude;
                $initialData['billing_longitude'] = $estimate->billing_longitude;
                $initialData['tax_rate'] = $estimate->tax_rate;
                $initialData['tax_jurisdiction'] = $estimate->tax_jurisdiction;

                $initialData['user_id'] = $estimate->user_id;
                if ($estimate->user) {
                    $initialData['user'] = [
                        'id' => $estimate->user->id,
                        'display_name' => $estimate->user->display_name ?? $estimate->user->name ?? '',
                    ];
                }
                if ($estimate->opportunity_id && $estimate->opportunity) {
                    $initialData['opportunity_id'] = $estimate->opportunity_id;
                    $initialData['opportunity'] = [
                        'id' => $estimate->opportunity->id,
                        'display_name' => $estimate->opportunity->display_name,
                    ];
                }
                $initialData['title'] = 'Deal for Estimate #'.$estimate->sequence;
                if ($pv) {
                    $initialData['subtotal'] = $pv->subtotal;
                    $initialData['tax_total'] = $pv->tax;
                    $initialData['total'] = $pv->total;

                    $initialData['items'] = $pv->lineItems
                        ->values()
                        ->map(fn ($line, $idx) => [
                            'name' => $line->name,
                            'description' => $line->description ?? $line->notes ?? null,
                            'quantity' => $line->quantity,
                            'unit_price' => $line->unit_price,
                            'position' => $line->position ?? $idx,
                            'taxable' => true,
                            'addons' => ($line->addons ?? collect())->map(fn ($a) => [
                                'addon_id' => $a->addon_id,
                                'name' => $a->name ?? null,
                                'price' => $a->price ?? 0,
                                'quantity' => $a->quantity ?? 1,
                                'notes' => $a->notes ?? null,
                                'taxable' => true,
                            ])->toArray(),
                        ])
                        ->toArray();
                }
                $initialData['currency'] = 'USD';
            }
        }

        return inertia('Tenant/Transaction/Create', [
            'recordType' => 'Transaction',
            'recordTitle' => 'Transaction',
            'domainName' => 'Transaction',
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
            'initialData' => $initialData,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $result = ($this->createTransaction)($request->all());
        } catch (ValidationException $e) {
            throw $e;
        }

        if (! ($result['success'] ?? false) || ! isset($result['record'])) {
            return back()
                ->withInput()
                ->with('error', $result['message'] ?? 'Failed to create transaction.');
        }

        return redirect()
            ->route('transactions.show', $result['record']->id)
            ->with('success', 'Transaction created successfully.')
            ->with('recordId', $result['record']->id);
    }

    public function show(Request $request, $transaction)
    {
        $id = $transaction instanceof Transaction ? $transaction->getKey() : (int) $transaction;

        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();
        $account = AccountSettings::getCurrent();

        $record = Transaction::query()
            ->with([
                'items' => fn ($q) => $q
                    ->with([
                        'addons',
                        'itemable',
                        'assetVariant' => fn ($qv) => $qv->select(['id', 'display_name', 'name']),
                        'assetUnit' => fn ($qu) => $qu->select(['id', 'asset_id', 'asset_variant_id', 'serial_number', 'hin', 'sku', 'cost', 'asking_price']),
                        'estimateLineItem' => fn ($q2) => $q2
                            ->select(['id', 'asset_variant_id', 'asset_unit_id'])
                            ->with([
                                'assetVariant' => fn ($q3) => $q3->select(['id', 'display_name', 'name']),
                                'assetUnit' => fn ($q3) => $q3->select(['id', 'asset_id', 'asset_variant_id', 'serial_number', 'hin', 'sku', 'cost', 'asking_price']),
                            ]),
                    ])
                    ->orderBy('position')
                    ->orderBy('id'),
                'customer' => Customer::eagerWithContactSelect(['email', 'phone']),
                'user' => fn ($q) => $q->select(['id', 'display_name', 'email']),
                'estimate' => fn ($q) => $q
                    ->select(['id', 'sequence', 'uuid', 'status', 'tax_rate', 'primary_version_id'])
                    ->with([
                        'primaryVersion' => fn ($qv) => $qv
                            ->select(['id', 'estimate_id', 'tax_rate', 'subtotal', 'tax', 'total'])
                            ->with([
                                'lineItems' => fn ($ql) => $ql
                                    ->with([
                                        'addons',
                                        'assetVariant' => fn ($qav) => $qav->select(['id', 'display_name', 'name']),
                                        'assetUnit' => fn ($qau) => $qau->select(['id', 'asset_id', 'asset_variant_id', 'serial_number', 'hin', 'sku', 'cost', 'asking_price']),
                                    ])
                                    ->orderBy('position')
                                    ->orderBy('id'),
                            ]),
                    ]),
                'opportunity' => fn ($q) => $q->select(['id', 'display_name']),
                // Full row so Contract::$appends display_name (getDisplayNameAttribute) serializes correctly.
                'contract',
                'serviceTickets' => fn ($q) => $q->select(['id', 'transaction_id', 'service_ticket_number', 'status'])->orderByDesc('id')->limit(20),
                'subsidiary' => fn ($q) => $q->select(['id', 'display_name']),
                'location' => fn ($q) => $q->select(['id', 'display_name']),
            ])
            ->findOrFail($id);

        return inertia('Tenant/Transaction/Show', [
            'record' => $record,
            'recordType' => 'transactions',
            'recordTitle' => 'Transaction',
            'domainName' => 'Transaction',
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
            'imageUrls' => (object) [],
        ]);
    }

    public function edit($transaction)
    {
        $id = $transaction instanceof Transaction ? $transaction->getKey() : (int) $transaction;

        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();
        $account = AccountSettings::getCurrent();

        $record = Transaction::query()
            ->with([
                'items' => fn ($q) => $q->with([
                    'addons',
                    'itemable',
                    'assetVariant' => fn ($qv) => $qv->select(['id', 'display_name', 'name']),
                    'assetUnit' => fn ($qu) => $qu->select(['id', 'asset_id', 'asset_variant_id', 'serial_number', 'hin', 'sku', 'cost', 'asking_price']),
                ])->orderBy('position')->orderBy('id'),
                'customer' => Customer::eagerWithContactSelect(),
                'user' => fn ($q) => $q->select(['id', 'display_name']),
                'estimate' => fn ($q) => $q->select(['id', 'sequence']),
                'opportunity' => fn ($q) => $q->select(['id', 'display_name']),
                'subsidiary' => fn ($q) => $q->select(['id', 'display_name']),
                'location' => fn ($q) => $q->select(['id', 'display_name']),
            ])
            ->findOrFail($id);

        return inertia('Tenant/Transaction/Edit', [
            'record' => $record,
            'recordType' => 'Transaction',
            'recordTitle' => 'Transaction',
            'domainName' => 'Transaction',
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
            'imageUrls' => (object) [],
        ]);
    }

    public function update(Request $request, $transaction)
    {
        $id = $transaction instanceof Transaction ? $transaction->getKey() : (int) $transaction;

        try {
            $result = ($this->updateTransaction)($id, $request->all());
        } catch (ValidationException $e) {
            throw $e;
        }

        if (! ($result['success'] ?? false)) {
            return back()
                ->withInput()
                ->with('error', $result['message'] ?? 'Failed to update transaction.');
        }

        return redirect()
            ->route('transactions.show', $id)
            ->with('success', 'Transaction updated successfully.');
    }

    public function destroy($transaction)
    {
        $id = $transaction instanceof Transaction ? $transaction->getKey() : (int) $transaction;
        $result = ($this->deleteTransaction)($id);

        if (! ($result['success'] ?? false)) {
            return back()->with('error', $result['message'] ?? 'Failed to delete transaction.');
        }

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaction deleted.');
    }
}
