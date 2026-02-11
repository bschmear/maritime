<?php
namespace App\Http\Controllers\Tenant;
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

        $query->orderBy('created_at', 'desc');
        $perPage = $request->get('per_page', 15);
        $records = $query->paginate($perPage);

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
        ])->values()->all();

        $serviceItems = \App\Domain\ServiceItem\Models\ServiceItem::query()
            ->where('inactive', false)
            ->orderBy('display_name')
            ->get()
            ->map(function ($si) {
                $defaults = $si->toWorkOrderDefaults();
                $billingType = \App\Enums\ServiceItem\BillingType::tryFrom($si->billing_type);
                return array_merge(
                    ['id' => $si->id, 'display_name' => $si->display_name, 'code' => $si->code ?? ''],
                    $defaults,
                    ['billing_type_label' => $billingType?->label() ?? 'Unknown']
                );
            })
            ->values()
            ->all();

        return inertia('Tenant/' . $this->domainName . '/Edit', [
            'record' => $record,
            'recordType' => $this->recordType,
            'formSchema' => $this->getFormSchema(),
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $this->getEnumOptions(),
            'imageUrls' => $this->getImageUrls($record, $fieldsSchema),
            'account' => \App\Models\AccountSettings::getCurrent(),
            'timezones' => Timezone::options(),
            'serviceItems' => $serviceItems,
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

        // Get active service items for line item selection (with WorkOrderServiceItem defaults)
        $serviceItems = \App\Domain\ServiceItem\Models\ServiceItem::query()
            ->where('inactive', false)
            ->orderBy('display_name')
            ->get()
            ->map(function ($si) {
                $defaults = $si->toWorkOrderDefaults();
                $billingType = \App\Enums\ServiceItem\BillingType::tryFrom($si->billing_type);
                return array_merge(
                    ['id' => $si->id, 'display_name' => $si->display_name, 'code' => $si->code ?? ''],
                    $defaults,
                    ['billing_type_label' => $billingType?->label() ?? 'Unknown']
                );
            })
            ->values()
            ->all();

        return inertia('Tenant/' . $this->domainName . '/Create', [
            'recordType' => $this->recordType,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'currentUser' => $currentUser,
            'users' => $users,
            'account' => $account,
            'timezones' => Timezone::options(),
            'serviceItems' => $serviceItems,
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

        // Get active service items for line item selection (for edit mode)
        $serviceItems = \App\Domain\ServiceItem\Models\ServiceItem::query()
            ->where('inactive', false)
            ->orderBy('display_name')
            ->get()
            ->map(function ($si) {
                $defaults = $si->toWorkOrderDefaults();
                $billingType = \App\Enums\ServiceItem\BillingType::tryFrom($si->billing_type);
                return array_merge(
                    ['id' => $si->id, 'display_name' => $si->display_name, 'code' => $si->code ?? ''],
                    $defaults,
                    ['billing_type_label' => $billingType?->label() ?? 'Unknown']
                );
            })
            ->values()
            ->all();

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
        ])->values()->all();

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
            'serviceItems' => $serviceItems,
        ]);
    }

}