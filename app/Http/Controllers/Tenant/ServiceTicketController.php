<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\HasSchemaSupport;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\ServiceTicketServiceItem\Models\ServiceTicketServiceItem;
use App\Services\ServiceTicketService;
use App\Enums\Timezone;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;

class ServiceTicketController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests, HasSchemaSupport;

    protected $domainName = 'ServiceTicket';
    protected $recordModel;
    protected ServiceTicketService $service;

    public function __construct(ServiceTicketService $service)
    {
        $this->middleware('auth');
        $this->recordModel = new ServiceTicket();
        $this->service = $service;
    }

    /**
     * Display a listing of service tickets.
     */
    public function index(Request $request)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        // Ensure asset unit relationship is loaded for display
        $relationships['assetUnit'] = function ($query) {
            $query->select(['id', 'display_name', 'serial_number', 'hin', 'sku', 'asset_id', 'customer_id'])
                  ->with(['asset' => function ($q) {
                      $q->select(['id', 'display_name']);
                  }]);
        };

        $query = ServiceTicket::with($relationships);

        // Apply search
        $searchQuery = $request->get('search');
        if ($searchQuery && !empty(trim($searchQuery))) {
            $query->where(function ($q) use ($searchQuery) {
                $q->whereRaw('LOWER(display_name) LIKE ?', ['%' . strtolower(trim($searchQuery)) . '%'])
                  ->orWhereRaw('LOWER(repair_description) LIKE ?', ['%' . strtolower(trim($searchQuery)) . '%'])
                  ->orWhere('uuid', 'like', '%' . trim($searchQuery) . '%');
            });
        }

        // Apply status filter
        $statusParam = $request->get('status');
        if ($statusParam && $statusParam !== 'all') {
            $query->where('status', $statusParam);
        }

        // Apply expedite filter
        $expediteParam = $request->get('expedite');
        if ($expediteParam === '1') {
            $query->where('expedite', true);
        }

        // Apply approved filter
        $approvedParam = $request->get('approved');
        if ($approvedParam !== null && $approvedParam !== 'all') {
            $query->where('approved', $approvedParam === '1');
        }

        $query->orderBy('created_at', 'desc');
        $perPage = $request->get('per_page', 15);
        $records = $query->paginate($perPage);

        // Stats
        $stats = [
            'open' => ServiceTicket::whereIn('status', [1, 2, 3])->count(),
            'approved' => ServiceTicket::where('approved', true)->whereNotIn('status', [6, 7, 8])->count(),
            'in_progress' => ServiceTicket::where('status', 5)->count(),
            'needs_reauth' => ServiceTicket::where('requires_reauthorization', true)->where('approved', false)->count(),
        ];

        return inertia('Tenant/ServiceTicket/Index', [
            'records' => $records,
            'schema' => $this->getTableSchema(),
            'formSchema' => $this->getFormSchema(),
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'stats' => $stats,
            'filters' => [
                'status' => $statusParam ?? 'all',
                'expedite' => $expediteParam ?? null,
                'approved' => $approvedParam ?? 'all',
                'search' => $searchQuery ?? '',
            ],
        ]);
    }

    /**
     * Show the form for creating a new service ticket.
     */
    public function create(Request $request)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();
        $enumOptions['billing_type'] = \App\Enums\ServiceItem\BillingType::options();

        $account = \App\Models\AccountSettings::getCurrent();

        return inertia('Tenant/ServiceTicket/Create', [
            'formSchema' => $this->getFormSchema(),
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
        ]);
    }

    /**
     * Store a newly created service ticket.
     */
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'subsidiary_id' => 'required|exists:subsidiaries,id',
            'location_id' => 'required|exists:locations,id',
        ]);

        $ticket = $this->service->create($request->all());

        return redirect()->route('servicetickets.show', $ticket->id);
    }

    /**
     * Display the specified service ticket.
     */
    public function show(Request $request, $id)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        $relationships['assetUnit'] = function ($query) {
            $query->select(['id', 'display_name', 'serial_number', 'hin', 'sku', 'asset_id', 'customer_id'])
                  ->with(['asset' => function ($q) {
                      $q->select(['id', 'display_name']);
                  }]);
        };

        $relationships['serviceItems'] = function ($query) {
            $query->where('inactive', false)->orderBy('sort_order')->orderBy('id');
        };

        $record = ServiceTicket::with($relationships)->findOrFail($id);

        $recordArray = $record->toArray();
        $recordArray['created_at'] = $record->created_at?->toISOString();
        $recordArray['updated_at'] = $record->updated_at?->toISOString();
        $recordArray['signed_at'] = $record->signed_at?->toISOString();
        $recordArray['reauthorized_at'] = $record->reauthorized_at?->toISOString();

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
            'billing_type' => $li->billing_type,
        ])->values()->all();

        $enumOptions = $this->getEnumOptions();
        $enumOptions['billing_type'] = \App\Enums\ServiceItem\BillingType::options();

        $account = \App\Models\AccountSettings::getCurrent();

        return inertia('Tenant/ServiceTicket/Show', [
            'record' => $recordArray,
            'formSchema' => $this->getFormSchema(),
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
        ]);
    }

    /**
     * Show the form for editing the specified service ticket.
     */
    public function edit($id)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        // Load record relationships for display
        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);

                if ($fieldDef['typeDomain'] === 'AssetUnit') {
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'display_name', 'serial_number', 'hin', 'sku', 'asset_id', 'customer_id'])
                              ->with(['asset' => function ($q) {
                                  $q->select(['id', 'display_name']);
                              }]);
                    };
                } else {
                    $selectFields = ['id', 'display_name'];
                    if (!in_array($relationshipName, $relationships)) {
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
            'billing_type' => $li->billing_type,
        ])->values()->all();

        $enumOptions = $this->getEnumOptions();
        $enumOptions['billing_type'] = \App\Enums\ServiceItem\BillingType::options();

        $account = \App\Models\AccountSettings::getCurrent();

        return inertia('Tenant/ServiceTicket/Edit', [
            'record' => $record,
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
        $this->service->update($ticket, $request->all());

        return redirect()->route('servicetickets.show', $id);
    }

    /**
     * Remove the specified service ticket.
     */
    public function destroy($id)
    {
        $ticket = ServiceTicket::findOrFail($id);
        $this->service->delete($ticket);

        return redirect()->route('servicetickets.index');
    }

    /**
     * Lookup service items for the modal picker.
     */
    public function lookupServiceItems(Request $request)
    {
        $query = $request->get('search', '');
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);

        $serviceItems = \App\Domain\ServiceItem\Models\ServiceItem::query()
            ->where('inactive', false)
            ->select([
                'id', 'display_name', 'code', 'description',
                'default_rate', 'default_cost', 'default_hours',
                'billable', 'warranty_eligible', 'billing_type'
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
            ]
        ]);
    }

    /**
     * Get tax rate for a location.
     */
    public function getLocationTaxRate(Request $request)
    {
        $locationId = $request->get('location_id');

        if (!$locationId) {
            return response()->json(['tax_rate' => null]);
        }

        $location = \App\Domain\Location\Models\Location::find($locationId);

        if (!$location) {
            return response()->json(['tax_rate' => null]);
        }

        $taxRateService = app(\App\Services\TaxRateService::class);
        $taxRate = $taxRateService->getTaxRate($location);

        return response()->json([
            'tax_rate' => $taxRate ? $taxRate * 100 : null
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
     * Helper: get enum options from fields schema.
     */
    protected function getEnumOptions(): array
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = [];

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['enum']) && !empty($fieldDef['enum'])) {
                $enumClass = $fieldDef['enum'];
                if (class_exists($enumClass) && method_exists($enumClass, 'options')) {
                    $enumOptions[$enumClass] = $enumClass::options();
                }
            }

            if (($fieldDef['type'] ?? '') === 'record' && isset($fieldDef['typeDomain'])) {
                $domainName = $fieldDef['typeDomain'];
                $modelClass = "App\\Domain\\{$domainName}\\Models\\{$domainName}";
                if (class_exists($modelClass)) {
                    try {
                        $records = $modelClass::select('id', 'display_name')->get();
                        $enumOptions[$fieldKey] = $records->map(fn ($r) => [
                            'id' => $r->id,
                            'name' => $r->display_name,
                            'value' => $r->id,
                        ])->toArray();
                    } catch (\Exception $e) {
                        \Log::warning("Failed to load record options for {$domainName}: " . $e->getMessage());
                        $enumOptions[$fieldKey] = [];
                    }
                }
            }
        }

        return $enumOptions;
    }
}