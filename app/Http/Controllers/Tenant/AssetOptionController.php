<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Asset\Models\Asset;
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
