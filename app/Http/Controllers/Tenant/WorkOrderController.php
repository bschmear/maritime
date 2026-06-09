<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\PublicStorage;
use App\Domain\Attachment\Services\InventoryImageAttachmentService;
use App\Domain\ServiceItem\Models\ServiceItem;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\User\Models\User;
use App\Domain\WorkOrder\Actions\CreateWorkOrder as CreateAction;
use App\Domain\WorkOrder\Actions\DeleteWorkOrder as DeleteAction;
use App\Domain\WorkOrder\Actions\LinkInventoryImagesToWorkOrderAfterCreate;
use App\Domain\WorkOrder\Actions\LogWorkOrderLineItemTime;
use App\Domain\WorkOrder\Actions\UpdateWorkOrder as UpdateAction;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Domain\WorkOrder\Models\WorkOrder as RecordModel;
use App\Domain\WorkOrder\Support\MapWorkOrderStatusToServiceTicketStatus;
use App\Domain\WorkOrder\Support\SyncWorkOrderStatusToServiceTicket;
use App\Domain\WorkOrderServiceItem\Models\WorkOrderServiceItem;
use App\Enums\RecordType;
use App\Enums\ServiceItem\BillingType;
use App\Enums\ServiceTicketServiceItem\WarrantyCoverageType;
use App\Enums\Tasks\Priority as TaskPriority;
use App\Enums\Tasks\Status as TaskStatus;
use App\Enums\Timezone;
use App\Enums\WorkOrder\Priority as WorkOrderPriority;
use App\Enums\WorkOrder\Status as WorkOrderStatus;
use App\Models\AccountSettings;
use App\Services\WorkOrderCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkOrderController extends RecordController
{
    public function __construct(Request $request)
    {
        $recordType = RecordType::WorkOrder;
        parent::__construct(
            $request,
            $recordType->plural(),
            $recordType->title(),
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            'WorkOrder' // Explicitly set domain name
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function taskBoardInertiaProps(): array
    {
        $fieldsPath = app_path('Domain/Task/Schema/fields.json');
        $formPath = app_path('Domain/Task/Schema/form.json');

        $fieldsRaw = is_file($fieldsPath)
            ? json_decode((string) file_get_contents($fieldsPath), true) ?? []
            : [];
        $taskFieldsSchema = $fieldsRaw['fields'] ?? $fieldsRaw;

        $taskFormSchema = is_file($formPath)
            ? json_decode((string) file_get_contents($formPath), true) ?? []
            : [];

        return [
            'taskBoardFormSchema' => $taskFormSchema,
            'taskBoardFieldsSchema' => $taskFieldsSchema,
            'taskBoardEnumOptions' => [
                'App\\Enums\\Tasks\\Status' => TaskStatus::options(),
                'App\\Enums\\Tasks\\Priority' => TaskPriority::options(),
            ],
            'taskStatusOptions' => TaskStatus::options(),
        ];
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

        if (! in_array('id', $actualColumns)) {
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

        $completedDrilldown = $request->filled('completed_from') && $request->filled('completed_to');
        if ($completedDrilldown) {
            $from = Carbon::parse($request->string('completed_from'))->startOfDay();
            $to = Carbon::parse($request->string('completed_to'))->endOfDay();
            $query->whereBetween('completed_at', [$from, $to])->whereNotNull('completed_at');
            if ($request->filled('subsidiary_id')) {
                $query->where('subsidiary_id', $request->integer('subsidiary_id'));
            }
            if ($request->filled('location_id')) {
                $query->where('location_id', $request->integer('location_id'));
            }
            if ($request->boolean('warranty_pending_only')) {
                $query->where('has_warranty', true)
                    ->whereNotExists(function ($sub): void {
                        $sub->select(DB::raw('1'))
                            ->from('invoices')
                            ->whereColumn('invoices.work_order_id', 'work_orders.id')
                            ->whereNotIn('invoices.status', ['draft', 'void'])
                            ->whereNull('invoices.deleted_at');
                    });

                $pendingType = strtolower(trim($request->string('warranty_pending_type')->toString()));
                $dealership = WarrantyCoverageType::Dealership->value;
                $manufacturer = WarrantyCoverageType::Manufacturer->value;
                if ($pendingType === 'dealership') {
                    $query->whereExists(function ($sub) use ($dealership): void {
                        $sub->select(DB::raw('1'))
                            ->from('work_order_service_items as wosi')
                            ->whereColumn('wosi.work_order_id', 'work_orders.id')
                            ->where('wosi.billable', true)
                            ->where('wosi.inactive', false)
                            ->where(function ($q) use ($dealership): void {
                                $q->where(function ($w) use ($dealership): void {
                                    $w->where('wosi.warranty', true)
                                        ->where('wosi.warranty_type', $dealership);
                                })->orWhere(function ($w): void {
                                    $w->where('wosi.warranty', true)
                                        ->whereNull('wosi.warranty_type')
                                        ->where('wosi.billable_to', 'internal');
                                });
                            });
                    });
                } elseif ($pendingType === 'manufacturer') {
                    $query->whereExists(function ($sub) use ($manufacturer): void {
                        $sub->select(DB::raw('1'))
                            ->from('work_order_service_items as wosi')
                            ->whereColumn('wosi.work_order_id', 'work_orders.id')
                            ->where('wosi.billable', true)
                            ->where('wosi.inactive', false)
                            ->where(function ($q) use ($manufacturer): void {
                                $q->where(function ($w) use ($manufacturer): void {
                                    $w->where('wosi.warranty', true)
                                        ->where('wosi.warranty_type', $manufacturer);
                                })->orWhere('wosi.billable_to', 'manufacturer');
                            });
                    });
                }
            }
        }

        // Apply search (WO #, id, description — display_name is computed, not a column)
        $searchQuery = $request->get('search');
        if ($searchQuery && ! empty(trim($searchQuery))) {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], trim($searchQuery));
            $term = '%'.strtolower($escaped).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(CAST(work_orders.work_order_number AS TEXT)) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(CAST(work_orders.id AS TEXT)) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(work_orders.description, \'\')) LIKE ?', [$term]);
            });
        }

        $userParam = $request->get('user');

        if ($userParam !== null && $userParam !== 'all') {
            $query->where('assigned_user_id', $userParam);
        }

        $statusesForQuery = $this->resolveWorkOrderIndexStatuses($request);
        $prioritiesForQuery = $this->resolveWorkOrderIndexPriorities($request);
        $this->applyWorkOrderIndexStatusScope($query, $request, $statusesForQuery, true);
        if ($prioritiesForQuery !== null) {
            $query->whereIn('priority', $prioritiesForQuery);
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

        $query->orderBy('scheduled_start_at', 'asc')->orderBy('due_at', 'asc');
        $perPage = $request->get('per_page', 15);
        $records = $query->paginate($perPage)->appends($request->query());

        if ($json = $this->indexAjaxJsonResponse($request, $records, $schema, $fieldsSchema)) {
            return $json;
        }

        // Get other users (excluding current user)
        $users = User::select('id', 'display_name')
            ->where('id', '!=', $currentUser->id)
            ->orderBy('display_name')
            ->get();

        // Calculate dynamic stats based on current filters
        $baseQuery = WorkOrder::query();

        // Apply user filter if specified
        $userParam = $request->get('user');
        if ($userParam && $userParam !== 'all') {
            $baseQuery->where('assigned_user_id', $userParam);
        }

        $this->applyWorkOrderIndexStatusScope($baseQuery, $request, $statusesForQuery, false);
        if ($prioritiesForQuery !== null) {
            $baseQuery->whereIn('priority', $prioritiesForQuery);
        }

        // Calculate stats based on filtered data
        $stats = [
            'open' => (clone $baseQuery)->whereIn('status', [2, 3, 4, 5])->count(), // Open, Scheduled, In Progress, Waiting
            'in_progress' => (clone $baseQuery)->where('status', 4)->count(), // In Progress status
            'overdue' => (clone $baseQuery)->where('due_at', '<', now())->whereNotIn('status', [7, 8])->count(), // Not Completed or Closed
            'completed_week' => (clone $baseQuery)->whereIn('status', [7, 8])->whereNotNull('completed_at')->whereBetween('completed_at', [now()->startOfWeek(), now()->endOfWeek()])->count(), // Completed or closed this week (by date completed)
        ];

        $pluralTitle = Str::plural($this->recordTitle);

        $kanbanRecords = $this->loadKanbanWorkOrders(
            $request,
            $currentUser,
            $actualColumns,
            $relationships,
            $completedDrilldown,
        );

        return inertia('Tenant/'.$this->domainName.'/Index', [
            'records' => $records,
            'kanbanRecords' => $kanbanRecords,
            'workOrderStatusOptions' => $this->workOrderKanbanStatusOptions(),
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
                'user' => $userParam ?? 'all',
                'status' => $statusesForQuery === null ? 'all' : $statusesForQuery,
                'priority' => $prioritiesForQuery === null ? 'all' : $prioritiesForQuery,
                'show_closed' => $request->boolean('show_closed'),
                'show_cancelled' => $request->boolean('show_cancelled'),
            ],
        ]);
    }

    /**
     * @return list<int>
     */
    private function workOrderFilterableStatusIds(): array
    {
        return [
            WorkOrderStatus::Draft->id(),
            WorkOrderStatus::Open->id(),
            WorkOrderStatus::Scheduled->id(),
            WorkOrderStatus::InProgress->id(),
            WorkOrderStatus::Waiting->id(),
            WorkOrderStatus::Blocked->id(),
            WorkOrderStatus::Completed->id(),
        ];
    }

    /**
     * @return list<int>
     */
    private function workOrderKanbanStatusIds(): array
    {
        return [
            WorkOrderStatus::Open->id(),
            WorkOrderStatus::Scheduled->id(),
            WorkOrderStatus::InProgress->id(),
            WorkOrderStatus::Waiting->id(),
            WorkOrderStatus::Blocked->id(),
            WorkOrderStatus::Completed->id(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function workOrderKanbanStatusOptions(): array
    {
        $kanbanIds = $this->workOrderKanbanStatusIds();

        return array_values(array_filter(
            WorkOrderStatus::options(),
            static fn (array $option): bool => in_array((int) $option['id'], $kanbanIds, true),
        ));
    }

    /**
     * @return list<int>
     */
    private function workOrderFilterablePriorityIds(): array
    {
        return array_map(static fn (WorkOrderPriority $p) => $p->id(), WorkOrderPriority::cases());
    }

    /**
     * @return list<int>|null
     */
    private function resolveWorkOrderIndexStatuses(Request $request): ?array
    {
        $allowed = $this->workOrderFilterableStatusIds();
        $raw = $request->input('status');

        if ($raw === 'all') {
            return null;
        }

        if (is_array($raw)) {
            $picked = array_values(array_unique(array_map(
                'intval',
                array_intersect($allowed, array_map(static fn ($v) => (int) $v, $raw)),
            )));

            return count($picked) > 0 ? $picked : null;
        }

        if (is_string($raw) && $raw !== '' && $raw !== 'all') {
            $id = (int) $raw;

            return in_array($id, $allowed, true) ? [$id] : null;
        }

        return null;
    }

    /**
     * @return list<int>|null
     */
    private function resolveWorkOrderIndexPriorities(Request $request): ?array
    {
        $allowed = $this->workOrderFilterablePriorityIds();
        $raw = $request->input('priority');

        if ($raw === 'all') {
            return null;
        }

        if (is_array($raw)) {
            $picked = array_values(array_unique(array_map(
                'intval',
                array_intersect($allowed, array_map(static fn ($v) => (int) $v, $raw)),
            )));

            return count($picked) > 0 ? $picked : null;
        }

        if (is_string($raw) && $raw !== '' && $raw !== 'all') {
            $id = (int) $raw;

            return in_array($id, $allowed, true) ? [$id] : null;
        }

        return null;
    }

    /**
     * @param  list<int>|null  $statusesForQuery
     */
    private function applyWorkOrderIndexStatusScope(
        Builder $query,
        Request $request,
        ?array $statusesForQuery,
        bool $allowTerminalToggles,
    ): void {
        $filterable = $statusesForQuery ?? $this->workOrderFilterableStatusIds();
        $showClosed = $allowTerminalToggles && $request->boolean('show_closed');
        $showCancelled = $allowTerminalToggles && $request->boolean('show_cancelled');

        if (! $showClosed && ! $showCancelled) {
            $query->whereIn('status', $filterable);

            return;
        }

        $query->where(function (Builder $q) use ($filterable, $showClosed, $showCancelled): void {
            $q->whereIn('status', $filterable);
            $terminal = [];
            if ($showClosed) {
                $terminal[] = WorkOrderStatus::Closed->id();
            }
            if ($showCancelled) {
                $terminal[] = WorkOrderStatus::Cancelled->id();
            }
            if ($terminal !== []) {
                $q->orWhereIn('status', $terminal);
            }
        });
    }

    /**
     * Work orders for the kanban board (non-paginated, active statuses only).
     *
     * @param  list<string>  $actualColumns
     * @param  array<string, mixed>  $relationships
     * @return list<array<string, mixed>>
     */
    private function loadKanbanWorkOrders(
        Request $request,
        $currentUser,
        array $actualColumns,
        array $relationships,
        bool $completedDrilldown,
    ): array {
        $kanbanStatusIds = $this->workOrderKanbanStatusIds();
        $statusesForQuery = $this->resolveWorkOrderIndexStatuses($request);
        if ($statusesForQuery !== null) {
            $kanbanStatusIds = array_values(array_intersect($kanbanStatusIds, $statusesForQuery));
        }

        $prioritiesForQuery = $this->resolveWorkOrderIndexPriorities($request);

        $query = $this->recordModel->select($actualColumns)->with($relationships);

        if ($completedDrilldown) {
            $from = Carbon::parse($request->string('completed_from'))->startOfDay();
            $to = Carbon::parse($request->string('completed_to'))->endOfDay();
            $query->whereBetween('completed_at', [$from, $to])->whereNotNull('completed_at');
            if ($request->filled('subsidiary_id')) {
                $query->where('subsidiary_id', $request->integer('subsidiary_id'));
            }
            if ($request->filled('location_id')) {
                $query->where('location_id', $request->integer('location_id'));
            }
        }

        $searchQuery = $request->get('search');
        if ($searchQuery && ! empty(trim($searchQuery))) {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], trim($searchQuery));
            $term = '%'.strtolower($escaped).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(CAST(work_orders.work_order_number AS TEXT)) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(CAST(work_orders.id AS TEXT)) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(work_orders.description, \'\')) LIKE ?', [$term]);
            });
        }

        $userParam = $request->get('user');
        if ($userParam !== null && $userParam !== 'all') {
            $query->where('assigned_user_id', $userParam);
        }

        if ($prioritiesForQuery !== null) {
            $query->whereIn('priority', $prioritiesForQuery);
        }

        $filtersParam = $request->get('filters');
        if ($filtersParam) {
            try {
                $filters = json_decode(urldecode((string) $filtersParam), true);
                if (is_array($filters)) {
                    $fieldsSchema = $this->getUnwrappedFieldsSchema();
                    $query = $this->applyFilters($query, $filters, $fieldsSchema);
                }
            } catch (\Throwable) {
            }
        }

        if ($kanbanStatusIds === []) {
            return [];
        }

        return $query
            ->whereIn('status', $kanbanStatusIds)
            ->orderBy('scheduled_start_at', 'asc')
            ->orderBy('due_at', 'asc')
            ->limit(500)
            ->get()
            ->map(fn ($wo) => $wo->toArray())
            ->values()
            ->all();
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
                } elseif ($fieldDef['typeDomain'] === 'Qualification') {
                    $selectFields = ['id', 'sequence'];
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'sequence']);
                    };
                } elseif ($fieldDef['typeDomain'] === 'Transaction' || $fieldDef['typeDomain'] === 'Estimate') {
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'sequence']);
                    };
                } elseif ($fieldDef['typeDomain'] === 'Customer') {
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'contact_id'])
                            ->with(['contact' => function ($q) {
                                $q->select(['id', 'display_name', 'first_name', 'last_name']);
                            }]);
                    };
                } else {
                    $selectFields[] = 'display_name';
                }

                if (isset($fieldDef['displayField']) && $fieldDef['displayField'] !== 'display_name') {
                    $selectFields[] = $fieldDef['displayField'];
                }

                $selectFields = array_unique($selectFields);

                if (! isset($relationships[$relationshipName])) {
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

        // Service items are now loaded on-demand via the paginated modal

        $enumOptions = $this->getEnumOptions();
        $enumOptions['billing_type'] = BillingType::options();
        $enumOptions['warranty_type'] = WarrantyCoverageType::options();

        // Load service ticket data if linked
        $serviceTicket = null;
        if ($record->service_ticket_id) {
            $ticket = ServiceTicket::find($record->service_ticket_id);
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

        $account = AccountSettings::getCurrent();

        return inertia('Tenant/'.$this->domainName.'/Edit', [
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
        $users = User::select('id', 'display_name')
            ->where('id', '!=', $currentUser->id)
            ->orderBy('display_name')
            ->get();

        // Get account settings for timezone display (cached)
        $account = AccountSettings::getCurrent();

        // Service items are now loaded on-demand via the paginated modal

        $enumOptions = $this->getEnumOptions();
        $enumOptions['billing_type'] = BillingType::options();
        $enumOptions['warranty_type'] = WarrantyCoverageType::options();

        // Check if creating from a service ticket
        $serviceTicket = null;
        $serviceTicketItems = [];
        $serviceTicketId = request()->get('service_ticket_id');

        if ($serviceTicketId) {
            $ticket = ServiceTicket::with([
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
                    'type' => $ticket->type,
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
            }
        }

        return inertia('Tenant/'.$this->domainName.'/Create', [
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
        $account = AccountSettings::getCurrent();

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

        // Load service ticket data if linked
        $serviceTicket = null;
        if ($record->service_ticket_id) {
            $ticket = ServiceTicket::find($record->service_ticket_id);
            if ($ticket) {
                $serviceTicket = [
                    'id' => $ticket->id,
                    'service_ticket_number' => $ticket->service_ticket_number,
                    'status' => (int) $ticket->status,
                    'estimated_total' => (float) ($ticket->estimated_total ?? 0),
                    'estimated_subtotal' => (float) ($ticket->estimated_subtotal ?? 0),
                    'estimated_tax' => (float) ($ticket->estimated_tax ?? 0),
                    'tax_rate' => (float) ($ticket->tax_rate ?? 0),
                ];
            }
        }

        $workOrderWithTasks = RecordModel::query()
            ->with([
                'tasks' => fn ($q) => $q->with([
                    'assigned' => fn ($a) => $a->select(['id', 'display_name', 'first_name', 'last_name']),
                ])
                    ->orderByRaw('case when due_date is null then 1 else 0 end')
                    ->orderBy('due_date'),
            ])
            ->findOrFail($id);

        return inertia('Tenant/'.$this->domainName.'/Show', array_merge([
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
            'serviceTicketStatusMap' => MapWorkOrderStatusToServiceTicketStatus::all(),
            'estimateThreshold' => (float) ($account->estimate_threshold_percent ?? 20),
            'tasks' => $workOrderWithTasks->tasks,
        ], $this->taskBoardInertiaProps()));
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

        $linkServiceTicketImageIds = $data['link_service_ticket_image_ids'] ?? [];
        if (! is_array($linkServiceTicketImageIds)) {
            $linkServiceTicketImageIds = [];
        }
        $linkServiceTicketImageIds = array_values(array_unique(array_map(static fn ($v) => (int) $v, $linkServiceTicketImageIds)));
        $linkAllServiceTicketImages = filter_var($data['link_all_service_ticket_images'] ?? false, FILTER_VALIDATE_BOOLEAN);
        unset($data['link_service_ticket_image_ids'], $data['link_all_service_ticket_images']);

        // Validate threshold if linked to a service ticket
        $serviceTicketId = $data['service_ticket_id'] ?? null;
        if ($serviceTicketId) {
            $thresholdError = $this->validateThreshold($serviceTicketId, $serviceItems, $data['tax_rate'] ?? 0);
            if ($thresholdError) {
                return back()->withErrors(['threshold' => $thresholdError]);
            }
        }

        if (($linkAllServiceTicketImages || $linkServiceTicketImageIds !== []) && ! $serviceTicketId) {
            return back()->withErrors([
                'link_service_ticket_image_ids' => ['Select a service ticket before linking ticket images to this work order.'],
            ]);
        }

        if ($linkServiceTicketImageIds !== [] || $linkAllServiceTicketImages) {
            $attach = app(InventoryImageAttachmentService::class);
            foreach ($linkServiceTicketImageIds as $imgId) {
                if ($imgId <= 0) {
                    continue;
                }
                if (! $attach->imageBelongsToServiceTicket($imgId, (int) $serviceTicketId)) {
                    return back()->withErrors([
                        'link_service_ticket_image_ids' => ['One or more selected images are not on this service ticket.'],
                    ]);
                }
            }
        }

        // Create the work order first
        $request->merge($data);
        // merge() does not remove keys omitted from $data; the original request may still carry these.
        $request->request->remove('service_items');
        $request->request->remove('link_service_ticket_image_ids');
        $request->request->remove('link_all_service_ticket_images');
        $response = parent::store($request, $publicStorage);

        // If successful, always recalculate work order totals
        if ($response->getStatusCode() === 302) {
            // Extract work order ID from redirect URL
            $location = $response->getTargetUrl();
            if (preg_match('/\/workorders\/(\d+)/', $location, $matches)) {
                $workOrderId = $matches[1];
                $calculator = app(WorkOrderCalculator::class);
                $workOrder = WorkOrder::find($workOrderId);
                if ($workOrder) {
                    if (! empty($serviceItems)) {
                        $this->createServiceItems($workOrderId, $serviceItems);
                    } else {
                        // Recalculate totals even without service items
                        $calculator->recalculateWorkOrder($workOrder);
                    }

                    if ($linkAllServiceTicketImages || $linkServiceTicketImageIds !== []) {
                        (new LinkInventoryImagesToWorkOrderAfterCreate)(
                            $workOrder,
                            $linkServiceTicketImageIds,
                            $linkAllServiceTicketImages
                        );
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

        $syncServiceTicketStatus = filter_var($data['sync_service_ticket_status'] ?? false, FILTER_VALIDATE_BOOLEAN);
        unset($data['sync_service_ticket_status']);
        $statusToSync = isset($data['status']) ? (int) $data['status'] : null;

        // Validate threshold if linked to a service ticket
        $workOrder = WorkOrder::find($id);
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
        $calculator = app(WorkOrderCalculator::class);
        $workOrder = WorkOrder::find($id);
        if ($workOrder) {
            if ($syncServiceTicketStatus && $statusToSync !== null && (int) $workOrder->status === $statusToSync) {
                (new SyncWorkOrderStatusToServiceTicket)($workOrder, $statusToSync);
            }

            // Update service items if provided
            if (! empty($serviceItems)) {
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

        $serviceItems = ServiceItem::query()
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
                'warranty_type',
                'billing_type',
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
     * Validate that the work order total does not exceed the service ticket estimate threshold.
     * Returns an error message string if over threshold, null if OK.
     */
    protected function validateThreshold(int $serviceTicketId, array $serviceItems, float $taxRate): ?string
    {
        $ticket = ServiceTicket::find($serviceTicketId);
        if (! $ticket) {
            return null; // No ticket to validate against
        }

        $account = AccountSettings::getCurrent();
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

            if (! $billable || $warranty) {
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
        $workOrder = WorkOrder::latest()->first();

        return $workOrder ? $workOrder->id : null;
    }

    /**
     * Create service items for a newly created work order
     */
    protected function createServiceItems(int $workOrderId, array $serviceItems)
    {
        $calculator = app(WorkOrderCalculator::class);
        $workOrder = WorkOrder::find($workOrderId);

        foreach ($serviceItems as $index => $itemData) {
            $warranty = $itemData['warranty'] ?? false;
            $billableTo = $itemData['billable_to']
                ?? (! $warranty ? 'customer' : (($itemData['warranty_type'] ?? null) === 'manufacturer' ? 'manufacturer' : 'internal'));
            $lineItem = WorkOrderServiceItem::create([
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
                'warranty' => $warranty,
                'warranty_type' => $warranty ? ($itemData['warranty_type'] ?? null) : null,
                'billable_to' => $billableTo,
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
        $calculator = app(WorkOrderCalculator::class);
        $workOrder = WorkOrder::find($workOrderId);

        // Delete existing service items
        WorkOrderServiceItem::where('work_order_id', $workOrderId)->delete();

        // Create new ones and recalculate
        foreach ($serviceItems as $index => $itemData) {
            $warranty = $itemData['warranty'] ?? false;
            $billableTo = $itemData['billable_to']
                ?? (! $warranty ? 'customer' : (($itemData['warranty_type'] ?? null) === 'manufacturer' ? 'manufacturer' : 'internal'));
            $lineItem = WorkOrderServiceItem::create([
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
                'warranty' => $warranty,
                'warranty_type' => $warranty ? ($itemData['warranty_type'] ?? null) : null,
                'billable_to' => $billableTo,
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
        $account = AccountSettings::getCurrent();

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

        return inertia('Tenant/'.$this->domainName.'/Public', [
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

    /**
     * Quick-add actual hours to a work order line item (JSON).
     */
    public function logLineItemTime(Request $request, int $id, LogWorkOrderLineItemTime $action)
    {
        $payload = $action($id, $request->all());

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return back();
    }
}
