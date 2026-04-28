<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\PublicStorage;
use App\Domain\Document\Models\Document;
use App\Domain\WarrantyClaim\Actions\CreateWarrantyClaim;
use App\Domain\WarrantyClaim\Actions\DeleteWarrantyClaim;
use App\Domain\WarrantyClaim\Actions\UpdateWarrantyClaim;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
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
        $relationships['invoice'] = fn ($q) => $q->select(['id', 'sequence', 'status', 'total', 'amount_due', 'uuid']);
        $relationships['workOrder'] = fn ($q) => $q->select(['id', 'display_name', 'sequence']);
        $relationships['lineItems'] = fn ($q) => $q->orderBy('id');

        return $relationships;
    }

    public function index(Request $request)
    {
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
        $relationships['invoice'] = fn ($q) => $q->select(['id', 'sequence', 'status', 'total']);
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
        $sortable = ['id', 'claim_number', 'status', 'total_amount', 'created_at', 'updated_at'];
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
                'meta' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ],
            ]);
        }

        return inertia('Tenant/WarrantyClaim/Index', [
            'records' => $records,
            'recordType' => 'warrantyclaims',
            'recordTitle' => 'Warranty claim',
            'pluralTitle' => 'Warranty claims',
            'schema' => $schema,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
        ]);
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

    public function create()
    {
        return inertia('Tenant/WarrantyClaim/Create', $this->createEditSharedProps());
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
