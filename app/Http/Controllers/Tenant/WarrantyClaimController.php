<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\PublicStorage;
use App\Domain\Attachment\Models\AttachmentLink;
use App\Domain\Attachment\Services\InventoryImageAttachmentService;
use App\Domain\Contact\Models\Contact;
use App\Domain\Document\Models\Document;
use App\Domain\InventoryImage\Models\InventoryImage;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\Vendor\Models\Vendor;
use App\Domain\WarrantyClaim\Actions\AttachWarrantyClaimImagesAfterCreate;
use App\Domain\WarrantyClaim\Actions\CreateWarrantyClaim;
use App\Domain\WarrantyClaim\Actions\DeleteWarrantyClaim;
use App\Domain\WarrantyClaim\Actions\UpdateWarrantyClaim;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Support\ContactDocumentLinker;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Domain\WorkOrderServiceItem\Models\WorkOrderServiceItem;
use App\Enums\Timezone;
use App\Enums\WarrantyClaim\Status as WarrantyClaimStatus;
use App\Http\Controllers\Concerns\HasSchemaSupport;
use App\Models\AccountSettings;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class WarrantyClaimController extends BaseController
{
    use AuthorizesRequests, HasSchemaSupport, ValidatesRequests;

    protected string $domainName = 'WarrantyClaim';

    /** @var WarrantyClaim */
    protected $recordModel;

    public function __construct(
        protected CreateWarrantyClaim $createWarrantyClaim,
        protected UpdateWarrantyClaim $updateWarrantyClaim,
        protected DeleteWarrantyClaim $deleteWarrantyClaim,
        protected NotificationService $notifications,
    ) {
        $this->middleware('auth');
        $this->recordModel = new WarrantyClaim;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getUnwrappedFieldsSchema(): array
    {
        $fieldsSchemaRaw = $this->getFieldsSchema();
        if (! is_array($fieldsSchemaRaw)) {
            return [];
        }

        $unwrapped = isset($fieldsSchemaRaw['fields']) ? $fieldsSchemaRaw['fields'] : $fieldsSchemaRaw;

        return is_array($unwrapped) ? $unwrapped : [];
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function detailRelationships(): array
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        $relationships['vendor'] = fn ($q) => $q->select(['id', 'display_name']);
        $relationships['workOrder'] = fn ($q) => $q->select(['id', 'work_order_number']);
        $relationships['subsidiary'] = fn ($q) => $q->select(['id', 'display_name']);
        $relationships['location'] = fn ($q) => $q->select(['id', 'display_name']);
        $relationships['lineItems'] = fn ($q) => $q->orderBy('id')->with([
            'workOrderServiceItem' => fn ($q2) => $q2->select(['id', 'display_name', 'description', 'work_order_id']),
        ]);
        $relationships['documents'] = fn ($q) => $q;
        $relationships['images'] = fn ($q) => $q;

        return $relationships;
    }

    public function index(Request $request)
    {
        $tab = (string) $request->get('tab', 'claims');
        if (! in_array($tab, ['claims', 'work-orders'], true)) {
            $tab = 'claims';
        }

        if ($tab === 'work-orders') {
            if (! Schema::hasColumn((new WorkOrder)->getTable(), 'has_warranty')) {
                $workOrderQueue = WorkOrder::query()->whereRaw('0 = 1')->paginate(table_per_page($request))->withQueryString();
            } else {
                $workOrderQueue = $this->paginateWorkOrderWarrantyQueue($request);
            }
            $woFields = $this->readWorkOrderFieldsUnwrapped();
            $woTableSchema = $this->readWorkOrderTableSchemaForQueue();
            $woFormSchema = json_decode((string) file_get_contents(app_path('Domain/WorkOrder/Schema/form.json')), true);
            $woEnumOptions = HasSchemaSupport::enumOptionsFromUnwrappedFields($woFields);

            if ($request->ajax() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'records' => $workOrderQueue->items(),
                    'schema' => $woTableSchema,
                    'fieldsSchema' => $woFields,
                    'tab' => $tab,
                    'meta' => [
                        'current_page' => $workOrderQueue->currentPage(),
                        'last_page' => $workOrderQueue->lastPage(),
                        'per_page' => $workOrderQueue->perPage(),
                        'total' => $workOrderQueue->total(),
                    ],
                ]);
            }

            return inertia('Tenant/WarrantyClaim/Index', [
                'tab' => $tab,
                'records' => null,
                'recordType' => 'warrantyclaims',
                'recordTitle' => 'Warranty claim',
                'pluralTitle' => 'Warranty claims',
                'schema' => null,
                'formSchema' => $this->getFormSchema(),
                'fieldsSchema' => $this->getUnwrappedFieldsSchema(),
                'enumOptions' => $this->getEnumOptions(),
                'workOrderQueueRecords' => $workOrderQueue,
                'workOrderQueueSchema' => $woTableSchema,
                'workOrderQueueFormSchema' => $woFormSchema,
                'workOrderQueueFieldsSchema' => $woFields,
                'workOrderQueueEnumOptions' => $woEnumOptions,
            ]);
        }

        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $schema = $this->getTableSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();

        $columns = $this->getSchemaColumns();
        $tableName = $this->recordModel->getTable();
        $dbColumns = Schema::connection($this->recordModel->getConnectionName())->getColumnListing($tableName);

        $actualColumns = [];
        foreach ($columns as $column) {
            if (str_contains($column, '.')) {
                continue;
            }
            if (in_array($column, $dbColumns, true)) {
                $actualColumns[] = $column;
            }
        }
        if (! in_array('id', $actualColumns, true)) {
            $actualColumns[] = 'id';
        }

        if (! in_array('sequence', $actualColumns, true)) {
            $actualColumns[] = 'sequence';
        }

        $relationships = $this->getRelationshipsToLoad($fieldsSchema);
        $relationships['vendor'] = fn ($q) => $q->select(['id', 'display_name']);
        $relationships['workOrder'] = fn ($q) => $q->select(['id', 'work_order_number']);
        $relationships['subsidiary'] = fn ($q) => $q->select(['id', 'display_name']);
        $relationships['location'] = fn ($q) => $q->select(['id', 'display_name']);

        $query = WarrantyClaim::query()
            ->select(array_map(static fn (string $c) => $tableName.'.'.$c, $actualColumns))
            ->with($relationships);

        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            $like = '%'.strtolower($search).'%';
            $query->where(function ($q) use ($like, $tableName) {
                $q->whereRaw('LOWER(CAST('.$tableName.'.sequence AS CHAR)) LIKE ?', [$like])
                    ->orWhereRaw('LOWER('.$tableName.'.claim_number) LIKE ?', [$like])
                    ->orWhereRaw('LOWER('.$tableName.'.status) LIKE ?', [$like]);
            });
        }

        $sort = (string) $request->get('sort', 'updated_at');
        $dir = strtolower((string) $request->get('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = ['id', 'sequence', 'status', 'total_amount', 'submitted_at', 'paid_at', 'created_at', 'updated_at'];
        if (in_array($sort, $sortable, true)) {
            $query->orderBy($tableName.'.'.$sort, $dir);
        } else {
            $query->orderBy($tableName.'.updated_at', 'desc');
        }

        $perPage = table_per_page($request);
        $records = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() && ! $request->header('X-Inertia')) {
            return response()->json([
                'records' => $records->items(),
                'schema' => $schema,
                'fieldsSchema' => $fieldsSchema,
                'tab' => $tab,
                'meta' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ],
            ]);
        }

        return inertia('Tenant/WarrantyClaim/Index', [
            'tab' => $tab,
            'records' => $records,
            'recordType' => 'warrantyclaims',
            'recordTitle' => 'Warranty claim',
            'pluralTitle' => 'Warranty claims',
            'schema' => $schema,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'workOrderQueueRecords' => null,
            'workOrderQueueSchema' => null,
            'workOrderQueueFormSchema' => null,
            'workOrderQueueFieldsSchema' => null,
            'workOrderQueueEnumOptions' => null,
        ]);
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, WorkOrder>
     */
    private function paginateWorkOrderWarrantyQueue(Request $request)
    {
        $woModel = new WorkOrder;
        $table = $woModel->getTable();
        $tableSchema = json_decode((string) file_get_contents(app_path('Domain/WorkOrder/Schema/table.json')), true);
        $columnKeys = [];
        foreach ($tableSchema['columns'] ?? [] as $col) {
            if (is_array($col) && isset($col['key'])) {
                $columnKeys[] = $col['key'];
            }
        }

        $dbColumns = Schema::connection($woModel->getConnectionName())->getColumnListing($table);
        $actualColumns = [];
        foreach ($columnKeys as $column) {
            if (str_contains($column, '.')) {
                continue;
            }
            if (in_array($column, $dbColumns, true)) {
                $actualColumns[] = $column;
            }
        }
        if (! in_array('id', $actualColumns, true)) {
            $actualColumns[] = 'id';
        }

        $relationships = $this->workOrderQueueRelationships();

        $query = WorkOrder::query()
            ->select(array_map(static fn (string $c) => $table.'.'.$c, $actualColumns))
            ->where($table.'.has_warranty', true)
            ->where($table.'.warranty_closed', false)
            ->with($relationships);

        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $search);
            $like = '%'.strtolower($escaped).'%';
            $query->where(function ($q) use ($like, $table) {
                $q->whereRaw('LOWER(CAST('.$table.'.work_order_number AS TEXT)) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(CAST('.$table.'.id AS TEXT)) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE('.$table.'.description, \'\')) LIKE ?', [$like]);
            });
        }

        $sort = (string) $request->get('sort', 'due_at');
        $dir = strtolower((string) $request->get('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortable = ['id', 'work_order_number', 'status', 'due_at', 'has_warranty', 'warranty_closed', 'created_at', 'updated_at'];
        if (in_array($sort, $sortable, true)) {
            $query->orderBy($table.'.'.$sort, $dir);
        } else {
            $query->orderBy($table.'.due_at', 'asc');
        }

        $perPage = table_per_page($request);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    private function readWorkOrderFieldsUnwrapped(): array
    {
        $raw = json_decode((string) file_get_contents(app_path('Domain/WorkOrder/Schema/fields.json')), true);
        if (! is_array($raw)) {
            return [];
        }

        return isset($raw['fields']) && is_array($raw['fields']) ? $raw['fields'] : $raw;
    }

    /**
     * @return array<string, mixed>
     */
    private function readWorkOrderTableSchemaForQueue(): array
    {
        $schema = json_decode((string) file_get_contents(app_path('Domain/WorkOrder/Schema/table.json')), true);
        if (! is_array($schema)) {
            return ['columns' => []];
        }
        $schema['filters'] = [];

        return $schema;
    }

    /**
     * @return array<string, callable(\Illuminate\Database\Eloquent\Relations\Relation): void>
     */
    private function workOrderQueueRelationships(): array
    {
        return [
            'customer' => function ($q) {
                $q->select(['id', 'contact_id'])
                    ->with(['contact' => function ($c) {
                        $c->select(['id', 'display_name']);
                    }]);
            },
            'assignedUser' => fn ($q) => $q->select(['id', 'display_name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createEditSharedProps(): array
    {
        return [
            'recordType' => 'warrantyclaims',
            'recordTitle' => 'Warranty claim',
            'domainName' => $this->domainName,
            'formSchema' => $this->getFormSchema(),
            'fieldsSchema' => $this->getUnwrappedFieldsSchema(),
            'enumOptions' => $this->getEnumOptions(),
            'account' => AccountSettings::getCurrent(),
            'timezones' => Timezone::options(),
        ];
    }

    public function create(Request $request)
    {
        return inertia('Tenant/WarrantyClaim/Create', array_merge($this->createEditSharedProps(), [
            'prefill' => [
                'work_order_id' => $request->filled('work_order_id') ? (int) $request->get('work_order_id') : null,
            ],
        ]));
    }

    /**
     * Images linkable onto a new warranty claim from the work order and its linked service ticket,
     * plus manufacturer warranty service lines for claim line items.
     */
    public function workOrderServiceTicketImages(WorkOrder $workorder): JsonResponse
    {
        $ticketFqcn = ServiceTicket::class;
        $woFqcn = WorkOrder::class;
        $stId = $workorder->service_ticket_id ? (int) $workorder->service_ticket_id : null;

        $workOrderImages = $this->galleryRowsForAttachable($woFqcn, (int) $workorder->id);
        $workOrderImages = $this->mergeMorphImagesBeyondLinks($woFqcn, (int) $workorder->id, $workOrderImages);

        $base = [
            'subsidiary_id' => $workorder->subsidiary_id ? (int) $workorder->subsidiary_id : null,
            'location_id' => $workorder->location_id ? (int) $workorder->location_id : null,
            'warranty_service_items' => $this->warrantyServiceItemsPayload($workorder),
            'work_order_images' => $workOrderImages,
        ];

        if ($stId === null || $stId <= 0) {
            return response()->json(array_merge($base, [
                'service_ticket_id' => null,
                'service_ticket_images' => [],
            ]));
        }

        $serviceTicketImages = $this->galleryRowsForAttachable($ticketFqcn, $stId);
        $serviceTicketImages = $this->mergeMorphImagesBeyondLinks($ticketFqcn, $stId, $serviceTicketImages);

        return response()->json(array_merge($base, [
            'service_ticket_id' => $stId,
            'service_ticket_images' => $serviceTicketImages,
        ]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function warrantyServiceItemsPayload(WorkOrder $workorder): array
    {
        return WorkOrderServiceItem::manufacturerWarrantyLinesForWorkOrder((int) $workorder->id)
            ->get([
                'id',
                'display_name',
                'description',
                'quantity',
                'unit_price',
                'unit_cost',
                'total_price',
                'total_cost',
                'warranty_type',
                'billing_type',
            ])
            ->map(static function (WorkOrderServiceItem $row): array {
                $type = $row->warranty_type;

                return [
                    'id' => $row->id,
                    'display_name' => $row->display_name,
                    'description' => $row->description,
                    'quantity' => (float) $row->quantity,
                    'unit_price' => (float) $row->unit_price,
                    'unit_cost' => $row->unit_cost !== null ? (float) $row->unit_cost : null,
                    'total_price' => (float) $row->total_price,
                    'total_cost' => $row->total_cost !== null ? (float) $row->total_cost : null,
                    'warranty_type' => $type?->value,
                    'warranty_type_label' => $type?->label(),
                    'billing_type' => (int) ($row->billing_type ?? 1),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Include legacy morph-owned images not yet represented by attachment_links (same file row).
     *
     * @param  list<array<string, mixed>>  $linkRows
     * @return list<array<string, mixed>>
     */
    private function mergeMorphImagesBeyondLinks(string $attachableFqcn, int $attachableId, array $linkRows): array
    {
        $linkedIds = collect($linkRows)->pluck('id')->all();

        $orphanMorph = InventoryImage::query()
            ->where('imageable_type', $attachableFqcn)
            ->where('imageable_id', $attachableId)
            ->when($linkedIds !== [], fn ($q) => $q->whereNotIn('id', $linkedIds))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($orphanMorph as $img) {
            $linkRows[] = $img->toArray();
        }

        return $linkRows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function galleryRowsForAttachable(string $attachableType, int $attachableId): array
    {
        $links = AttachmentLink::query()
            ->where('attachable_type', $attachableType)
            ->where('attachable_id', $attachableId)
            ->with(['inventoryImage'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($links as $link) {
            $img = $link->inventoryImage;
            if (! $img) {
                continue;
            }
            $row = $img->toArray();
            $row['attachment_link_id'] = $link->id;
            $row['sort_order'] = (int) $link->sort_order;
            $row['is_primary'] = (bool) $link->is_primary;

            $out[] = $row;
        }

        return $out;
    }

    public function store(Request $request, PublicStorage $publicStorage): RedirectResponse
    {
        try {
            $request->validate([
                'reuse_inventory_image_ids' => ['sometimes', 'array'],
                'reuse_inventory_image_ids.*' => ['integer', 'distinct', 'exists:inventory_images,id'],
                'claim_images' => ['sometimes', 'array'],
                'claim_images.*' => ['file', 'image', 'max:51200'],
            ]);

            $reuseIds = $request->input('reuse_inventory_image_ids', []);
            if (! is_array($reuseIds)) {
                $reuseIds = [];
            }
            $reuseIds = array_values(array_unique(array_map(static fn ($v) => (int) $v, $reuseIds)));

            if ($reuseIds !== [] && ! $request->filled('work_order_id')) {
                throw ValidationException::withMessages([
                    'reuse_inventory_image_ids' => ['Select a work order before reusing images.'],
                ]);
            }

            if ($request->filled('work_order_id')) {
                $wo = WorkOrder::query()->find((int) $request->input('work_order_id'));
                if (! $wo) {
                    throw ValidationException::withMessages([
                        'work_order_id' => ['The selected work order is invalid.'],
                    ]);
                }

                $attach = app(InventoryImageAttachmentService::class);
                foreach ($reuseIds as $rid) {
                    if (! $attach->imageIsUsableOnWarrantyClaimFromWorkOrder($rid, $wo)) {
                        throw ValidationException::withMessages([
                            'reuse_inventory_image_ids' => ['One or more selected images are not available from this work order or its linked service ticket.'],
                        ]);
                    }
                }
            }

            $data = $this->collectStoreUpdatePayload($request, $publicStorage);
            $result = ($this->createWarrantyClaim)($data, current_tenant_user_id());

            if (! is_array($result)) {
                $result = ['success' => true, 'record' => $result];
            }

            if (($result['success'] ?? false) && isset($result['record'])) {
                $record = $result['record'];
                if ($record instanceof WarrantyClaim) {
                    $uploads = $request->file('claim_images', []);
                    if (! is_array($uploads)) {
                        $uploads = $uploads ? [$uploads] : [];
                    }
                    (new AttachWarrantyClaimImagesAfterCreate)($record, $reuseIds, $uploads);
                }

                return redirect()
                    ->route('warrantyclaims.show', $result['record']->id)
                    ->with('success', 'Warranty claim created successfully.')
                    ->with('recordId', $result['record']->id);
            }

            return back()
                ->withInput()
                ->with('error', $result['message'] ?? 'Failed to create warranty claim.');
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
    }

    public function show(Request $request, WarrantyClaim $warrantyclaim)
    {
        $record = WarrantyClaim::query()
            ->whereKey($warrantyclaim->getKey())
            ->with($this->detailRelationships())
            ->firstOrFail();

        ContactDocumentLinker::hydrateDocumentsRelationIfApplicable($record);

        $fieldsSchema = $this->getUnwrappedFieldsSchema();

        $vendorContacts = [];
        if ($record->vendor_id) {
            $vendor = Vendor::query()
                ->with([
                    'linkedContacts' => fn ($q) => $q->select([
                        'contacts.id',
                        'contacts.display_name',
                        'contacts.first_name',
                        'contacts.last_name',
                        'contacts.email',
                        'contacts.secondary_email',
                    ]),
                ])
                ->find((int) $record->vendor_id);
            if ($vendor) {
                $primaryId = $vendor->primary_contact_id !== null ? (int) $vendor->primary_contact_id : null;
                foreach ($vendor->linkedContacts as $c) {
                    if (! (bool) ($c->pivot?->portal_access ?? false)) {
                        continue;
                    }
                    $vendorContacts[] = [
                        'id' => $c->id,
                        'display_name' => $c->display_name ?? trim(($c->first_name ?? '').' '.($c->last_name ?? '')),
                        'email' => $c->email,
                        'secondary_email' => $c->secondary_email,
                        'is_primary' => (bool) ($c->pivot?->is_primary ?? false) || ($primaryId !== null && $primaryId === (int) $c->id),
                    ];
                }
            }
        }

        return inertia('Tenant/WarrantyClaim/Show', [
            'record' => $record,
            'recordType' => 'warrantyclaims',
            'recordTitle' => 'Warranty claim',
            'domainName' => $this->domainName,
            'formSchema' => $this->getFormSchema(),
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $this->getEnumOptions(),
            'account' => AccountSettings::getCurrent(),
            'timezones' => Timezone::options(),
            'imageUrls' => [],
            'availableSpecs' => [],
            'vendorContacts' => $vendorContacts,
        ]);
    }

    public function edit(WarrantyClaim $warrantyclaim)
    {
        $record = WarrantyClaim::query()
            ->whereKey($warrantyclaim->getKey())
            ->with($this->detailRelationships())
            ->firstOrFail();

        ContactDocumentLinker::hydrateDocumentsRelationIfApplicable($record);

        return inertia('Tenant/WarrantyClaim/Edit', array_merge($this->createEditSharedProps(), [
            'record' => $record,
            'imageUrls' => [],
            'availableSpecs' => [],
        ]));
    }

    public function update(Request $request, WarrantyClaim $warrantyclaim, PublicStorage $publicStorage): RedirectResponse
    {
        try {
            $data = $this->collectStoreUpdatePayload($request, $publicStorage);
            $result = ($this->updateWarrantyClaim)((int) $warrantyclaim->getKey(), $data);

            if ($result['success'] ?? false) {
                return redirect()
                    ->route('warrantyclaims.show', $warrantyclaim->getKey())
                    ->with('success', 'Warranty claim updated successfully.');
            }

            return back()
                ->withInput()
                ->with('error', $result['message'] ?? 'Failed to update warranty claim.');
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
    }

    public function sendToVendor(Request $request, WarrantyClaim $warrantyclaim): RedirectResponse
    {
        $claim = WarrantyClaim::query()->whereKey($warrantyclaim->getKey())->firstOrFail();

        $this->authorize('sendToVendor', $claim);

        $request->validate([
            'contact_ids' => ['required', 'array', 'min:1'],
            'contact_ids.*' => ['integer', 'distinct'],
        ]);

        $status = $claim->status instanceof WarrantyClaimStatus
            ? $claim->status
            : WarrantyClaimStatus::tryFrom((string) $claim->getRawOriginal('status')) ?? WarrantyClaimStatus::Draft;

        if ($status !== WarrantyClaimStatus::Submitted) {
            throw ValidationException::withMessages([
                'contact_ids' => ['The claim must be submitted before sending to the vendor.'],
            ]);
        }

        $ids = array_values(array_unique(array_map(static fn ($v) => (int) $v, $request->input('contact_ids', []))));
        $contacts = $this->warrantyClaimVendorRecipientsOrFail($claim, $ids);

        $this->notifications->sendWarrantyClaimToVendorContacts($claim, AccountSettings::getCurrent(), $contacts, $request->user());

        return back()->with('success', 'Warranty claim sent to '.$contacts->count().' contact(s).');
    }

    public function submit(Request $request, WarrantyClaim $warrantyclaim): RedirectResponse
    {
        $claim = WarrantyClaim::query()->whereKey($warrantyclaim->getKey())->firstOrFail();

        $this->authorize('submit', $claim);

        $request->validate([
            'contact_ids' => ['sometimes', 'nullable', 'array'],
            'contact_ids.*' => ['integer', 'distinct'],
        ]);

        $status = $claim->status instanceof WarrantyClaimStatus
            ? $claim->status
            : WarrantyClaimStatus::tryFrom((string) $claim->getRawOriginal('status')) ?? WarrantyClaimStatus::Draft;

        if ($status !== WarrantyClaimStatus::Draft) {
            throw ValidationException::withMessages([
                'contact_ids' => ['Only draft warranty claims can be submitted from this screen.'],
            ]);
        }

        $rawIds = $request->input('contact_ids', []);
        $ids = is_array($rawIds)
            ? array_values(array_unique(array_filter(
                array_map(static fn ($v) => (int) $v, $rawIds),
                static fn (int $id) => $id > 0,
            )))
            : [];

        $contacts = $ids === []
            ? new EloquentCollection
            : $this->warrantyClaimVendorRecipientsOrFail($claim, $ids);

        $result = ($this->updateWarrantyClaim)((int) $claim->getKey(), [
            'vendor_id' => (int) $claim->vendor_id,
            'work_order_id' => $claim->work_order_id !== null ? (int) $claim->work_order_id : null,
            'subsidiary_id' => $claim->subsidiary_id !== null ? (int) $claim->subsidiary_id : null,
            'location_id' => $claim->location_id !== null ? (int) $claim->location_id : null,
            'status' => WarrantyClaimStatus::Submitted->value,
        ]);

        if (! ($result['success'] ?? false)) {
            return back()
                ->withInput()
                ->with('error', $result['message'] ?? 'Failed to submit warranty claim.');
        }

        $claim->refresh();

        if ($contacts->isNotEmpty()) {
            $this->notifications->sendWarrantyClaimToVendorContacts($claim, AccountSettings::getCurrent(), $contacts, $request->user());
        }

        $message = $contacts->isNotEmpty()
            ? 'Warranty claim submitted and emailed to '.$contacts->count().' contact(s).'
            : 'Warranty claim submitted.';

        return back()->with('success', $message);
    }

    /**
     * @param  list<int>  $ids
     * @return EloquentCollection<int, Contact>
     */
    private function warrantyClaimVendorRecipientsOrFail(WarrantyClaim $claim, array $ids): EloquentCollection
    {
        if (! $claim->vendor_id) {
            throw ValidationException::withMessages([
                'contact_ids' => ['This claim has no manufacturer selected.'],
            ]);
        }

        $vendorId = (int) $claim->vendor_id;

        $contacts = Contact::query()
            ->whereIn('id', $ids)
            ->whereHas(
                'vendorsWithPortalAccess',
                static fn ($q) => $q->where('vendors.id', $vendorId),
            )
            ->get();

        if ($contacts->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'contact_ids' => ['One or more contacts are invalid for this manufacturer.'],
            ]);
        }

        foreach ($contacts as $c) {
            $hasEmail = ($c->email !== null && trim((string) $c->email) !== '')
                || ($c->secondary_email !== null && trim((string) $c->secondary_email) !== '');
            if (! $hasEmail) {
                throw ValidationException::withMessages([
                    'contact_ids' => ["Contact #{$c->id} has no email address."],
                ]);
            }
        }

        return $contacts;
    }

    public function destroy(WarrantyClaim $warrantyclaim): RedirectResponse
    {
        $result = ($this->deleteWarrantyClaim)((int) $warrantyclaim->getKey());

        if ($result['success'] ?? false) {
            return redirect()
                ->route('warrantyclaims.index')
                ->with('success', $result['message'] ?? 'Warranty claim deleted.');
        }

        return back()->with('error', $result['message'] ?? 'Failed to delete warranty claim.');
    }

    /**
     * @return array<string, mixed>
     */
    private function collectStoreUpdatePayload(Request $request, PublicStorage $publicStorage): array
    {
        $data = $request->all();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (($fieldDef['type'] ?? '') !== 'image' || ! $request->hasFile($fieldKey)) {
                continue;
            }

            $file = $request->file($fieldKey);
            $meta = $fieldDef['meta'] ?? [];
            $directory = $meta['directory'] ?? ($this->domainName.'/'.$fieldKey);
            $isPrivate = $meta['private'] ?? false;
            $resizeWidth = $meta['max_width'] ?? null;
            $crop = $meta['crop'] ?? false;

            $result = $publicStorage->store(
                file: $file,
                directory: $directory,
                resizeWidth: $resizeWidth,
                existingFile: null,
                crop: $crop,
                deleteOld: false,
                isPrivate: $isPrivate
            );

            $document = Document::create([
                'display_name' => $result['display_name'],
                'file' => $result['key'],
                'file_extension' => $result['file_extension'],
                'file_size' => $result['file_size'],
                'created_by_id' => current_tenant_user_id(),
                'updated_by_id' => current_tenant_user_id(),
            ]);

            $data[$fieldKey] = $document->id;
        }

        return $data;
    }
}
