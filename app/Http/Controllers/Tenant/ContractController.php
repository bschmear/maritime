<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Contract\Actions\CreateContract;
use App\Domain\Contract\Actions\DeleteContract;
use App\Domain\Contract\Actions\UpdateContract;
use App\Domain\Contract\Models\Contract;
use App\Domain\Customer\Models\Customer;
use App\Domain\Estimate\Models\Estimate;
use App\Domain\Transaction\Models\Transaction;
use App\Enums\Contract\ContractStatus;
use App\Enums\Payments\Terms;
use App\Enums\Timezone;
use App\Http\Controllers\Concerns\HasSchemaSupport;
use App\Mail\ContractReviewRequest;
use App\Models\AccountSettings;
use App\Support\ContractEnumMapper;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Mail;

class ContractController extends BaseController
{
    use AuthorizesRequests, HasSchemaSupport, ValidatesRequests;

    protected string $domainName = 'Contract';

    protected Contract $recordModel;

    public function __construct()
    {
        $this->middleware('auth');
        $this->recordModel = new Contract;
    }

    protected function getUnwrappedFieldsSchema(): array
    {
        $fieldsSchemaRaw = $this->getFieldsSchema();
        if (isset($fieldsSchemaRaw['fields'])) {
            return $fieldsSchemaRaw['fields'];
        }

        return $fieldsSchemaRaw ?? [];
    }

    /**
     * Enum options + record pickers (Transaction has no display_name column).
     */
    protected function getContractEnumOptions(): array
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = [];

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['enum']) && ! empty($fieldDef['enum'])) {
                $enumClass = $fieldDef['enum'];
                if (class_exists($enumClass) && method_exists($enumClass, 'options')) {
                    $enumOptions[$enumClass] = $enumClass::options();
                }
            }

            if (($fieldDef['type'] ?? '') === 'record' && isset($fieldDef['typeDomain'])) {
                $domainName = $fieldDef['typeDomain'];
                if ($fieldDef['typeDomain'] === 'Estimate') {
                    try {
                        $enumOptions[$fieldKey] = Estimate::query()
                            ->select(['id', 'sequence'])
                            ->orderByDesc('id')
                            ->limit(500)
                            ->get()
                            ->map(fn ($e) => [
                                'id' => $e->id,
                                'name' => $e->display_name,
                                'value' => $e->id,
                            ])->toArray();
                    } catch (\Throwable $e) {
                        \Log::warning('Failed to load estimate options: '.$e->getMessage());
                        $enumOptions[$fieldKey] = [];
                    }

                    continue;
                }

                if ($fieldKey === 'transaction_id') {
                    try {
                        $enumOptions[$fieldKey] = Transaction::query()
                            ->select(['id', 'title', 'sequence', 'customer_name'])
                            ->orderByDesc('id')
                            ->limit(500)
                            ->get()
                            ->map(fn ($t) => [
                                'id' => $t->id,
                                'name' => $t->title ?: $t->customer_name ?: ('TX-'.($t->sequence ?? $t->id)),
                                'value' => $t->id,
                            ])->toArray();
                    } catch (\Throwable $e) {
                        \Log::warning('Failed to load transaction options: '.$e->getMessage());
                        $enumOptions[$fieldKey] = [];
                    }

                    continue;
                }

                $modelClass = "App\\Domain\\{$domainName}\\Models\\{$domainName}";
                if (class_exists($modelClass)) {
                    try {
                        $records = $domainName === 'Customer'
                            ? Customer::queryOrderedByContactDisplayName()->get()
                            : $modelClass::select('id', 'display_name')->get();
                        $enumOptions[$fieldKey] = $records->map(fn ($r) => [
                            'id' => $r->id,
                            'name' => $r->display_name,
                            'value' => $r->id,
                        ])->toArray();
                    } catch (\Exception $e) {
                        \Log::warning("Failed to load record options for {$domainName}: ".$e->getMessage());
                        $enumOptions[$fieldKey] = [];
                    }
                }
            }
        }

        return $enumOptions;
    }

    protected function relationshipClosuresForSchema(): array
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $relationships = [];

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (($fieldDef['type'] ?? '') !== 'record' || ! isset($fieldDef['typeDomain'])) {
                continue;
            }

            $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
            $selectFields = ['id'];

            if ($fieldDef['typeDomain'] === 'Customer') {
                $relationships[$relationshipName] = Customer::eagerWithContactSelect();

                continue;
            }

            if ($fieldDef['typeDomain'] === 'Contact') {
                $selectFields = ['id', 'display_name', 'first_name', 'last_name', 'email'];
            } elseif ($fieldDef['typeDomain'] === 'Estimate') {
                $selectFields = ['id', 'sequence'];
            } elseif ($fieldDef['typeDomain'] === 'Transaction') {
                $selectFields = ['id', 'title', 'sequence', 'customer_name'];
            } else {
                $selectFields[] = 'display_name';
            }

            if (isset($fieldDef['displayField']) && $fieldDef['displayField'] !== 'display_name') {
                $selectFields[] = $fieldDef['displayField'];
            }

            $selectFields = array_unique($selectFields);

            $relationships[$relationshipName] = function ($query) use ($selectFields) {
                $query->select($selectFields);
            };
        }

        return $relationships;
    }

    protected function isLocked(Contract $contract): bool
    {
        return $contract->signed_at !== null
            || $contract->status === ContractStatus::Signed->value;
    }

    public function index(Request $request)
    {
        $columns = $this->getSchemaColumns();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $schema = $this->getTableSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getContractEnumOptions();

        $actualColumns = [];
        $dbColumns = \Schema::connection($this->recordModel->getConnectionName())
            ->getColumnListing($this->recordModel->getTable());

        foreach ($columns as $column) {
            if (strpos($column, '.') !== false) {
                continue;
            }
            if (in_array($column, $dbColumns, true)) {
                $actualColumns[] = $column;
            }
        }

        if (! in_array('id', $actualColumns, true)) {
            $actualColumns[] = 'id';
        }

        $relationships = $this->relationshipClosuresForSchema();

        $settings = AccountSettings::getCurrent();
        $query = Contract::query()
            ->select($actualColumns)
            ->where('account_settings_id', $settings->id)
            ->with($relationships);

        $searchQuery = $request->get('search');
        if ($searchQuery && ! empty(trim($searchQuery))) {
            $term = '%'.strtolower(trim($searchQuery)).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(contract_number) LIKE ?', [$term])
                    ->orWhereRaw('CAST(id AS TEXT) LIKE ?', [$term])
                    ->orWhereHas('contact', function ($cq) use ($term) {
                        $cq->whereRaw('LOWER(COALESCE(display_name, \'\')) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(COALESCE(first_name, \'\')) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(COALESCE(last_name, \'\')) LIKE ?', [$term]);
                    });
            });
        }

        $filtersParam = $request->get('filters');
        if ($filtersParam) {
            try {
                $filters = json_decode(urldecode($filtersParam), true);
                if (is_array($filters)) {
                    foreach ($filters as &$filter) {
                        if (! is_array($filter)) {
                            continue;
                        }
                        $field = $filter['field'] ?? null;
                        $value = $filter['value'] ?? null;
                        if ($field === 'status' && $value !== null && $value !== '' && is_numeric($value)) {
                            $filter['value'] = ContractEnumMapper::statusToValue((int) $value);
                        }
                        if ($field === 'payment_status' && $value !== null && $value !== '' && is_numeric($value)) {
                            $filter['value'] = ContractEnumMapper::paymentStatusToValue((int) $value);
                        }
                    }
                    unset($filter);
                    $query = $this->applyFilters($query, $filters, $fieldsSchema);
                }
            } catch (\Exception $e) {
                // ignore invalid filters
            }
        }

        $query->orderBy('created_at', 'desc');
        $perPage = (int) $request->get('per_page', 15);
        $records = $query->paginate($perPage);

        return inertia('Tenant/Contract/Index', [
            'records' => $records,
            'recordType' => 'contracts',
            'recordTitle' => 'Contract',
            'pluralTitle' => 'Contracts',
            'schema' => $schema,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
        ]);
    }

    public function create(Request $request)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $settings = AccountSettings::getCurrent();
        $paymentTermDefault = $settings->default_payment_term;
        $paymentTermValue = $paymentTermDefault instanceof Terms
            ? $paymentTermDefault->value
            : ($paymentTermDefault ?: Terms::DueOnReceipt->value);

        $initialData = [
            'payment_term' => $paymentTermValue,
            'contract_terms' => $settings->default_contract_terms,
            'payment_terms' => $settings->default_payment_terms,
            'delivery_terms' => $settings->default_delivery_terms,
        ];
        if ($request->filled('estimate_id')) {
            $initialData['estimate_id'] = (int) $request->get('estimate_id');
        }

        return inertia('Tenant/Contract/Create', [
            'formSchema' => $this->getFormSchema(),
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $this->getContractEnumOptions(),
            'account' => AccountSettings::getCurrent(),
            'timezones' => Timezone::options(),
            'initialData' => $initialData,
        ]);
    }

    public function store(Request $request)
    {
        $result = (new CreateContract)($request->all());

        if (! $result['success'] || empty($result['record'])) {
            return back()
                ->withErrors(['message' => $result['message'] ?? 'Could not create contract.'])
                ->withInput();
        }

        return redirect()->route('contracts.show', $result['record']->id);
    }

    public function show(int $contract)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $relationships = $this->relationshipClosuresForSchema();

        // Override the transaction loader so we also pull its line items + addons + billing snapshot,
        // plus subsidiary + location for contract preview header (same pattern as service ticket preview).
        $relationships['transaction'] = fn ($q) => $q
            ->select([
                'id', 'title', 'sequence', 'customer_name', 'customer_email', 'customer_phone',
                'tax_rate', 'currency', 'subsidiary_id', 'location_id',
                'billing_address_line1', 'billing_address_line2', 'billing_city',
                'billing_state', 'billing_postal', 'billing_country',
            ])
            ->with([
                'items' => fn ($q2) => $q2
                    ->with([
                        'addons',
                        'assetVariant' => fn ($qv) => $qv->select(['id', 'display_name', 'name']),
                        'assetUnit' => fn ($qu) => $qu->select(['id', 'asset_id', 'asset_variant_id', 'serial_number', 'hin', 'sku', 'cost', 'asking_price']),
                        'estimateLineItem' => fn ($q3) => $q3
                            ->select(['id', 'asset_variant_id', 'asset_unit_id'])
                            ->with([
                                'assetVariant' => fn ($q4) => $q4->select(['id', 'display_name', 'name']),
                                'assetUnit' => fn ($q4) => $q4->select(['id', 'asset_id', 'asset_variant_id', 'serial_number', 'hin', 'sku', 'cost', 'asking_price']),
                            ]),
                    ])
                    ->orderBy('position')
                    ->orderBy('id'),
                'subsidiary' => fn ($q2) => $q2->select(['id', 'display_name']),
                'location' => fn ($q2) => $q2->select([
                    'id', 'display_name',
                    'address_line_1', 'address_line_2', 'city', 'state', 'postal_code',
                    'phone', 'email',
                ]),
            ]);

        $relationships['estimate'] = fn ($q) => $q
            ->select(['id', 'sequence', 'tax_rate', 'primary_version_id'])
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
            ]);

        $settings = AccountSettings::getCurrent();
        $record = Contract::query()
            ->where('account_settings_id', $settings->id)
            ->with($relationships)
            ->findOrFail($contract);

        $recordArray = $record->toArray();
        $recordArray['created_at'] = $record->created_at?->toISOString();
        $recordArray['updated_at'] = $record->updated_at?->toISOString();
        $recordArray['signed_at'] = $record->signed_at?->toISOString();

        return inertia('Tenant/Contract/Show', [
            'record' => $recordArray,
            'formSchema' => $this->getFormSchema(),
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $this->getContractEnumOptions(),
            'account' => $settings,
            'timezones' => Timezone::options(),
        ]);
    }

    public function edit(int $contract)
    {
        $settings = AccountSettings::getCurrent();
        $record = Contract::query()
            ->where('account_settings_id', $settings->id)
            ->findOrFail($contract);

        if ($this->isLocked($record)) {
            return redirect()->route('contracts.show', $contract)
                ->with('error', 'This contract cannot be edited because it has been signed.');
        }

        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $relationships = $this->relationshipClosuresForSchema();

        $record = Contract::query()
            ->where('account_settings_id', $settings->id)
            ->with($relationships)
            ->findOrFail($contract);

        return inertia('Tenant/Contract/Edit', [
            'record' => $record,
            'formSchema' => $this->getFormSchema(),
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $this->getContractEnumOptions(),
            'account' => $settings,
            'timezones' => Timezone::options(),
        ]);
    }

    public function update(Request $request, int $contract)
    {
        $settings = AccountSettings::getCurrent();
        $existing = Contract::query()
            ->where('account_settings_id', $settings->id)
            ->findOrFail($contract);

        if ($this->isLocked($existing)) {
            return redirect()->route('contracts.show', $contract)
                ->with('error', 'This contract cannot be updated because it has been signed.');
        }

        $result = (new UpdateContract)($contract, $request->all());

        if (! $result['success'] || empty($result['record'])) {
            return back()
                ->withErrors(['message' => $result['message'] ?? 'Could not update contract.'])
                ->withInput();
        }

        return redirect()->route('contracts.show', $contract);
    }

    public function sendToCustomer(int $contract)
    {
        $settings = AccountSettings::getCurrent();
        $record = Contract::query()
            ->where('account_settings_id', $settings->id)
            ->with(['customer', 'transaction'])
            ->findOrFail($contract);

        $customerEmail = $record->customer?->email
            ?? $record->transaction?->customer_email;

        if (! $customerEmail) {
            return back()->with('error', 'No customer email found for this contract.');
        }

        $record->update(['status' => ContractStatus::PendingApproval->value]);

        $reviewUrl = route('contracts.review', $record->uuid);
        Mail::to($customerEmail)->send(new ContractReviewRequest($record, $settings, $reviewUrl));

        return back()->with('success', 'Contract sent to '.$customerEmail);
    }

    public function destroy(int $contract)
    {
        $settings = AccountSettings::getCurrent();
        Contract::query()
            ->where('account_settings_id', $settings->id)
            ->findOrFail($contract);

        $result = (new DeleteContract)($contract);

        if (! $result['success']) {
            return redirect()->route('contracts.index')
                ->with('error', $result['message'] ?? 'Could not delete contract.');
        }

        return redirect()->route('contracts.index')
            ->with('success', $result['message'] ?? 'Contract deleted.');
    }
}
