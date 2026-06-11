<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetOption\Actions\AttachAssetOptionToCatalog;
use App\Domain\AssetOption\Actions\CreateAssetOption as CreateAction;
use App\Domain\AssetOption\Actions\DeleteAssetOption as DeleteAction;
use App\Domain\AssetOption\Actions\SyncAssetOptionAssignments;
use App\Domain\AssetOption\Actions\UpdateAssetOption as UpdateAction;
use App\Domain\AssetOption\Models\AssetOption as RecordModel;
use App\Domain\AssetOption\Models\AssetOptionValue;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\BoatMake\Models\BoatMake;
use App\Services\AssetOptionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Response as InertiaResponse;

class AssetOptionController extends RecordController
{
    protected $table = null;

    protected function appendShowRelationships(array &$relationships): void
    {
        $relationships['allValues'] = fn ($q) => $q->orderBy('sort_order');
        $relationships['makeAssignments.make'] = fn ($q) => $q->select(['id', 'display_name']);
        $relationships['assignments.asset'] = fn ($q) => $q->select(['id', 'display_name', 'make_id']);
        $relationships['assignments.variant'] = fn ($q) => $q->select(['id', 'asset_id', 'display_name', 'name']);
    }

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'asset-options',
            'Asset Option',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            'AssetOption'
        );
    }

    /**
     * Asset options index — no RecordController relationship eager-load (options have no make_id column).
     */
    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $schema = $this->getTableSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();

        $tableName = $this->recordModel->getTable();
        $dbColumns = Schema::connection($this->recordModel->getConnectionName())->getColumnListing($tableName);

        $actualColumns = [];
        foreach ($this->getSchemaColumns() as $column) {
            if (! str_contains($column, '.') && in_array($column, $dbColumns, true)) {
                $actualColumns[] = $column;
            }
        }

        if (! in_array('id', $actualColumns, true)) {
            $actualColumns[] = 'id';
        }

        $query = $this->recordModel->newQuery()->select($actualColumns);

        $searchQuery = $request->get('search');
        if (is_string($searchQuery) && trim($searchQuery) !== '') {
            $term = '%'.strtolower(trim($searchQuery)).'%';
            $query->whereRaw('LOWER('.$tableName.'.name) LIKE ?', [$term]);
        }

        $appliedFilters = $this->resolveIndexFiltersFromRequest($request, $schema);
        if ($appliedFilters !== []) {
            $query = $this->applyFilters($query, $appliedFilters, $fieldsSchema);
        }

        $statsBaseQuery = clone $query;

        if (! $this->applyRecordIndexSort($query, $request, $schema, $dbColumns, $tableName, $actualColumns, $fieldsSchema)) {
            $query->orderBy($tableName.'.name');
        }

        $perPage = table_per_page($request);
        $records = $query->paginate($perPage);
        $tableStats = $this->indexTableStats($request, $statsBaseQuery, $schema);

        if ($request->ajax() && ! $request->header('X-Inertia')) {
            return response()->json([
                'records' => $records->items(),
                'schema' => $schema,
                'fieldsSchema' => $fieldsSchema,
                'stats' => $tableStats,
                'meta' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ],
            ]);
        }

        $indexProps = $this->indexInertiaProps($request, $records, $schema, $fieldsSchema, $formSchema, $enumOptions, $appliedFilters);
        $indexProps['stats'] = $tableStats;

        return inertia('Tenant/AssetOption/Index', $indexProps);
    }

    /**
     * Brand is not a column on asset_options — match make-wide or per-asset catalog assignments.
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

            $query->where(function ($q) use ($makeIds) {
                $q->whereHas('makeAssignments', fn ($mq) => $mq->whereIn('make_id', $makeIds))
                    ->orWhereHas('assignments.asset', fn ($aq) => $aq->whereIn('make_id', $makeIds));
            });
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

    public function resolveForAsset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'variant_id' => ['nullable', 'integer', 'exists:asset_variants,id'],
        ]);

        $asset = Asset::query()->findOrFail((int) $validated['asset_id']);

        $variant = null;
        if (! empty($validated['variant_id'])) {
            $variant = AssetVariant::query()
                ->whereKey((int) $validated['variant_id'])
                ->where('asset_id', $asset->id)
                ->firstOrFail();
        }

        $options = app(AssetOptionResolver::class)->resolve($asset, $variant);

        return response()->json(['options' => $options]);
    }

    public function syncAssignments(Request $request, RecordModel $assetOption): JsonResponse|Response
    {
        $validated = $request->validate([
            'sync_all_brands' => ['required', 'boolean'],
            'brands' => ['nullable', 'array'],
            'brands.*.make_id' => ['required', 'integer', 'exists:boat_make,id'],
            'brands.*.apply_to_all_models' => ['required', 'boolean'],
            'brands.*.rows' => ['nullable', 'array'],
            'brands.*.rows.*.asset_id' => ['required', 'integer', 'exists:assets,id'],
            'brands.*.rows.*.variant_id' => ['nullable', 'integer', 'exists:asset_variants,id'],
        ]);

        try {
            app(SyncAssetOptionAssignments::class)(
                $assetOption->id,
                (bool) $validated['sync_all_brands'],
                $validated['brands'] ?? [],
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        }

        return response()->noContent();
    }

    /**
     * Add one catalog assignment for this option (does not replace other assignments — unlike sync-assignments).
     */
    public function attachCatalog(Request $request, RecordModel $assetOption): JsonResponse
    {
        $validated = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'variant_id' => ['nullable', 'integer', 'exists:asset_variants,id'],
            'scope' => ['required', 'string', Rule::in(['variant', 'asset', 'brand'])],
        ]);

        $asset = Asset::query()->findOrFail((int) $validated['asset_id']);

        $variant = null;
        if (! empty($validated['variant_id'])) {
            $variant = AssetVariant::query()
                ->whereKey((int) $validated['variant_id'])
                ->where('asset_id', $asset->id)
                ->firstOrFail();
        }

        if ($validated['scope'] === 'variant' && $variant === null) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => ['variant_id' => ['A variant is required for this scope.']],
            ], 422);
        }

        try {
            app(AttachAssetOptionToCatalog::class)(
                $assetOption->id,
                $validated['scope'],
                $asset,
                $validated['scope'] === 'variant' ? $variant : null
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        }

        return response()->json(['success' => true]);
    }

    public function storeValue(Request $request, RecordModel $assetOption): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string', 'max:255'],
            'color_hex' => ['nullable', 'string', 'max:32'],
            'cost' => ['nullable', 'numeric'],
            'price' => ['nullable', 'numeric'],
            'sort_order' => ['nullable', 'integer'],
            'is_default' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $value = $assetOption->allValues()->create([
            'label' => $validated['label'],
            'value' => $validated['value'] ?? null,
            'color_hex' => $validated['color_hex'] ?? null,
            'cost' => $validated['cost'] ?? null,
            'price' => $validated['price'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_default' => $validated['is_default'] ?? false,
            'active' => $validated['active'] ?? true,
        ]);

        return response()->json(['value' => $value], 201);
    }

    public function updateValue(Request $request, RecordModel $assetOption, AssetOptionValue $value): JsonResponse
    {
        abort_if($value->option_id !== $assetOption->id, 404);

        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'value' => ['nullable', 'string', 'max:255'],
            'color_hex' => ['nullable', 'string', 'max:32'],
            'cost' => ['nullable', 'numeric'],
            'price' => ['nullable', 'numeric'],
            'sort_order' => ['nullable', 'integer'],
            'is_default' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $value->update($validated);

        return response()->json(['value' => $value->fresh()]);
    }

    public function destroyValue(RecordModel $assetOption, AssetOptionValue $value): Response
    {
        abort_if($value->option_id !== $assetOption->id, 404);
        $value->delete();

        return response()->noContent();
    }

    /**
     * Brands and tenant assets for the assignment UI (scoped server-side by make_id when saving).
     *
     * @return array{makers: \Illuminate\Support\Collection, sample_assets: array<int, mixed>}
     */
    public function assignmentLookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'make_id' => ['nullable', 'integer', 'exists:boat_make,id'],
        ]);

        $makers = BoatMake::query()
            ->where('active', true)
            ->orderBy('display_name')
            ->get(['id', 'display_name']);

        $assets = [];
        if (! empty($validated['make_id'])) {
            $assets = Asset::query()
                ->where('make_id', (int) $validated['make_id'])
                ->where('inactive', false)
                ->with(['variants:id,asset_id,name,display_name,inactive'])
                ->orderBy('display_name')
                ->get(['id', 'display_name', 'has_variants', 'make_id'])
                ->map(fn (Asset $a) => [
                    'id' => $a->id,
                    'display_name' => $a->display_name,
                    'has_variants' => (bool) $a->has_variants,
                    'variants' => $a->variants->where('inactive', false)->values(),
                ])
                ->values()
                ->all();
        }

        return response()->json([
            'makers' => $makers,
            'assets' => $assets,
        ]);
    }
}
