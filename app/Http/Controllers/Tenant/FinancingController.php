<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Financing\Actions\CreateFinancing;
use App\Domain\Financing\Actions\DeleteFinancing;
use App\Domain\Financing\Actions\UpdateFinancing;
use App\Domain\Financing\Models\Financing;
use App\Domain\Financing\Support\FinancingCsvImportService;
use App\Domain\Financing\Support\FinancingCsvParser;
use App\Enums\Timezone;
use App\Http\Controllers\Concerns\EnforcesTenantRecordPermissions;
use App\Http\Controllers\Concerns\HasSchemaSupport;
use App\Models\AccountSettings;
use App\Services\Financing\FinancingOverviewDataService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use RuntimeException;

class FinancingController extends BaseController
{
    use AuthorizesRequests;
    use EnforcesTenantRecordPermissions;
    use HasSchemaSupport;
    use ValidatesRequests;

    protected string $recordType = 'financings';

    protected string $recordTitle = 'Financing';

    protected string $domainName = 'Financing';

    public function __construct(
        protected Financing $recordModel,
        private readonly CreateFinancing $createFinancing,
        private readonly UpdateFinancing $updateFinancing,
        private readonly DeleteFinancing $deleteFinancing,
        private readonly FinancingOverviewDataService $overview,
    ) {
        $this->middleware('auth');
        $this->registerTenantRecordPermissionMiddleware();
    }

    public function index(Request $request): Response|JsonResponse
    {
        $schema = $this->getTableSchema();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->enumOptions();

        $relationships = $this->recordFieldRelationships($fieldsSchema);
        $query = $this->recordModel->newQuery()->with($relationships);

        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            $term = '%'.strtolower($search).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('CAST(sequence AS TEXT) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(serial_vin, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(lender_invoice_number, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(supplier_name, \'\')) LIKE ?', [$term]);
            });
        }

        $appliedFilters = $this->resolveIndexFiltersFromRequest($request, $schema);
        if ($appliedFilters !== []) {
            $query = $this->applyFilters($query, $appliedFilters, $fieldsSchema);
        }

        $this->applyIndexSort($query, $request, $schema, $fieldsSchema);

        $records = $query->paginate(table_per_page($request));

        $overview = [
            'stats' => $this->overview->buildStats(),
            'statContext' => $this->overview->buildStatContext(),
            'charts' => $this->overview->buildCharts(),
            'activeFinancings' => $this->overview->activeFinancingPreview(),
        ];

        if ($request->ajax() && ! $request->header('X-Inertia')) {
            return response()->json([
                'records' => $records->items(),
                'schema' => $schema,
                'fieldsSchema' => $fieldsSchema,
                'stats' => $overview['stats'],
                'meta' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ],
            ]);
        }

        return inertia('Tenant/Financing/Index', [
            'records' => $records,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'pluralTitle' => Str::plural($this->recordTitle),
            'schema' => $schema,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'appliedFilters' => $appliedFilters,
            'stats' => $overview['stats'],
            'statContext' => $overview['statContext'],
            'charts' => $overview['charts'],
            'activeFinancings' => $overview['activeFinancings'],
        ]);
    }

    public function create(): Response
    {
        $initialData = [];
        $assetUnitId = request()->query('asset_unit_id');
        if ($assetUnitId) {
            $initialData['asset_unit_id'] = (int) $assetUnitId;
        }

        return inertia('Tenant/Financing/Create', [
            'recordType' => $this->recordType,
            'formSchema' => $this->getFormSchema(),
            'fieldsSchema' => $this->getUnwrappedFieldsSchema(),
            'enumOptions' => $this->enumOptions(),
            'account' => AccountSettings::getCurrent(),
            'timezones' => Timezone::options(),
            'initialData' => $initialData,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        try {
            $fieldsSchema = $this->getUnwrappedFieldsSchema();
            $data = $request->all();

            $schemaFailure = $this->validateSchemaFormInput($data, $this->getFormSchema(), $fieldsSchema);
            if ($schemaFailure !== null) {
                return $this->actionFailureResponse($request, $schemaFailure, $fieldsSchema);
            }

            $result = ($this->createFinancing)($data);

            if (! $result['success']) {
                return $this->actionFailureResponse($request, $result, $fieldsSchema);
            }

            $record = $result['record'];

            if ($request->ajax() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'recordId' => $record->id,
                    'record' => $this->loadFinancing($record->id),
                    'message' => 'Financing created successfully',
                ]);
            }

            return redirect()
                ->route('financings.show', $record->id)
                ->with('success', 'Financing created successfully')
                ->with('recordId', $record->id);
        } catch (ValidationException $e) {
            if ($request->ajax() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                    'message' => 'Validation failed',
                ], 422);
            }

            return back()->withInput()->withErrors($e->errors());
        }
    }

    public function show(Request $request, int $financing): Response|JsonResponse
    {
        $record = $this->loadFinancing($financing);
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->enumOptions();
        $account = AccountSettings::getCurrent();

        $payload = [
            'record' => $record,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'domainName' => $this->domainName,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
            'financingMetrics' => $record->metrics()->toArray(),
        ];

        if ($request->ajax() && ! $request->header('X-Inertia')) {
            return response()->json($payload);
        }

        return inertia('Tenant/Financing/Show', $payload);
    }

    public function edit(int $financing): Response
    {
        $record = $this->loadFinancing($financing);

        return inertia('Tenant/Financing/Edit', [
            'record' => $record,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'domainName' => $this->domainName,
            'formSchema' => $this->getFormSchema(),
            'fieldsSchema' => $this->getUnwrappedFieldsSchema(),
            'enumOptions' => $this->enumOptions(),
            'account' => AccountSettings::getCurrent(),
            'timezones' => Timezone::options(),
        ]);
    }

    public function update(Request $request, int $financing): RedirectResponse|JsonResponse
    {
        try {
            $fieldsSchema = $this->getUnwrappedFieldsSchema();
            $data = $request->all();

            $schemaFailure = $this->validateSchemaFormInput($data, $this->getFormSchema(), $fieldsSchema);
            if ($schemaFailure !== null) {
                return $this->actionFailureResponse($request, $schemaFailure, $fieldsSchema, 'update');
            }

            $result = ($this->updateFinancing)($financing, $data);

            if (! $result['success']) {
                return $this->actionFailureResponse($request, $result, $fieldsSchema, 'update');
            }

            if ($request->ajax() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'recordId' => $financing,
                    'record' => $this->loadFinancing($financing),
                    'message' => 'Financing updated successfully',
                ]);
            }

            return redirect()
                ->route('financings.show', $financing)
                ->with('success', 'Financing updated successfully');
        } catch (ValidationException $e) {
            if ($request->ajax() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                    'message' => 'Validation failed',
                ], 422);
            }

            return back()->withInput()->withErrors($e->errors());
        }
    }

    public function updateInterestRate(Request $request, int $financing): JsonResponse
    {
        $validated = $request->validate([
            'annual_interest_rate' => 'required|numeric|min:0.0001|max:100',
            'loan_term_months' => 'nullable|integer|min:1|max:600',
        ]);

        $result = ($this->updateFinancing)($financing, $validated);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Could not save interest rate.',
                'errors' => $result['errors'] ?? [],
            ], 422);
        }

        $record = $this->loadFinancing($financing);

        return response()->json([
            'success' => true,
            'record' => $record,
            'financingMetrics' => $record->metrics()->toArray(),
            'message' => 'Interest rate saved.',
        ]);
    }

    public function destroy(int $financing): RedirectResponse
    {
        $result = ($this->deleteFinancing)($financing);

        if ($result['success']) {
            return redirect()
                ->route('financings.index')
                ->with('success', 'Financing deleted successfully');
        }

        return back()->with('error', $result['message'] ?? 'Failed to delete financing');
    }

    public function import(): Response
    {
        return inertia('Tenant/Financing/Import', $this->importPageProps());
    }

    public function importParse(Request $request, FinancingCsvParser $parser): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $rawRows = $parser->readRawRows($request->file('file'));
        $cacheKey = 'financing_csv_import:'.uniqid('', true);

        Cache::put($cacheKey, [
            'raw_rows' => $rawRows,
            'parsed' => null,
        ], now()->addHour());

        return response()->json([
            'cache_key' => $cacheKey,
            'suggested_header_row_index' => $parser->suggestHeaderRowIndex($rawRows),
            'preview_rows' => array_slice($rawRows, 0, 20),
            'total_raw_rows' => count($rawRows),
        ]);
    }

    public function importConfirmHeader(Request $request, FinancingCsvParser $parser): JsonResponse
    {
        $validated = $request->validate([
            'cache_key' => 'required|string',
            'header_row_index' => 'required|integer|min:0',
        ]);

        $session = Cache::get($validated['cache_key']);
        if (! is_array($session) || ! is_array($session['raw_rows'] ?? null)) {
            return response()->json(['message' => 'Import session expired. Upload the file again.'], 422);
        }

        try {
            $parsed = $parser->parseRawRows($session['raw_rows'], (int) $validated['header_row_index']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        Cache::put($validated['cache_key'], [
            'raw_rows' => $session['raw_rows'],
            'parsed' => $parsed,
        ], now()->addHour());

        $settings = AccountSettings::getCurrent();

        return response()->json([
            'cache_key' => $validated['cache_key'],
            'columns' => $parsed['columns'],
            'row_count' => count($parsed['rows']),
            'header_row_index' => $parsed['header_row_index'],
            'preamble' => $parsed['preamble'],
            'default_column_map' => $this->resolvedImportColumnMap($settings),
            'suggested_match_column' => $this->suggestMatchColumn($parsed['columns']),
        ]);
    }

    public function importPreview(Request $request, FinancingCsvImportService $importService): JsonResponse
    {
        $validated = $request->validate([
            'cache_key' => 'required|string',
            'match_column' => 'required|string',
            'asset_unit_match_field' => 'required|string|in:hin,serial_number',
            'column_map' => 'nullable|array',
            'vendor_id' => 'nullable|integer|exists:vendors,id',
        ]);

        $parsed = $this->parsedImportSession($validated['cache_key']);
        if ($parsed === null) {
            return response()->json(['message' => 'Confirm the header row before continuing.'], 422);
        }

        return response()->json($importService->preview(
            $parsed['rows'],
            $validated['match_column'],
            $validated['asset_unit_match_field'],
            $validated['column_map'] ?? [],
            isset($validated['vendor_id']) ? (int) $validated['vendor_id'] : null,
        ));
    }

    public function importRun(Request $request, FinancingCsvImportService $importService): JsonResponse
    {
        $validated = $request->validate([
            'cache_key' => 'required|string',
            'match_column' => 'required|string',
            'asset_unit_match_field' => 'required|string|in:hin,serial_number',
            'vendor_id' => 'required|integer|exists:vendors,id',
            'column_map' => 'nullable|array',
            'days_alert_threshold' => 'nullable|integer|min:0',
            'interest_alert_threshold' => 'nullable|numeric|min:0',
        ]);

        $parsed = $this->parsedImportSession($validated['cache_key']);
        if ($parsed === null) {
            return response()->json(['message' => 'Confirm the header row before continuing.'], 422);
        }

        $result = $importService->import(
            $parsed['rows'],
            $validated['match_column'],
            $validated['asset_unit_match_field'],
            (int) $validated['vendor_id'],
            $validated['column_map'] ?? [],
            isset($validated['days_alert_threshold']) ? (int) $validated['days_alert_threshold'] : null,
            isset($validated['interest_alert_threshold']) ? (float) $validated['interest_alert_threshold'] : null,
        );

        Cache::forget($validated['cache_key']);

        return response()->json($result);
    }

    public function linkAssetUnit(Request $request, int $financing): JsonResponse
    {
        $validated = $request->validate([
            'asset_unit_id' => 'required|integer|exists:asset_units,id',
        ]);

        $result = ($this->updateFinancing)($financing, $validated);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to link asset unit.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'record' => $this->loadFinancing($financing),
        ]);
    }

    /**
     * @return array{columns: list<string>, header_row_index: int, rows: list<array<string, string|null>>, preamble: list<string>}|null
     */
    private function parsedImportSession(string $cacheKey): ?array
    {
        $session = Cache::get($cacheKey);
        if (! is_array($session) || ! is_array($session['parsed'] ?? null) || ! isset($session['parsed']['rows'])) {
            return null;
        }

        return $session['parsed'];
    }

    /**
     * @return array<string, mixed>
     */
    private function importPageProps(): array
    {
        $settings = AccountSettings::getCurrent();

        return [
            'financingImportDefaults' => [
                'column_map' => $this->resolvedImportColumnMap($settings),
                'days_alert_threshold' => $settings->financing_max_days_in_inventory,
                'interest_alert_threshold' => $settings->financing_interest_alert_amount !== null
                    ? (float) $settings->financing_interest_alert_amount
                    : null,
                'match_fields' => [
                    ['value' => 'hin', 'label' => 'Hull number (HIN)'],
                    ['value' => 'serial_number', 'label' => 'Serial number'],
                ],
                'match_columns' => FinancingCsvParser::defaultMatchColumns(),
            ],
            'importFieldOptions' => FinancingCsvParser::importFieldOptions(),
        ];
    }

    private function resolvedImportColumnMap(AccountSettings $settings): array
    {
        $defaults = FinancingCsvParser::defaultNorthpointColumnMap();
        $saved = $settings->financing_csv_column_map;

        if (! is_array($saved) || $saved === []) {
            return $defaults;
        }

        return array_merge($defaults, $saved);
    }

    /**
     * @param  list<string>  $columns
     */
    private function suggestMatchColumn(array $columns): ?string
    {
        foreach (FinancingCsvParser::defaultMatchColumns() as $preferred) {
            if (in_array($preferred, $columns, true)) {
                return $preferred;
            }
        }

        return $columns[0] ?? null;
    }

    private function loadFinancing(int $id): Financing
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $relationships = $this->recordFieldRelationships($fieldsSchema);

        $relationships['bills'] = fn ($q) => $q->orderByDesc('txn_date');

        return $this->recordModel->newQuery()
            ->with($relationships)
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $fieldsSchema
     * @return array<string, callable>
     */
    private function recordFieldRelationships(array $fieldsSchema): array
    {
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (($fieldDef['type'] ?? null) !== 'record' || ! isset($fieldDef['typeDomain'])) {
                continue;
            }

            $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);

            if ($fieldDef['typeDomain'] === 'AssetUnit') {
                $relationships[$relationshipName] = fn ($query) => $query
                    ->select(['id', 'serial_number', 'hin', 'sku', 'asset_id'])
                    ->with(['asset' => fn ($q) => $q->select(['id', 'display_name'])]);
            } elseif ($fieldDef['typeDomain'] === 'Vendor') {
                $relationships[$relationshipName] = fn ($query) => $query
                    ->select(['id', 'display_name', 'vendor_type']);
            } elseif (! isset($relationships[$relationshipName])) {
                $relationships[$relationshipName] = fn ($query) => $query->select(['id', 'display_name']);
            }
        }

        return $relationships;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function enumOptions(): array
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = [];

        foreach ($fieldsSchema as $fieldDef) {
            $enumClass = $fieldDef['enum'] ?? null;
            if (! is_string($enumClass) || $enumClass === '' || ! class_exists($enumClass)) {
                continue;
            }

            $enumOptions[$enumClass] = method_exists($enumClass, 'options')
                ? $enumClass::options()
                : array_map(fn ($case) => [
                    'id' => $case->value,
                    'name' => $case->name ?? $case->value,
                ], $enumClass::cases());
        }

        return $enumOptions;
    }

    /**
     * @param  Builder<Financing>  $query
     */
    private function applyIndexSort($query, Request $request, ?array $schema, array $fieldsSchema): void
    {
        $allowed = $this->sortableColumnsFromTableSchema($schema);
        $sp = $this->sortParamsFromRequest($request);

        if ($sp['key'] === null || ! isset($allowed[$sp['key']])) {
            $query->orderByDesc('financings.created_at');

            return;
        }

        $column = match ($sp['key']) {
            'display_name' => 'sequence',
            default => $sp['key'],
        };

        $query->orderBy('financings.'.$column, $sp['dir']);
    }
}
