<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Asset\Models\Asset;
use App\Domain\BoatMake\Actions\CreateBoatMake as CreateAction;
use App\Domain\BoatMake\Actions\DeleteBoatMake as DeleteAction;
use App\Domain\BoatMake\Actions\UpdateBoatMake as UpdateAction;
use App\Domain\BoatMake\Models\BoatMake as RecordModel;
use App\Domain\BoatMake\Models\BoatMakeModelImport;
use App\Domain\InventoryCatalog\Services\CatalogImportService;
use App\Jobs\ProcessBoatMakeModelImportJob;
use App\Services\BoatMetaAIService;
use App\Support\ManufacturerCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BoatMakeController extends RecordController
{
    protected $recordType = 'BoatMake';

    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'boatmakes',
            'Brand',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            $this->recordType // Domain name for schema lookup
        );
    }

    protected function indexInertiaProps(Request $request, $records, $schema, array $fieldsSchema, $formSchema, array $enumOptions): array
    {
        $props = parent::indexInertiaProps($request, $records, $schema, $fieldsSchema, $formSchema, $enumOptions);
        $props['manufacturers'] = ManufacturerCatalog::entries();
        $props['existingBrandKeys'] = RecordModel::query()
            ->whereNotNull('brand_key')
            ->pluck('brand_key')
            ->all();

        return $props;
    }

    protected function showPageExtraProps($record): array
    {
        /** @var RecordModel $record */
        $preview = app(CatalogImportService::class)->preview($record);

        return [
            'catalogPreview' => $preview,
            'libraryModels' => $this->libraryModelsFromInventoryPreview($record, $preview),
            'pendingModelImports' => BoatMakeModelImport::query()
                ->where('boat_make_id', $record->id)
                ->whereIn('status', [BoatMakeModelImport::STATUS_PENDING, BoatMakeModelImport::STATUS_PROCESSING])
                ->orderBy('id')
                ->get(['id', 'model_slug', 'model_label', 'status', 'catalog_asset_key'])
                ->all(),
            'recentFailedModelImports' => BoatMakeModelImport::query()
                ->where('boat_make_id', $record->id)
                ->where('status', BoatMakeModelImport::STATUS_FAILED)
                ->where('updated_at', '>', now()->subDay())
                ->orderByDesc('updated_at')
                ->limit(15)
                ->get(['id', 'model_slug', 'model_label', 'error_message', 'updated_at'])
                ->all(),
        ];
    }

    /**
     * Model lines from the external inventory database (`inventory` connection): same rows as catalog preview.
     *
     * @param  array{catalog_rows: list<array<string, mixed>>, imported_keys: list<string>}  $preview
     * @return list<array{slug: string, label: string}>
     */
    protected function libraryModelsFromInventoryPreview(RecordModel $record, array $preview): array
    {
        if ($record->brand_key === null || $record->brand_key === '') {
            return [];
        }

        $prefix = $record->brand_key.'--';
        $out = [];

        foreach ($preview['catalog_rows'] as $row) {
            $catalogKey = $row['catalog_asset_key'] ?? '';
            if ($catalogKey === '' || ! str_starts_with($catalogKey, $prefix)) {
                continue;
            }
            $modelSlug = Str::after($catalogKey, $prefix);
            if ($modelSlug === '') {
                continue;
            }
            $out[] = [
                'slug' => $modelSlug,
                'label' => (string) ($row['display_name'] ?? $row['model'] ?? $modelSlug),
            ];
        }

        return $out;
    }

    /**
     * Create a brand manually (no inventory catalog link — brand_key stays null).
     * Returns JSON for axios. On possible duplicate, returns 422 with a `code` payload for the UI.
     */
    public function storeManual(Request $request, CreateAction $create): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'display_name' => ['required', 'string', 'max:255'],
            'asset_types' => ['required', 'array', 'min:1'],
            'asset_types.*' => ['integer', 'in:1,2,3,4'],
            'confirm_tenant_duplicate' => ['sometimes', 'boolean'],
            'confirm_catalog_match' => ['sometimes', 'boolean'],
        ])->validate();

        $normalized = mb_strtolower(trim($data['display_name']));

        $tenantDup = RecordModel::query()
            ->whereRaw('lower(trim(display_name)) = ?', [$normalized])
            ->first();

        if ($tenantDup && ! ($data['confirm_tenant_duplicate'] ?? false)) {
            return response()->json([
                'code' => 'TENANT_DUPLICATE',
                'message' => 'A brand with this name already exists. Is this your brand?',
                'existing' => [
                    'id' => $tenantDup->id,
                    'display_name' => $tenantDup->display_name,
                ],
            ], 422);
        }

        $catalogRow = ManufacturerCatalog::findRowByNormalizedDisplayName($normalized);

        if ($catalogRow && ! ($data['confirm_catalog_match'] ?? false)) {
            return response()->json([
                'code' => 'CATALOG_MATCH',
                'message' => 'This name matches a catalog manufacturer. Add it from the list to sync with inventory, or create a manual brand without a catalog link.',
                'catalog' => $catalogRow,
            ], 422);
        }

        $result = $create([
            'display_name' => trim($data['display_name']),
            'asset_types' => $data['asset_types'],
            'is_custom' => true,
            'active' => true,
        ]);

        if (($result['success'] ?? false) !== true) {
            return response()->json([
                'message' => $result['message'] ?? 'Could not create brand.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'record' => [
                'id' => $result['record']->id,
                'display_name' => $result['record']->display_name,
            ],
        ]);
    }

    public function bulkFromCatalog(Request $request, CreateAction $create)
    {
        $data = Validator::make($request->all(), [
            'brand_keys' => ['required', 'array', 'min:1'],
            'brand_keys.*' => ['required', 'string', 'max:255'],
            'asset_types' => ['sometimes', 'array', 'min:1'],
            'asset_types.*' => ['integer', 'in:1,2,3,4'],
        ])->validate();

        $allowed = collect(ManufacturerCatalog::entries())->keyBy('slug');
        $assetTypes = $data['asset_types'] ?? [1];

        $created = 0;
        foreach ($data['brand_keys'] as $slug) {
            if (! isset($allowed[$slug])) {
                continue;
            }
            if (RecordModel::query()->where('brand_key', $slug)->exists()) {
                continue;
            }
            $label = $allowed[$slug]['display_name'];
            $result = $create([
                'display_name' => $label,
                'asset_types' => $assetTypes,
                'is_custom' => false,
                'active' => true,
                'brand_key' => $slug,
            ]);
            if (($result['success'] ?? false) === true) {
                $created++;
            }
        }

        return back()->with('success', $created.' brand(s) added.');
    }

    public function catalogImport(Request $request, string $id)
    {
        $record = RecordModel::query()->findOrFail($id);
        $data = Validator::make($request->all(), [
            'catalog_asset_keys' => ['nullable', 'array'],
            'catalog_asset_keys.*' => ['string', 'max:255'],
        ])->validate();

        $keys = $data['catalog_asset_keys'] ?? null;
        $result = app(CatalogImportService::class)->import($record, $keys);

        return back()->with('success', "Imported {$result['imported']} catalog row(s).");
    }

    public function catalogGenerateModel(Request $request, string $id, BoatMetaAIService $ai)
    {
        $record = RecordModel::query()->findOrFail($id);
        if (! $record->brand_key) {
            return back()->withErrors(['brand' => 'Brand key is required.']);
        }
        $data = Validator::make($request->all(), [
            'model_slug' => ['nullable', 'string', 'max:120'],
            'model_label' => ['required', 'string', 'max:255'],
        ])->validate();

        $slug = trim((string) ($data['model_slug'] ?? '')) !== ''
            ? Str::slug($data['model_slug'])
            : Str::slug($data['model_label']);

        if ($slug === '') {
            return back()->withErrors([
                'model_label' => 'Could not derive a model key from that name. Try a different spelling.',
            ]);
        }

        try {
            $ai->generate(
                $record->brand_key,
                $slug,
                $record->display_name,
                $data['model_label']
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'model_label' => 'Could not generate model details: '.$e->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Added "'.$data['model_label'].'" to the inventory catalog. You can import it from the list above or use "Add all models".'
        );
    }

    public function queueImportDiscoveredModels(Request $request, string $id)
    {
        $record = RecordModel::query()->findOrFail($id);
        if (! $record->brand_key) {
            return back()->withErrors([
                'import_discovered' => 'This brand needs a catalog link (brand key) before library imports can run.',
            ]);
        }

        $data = Validator::make($request->all(), [
            'models' => ['required', 'array', 'min:1', 'max:40'],
            'models.*.model_slug' => ['required', 'string', 'max:120'],
            'models.*.model_label' => ['required', 'string', 'max:255'],
        ])->validate();

        $brandKey = $record->brand_key;
        $skippedAlreadyList = 0;
        $queuedIds = [];

        foreach ($data['models'] as $row) {
            $slug = Str::slug($row['model_slug'] ?? '');
            $label = trim((string) ($row['model_label'] ?? ''));
            if ($slug === '' || $label === '') {
                continue;
            }

            $catalogKey = $brandKey.'--'.$slug;

            if (Asset::query()->where('make_id', $record->id)->where('catalog_asset_key', $catalogKey)->exists()) {
                $skippedAlreadyList++;

                continue;
            }

            $duplicateQueue = BoatMakeModelImport::query()
                ->where('boat_make_id', $record->id)
                ->where('model_slug', $slug)
                ->whereIn('status', [BoatMakeModelImport::STATUS_PENDING, BoatMakeModelImport::STATUS_PROCESSING])
                ->exists();

            if ($duplicateQueue) {
                continue;
            }

            $importRow = BoatMakeModelImport::query()->create([
                'boat_make_id' => $record->id,
                'model_slug' => $slug,
                'model_label' => $label,
                'status' => BoatMakeModelImport::STATUS_PENDING,
            ]);
            $queuedIds[] = $importRow->id;
        }

        if ($queuedIds === []) {
            if ($skippedAlreadyList > 0) {
                return back()->with(
                    'success',
                    $skippedAlreadyList === 1
                        ? 'That model is already on your list.'
                        : 'Those models are already on your list.'
                );
            }

            return back()->with('success', 'Nothing new to import.');
        }

        try {
            Bus::chain(
                array_map(static fn (int $id) => new ProcessBoatMakeModelImportJob($id), $queuedIds)
            )->dispatch();
        } catch (\Throwable $e) {
            report($e);
            BoatMakeModelImport::query()->whereIn('id', $queuedIds)->update([
                'status' => BoatMakeModelImport::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'import_discovered' => 'Could not queue imports: '.$e->getMessage(),
            ]);
        }

        $n = count($queuedIds);
        $msg = $n === 1
            ? '1 model is importing in the background. It will appear on your list when ready.'
            : $n.' models are importing in the background one at a time. They will appear on your list as each finishes.';

        if ($skippedAlreadyList > 0) {
            $msg .= ' '.$skippedAlreadyList.' '.($skippedAlreadyList === 1 ? 'was' : 'were').' already on your list (skipped).';
        }

        return back()->with('success', $msg);
    }
}
