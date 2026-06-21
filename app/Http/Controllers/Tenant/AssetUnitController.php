<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Support\AssetLayoutFootprint;
use App\Domain\AssetUnit\Actions\CreateAssetUnit as CreateAction;
use App\Domain\AssetUnit\Actions\DeleteAssetUnit as DeleteAction;
use App\Domain\AssetUnit\Actions\UpdateAssetUnit as UpdateAction;
use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\AssetUnit\Models\AssetUnit as RecordModel;
use App\Domain\AssetUnit\Support\AssetUnitGoogleSheetSyncService;
use App\Domain\AssetUnit\Support\AssetUnitImportService;
use App\Domain\AssetUnit\Support\AssetUnitSpreadsheetExporter;
use App\Domain\AssetUnit\Support\AssetUnitSpreadsheetParser;
use App\Domain\Financing\Models\Financing;
use App\Domain\Integration\Support\GoogleIntegrationSettings;
use App\Domain\MsoRecord\Models\MsoRecord;
use App\Enums\RecordType;
use App\Enums\Timezone;
use App\Models\AccountSettings;
use App\Services\AssetOptionResolver;
use App\Services\Google\GoogleOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Inertia\Response as InertiaResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetUnitController extends RecordController
{
    public function __construct(Request $request)
    {
        $recordType = RecordType::AssetUnit;
        parent::__construct(
            $request,
            $recordType->plural(),
            $recordType->title(),
            new RecordModel,
            app(CreateAction::class),
            app(UpdateAction::class),
            new DeleteAction,
            $recordType->domainName()
        );
    }

    public function index(Request $request): JsonResponse|InertiaResponse
    {
        return $this->unitsIndex($request);
    }

    /**
     * Global units list — dedicated entry point (Assets → Units tab) with through-filters on the parent Asset.
     */
    public function unitsIndex(Request $request): JsonResponse|InertiaResponse
    {
        return parent::index($request);
    }

    protected function indexSupplementInertiaProps(Request $request): array
    {
        $oauth = app(GoogleOAuthService::class);
        $integration = $oauth->integration();
        $settings = GoogleIntegrationSettings::from($integration);

        return [
            'googleConnected' => $oauth->hasCredentials(),
            'googleSheetSettings' => $settings->toArray(),
        ];
    }

    /**
     * Brand (make_id) is stored on the related Asset, not on asset_units.
     */
    protected function applyFilters($query, array $filters, $fieldsSchema)
    {
        $remaining = [];

        foreach ($filters as $filter) {
            if (! is_array($filter) || ($filter['field'] ?? '') !== 'make_id') {
                $remaining[] = $filter;

                continue;
            }

            $makeIds = $this->resolveMakeIdsFromFilter($filter);
            if ($makeIds === []) {
                continue;
            }

            $query->whereHas('asset', fn ($q) => $q->whereIn('make_id', $makeIds));
        }

        return parent::applyFilters($query, $remaining, $fieldsSchema);
    }

    /**
     * @return list<int>
     */
    private function resolveMakeIdsFromFilter(array $filter): array
    {
        $operator = $filter['operator'] ?? 'equals';
        $value = $filter['value'] ?? null;

        if ($operator === 'any_of' && is_array($value)) {
            $ids = array_map(fn ($v) => (int) $v, $value);

            return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
        }

        if (($operator === 'equals' || $operator === 'any_of') && $value !== null && $value !== '') {
            $id = (int) $value;

            return $id > 0 ? [$id] : [];
        }

        return [];
    }

    /**
     * Include catalog flags on the parent asset so the UI can hide options when variants exist.
     */
    protected function appendShowRelationships(array &$relationships): void
    {
        $relationships['asset'] = function ($query) {
            $query->select(['id', 'display_name', 'has_variants', 'make_id', 'year', 'model'])
                ->with([
                    'make' => fn ($q) => $q->select(['id', 'display_name']),
                ]);
        };
        $relationships['assetVariant'] = fn ($q) => $q->select(['id', 'display_name', 'name', 'asset_id']);
        $relationships['customer'] = fn ($q) => $q->select(['id', 'contact_id'])
            ->with(['contact' => fn ($cq) => $cq->select(['id', 'display_name', 'first_name', 'last_name'])]);
        $relationships['documents'] = fn ($q) => $q->select([
            'documents.id',
            'documents.display_name',
            'documents.description',
            'documents.file',
            'documents.file_extension',
            'documents.file_size',
            'documents.created_at',
        ]);
    }

    public function create()
    {
        $request = request();
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();
        $account = AccountSettings::getCurrent();

        $prefill = [];
        $assetId = (int) $request->query('asset_id', 0);
        if ($assetId > 0) {
            $asset = Asset::query()
                ->select(['id', 'display_name', 'has_variants'])
                ->find($assetId);
            if ($asset) {
                $prefill = [
                    'asset_id' => $asset->id,
                    'asset' => $asset,
                ];
            }
        }

        $hin = trim((string) $request->query('hin', ''));
        if ($hin !== '') {
            $prefill['hin'] = $hin;
        }

        $serialNumber = trim((string) $request->query('serial_number', ''));
        if ($serialNumber !== '') {
            $prefill['serial_number'] = $serialNumber;
        }

        $linkFinancingId = (int) $request->query('link_financing_id', 0) ?: null;

        $linkFinancing = null;
        if ($linkFinancingId) {
            $fin = Financing::query()
                ->with(['vendor:id,display_name'])
                ->select([
                    'id', 'sequence', 'serial_vin', 'model_year', 'model_number',
                    'supplier_name', 'lender_invoice_number', 'dealer_name', 'principal_amount',
                    'current_balance', 'financed_at', 'vendor_id',
                ])
                ->find($linkFinancingId);

            if ($fin) {
                $linkFinancing = [
                    'id' => $fin->id,
                    'display_name' => $fin->display_name,
                    'serial_vin' => $fin->serial_vin,
                    'model_year' => $fin->model_year,
                    'model_number' => $fin->model_number,
                    'supplier_name' => $fin->supplier_name,
                    'lender_invoice_number' => $fin->lender_invoice_number,
                    'dealer_name' => $fin->dealer_name,
                    'principal_amount' => $fin->principal_amount,
                    'current_balance' => $fin->current_balance,
                    'financed_at' => $fin->financed_at?->toDateString(),
                    'vendor_name' => $fin->vendor?->display_name,
                ];
            }
        }

        return inertia('Tenant/AssetUnit/Create', [
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
            'prefill' => $prefill,
            'linkFinancingId' => $linkFinancingId,
            'linkFinancing' => $linkFinancing,
            'returnUrl' => trim((string) $request->query('return_url', '')) ?: null,
        ]);
    }

    /**
     * @param  AssetUnit  $record
     */
    protected function showPageExtraProps($record): array
    {
        $base = parent::showPageExtraProps($record);

        $asset = Asset::query()
            ->select(['id', 'display_name', 'has_variants', 'make_id', 'year', 'model'])
            ->with([
                'make' => fn ($q) => $q->select(['id', 'display_name']),
            ])
            ->find($record->asset_id);

        if ($asset === null) {
            $catalog = [
                'catalogResolvedOptions' => null,
                'catalogContext' => null,
            ];
        } elseif (filter_var($asset->getAttribute('has_variants'), FILTER_VALIDATE_BOOLEAN)) {
            $catalog = [
                'catalogResolvedOptions' => null,
                'catalogContext' => null,
            ];
        } else {
            $resolved = app(AssetOptionResolver::class)->resolve($asset, null);
            $catalog = [
                'catalogResolvedOptions' => $resolved->values()->all(),
                'catalogContext' => [
                    'asset_id' => $asset->id,
                    'variant_id' => null,
                    'make_id' => $asset->make_id,
                    'has_variants' => false,
                    'show_variant_scope' => false,
                ],
            ];
        }

        $msoRecords = $this->serializeMsoRecordsForUnit($record);
        $financingContext = $this->buildFinancingContext($record);

        if (! $record->is_consignment) {
            return array_merge($base, $catalog, [
                'msoRecords' => $msoRecords,
                'financingContext' => $financingContext,
            ]);
        }

        $hasSigned = $record->consignmentAgreements()->signed()->exists();
        $draft = $record->consignmentAgreements()->unsigned()->latest('id')->first();
        $reviewUrl = $draft ? URL::route('consignment-agreements.review', ['uuid' => $draft->uuid]) : null;
        $latestSigned = $hasSigned
            ? $record->consignmentAgreements()->signed()->latest('id')->first()
            : null;
        $signedReviewUrl = $latestSigned
            ? URL::route('consignment-agreements.review', ['uuid' => $latestSigned->uuid])
            : null;

        return array_merge($base, $catalog, [
            'msoRecords' => $msoRecords,
            'consignmentAgreementContext' => [
                'needs_agreement' => ! $hasSigned,
                'has_signed_agreement' => $hasSigned,
                'review_url' => $reviewUrl,
                'signed_review_url' => $signedReviewUrl,
                'create_url' => URL::route('consignmentagreements.create', ['asset_unit_id' => $record->id]),
                'draft_edit_url' => $draft ? URL::route('consignmentagreements.edit', $draft->id) : null,
                'signed_record_url' => $latestSigned ? URL::route('consignmentagreements.show', $latestSigned->id) : null,
                'draft' => $draft ? $draft->only([
                    'id',
                    'uuid',
                    'agreement_date',
                    'signed_at',
                ]) : null,
            ],
            'financingContext' => $financingContext,
        ]);
    }

    /**
     * @param  AssetUnit  $record
     */
    protected function editPageExtraProps($record): array
    {
        return [
            'financingContext' => $this->buildFinancingContext($record),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFinancingContext(AssetUnit $record): array
    {
        $financing = $record->activeFinancing()
            ->with(['vendor:id,display_name'])
            ->first();

        $metrics = $financing?->metrics()->toArray();

        return [
            'financing' => $financing,
            'metrics' => $metrics,
            'create_url' => URL::route('financings.create', ['asset_unit_id' => $record->id]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeMsoRecordsForUnit(AssetUnit $record): array
    {
        return MsoRecord::query()
            ->where('asset_unit_id', $record->id)
            ->with(['transaction' => fn ($q) => $q->select(['id', 'sequence'])])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (MsoRecord $mso) => [
                'id' => $mso->id,
                'display_name' => $mso->display_name,
                'status' => $mso->status?->value,
                'status_label' => $mso->status?->label(),
                'created_at' => $mso->created_at?->toIso8601String(),
                'submitted_at' => $mso->submitted_at?->toIso8601String(),
                'show_url' => route('mso.show', $mso->id),
                'transaction_label' => $mso->transaction
                    ? 'DL-'.($mso->transaction->sequence ?: $mso->transaction->id)
                    : null,
            ])
            ->values()
            ->all();
    }

    public function transferLocation(Request $request, AssetUnit $assetUnit): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
        ]);

        $assetUnit->update(['location_id' => $validated['location_id']]);

        return response()->json([
            'success' => true,
            'location_id' => (int) $validated['location_id'],
        ]);
    }

    public function updateLayoutFootprint(Request $request, AssetUnit $assetUnit): JsonResponse
    {
        $validated = $request->validate([
            'length_ft' => ['required', 'numeric', 'min:0.01', 'max:500'],
            'width_ft' => ['required', 'numeric', 'min:0.01', 'max:500'],
        ]);

        $assetUnit->load(['asset', 'assetVariant']);

        $asset = $assetUnit->asset;
        if ($asset === null) {
            return response()->json(['message' => 'Unit has no linked asset.'], 422);
        }

        AssetLayoutFootprint::applyFootprintFeet(
            $asset,
            $assetUnit,
            (float) $validated['length_ft'],
            (float) $validated['width_ft'],
        );

        return response()->json([
            'success' => true,
            'length_ft' => (float) $validated['length_ft'],
            'width_ft' => (float) $validated['width_ft'],
        ]);
    }

    public function export(Request $request, AssetUnitSpreadsheetExporter $exporter): StreamedResponse
    {
        $format = strtolower((string) $request->query('format', 'xlsx'));

        $units = AssetUnit::query()
            ->with(['asset:id,display_name'])
            ->orderBy('id')
            ->get();

        return $format === 'csv'
            ? $exporter->toCsv($units)
            : $exporter->toXlsx($units);
    }

    public function import(): InertiaResponse
    {
        return inertia('Tenant/AssetUnit/Import', $this->importPageProps());
    }

    public function importParse(Request $request, AssetUnitSpreadsheetParser $parser): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $rawRows = $parser->readRawRows($request->file('file'));
        $cacheKey = 'asset_unit_spreadsheet_import:'.uniqid('', true);

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

    public function importConfirmHeader(Request $request, AssetUnitSpreadsheetParser $parser): JsonResponse
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

        return response()->json([
            'cache_key' => $validated['cache_key'],
            'columns' => $parsed['columns'],
            'row_count' => count($parsed['rows']),
            'header_row_index' => $parsed['header_row_index'],
            'preamble' => $parsed['preamble'],
            'default_column_map' => AssetUnitSpreadsheetParser::defaultColumnMap(),
            'suggested_match_column' => $this->suggestMatchColumn($parsed['columns']),
        ]);
    }

    public function importPreview(Request $request, AssetUnitImportService $importService): JsonResponse
    {
        $validated = $request->validate([
            'cache_key' => 'required|string',
            'match_column' => 'required|string',
            'match_field' => 'required|string|in:id,hin,serial_number',
            'column_map' => 'nullable|array',
        ]);

        $parsed = $this->parsedImportSession($validated['cache_key']);
        if ($parsed === null) {
            return response()->json(['message' => 'Confirm the header row before continuing.'], 422);
        }

        return response()->json($importService->preview(
            $parsed['rows'],
            $validated['match_column'],
            $validated['match_field'],
            $validated['column_map'] ?? [],
        ));
    }

    public function importRun(Request $request, AssetUnitImportService $importService): JsonResponse
    {
        $validated = $request->validate([
            'cache_key' => 'required|string',
            'match_column' => 'required|string',
            'match_field' => 'required|string|in:id,hin,serial_number',
            'column_map' => 'nullable|array',
        ]);

        $parsed = $this->parsedImportSession($validated['cache_key']);
        if ($parsed === null) {
            return response()->json(['message' => 'Confirm the header row before continuing.'], 422);
        }

        $result = $importService->import(
            $parsed['rows'],
            $validated['match_column'],
            $validated['match_field'],
            $validated['column_map'] ?? [],
        );

        Cache::forget($validated['cache_key']);

        return response()->json($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function importPageProps(): array
    {
        return [
            'importDefaults' => [
                'column_map' => AssetUnitSpreadsheetParser::defaultColumnMap(),
                'match_fields' => [
                    ['value' => 'id', 'label' => 'Unit ID'],
                    ['value' => 'hin', 'label' => 'Hull number (HIN)'],
                    ['value' => 'serial_number', 'label' => 'Serial number'],
                ],
                'match_columns' => AssetUnitSpreadsheetParser::defaultMatchColumns(),
            ],
            'importFieldOptions' => AssetUnitSpreadsheetParser::importFieldOptions(),
        ];
    }

    /**
     * @param  list<string>  $columns
     */
    private function suggestMatchColumn(array $columns): ?string
    {
        foreach (AssetUnitSpreadsheetParser::defaultMatchColumns() as $preferred) {
            if (in_array($preferred, $columns, true)) {
                return $preferred;
            }
        }

        return $columns[0] ?? null;
    }

    /**
     * @return array{columns: list<string>, header_row_index: int, rows: list<array<string, string|null>>, preamble: list<string>}|null
     */
    private function parsedImportSession(string $cacheKey): ?array
    {
        $session = Cache::get($cacheKey);
        if (! is_array($session) || ! is_array($session['parsed'] ?? null)) {
            return null;
        }

        return $session['parsed'];
    }

    public function googleSheetPush(AssetUnitGoogleSheetSyncService $syncService): JsonResponse
    {
        try {
            return response()->json($syncService->push());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function googleSheetPull(AssetUnitGoogleSheetSyncService $syncService): JsonResponse
    {
        try {
            return response()->json($syncService->pull());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function googleSheetRecreate(AssetUnitGoogleSheetSyncService $syncService): JsonResponse
    {
        try {
            return response()->json($syncService->recreate());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
