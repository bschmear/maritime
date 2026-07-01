<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Customer\Models\Customer;
use App\Domain\ServiceItem\Models\ServiceItem;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\ServiceTicket\Support\SyncServiceTicketCompletionToWorkOrders;
use App\Domain\Transaction\Models\Transaction;
use App\Enums\Inventory\UnitStatus;
use App\Enums\ServiceItem\BillingType;
use App\Enums\ServiceTicket\Status as ServiceTicketStatus;
use App\Enums\ServiceTicketServiceItem\WarrantyCoverageType;
use App\Enums\Timezone;
use App\Http\Controllers\Concerns\EnforcesTenantRecordPermissions;
use App\Http\Controllers\Concerns\HasSchemaSupport;
use App\Mail\ServiceTicketApprovalRequest;
use App\Models\AccountSettings;
use App\Services\Mail\TenantMailService;
use App\Services\ServiceTicketService;
use App\Services\SMS\SmsService;
use App\Support\ContactDocumentLinker;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;

class ServiceTicketController extends BaseController
{
    use AuthorizesRequests, EnforcesTenantRecordPermissions, HasSchemaSupport, ValidatesRequests;

    protected $domainName = 'ServiceTicket';

    protected $recordModel;

    protected ServiceTicketService $service;

    public function __construct(ServiceTicketService $service)
    {
        $this->middleware('auth');
        $this->registerTenantRecordPermissionMiddleware();
        $this->recordModel = new ServiceTicket;
        $this->service = $service;
    }

    /**
     * Display a listing of service tickets.
     *
     * Uses the same JSON `filters` query format as {@see Table}: by default we exclude
     * "Completed" status (treat as completed = false). Quick filters use multi-select like Estimates.
     */
    public function index(Request $request)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $schema = $this->getTableSchema();

        $completedId = ServiceTicketStatus::Completed->id();
        // Same as "completed = false": all workflow statuses except Completed. Use any_of so
        // Table.vue quick filters (multi-select) stay in sync with the URL.
        $defaultStatusIds = collect(ServiceTicketStatus::cases())
            ->map(fn (ServiceTicketStatus $s) => $s->id())
            ->filter(fn (int $id) => $id !== $completedId)
            ->values()
            ->all();
        $defaultFilters = [
            ['field' => 'status', 'operator' => 'any_of', 'value' => $defaultStatusIds],
        ];

        if (! $request->has('filters')) {
            $queryParams = array_filter([
                'filters' => json_encode($defaultFilters),
                'search' => $request->get('search'),
                'page' => $request->get('page'),
                'sort' => $request->get('sort'),
                'direction' => $request->get('direction'),
                'per_page' => $request->get('per_page'),
            ], fn ($v) => $v !== null && $v !== '');

            return redirect()->route('servicetickets.index', $queryParams);
        }

        $filtersParam = $request->get('filters');
        $activeFilters = [];
        if ($filtersParam !== null && $filtersParam !== '') {
            try {
                $activeFilters = json_decode(urldecode((string) $filtersParam), true) ?? [];
            } catch (\Throwable) {
                $activeFilters = [];
            }
        }
        if (! is_array($activeFilters)) {
            $activeFilters = [];
        }

        $this->domainName = 'ServiceTicket';
        $this->recordModel = new ServiceTicket;

        $query = ServiceTicket::query()->with($this->indexEagerLoads());

        $searchQuery = $request->get('search');
        if ($searchQuery && ! empty(trim((string) $searchQuery))) {
            $searchTerm = '%'.strtolower(trim((string) $searchQuery)).'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(service_tickets.service_ticket_number) LIKE ?', [$searchTerm])
                    ->orWhereHas('customer', function ($customerQuery) use ($searchTerm) {
                        $customerQuery->whereRaw('LOWER(display_name) LIKE ?', [$searchTerm]);
                    })
                    ->orWhereHas('assetUnit', function ($assetQuery) use ($searchTerm) {
                        $assetQuery->whereRaw('LOWER(hin) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(serial_number) LIKE ?', [$searchTerm]);
                    });
            });
        }

        if ($activeFilters !== []) {
            $query = $this->applyFilters($query, $activeFilters, $fieldsSchema);
        }

        $table = $this->recordModel->getTable();
        $connection = $this->recordModel->getConnectionName() ?? $this->recordModel->getConnection()->getName();
        $dbColumns = static::getTableColumnListingFor($connection, $table);

        if (! $this->applyRecordIndexSort($query, $request, $schema, $dbColumns, $table, $dbColumns, $fieldsSchema)) {
            $query->orderBy($table.'.created_at', 'desc');
        }

        $perPage = table_per_page($request);
        $records = $query->paginate($perPage)->withQueryString();

        if ($json = $this->indexAjaxJsonResponse($request, $records, $schema, $fieldsSchema)) {
            return $json;
        }

        return inertia('Tenant/ServiceTicket/Index', [
            'records' => $records,
            'schema' => $schema,
            'formSchema' => $this->getFormSchema(),
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => Inertia::defer(fn () => $this->getEnumClassOptions(), 'serviceticket-table'),
            'stats' => $this->indexStats(),
        ]);
    }

    /**
     * Show the form for creating a new service ticket.
     */
    public function create(Request $request)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();
        $enumOptions['billing_type'] = BillingType::options();
        $enumOptions['warranty_type'] = WarrantyCoverageType::options();
        $enumOptions['asset_unit_status'] = UnitStatus::options();

        $account = AccountSettings::getCurrent();

        $transactionId = $request->filled('transaction_id')
            ? (int) $request->get('transaction_id')
            : null;

        $assetUnitId = $request->filled('asset_unit_id')
            ? (int) $request->get('asset_unit_id')
            : null;

        $transactionBootstrap = $this->transactionBootstrapForServiceTicketCreate($transactionId)
            ?? $this->assetUnitBootstrapForServiceTicketCreate($assetUnitId);

        return inertia('Tenant/ServiceTicket/Create', [
            'formSchema' => $this->getFormSchema(),
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
            'transactionId' => $transactionId,
            'transactionBootstrap' => $transactionBootstrap,
            'defaultSubsidiaryId' => Customer::defaultSubsidiaryId(),
        ]);
    }

    /**
     * Prefill data when opening the service-ticket wizard from a transaction (customer + asset units on the deal).
     *
     * @return array<string, mixed>|null
     */
    private function transactionBootstrapForServiceTicketCreate(?int $transactionId): ?array
    {
        if (! $transactionId) {
            return null;
        }

        $tx = Transaction::query()
            ->select(['id', 'customer_id', 'sequence', 'subsidiary_id', 'location_id'])
            ->with([
                'subsidiary' => fn ($q) => $q->select(['id', 'display_name']),
                'location' => fn ($q) => $q->select(['id', 'display_name']),
                'items' => function ($q) {
                    $q->where('itemable_type', Asset::class)
                        ->whereNotNull('asset_unit_id')
                        ->orderBy('position')
                        ->orderBy('id')
                        ->with([
                            'assetUnit' => function ($uq) {
                                $uq->select([
                                    'id',
                                    'serial_number',
                                    'hin',
                                    'sku',
                                    'asset_id',
                                    'customer_id',
                                    'asset_variant_id',
                                ])->with([
                                    'asset' => function ($aq) {
                                        $aq->select(['id', 'display_name', 'year', 'make_id', 'has_variants'])
                                            ->with(['make' => fn ($mq) => $mq->select(['id', 'display_name'])]);
                                    },
                                    'assetVariant' => fn ($vq) => $vq->select(['id', 'name', 'display_name']),
                                ]);
                            },
                        ]);
                },
            ])
            ->find($transactionId);

        if (! $tx || ! $tx->customer_id) {
            return null;
        }

        $seenUnitIds = [];
        $assetUnits = [];
        foreach ($tx->items as $line) {
            $unit = $line->assetUnit;
            if (! $unit || isset($seenUnitIds[$unit->id])) {
                continue;
            }
            $seenUnitIds[$unit->id] = true;
            $assetUnits[] = $this->formatAssetUnitForServiceTicketBootstrap($unit);
        }

        return [
            'transaction' => [
                'id' => $tx->id,
                'display_name' => $tx->display_name,
            ],
            'customer_id' => $tx->customer_id,
            'subsidiary_id' => $tx->subsidiary_id,
            'location_id' => $tx->location_id,
            'subsidiary' => $tx->subsidiary ? [
                'id' => $tx->subsidiary->id,
                'display_name' => $tx->subsidiary->display_name,
            ] : null,
            'location' => $tx->location ? [
                'id' => $tx->location->id,
                'display_name' => $tx->location->display_name,
            ] : null,
            'asset_units' => $assetUnits,
        ];
    }

    /**
     * Prefill data when opening the service-ticket wizard from an asset unit (?asset_unit_id=).
     *
     * @return array<string, mixed>|null
     */
    private function assetUnitBootstrapForServiceTicketCreate(?int $assetUnitId): ?array
    {
        if (! $assetUnitId) {
            return null;
        }

        $unit = AssetUnit::query()
            ->select([
                'id',
                'serial_number',
                'hin',
                'sku',
                'asset_id',
                'customer_id',
                'asset_variant_id',
                'subsidiary_id',
                'location_id',
            ])
            ->with([
                'subsidiary' => fn ($q) => $q->select(['id', 'display_name']),
                'location' => fn ($q) => $q->select(['id', 'display_name']),
                ...$this->assetUnitRelationsForServiceTicketBootstrap(),
            ])
            ->find($assetUnitId);

        if (! $unit) {
            return null;
        }

        return [
            'asset_unit' => [
                'id' => $unit->id,
                'display_name' => $unit->display_name,
            ],
            'customer_id' => $unit->customer_id,
            'subsidiary_id' => $unit->subsidiary_id,
            'location_id' => $unit->location_id,
            'subsidiary' => $unit->subsidiary ? [
                'id' => $unit->subsidiary->id,
                'display_name' => $unit->subsidiary->display_name,
            ] : null,
            'location' => $unit->location ? [
                'id' => $unit->location->id,
                'display_name' => $unit->location->display_name,
            ] : null,
            'asset_units' => [$this->formatAssetUnitForServiceTicketBootstrap($unit)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAssetUnitForServiceTicketBootstrap(AssetUnit $unit): array
    {
        $asset = $unit->asset;
        $make = $asset?->make;
        $variant = $unit->assetVariant;

        return [
            'id' => $unit->id,
            'display_name' => $unit->display_name ?: ($asset?->display_name ?? 'Unit #'.$unit->id),
            'serial_number' => $unit->serial_number,
            'hin' => $unit->hin,
            'sku' => $unit->sku,
            'asset_id' => $unit->asset_id,
            'customer_id' => $unit->customer_id,
            'asset_variant_id' => $unit->asset_variant_id,
            'asset' => $asset ? [
                'id' => $asset->id,
                'display_name' => $asset->display_name,
                'year' => $asset->year,
                'has_variants' => (bool) $asset->has_variants,
                'make' => $make ? ['id' => $make->id, 'display_name' => $make->display_name] : null,
            ] : null,
            'asset_variant' => $variant ? [
                'id' => $variant->id,
                'name' => $variant->name,
                'display_name' => $variant->display_name,
            ] : null,
        ];
    }

    /**
     * @return array<string, \Closure>
     */
    private function assetUnitRelationsForServiceTicketBootstrap(): array
    {
        return [
            'asset' => function ($aq) {
                $aq->select(['id', 'display_name', 'year', 'make_id', 'has_variants'])
                    ->with(['make' => fn ($mq) => $mq->select(['id', 'display_name'])]);
            },
            'assetVariant' => fn ($vq) => $vq->select(['id', 'name', 'display_name']),
        ];
    }

    /**
     * Store a newly created service ticket.
     */
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customer_profiles,id',
            'subsidiary_id' => 'required|exists:subsidiaries,id',
            'location_id' => 'required|exists:locations,id',
            'transaction_id' => 'nullable|integer|exists:transactions,id',
        ]);

        $ticket = $this->service->create($request->all());

        return redirect()->route('servicetickets.show', $ticket->id);
    }

    public function destroy($id)
    {
        $ticket = ServiceTicket::findOrFail($id);
        $this->service->delete($ticket);

        return redirect()->route('servicetickets.index');
    }

    /**
     * Display the specified service ticket.
     */
    public function show(Request $request, $id)
    {
        // Lightweight Inertia partial reload after approval send (only flash prop requested).
        if (
            $request->header('X-Inertia')
            && $request->header('X-Inertia-Partial-Data') === 'flash'
        ) {
            return inertia('Tenant/ServiceTicket/Show');
        }

        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        $relationships['assetUnit'] = function ($query) {
            $query->select(['id', 'serial_number', 'hin', 'sku', 'asset_id', 'customer_id', 'asset_variant_id'])
                ->with([
                    'asset' => function ($q) {
                        $q->select(['id', 'display_name', 'model', 'year', 'make_id'])
                            ->with(['make' => function ($mq) {
                                $mq->select(['id', 'display_name']);
                            }]);
                    },
                    'assetVariant' => fn ($vq) => $vq->select(['id', 'name', 'display_name']),
                ]);
        };

        $relationships['serviceItems'] = function ($query) {
            $query->where('inactive', false)->orderBy('sort_order')->orderBy('id');
        };

        $relationships['workOrders'] = function ($query) {
            $query->select(['id', 'service_ticket_id', 'work_order_number', 'status']);
        };

        $relationships['transaction'] = function ($query) {
            $query->select(['id', 'title', 'sequence']);
        };

        $formSchema = $this->getFormSchema();

        if (isset($formSchema['sublists']) && is_array($formSchema['sublists'])) {
            foreach ($formSchema['sublists'] as $sublist) {
                if (isset($sublist['modelRelationship']) && ! isset($relationships[$sublist['modelRelationship']])) {
                    $relationships[$sublist['modelRelationship']] = function ($query) {
                        $query->select('*');
                    };
                }
            }
        }

        $record = ServiceTicket::with($relationships)->findOrFail($id);

        ContactDocumentLinker::hydrateDocumentsRelationIfApplicable($record);

        $recordArray = $record->toArray();
        $recordArray['created_at'] = $record->created_at?->toISOString();
        $recordArray['updated_at'] = $record->updated_at?->toISOString();
        $recordArray['signed_at'] = $record->signed_at?->toISOString();
        $recordArray['reauthorized_at'] = $record->reauthorized_at?->toISOString();
        $recordArray['pickup_delivery_requested_at'] = $record->pickup_delivery_requested_at?->format('Y-m-d');
        $recordArray['signature_url'] = $record->signature_url;
        $recordArray['transaction_id'] = $record->transaction_id;
        if ($record->relationLoaded('transaction') && $record->transaction) {
            $recordArray['transaction'] = [
                'id' => $record->transaction->id,
                'title' => $record->transaction->title,
                'sequence' => $record->transaction->sequence,
                'display_name' => $record->transaction->display_name,
            ];
        }

        $recordArray['service_items'] = $record->serviceItems->map(fn ($li) => [
            'id' => $li->id,
            'service_item_id' => $li->service_item_id,
            'display_name' => $li->display_name,
            'description' => $li->description,
            'quantity' => (float) $li->quantity,
            'unit_price' => (float) $li->unit_price,
            'unit_cost' => (float) $li->unit_cost,
            'estimated_hours' => (float) ($li->estimated_hours ?? 0),
            'actual_hours' => (float) ($li->actual_hours ?? 0),
            'billable' => $li->billable,
            'warranty' => $li->warranty,
            'warranty_type' => $li->warranty_type instanceof WarrantyCoverageType
                ? $li->warranty_type->value
                : ($li->warranty_type ?? null),
            'billable_to' => $li->billable_to ?? (! $li->warranty
                ? 'customer'
                : ((($li->warranty_type instanceof WarrantyCoverageType ? $li->warranty_type->value : $li->warranty_type) === 'manufacturer')
                    ? 'manufacturer'
                    : 'internal')),
            'billing_type' => $li->billing_type,
        ])->values()->all();

        $enumOptions = $this->getEnumOptions();
        $enumOptions['billing_type'] = BillingType::options();
        $enumOptions['warranty_type'] = WarrantyCoverageType::options();

        $account = AccountSettings::getCurrent();

        $smsService = app(SmsService::class);
        $serviceTicketApprovalSms = $smsService->serviceTicketApprovalSmsCanBeOffered($record->customer, request()->user());

        return inertia('Tenant/ServiceTicket/Show', [
            'record' => $recordArray,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
            'serviceTicketApprovalSms' => $serviceTicketApprovalSms,
            'workOrders' => $record->workOrders->map(fn ($wo) => [
                'id' => $wo->id,
                'display_name' => $wo->display_name,
                'work_order_number' => $wo->work_order_number,
                'status' => $wo->status,
            ])->values()->all(),
        ]);
    }

    /**
     * Show the form for editing the specified service ticket.
     */
    public function edit($id)
    {
        $record = ServiceTicket::findOrFail($id);

        // Prevent editing if the service ticket has been approved/signed
        if ($record->approved || $record->signed_at || $record->customer_signature) {
            return redirect()->route('servicetickets.show', $id)
                ->with('error', 'This service ticket cannot be edited because it has already been approved and/or signed by the customer.');
        }

        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        // Load record relationships for display
        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);

                if ($fieldDef['typeDomain'] === 'AssetUnit') {
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'serial_number', 'hin', 'sku', 'asset_id', 'customer_id', 'asset_variant_id'])
                            ->with([
                                'asset' => function ($q) {
                                    $q->select(['id', 'display_name', 'model', 'year', 'make_id'])
                                        ->with(['make' => function ($mq) {
                                            $mq->select(['id', 'display_name']);
                                        }]);
                                },
                                'assetVariant' => fn ($vq) => $vq->select(['id', 'name', 'display_name']),
                            ]);
                    };
                } else {
                    $selectFields = ['id', 'display_name'];
                    if (! in_array($relationshipName, $relationships)) {
                        $relationships[$relationshipName] = function ($query) use ($selectFields) {
                            $query->select($selectFields);
                        };
                    }
                }
            }
        }

        $relationships['serviceItems'] = function ($query) {
            $query->where('inactive', false)->orderBy('sort_order')->orderBy('id');
        };

        $formSchema = $this->getFormSchema();
        if (isset($formSchema['sublists']) && is_array($formSchema['sublists'])) {
            foreach ($formSchema['sublists'] as $sublist) {
                if (! empty($sublist['modelRelationship'])) {
                    $relationships[$sublist['modelRelationship']] = function ($query) {
                        $query->select('*');
                    };
                }
            }
        }

        $record = ServiceTicket::with($relationships)->findOrFail($id);

        $record->service_items = $record->serviceItems->map(fn ($li) => [
            'id' => $li->id,
            'service_item_id' => $li->service_item_id,
            'display_name' => $li->display_name,
            'description' => $li->description,
            'quantity' => (float) $li->quantity,
            'unit_price' => (float) $li->unit_price,
            'unit_cost' => (float) $li->unit_cost,
            'estimated_hours' => (float) ($li->estimated_hours ?? 0),
            'actual_hours' => (float) ($li->actual_hours ?? 0),
            'billable' => $li->billable,
            'warranty' => $li->warranty,
            'warranty_type' => $li->warranty_type instanceof WarrantyCoverageType
                ? $li->warranty_type->value
                : ($li->warranty_type ?? null),
            'billable_to' => $li->billable_to ?? (! $li->warranty
                ? 'customer'
                : ((($li->warranty_type instanceof WarrantyCoverageType ? $li->warranty_type->value : $li->warranty_type) === 'manufacturer')
                    ? 'manufacturer'
                    : 'internal')),
            'billing_type' => $li->billing_type,
        ])->values()->all();

        $recordArray = $record->toArray();
        $recordArray['service_items'] = $record->service_items;
        $recordArray['pickup_delivery_requested_at'] = $record->pickup_delivery_requested_at?->format('Y-m-d');

        $enumOptions = $this->getEnumOptions();
        $enumOptions['billing_type'] = BillingType::options();
        $enumOptions['warranty_type'] = WarrantyCoverageType::options();

        $account = AccountSettings::getCurrent();

        return inertia('Tenant/ServiceTicket/Edit', [
            'record' => $recordArray,
            'formSchema' => $this->getFormSchema(),
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
        ]);
    }

    /**
     * Update the specified service ticket.
     */
    public function update(Request $request, $id)
    {
        $ticket = ServiceTicket::findOrFail($id);

        // Prevent updating if the service ticket has been approved/signed
        if ($ticket->approved || $ticket->signed_at || $ticket->customer_signature) {
            return redirect()->route('servicetickets.show', $id)
                ->with('error', 'This service ticket cannot be updated because it has already been approved and/or signed by the customer.');
        }

        $data = $request->all();
        $syncWorkOrderStatus = filter_var($data['sync_work_order_status'] ?? false, FILTER_VALIDATE_BOOLEAN);
        unset($data['sync_work_order_status']);
        $statusToSet = isset($data['status']) ? (int) $data['status'] : null;

        $ticket = $this->service->update($ticket, $data);

        if (
            $syncWorkOrderStatus
            && $statusToSet === ServiceTicketStatus::Completed->id()
            && (int) $ticket->status === $statusToSet
        ) {
            (new SyncServiceTicketCompletionToWorkOrders)($ticket);
        }

        return redirect()->route('servicetickets.show', $ticket->id);
    }

    /**
     * Lookup service items for the modal picker.
     */
    public function lookupServiceItems(Request $request)
    {
        $query = $request->get('search', '');
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);

        $serviceItems = ServiceItem::query()
            ->where('inactive', false)
            ->select([
                'id', 'display_name', 'code', 'description',
                'default_rate', 'default_cost', 'default_hours',
                'billable', 'warranty_eligible', 'warranty_type', 'billing_type',
            ])
            ->when($query, function ($q) use ($query) {
                $q->where(function ($subQuery) use ($query) {
                    $subQuery->where('display_name', 'like', "%{$query}%")
                        ->orWhere('code', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                });
            })
            ->orderBy('display_name')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'records' => $serviceItems->items(),
            'meta' => [
                'current_page' => $serviceItems->currentPage(),
                'last_page' => $serviceItems->lastPage(),
                'per_page' => $serviceItems->perPage(),
                'total' => $serviceItems->total(),
            ],
        ]);
    }

    /**
     * Helper: get unwrapped fields schema.
     */
    protected function getUnwrappedFieldsSchema()
    {
        $fieldsSchemaRaw = $this->getFieldsSchema();
        if (isset($fieldsSchemaRaw['fields'])) {
            return $fieldsSchemaRaw['fields'];
        }

        return $fieldsSchemaRaw ?? [];
    }

    /**
     * Eager loads for the index list (card/table views).
     *
     * @return array<string, mixed>
     */
    protected function indexEagerLoads(): array
    {
        return [
            'customer' => Customer::eagerWithContactSelect(),
            'location' => fn ($query) => $query->select(['id', 'display_name']),
            'subsidiary' => fn ($query) => $query->select(['id', 'display_name']),
            'assetUnit' => function ($query) {
                $query->select(['id', 'serial_number', 'hin', 'sku', 'asset_id', 'customer_id'])
                    ->with(['asset' => function ($q) {
                        $q->select(['id', 'display_name', 'model', 'year', 'make_id'])
                            ->with(['make' => function ($mq) {
                                $mq->select(['id', 'display_name']);
                            }]);
                    }]);
            },
        ];
    }

    /**
     * @return array{open: int, approved: int, in_progress: int, needs_reauth: int}
     */
    protected function indexStats(): array
    {
        $draft = ServiceTicketStatus::Draft->id();
        $open = ServiceTicketStatus::Open->id();
        $inProgress = ServiceTicketStatus::InProgress->id();
        $closed = ServiceTicketStatus::Closed->id();
        $cancelled = ServiceTicketStatus::Cancelled->id();

        $row = ServiceTicket::query()
            ->selectRaw(
                'sum(case when status in (?, ?, ?) then 1 else 0 end) as open_count,
                 sum(case when approved = ? and status not in (?, ?) then 1 else 0 end) as approved_count,
                 sum(case when status = ? then 1 else 0 end) as in_progress_count,
                 sum(case when requires_reauthorization = ? and approved = ? then 1 else 0 end) as needs_reauth_count',
                [$draft, $open, $inProgress, true, $closed, $cancelled, $inProgress, true, false],
            )
            ->first();

        return [
            'open' => (int) ($row->open_count ?? 0),
            'approved' => (int) ($row->approved_count ?? 0),
            'in_progress' => (int) ($row->in_progress_count ?? 0),
            'needs_reauth' => (int) ($row->needs_reauth_count ?? 0),
        ];
    }

    /**
     * PHP enum options only (no record dropdown queries). Enough for index filters and status badges.
     *
     * @return array<string, mixed>
     */
    protected function getEnumClassOptions(): array
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = [];

        foreach ($fieldsSchema as $fieldDef) {
            if (! isset($fieldDef['enum']) || empty($fieldDef['enum'])) {
                continue;
            }

            $enumClass = $fieldDef['enum'];
            if (class_exists($enumClass) && method_exists($enumClass, 'options')) {
                $enumOptions[$enumClass] = $enumClass::options();
            }
        }

        return $enumOptions;
    }

    /**
     * Record-picker options for forms (create/edit/show).
     *
     * @return array<string, list<array{id: mixed, name: string, value: mixed}>>
     */
    protected function getRecordEnumOptions(): array
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = [];
        $recordOptionsByModel = [];

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (($fieldDef['type'] ?? '') !== 'record' || ! isset($fieldDef['typeDomain'])) {
                continue;
            }

            $domainName = $fieldDef['typeDomain'];
            $modelClass = "App\\Domain\\{$domainName}\\Models\\{$domainName}";
            if (! class_exists($modelClass)) {
                continue;
            }

            try {
                $enumOptions[$fieldKey] = $this->recordSelectOptionsForDomain(
                    $domainName,
                    $modelClass,
                    $recordOptionsByModel,
                );
            } catch (\Exception $e) {
                \Log::warning("Failed to load record options for {$domainName}: ".$e->getMessage());
                $enumOptions[$fieldKey] = [];
            }
        }

        return $enumOptions;
    }

    /**
     * @param  array<class-string, list<array{id: mixed, name: string, value: mixed}>>  $recordOptionsByModel
     * @return list<array{id: mixed, name: string, value: mixed}>
     */
    protected function recordSelectOptionsForDomain(
        string $domainName,
        string $modelClass,
        array &$recordOptionsByModel,
    ): array {
        if ($domainName === 'Customer') {
            return Customer::queryOrderedByContactDisplayName()->get()
                ->map(fn ($record) => [
                    'id' => $record->id,
                    'name' => $record->display_name,
                    'value' => $record->id,
                ])->all();
        }

        if ($domainName === 'AssetUnit') {
            if (! array_key_exists($modelClass, $recordOptionsByModel)) {
                $recordOptionsByModel[$modelClass] = AssetUnit::query()
                    ->select(['id', 'serial_number', 'hin', 'sku', 'asset_id'])
                    ->with(['asset' => fn ($q) => $q->select(['id', 'display_name'])])
                    ->orderBy('id')
                    ->get()
                    ->map(fn ($record) => [
                        'id' => $record->id,
                        'name' => $record->display_name,
                        'value' => $record->id,
                    ])->all();
            }

            return $recordOptionsByModel[$modelClass];
        }

        if ($domainName === 'Qualification') {
            return $modelClass::query()
                ->select(['id', 'sequence'])
                ->orderBy('sequence')
                ->get()
                ->map(fn ($record) => [
                    'id' => $record->id,
                    'name' => $record->display_name,
                    'value' => $record->id,
                ])->all();
        }

        if (! array_key_exists($modelClass, $recordOptionsByModel)) {
            $recordOptionsByModel[$modelClass] = static::loadRecordSelectOptions($modelClass);
        }

        return $recordOptionsByModel[$modelClass];
    }

    /**
     * Helper: get enum options from fields schema.
     */
    protected function getEnumOptions(): array
    {
        return array_merge($this->getEnumClassOptions(), $this->getRecordEnumOptions());
    }

    /**
     * Send service ticket approval request to customer via email and optional SMS.
     */
    public function sendApprovalRequest(Request $request, $id, SmsService $smsService, TenantMailService $tenantMail)
    {
        $validated = $request->validate([
            'delivery' => 'required|string|in:email,email_sms',
        ]);

        try {
            $serviceTicket = ServiceTicket::with([
                'customer' => Customer::eagerWithContactSelect(['email', 'phone', 'mobile']),
                'subsidiary',
                'location',
                'assetUnit',
                'serviceItems',
            ])->findOrFail($id);

            $customerEmail = $serviceTicket->customer?->email;
            $account = AccountSettings::getCurrent();

            $tenant = tenant();
            $domain = $tenant?->domains->first()?->domain;
            if (! $domain) {
                return redirect()
                    ->route('servicetickets.show', $serviceTicket->id)
                    ->withErrors(['error' => 'Unable to resolve tenant domain.']);
            }

            $approvalUrl = "https://{$domain}/service-tickets/{$serviceTicket->uuid}/review";

            $mailable = new ServiceTicketApprovalRequest([
                'service_ticket' => $serviceTicket,
                'account' => $account,
                'approval_url' => $approvalUrl,
            ]);

            if (! $tenantMail->canSend($customerEmail, $mailable, $request->user())) {
                return redirect()
                    ->route('servicetickets.show', $serviceTicket->id)
                    ->withErrors(['error' => $tenantMail->validationErrorMessage($mailable)]);
            }

            if ($validated['delivery'] === 'email_sms') {
                $offer = $smsService->serviceTicketApprovalSmsCanBeOffered($serviceTicket->customer, $request->user());
                if (! $offer['offered']) {
                    return redirect()
                        ->route('servicetickets.show', $serviceTicket->id)
                        ->withErrors([
                            'delivery' => $offer['hint'] ?? 'SMS is not available for this send.',
                        ]);
                }
            }

            $tenantMail->send($customerEmail, $mailable, $request->user());

            $emailTarget = $tenantMail->displayRecipient($customerEmail, $mailable, $request->user());

            $smsNote = '';
            if ($validated['delivery'] === 'email_sms') {
                $result = $smsService->sendServiceTicketApprovalSms(
                    $request->user(),
                    $serviceTicket->customer,
                    $serviceTicket,
                    $approvalUrl,
                );
                if (! $result->success && ($result->status ?? '') === 'not_implemented') {
                    $smsNote = ' SMS is not wired yet (Twilio transport); only email was delivered.';
                } elseif (! $result->success) {
                    return redirect()
                        ->route('servicetickets.show', $serviceTicket->id)
                        ->with('success', "Approval request sent to {$emailTarget}.")
                        ->with('error', 'Email was sent, but SMS failed: '.($result->error ?? 'Unknown error'));
                } else {
                    $smsNote = ' A text message was also sent.';
                }
            }

            return redirect()
                ->route('servicetickets.show', $serviceTicket->id)
                ->with('success', 'Approval request sent successfully to '.$emailTarget.$smsNote);
        } catch (\Exception $e) {
            \Log::error('Failed to send approval request email: '.$e->getMessage());

            return redirect()
                ->route('servicetickets.show', $id)
                ->withErrors(['error' => 'Failed to send email. Please try again.']);
        }
    }

    /**
     * Get approval URL for preview
     */
    public function getApprovalUrl($id)
    {
        $serviceTicket = ServiceTicket::findOrFail($id);

        return response()->json([
            'approval_url' => route('service-tickets.review', $serviceTicket->uuid),
        ]);
    }
}
