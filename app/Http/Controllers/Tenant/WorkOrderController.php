<?php
namespace App\Http\Controllers\Tenant;

use App\Actions\PublicStorage;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\WorkOrder\Models\WorkOrder as RecordModel;
use App\Domain\WorkOrder\Actions\CreateWorkOrder as CreateAction;
use App\Domain\WorkOrder\Actions\UpdateWorkOrder as UpdateAction;
use App\Domain\WorkOrder\Actions\DeleteWorkOrder as DeleteAction;
use App\Enums\RecordType;
use App\Enums\Timezone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkOrderController extends RecordController
{
    public function __construct(Request $request)
    {
        $recordType = RecordType::WorkOrder;
        parent::__construct(
            $request,
            $recordType->plural(),
            $recordType->title(),
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            'WorkOrder' // Explicitly set domain name
        );
    }

    public function index(Request $request)
    {
        // Get current user first
        $currentUser = Auth::user();
        
        // Get base data from parent controller logic
        $columns = $this->getSchemaColumns();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $schema = $this->getTableSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();

        // Separate actual database columns from relationship columns
        $actualColumns = [];
        $relationshipColumns = [];

        foreach ($columns as $column) {
            if (strpos($column, '.') !== false) {
                // This is a relationship column like "asset.display_name"
                $relationshipColumns[] = $column;
            } else {
                // This is an actual database column
                $actualColumns[] = $column;
            }
        }

        if (!in_array('id', $actualColumns)) {
            $actualColumns[] = 'id';
        }

        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        // Load relationships needed for display names
        if ($this->domainName === 'AssetUnit') {
            $relationships['asset'] = function ($query) {
                $query->select(['id', 'display_name']);
            };
        } elseif ($this->domainName === 'InventoryUnit') {
            $relationships['inventoryItem'] = function ($query) {
                $query->select(['id', 'display_name']);
            };
        }

        // Load WorkOrder-specific relationships
        $relationships['assignedUser'] = function ($query) {
            $query->select(['id', 'display_name']);
        };

        $query = $this->recordModel->select($actualColumns)->with($relationships);

        // Apply search
        $searchQuery = $request->get('search');
        if ($searchQuery && !empty(trim($searchQuery))) {
            $query->whereRaw('LOWER(display_name) LIKE ?', ['%' . strtolower(trim($searchQuery)) . '%']);
        }

        // Apply user filtering - DEFAULT to current user if no filter is set
        $userParam = $request->get('user');
        
        // If no user parameter is provided, default to current user
        if ($userParam === null) {
            $query->where('assigned_user_id', $currentUser->id);
        } elseif ($userParam !== 'all') {
            // If a specific user is selected, filter by that user
            $query->where('assigned_user_id', $userParam);
        }
        // If 'all' is selected, don't apply any user filter

        // Apply status filtering
        $statusParam = $request->get('status');
        if ($statusParam && $statusParam !== 'all') {
            $query->where('status', $statusParam);
        }

        // Apply priority filtering
        $priorityParam = $request->get('priority');
        if ($priorityParam && $priorityParam !== 'all') {
            $query->where('priority', $priorityParam);
        }

        $query->orderBy('scheduled_start_at', 'asc')->orderBy('due_at', 'asc');
        $perPage = $request->get('per_page', 15);
        $records = $query->paginate($perPage);
// dd($records);
        // Get other users (excluding current user)
        $users = \App\Domain\User\Models\User::select('id', 'display_name')
            ->where('id', '!=', $currentUser->id)
            ->orderBy('display_name')
            ->get();

        // Calculate dynamic stats based on current filters
        $baseQuery = \App\Domain\WorkOrder\Models\WorkOrder::query();

        // Apply user filter if specified
        $userParam = $request->get('user');
        if ($userParam && $userParam !== 'all') {
            $baseQuery->where('assigned_user_id', $userParam);
        }

        // Apply status filter if specified
        $statusParam = $request->get('status');
        if ($statusParam && $statusParam !== 'all') {
            $baseQuery->where('status', $statusParam);
        }

        // Apply priority filter if specified
        $priorityParam = $request->get('priority');
        if ($priorityParam && $priorityParam !== 'all') {
            $baseQuery->where('priority', $priorityParam);
        }

        // Calculate stats based on filtered data
        $stats = [
            'open' => (clone $baseQuery)->whereIn('status', [2, 3, 4, 5])->count(), // Open, Scheduled, In Progress, Waiting
            'in_progress' => (clone $baseQuery)->where('status', 4)->count(), // In Progress status
            'overdue' => (clone $baseQuery)->where('due_at', '<', now())->whereNotIn('status', [7, 8])->count(), // Not Completed or Closed
            'completed_week' => (clone $baseQuery)->where('status', 7)->where('updated_at', '>=', now()->startOfWeek())->count(), // Completed this week
        ];

        $pluralTitle = \Illuminate\Support\Str::plural($this->recordTitle);

        return inertia('Tenant/' . $this->domainName . '/Index', [
            'records' => $records,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'pluralTitle' => $pluralTitle,
            'schema' => $schema,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'currentUser' => $currentUser,
            'users' => $users,
            'stats' => $stats,
            'filters' => [
                'user' => $userParam ?? $currentUser->id, // Pass the current filter value to the frontend
                'status' => $statusParam ?? 'all',
                'priority' => $priorityParam ?? 'all',
            ],
        ]);
    }

    public function edit($id)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        // Add record type relationships with id, display_name, and custom displayField
        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                $selectFields = ['id'];

                if ($fieldDef['typeDomain'] === 'AssetUnit') {
                    $selectFields = ['id', 'serial_number', 'hin', 'sku', 'asset_id'];
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'serial_number', 'hin', 'sku', 'asset_id'])
                              ->with(['asset' => function ($q) {
                                  $q->select(['id', 'display_name']);
                              }]);
                    };
                } else {
                    $selectFields[] = 'display_name';
                }

                if (isset($fieldDef['displayField']) && $fieldDef['displayField'] !== 'display_name') {
                    $selectFields[] = $fieldDef['displayField'];
                }

                $selectFields = array_unique($selectFields);

                if (!isset($relationships[$relationshipName])) {
                    $relationships[$relationshipName] = function ($query) use ($selectFields) {
                        $query->select($selectFields);
                    };
                }
            }
        }

        $relationships['serviceItems'] = function ($query) {
            $query->orderBy('sort_order')->orderBy('id');
        };

        $record = $this->recordModel->with($relationships)->findOrFail($id);

        // Add service_items in the expected format for the form
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

        // Service items are now loaded on-demand via the paginated modal

        $enumOptions = $this->getEnumOptions();
        $enumOptions['billing_type'] = \App\Enums\ServiceItem\BillingType::options();

        // Load service ticket data if linked
        $serviceTicket = null;
        if ($record->service_ticket_id) {
            $ticket = \App\Domain\ServiceTicket\Models\ServiceTicket::find($record->service_ticket_id);
            if ($ticket) {
                $serviceTicket = [
                    'id' => $ticket->id,
                    'service_ticket_number' => $ticket->service_ticket_number,
                    'estimated_total' => (float) ($ticket->estimated_total ?? 0),
                    'estimated_subtotal' => (float) ($ticket->estimated_subtotal ?? 0),
                    'estimated_tax' => (float) ($ticket->estimated_tax ?? 0),
                    'tax_rate' => (float) ($ticket->tax_rate ?? 0),
                ];
            }
        }

        $account = \App\Models\AccountSettings::getCurrent();

        return inertia('Tenant/' . $this->domainName . '/Edit', [
            'record' => $record,
            'recordType' => $this->recordType,
            'formSchema' => $this->getFormSchema(),
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'imageUrls' => $this->getImageUrls($record, $fieldsSchema),
            'account' => $account,
            'timezones' => Timezone::options(),
            'serviceItems' => [], // Service items are now loaded on-demand via paginated modal
            'serviceTicket' => $serviceTicket,
            'estimateThreshold' => (float) ($account->estimate_threshold_percent ?? 20),
        ]);
    }

    public function create()
    {
        // Get base data from parent controller
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();

        // Get current user for default assignment
        $currentUser = Auth::user();

        // Get other users for assignment dropdown
        $users = \App\Domain\User\Models\User::select('id', 'display_name')
            ->where('id', '!=', $currentUser->id)
            ->orderBy('display_name')
            ->get();

        // Get account settings for timezone display (cached)
        $account = \App\Models\AccountSettings::getCurrent();

        // Service items are now loaded on-demand via the paginated modal

        $enumOptions = $this->getEnumOptions();
        $enumOptions['billing_type'] = \App\Enums\ServiceItem\BillingType::options();

        // Check if creating from a service ticket
        $serviceTicket = null;
        $serviceTicketItems = [];
        $serviceTicketId = request()->get('service_ticket_id');

        if ($serviceTicketId) {
            $ticket = \App\Domain\ServiceTicket\Models\ServiceTicket::with([
                'serviceItems' => function ($query) {
                    $query->where('inactive', false)->orderBy('sort_order')->orderBy('id');
                },
                'customer',
                'subsidiary',
                'location',
                'assetUnit' => function ($query) {
                    $query->select(['id', 'serial_number', 'hin', 'sku', 'asset_id', 'customer_id'])
                          ->with(['asset' => function ($q) {
                              $q->select(['id', 'display_name']);
                          }]);
                },
            ])->find($serviceTicketId);

            if ($ticket) {
                $serviceTicket = [
                    'id' => $ticket->id,
                    'service_ticket_number' => $ticket->service_ticket_number,
                    'customer_id' => $ticket->customer_id,
                    'subsidiary_id' => $ticket->subsidiary_id,
                    'location_id' => $ticket->location_id,
                    'asset_unit_id' => $ticket->asset_unit_id,
                    'estimated_total' => (float) ($ticket->estimated_total ?? 0),
                    'estimated_subtotal' => (float) ($ticket->estimated_subtotal ?? 0),
                    'estimated_tax' => (float) ($ticket->estimated_tax ?? 0),
                    'tax_rate' => (float) ($ticket->tax_rate ?? 0),
                    'repair_description' => $ticket->repair_description,
                    'customer' => $ticket->customer,
                    'subsidiary' => $ticket->subsidiary,
                    'location' => $ticket->location,
                    'asset_unit' => $ticket->assetUnit,
                ];

                $serviceTicketItems = $ticket->serviceItems->map(fn ($li) => [
                    'service_item_id' => $li->service_item_id,
                    'display_name' => $li->display_name,
                    'description' => $li->description,
                    'quantity' => (float) $li->quantity,
                    'unit_price' => (float) $li->unit_price,
                    'unit_cost' => (float) $li->unit_cost,
                    'estimated_hours' => (float) ($li->estimated_hours ?? 0),
                    'actual_hours' => 0,
                    'billable' => $li->billable,
                    'warranty' => $li->warranty,
                    'billing_type' => $li->billing_type,
                ])->values()->all();
            }
        }

        return inertia('Tenant/' . $this->domainName . '/Create', [
            'recordType' => $this->recordType,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'currentUser' => $currentUser,
            'users' => $users,
            'account' => $account,
            'timezones' => Timezone::options(),
            'serviceItems' => [], // Service items are now loaded on-demand via paginated modal
            'serviceTicket' => $serviceTicket,
            'serviceTicketItems' => $serviceTicketItems,
            'estimateThreshold' => (float) ($account->estimate_threshold_percent ?? 20),
        ]);
    }

    public function show(Request $request, $id)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();

        // Build relationships array including both morph and record types
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        // Load WorkOrder-specific relationships
        $relationships['assignedUser'] = function ($query) {
            $query->select(['id', 'display_name']);
        };

        $relationships['requested_by_user'] = function ($query) {
            $query->select(['id', 'display_name']);
        };

        // Load the record with relationships including service items (WorkOrderServiceItem)
        $relationships['serviceItems'] = function ($query) {
            $query->orderBy('sort_order')->orderBy('id');
        };
        $record = $this->recordModel
            ->with($relationships)
            ->findOrFail($id);

        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();

        // Get account settings for timezone display (cached)
        $account = \App\Models\AccountSettings::getCurrent();

        // Service items are now loaded on-demand via the paginated modal

        // Ensure timestamps and service_items are included in the record
        $recordArray = $record->toArray();
        $recordArray['created_at'] = $record->created_at?->toISOString();
        $recordArray['updated_at'] = $record->updated_at?->toISOString();
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

        // Load service ticket data if linked
        $serviceTicket = null;
        if ($record->service_ticket_id) {
            $ticket = \App\Domain\ServiceTicket\Models\ServiceTicket::find($record->service_ticket_id);
            if ($ticket) {
                $serviceTicket = [
                    'id' => $ticket->id,
                    'service_ticket_number' => $ticket->service_ticket_number,
                    'estimated_total' => (float) ($ticket->estimated_total ?? 0),
                    'estimated_subtotal' => (float) ($ticket->estimated_subtotal ?? 0),
                    'estimated_tax' => (float) ($ticket->estimated_tax ?? 0),
                    'tax_rate' => (float) ($ticket->tax_rate ?? 0),
                ];
            }
        }

        return inertia('Tenant/' . $this->domainName . '/Show', [
            'record' => $recordArray,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'domainName' => $this->domainName,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
            'serviceItems' => [], // Service items are now loaded on-demand via paginated modal
            'serviceTicket' => $serviceTicket,
            'estimateThreshold' => (float) ($account->estimate_threshold_percent ?? 20),
        ]);
    }

    /**
     * Store a newly created work order (POST /workorders)
     * Add custom logic for line items (service_items) here.
     */
    public function store(Request $request, PublicStorage $publicStorage)
    {
        $data = $request->all();
        $serviceItems = $data['service_items'] ?? [];
        unset($data['service_items']);

        // Validate threshold if linked to a service ticket
        $serviceTicketId = $data['service_ticket_id'] ?? null;
        if ($serviceTicketId) {
            $thresholdError = $this->validateThreshold($serviceTicketId, $serviceItems, $data['tax_rate'] ?? 0);
            if ($thresholdError) {
                return back()->withErrors(['threshold' => $thresholdError]);
            }
        }

        // Create the work order first
        $request->merge($data);
        $response = parent::store($request, $publicStorage);

        // If successful, always recalculate work order totals
        if ($response->getStatusCode() === 302) {
            // Extract work order ID from redirect URL
            $location = $response->getTargetUrl();
            if (preg_match('/\/workorders\/(\d+)/', $location, $matches)) {
                $workOrderId = $matches[1];
                $calculator = app(\App\Services\WorkOrderCalculator::class);
                $workOrder = \App\Domain\WorkOrder\Models\WorkOrder::find($workOrderId);
                if ($workOrder) {
                    if (!empty($serviceItems)) {
                        $this->createServiceItems($workOrderId, $serviceItems);
                    } else {
                        // Recalculate totals even without service items
                        $calculator->recalculateWorkOrder($workOrder);
                    }
                }
            }
        }

        return $response;
    }

    /**
     * Update the specified work order (PUT/PATCH /workorders/{id})
     * Add custom logic for line items (service_items) here.
     */
    public function update(Request $request, $id, PublicStorage $publicStorage)
    {
        $data = $request->all();
        $serviceItems = $data['service_items'] ?? [];
        unset($data['service_items']);

        // Validate threshold if linked to a service ticket
        $workOrder = \App\Domain\WorkOrder\Models\WorkOrder::find($id);
        $serviceTicketId = $data['service_ticket_id'] ?? $workOrder->service_ticket_id ?? null;
        if ($serviceTicketId) {
            $thresholdError = $this->validateThreshold($serviceTicketId, $serviceItems, $data['tax_rate'] ?? 0);
            if ($thresholdError) {
                return back()->withErrors(['threshold' => $thresholdError]);
            }
        }

        $request->merge($data);
        $response = parent::update($request, $id, $publicStorage);

        // Always recalculate work order totals after update
        $calculator = app(\App\Services\WorkOrderCalculator::class);
        $workOrder = \App\Domain\WorkOrder\Models\WorkOrder::find($id);
        if ($workOrder) {
            // Update service items if provided
            if (!empty($serviceItems)) {
                $this->updateServiceItems($id, $serviceItems);
            } else {
                // Recalculate totals even without service item changes
                $calculator->recalculateWorkOrder($workOrder);
            }
        }

        return $response;
    }

    public function lookupServiceItems(Request $request)
    {
        $query = $request->get('search', '');
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);

        $serviceItems = \App\Domain\ServiceItem\Models\ServiceItem::query()
            ->where('inactive', false)
            ->select([
                'id',
                'display_name',
                'code',
                'description',
                'default_rate',
                'default_cost',
                'default_hours',
                'billable',
                'warranty_eligible',
                'billing_type'
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
     * Get tax rate for a location
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

        // Return as percentage for the frontend
        return response()->json([
            'tax_rate' => $taxRate ? $taxRate * 100 : null
        ]);
    }

    /**
     * Validate that the work order total does not exceed the service ticket estimate threshold.
     * Returns an error message string if over threshold, null if OK.
     */
    protected function validateThreshold(int $serviceTicketId, array $serviceItems, float $taxRate): ?string
    {
        $ticket = \App\Domain\ServiceTicket\Models\ServiceTicket::find($serviceTicketId);
        if (!$ticket) {
            return null; // No ticket to validate against
        }

        $account = \App\Models\AccountSettings::getCurrent();
        $thresholdPercent = (float) ($account->estimate_threshold_percent ?? 20);
        $estimatedTotal = (float) ($ticket->estimated_total ?? 0);

        if ($estimatedTotal <= 0) {
            return null; // No estimate to compare against
        }

        // Calculate the WO total from the submitted service items
        $subtotal = 0;
        foreach ($serviceItems as $item) {
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 1);
            $estimatedHours = (float) ($item['estimated_hours'] ?? 0);
            $billingType = (int) ($item['billing_type'] ?? 3);
            $billable = $item['billable'] ?? true;
            $warranty = $item['warranty'] ?? false;

            if (!$billable || $warranty) {
                continue;
            }

            $lineTotal = 0;
            switch ($billingType) {
                case 1: // Hourly
                    $lineTotal = $estimatedHours * $unitPrice;
                    break;
                case 2: // Flat
                    $lineTotal = $unitPrice;
                    break;
                case 3: // Quantity
                default:
                    $lineTotal = $quantity * $unitPrice;
                    break;
            }

            $subtotal += $lineTotal;
        }

        $tax = $subtotal * ($taxRate / 100);
        $woTotal = $subtotal + $tax;
        $thresholdLimit = $estimatedTotal * (1 + ($thresholdPercent / 100));

        if ($woTotal > $thresholdLimit) {
            return "Work order total (\${$woTotal}) exceeds the service ticket estimate threshold of \${$thresholdLimit} ({$thresholdPercent}% over \${$estimatedTotal}). A service ticket revision must be created before saving.";
        }

        return null;
    }

    /**
     * Get the ID of the work order that was just created
     */
    protected function getCreatedWorkOrderId(Request $request, $response)
    {
        // Try to get from session flash data first (set by parent controller)
        $workOrderId = session('created_record_id');
        if ($workOrderId) {
            return $workOrderId;
        }

        // Fallback: get the latest work order for current tenant
        $workOrder = \App\Domain\WorkOrder\Models\WorkOrder::latest()->first();
        return $workOrder ? $workOrder->id : null;
    }

    /**
     * Create service items for a newly created work order
     */
    protected function createServiceItems(int $workOrderId, array $serviceItems)
    {
        $calculator = app(\App\Services\WorkOrderCalculator::class);
        $workOrder = \App\Domain\WorkOrder\Models\WorkOrder::find($workOrderId);

        foreach ($serviceItems as $index => $itemData) {
            $lineItem = \App\Domain\WorkOrderServiceItem\Models\WorkOrderServiceItem::create([
                'work_order_id' => $workOrderId,
                'service_item_id' => $itemData['service_item_id'] ?? null,
                'display_name' => $itemData['display_name'] ?? '',
                'description' => $itemData['description'] ?? '',
                'quantity' => $itemData['quantity'] ?? 1,
                'unit_price' => $itemData['unit_price'] ?? 0,
                'unit_cost' => $itemData['unit_cost'] ?? 0,
                'estimated_hours' => $itemData['estimated_hours'] ?? 0,
                'actual_hours' => $itemData['actual_hours'] ?? 0,
                'billable' => $itemData['billable'] ?? true,
                'warranty' => $itemData['warranty'] ?? false,
                'billing_type' => $itemData['billing_type'] ?? null,
                'sort_order' => $itemData['sort_order'] ?? $index,
            ]);

            // Recalculate the line item using the service
            $calculator->recalculateLineItem($lineItem);
        }

        // Recalculate work order totals
        $calculator->recalculateWorkOrder($workOrder);
    }

    /**
     * Update service items for an existing work order
     */
    protected function updateServiceItems(int $workOrderId, array $serviceItems)
    {
        $calculator = app(\App\Services\WorkOrderCalculator::class);
        $workOrder = \App\Domain\WorkOrder\Models\WorkOrder::find($workOrderId);

        // Delete existing service items
        \App\Domain\WorkOrderServiceItem\Models\WorkOrderServiceItem::where('work_order_id', $workOrderId)->delete();

        // Create new ones and recalculate
        foreach ($serviceItems as $index => $itemData) {
            $lineItem = \App\Domain\WorkOrderServiceItem\Models\WorkOrderServiceItem::create([
                'work_order_id' => $workOrderId,
                'service_item_id' => $itemData['service_item_id'] ?? null,
                'display_name' => $itemData['display_name'] ?? '',
                'description' => $itemData['description'] ?? '',
                'quantity' => $itemData['quantity'] ?? 1,
                'unit_price' => $itemData['unit_price'] ?? 0,
                'unit_cost' => $itemData['unit_cost'] ?? 0,
                'estimated_hours' => $itemData['estimated_hours'] ?? 0,
                'actual_hours' => $itemData['actual_hours'] ?? 0,
                'billable' => $itemData['billable'] ?? true,
                'warranty' => $itemData['warranty'] ?? false,
                'billing_type' => $itemData['billing_type'] ?? null,
                'sort_order' => $itemData['sort_order'] ?? $index,
            ]);

            // Recalculate the line item using the service
            $calculator->recalculateLineItem($lineItem);
        }

        // Recalculate work order totals
        $calculator->recalculateWorkOrder($workOrder);
    }

    public function preview(Request $request, $id)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();

        // Build relationships array including both morph and record types
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        // Load WorkOrder-specific relationships
        $relationships['assignedUser'] = function ($query) {
            $query->select(['id', 'display_name']);
        };

        $relationships['requested_by_user'] = function ($query) {
            $query->select(['id', 'display_name']);
        };

        // Load the record with relationships including service items (WorkOrderServiceItem)
        $relationships['serviceItems'] = function ($query) {
            $query->orderBy('sort_order')->orderBy('id');
        };
        $record = $this->recordModel
            ->with($relationships)
            ->findOrFail($id);

        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();

        // Get account settings for timezone display (cached)
        $account = \App\Models\AccountSettings::getCurrent();

        // Service items are now loaded on-demand via the paginated modal

        // Ensure timestamps and service_items are included in the record
        $recordArray = $record->toArray();
        $recordArray['created_at'] = $record->created_at?->toISOString();
        $recordArray['updated_at'] = $record->updated_at?->toISOString();
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

        return inertia('Tenant/' . $this->domainName . '/Public', [
            'record' => $recordArray,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'domainName' => $this->domainName,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
            'serviceItems' => [], // Service items are now loaded on-demand via paginated modal
        ]);
    }

}
