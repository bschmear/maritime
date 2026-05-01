<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\PublicStorage;
use App\Domain\Document\Models\Document;
use App\Domain\WarrantyClaim\Actions\CreateWarrantyClaim;
use App\Domain\WarrantyClaim\Actions\DeleteWarrantyClaim;
use App\Domain\WarrantyClaim\Actions\UpdateWarrantyClaim;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Enums\Timezone;
use App\Http\Controllers\Concerns\HasSchemaSupport;
use App\Models\AccountSettings;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
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
        $relationships['workOrder'] = fn ($q) => $q->select(['id', 'display_name', 'sequence']);
        $relationships['lineItems'] = fn ($q) => $q->orderBy('id');
        $relationships['images'] = fn ($q) => $q->orderBy('sort_order')->orderBy('id');

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
                $workOrderQueue = WorkOrder::query()->whereRaw('0 = 1')->paginate(max(1, (int) $request->get('per_page', 15)))->withQueryString();
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

        $relationships = $this->getRelationshipsToLoad($fieldsSchema);
        $relationships['vendor'] = fn ($q) => $q->select(['id', 'display_name']);
        $relationships['workOrder'] = fn ($q) => $q->select(['id', 'display_name', 'sequence']);

        $query = WarrantyClaim::query()
            ->select(array_map(static fn (string $c) => $tableName.'.'.$c, $actualColumns))
            ->with($relationships);

        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            $like = '%'.strtolower($search).'%';
            $query->where(function ($q) use ($like, $tableName) {
                $q->whereRaw('LOWER('.$tableName.'.claim_number) LIKE ?', [$like])
                    ->orWhereRaw('LOWER('.$tableName.'.status) LIKE ?', [$like]);
            });
        }

        $sort = (string) $request->get('sort', 'updated_at');
        $dir = strtolower((string) $request->get('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = ['id', 'claim_number', 'status', 'total_amount', 'submitted_at', 'paid_at', 'created_at', 'updated_at'];
        if (in_array($sort, $sortable, true)) {
            $query->orderBy($tableName.'.'.$sort, $dir);
        } else {
            $query->orderBy($tableName.'.updated_at', 'desc');
        }

        $perPage = (int) $request->get('per_page', 15);
        $records = $query->paginate($perPage > 0 ? $perPage : 15)->withQueryString();

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
            $like = '%'.strtolower($search).'%';
            $query->whereRaw('LOWER('.$table.'.display_name) LIKE ?', [$like]);
        }

        $sort = (string) $request->get('sort', 'due_at');
        $dir = strtolower((string) $request->get('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortable = ['id', 'work_order_number', 'display_name', 'status', 'due_at', 'has_warranty', 'warranty_closed', 'created_at', 'updated_at'];
        if (in_array($sort, $sortable, true)) {
            $query->orderBy($table.'.'.$sort, $dir);
        } else {
            $query->orderBy($table.'.due_at', 'asc');
        }

        $perPage = (int) $request->get('per_page', 15);

        return $query->paginate($perPage > 0 ? $perPage : 15)->withQueryString();
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

    public function store(Request $request, PublicStorage $publicStorage): RedirectResponse
    {
        try {
            $data = $this->collectStoreUpdatePayload($request, $publicStorage);
            $result = ($this->createWarrantyClaim)($data);

            if (! is_array($result)) {
                $result = ['success' => true, 'record' => $result];
            }

            if (($result['success'] ?? false) && isset($result['record'])) {
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

        $fieldsSchema = $this->getUnwrappedFieldsSchema();

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
        ]);
    }

    public function edit(WarrantyClaim $warrantyclaim)
    {
        $record = WarrantyClaim::query()
            ->whereKey($warrantyclaim->getKey())
            ->with($this->detailRelationships())
            ->firstOrFail();

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
                'created_by_id' => auth()->id(),
                'updated_by_id' => auth()->id(),
            ]);

            $data[$fieldKey] = $document->id;
        }

        return $data;
    }
}
