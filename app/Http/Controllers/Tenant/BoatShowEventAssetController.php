<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\BoatShow\Models\BoatShow;
use App\Domain\BoatShowEvent\Models\BoatShowEvent;
use App\Domain\BoatShowEvent\Models\BoatShowEventAsset;
use App\Domain\BoatShowEvent\Support\EventAssetsPayload;
use App\Domain\BoatShowLayout\Models\BoatShowLayout;
use App\Enums\Inventory\AssetType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoatShowEventAssetController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $event = $this->resolveEvent($request);

        $validated = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'asset_unit_id' => [
                'nullable',
                'integer',
            ],
        ]);

        $asset = Asset::query()
            ->with(['specValues.definition'])
            ->findOrFail($validated['asset_id']);

        if (! in_array((int) $asset->type, [
            AssetType::Boat->value,
            AssetType::Engine->value,
            AssetType::Trailer->value,
        ], true)) {
            return response()->json([
                'message' => 'Only boat, engine, and trailer assets can be assigned to an event.',
            ], 422);
        }

        if (BoatShowEventAsset::query()
            ->where('boat_show_event_id', $event->id)
            ->where('asset_id', $asset->id)
            ->exists()) {
            return response()->json([
                'message' => 'This asset is already on the event.',
            ], 422);
        }

        $unitId = $validated['asset_unit_id'] ?? null;
        $unit = null;
        if ($unitId !== null) {
            $unit = AssetUnit::query()
                ->where('id', $unitId)
                ->where('asset_id', $asset->id)
                ->with('assetVariant')
                ->first();
            if ($unit === null) {
                return response()->json([
                    'message' => 'The selected unit does not belong to this asset.',
                ], 422);
            }
        }

        $footprint = EventAssetsPayload::defaultLayoutFootprint($asset, $unit);

        BoatShowEventAsset::query()->create([
            'boat_show_event_id' => $event->id,
            'asset_id' => $asset->id,
            'asset_unit_id' => $unitId,
            'include_in_layout' => false,
            'x' => 0,
            'y' => 0,
            'rotation' => 0,
            'z_index' => 0,
            'length_ft' => $footprint['length_ft'],
            'width_ft' => $footprint['width_ft'],
            'color' => self::canonicalLayoutColorForAssetType((int) $asset->type),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Persist floor plan: boat_show_event_assets rows + boat_show_layouts dimensions.
     */
    public function syncLayout(Request $request): JsonResponse
    {
        $event = $this->resolveEvent($request);

        $validated = $request->validate([
            'width_ft' => ['required', 'integer', 'min:10', 'max:200'],
            'height_ft' => ['required', 'integer', 'min:10', 'max:200'],
            'items' => ['present', 'array'],
            'items.*.event_asset_id' => ['required', 'integer', 'exists:boat_show_event_assets,id'],
            'items.*.include_in_layout' => ['sometimes', 'boolean'],
            'items.*.x' => ['required', 'numeric'],
            'items.*.y' => ['required', 'numeric'],
            'items.*.rotation' => ['required', 'integer', 'min:0', 'max:359'],
            'items.*.z_index' => ['sometimes', 'integer'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.length_ft' => ['required', 'numeric', 'min:0.01', 'max:500'],
            'items.*.width_ft' => ['required', 'numeric', 'min:0.01', 'max:500'],
        ]);

        $layoutableTypes = [
            AssetType::Boat->value,
            AssetType::Engine->value,
            AssetType::Trailer->value,
        ];

        $rows = BoatShowEventAsset::query()
            ->where('boat_show_event_id', $event->id)
            ->whereHas('asset', fn ($q) => $q->whereIn('type', $layoutableTypes))
            ->with('asset:id,type')
            ->get();

        $byId = collect($validated['items'])->keyBy('event_asset_id');

        foreach ($rows as $row) {
            $p = $byId->get($row->id);
            if ($p !== null) {
                $assetType = (int) $row->asset->type;
                $row->update([
                    'include_in_layout' => (bool) ($p['include_in_layout'] ?? true),
                    'x' => $p['x'],
                    'y' => $p['y'],
                    'rotation' => (int) $p['rotation'],
                    'z_index' => (int) ($p['z_index'] ?? 0),
                    'name' => $p['name'] ?? null,
                    'length_ft' => $p['length_ft'],
                    'width_ft' => $p['width_ft'],
                    'color' => self::canonicalLayoutColorForAssetType($assetType),
                ]);
            } else {
                $row->update(['include_in_layout' => false]);
            }
        }

        $layout = BoatShowLayout::query()
            ->where('boat_show_event_id', $event->id)
            ->orderBy('id')
            ->first();

        if ($layout === null) {
            $layout = BoatShowLayout::query()->create([
                'boat_show_event_id' => $event->id,
                'name' => 'Default',
                'width_ft' => $validated['width_ft'],
                'height_ft' => $validated['height_ft'],
                'grid_size' => 1,
                'scale' => 10,
            ]);
        } else {
            $layout->update([
                'width_ft' => $validated['width_ft'],
                'height_ft' => $validated['height_ft'],
            ]);
        }

        return response()->json([
            'success' => true,
            'layoutSpace' => [
                'width_ft' => $layout->width_ft,
                'height_ft' => $layout->height_ft,
            ],
        ]);
    }

    public function destroy(Request $request, BoatShowEventAsset $eventAsset): JsonResponse
    {
        $event = $this->resolveEvent($request);

        if ((int) $eventAsset->boat_show_event_id !== (int) $event->id) {
            abort(404);
        }

        $eventAsset->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Units for a given asset (for optional unit picker).
     */
    public function units(Request $request): JsonResponse
    {
        $this->resolveEvent($request);

        $assetId = $request->integer('asset_id');
        if ($assetId < 1) {
            return response()->json(['units' => []]);
        }

        $units = AssetUnit::query()
            ->where('asset_id', $assetId)
            ->with('asset:id,display_name')
            ->orderBy('id')
            ->get(['id', 'asset_id', 'serial_number', 'hin', 'sku']);

        return response()->json([
            'units' => $units->map(fn (AssetUnit $u) => [
                'id' => $u->id,
                'display_name' => $u->display_name,
            ])->values()->all(),
        ]);
    }

    /**
     * Matches tenant asset list accents (Tailwind *-500): boats blue, engines orange, trailers green.
     */
    private static function canonicalLayoutColorForAssetType(int $assetType): string
    {
        return match ($assetType) {
            AssetType::Boat->value => '#3B82F6',
            AssetType::Engine->value => '#F97316',
            AssetType::Trailer->value => '#22C55E',
            default => '#64748B',
        };
    }

    private function resolveEvent(Request $request): BoatShowEvent
    {
        $eventId = (int) $request->route('event');
        $event = BoatShowEvent::query()->findOrFail($eventId);

        $boatShow = $request->route('boatShow');
        if ($boatShow !== null) {
            $show = $this->resolveBoatShow($boatShow);
            abort_unless((int) $event->boat_show_id === (int) $show->id, 404);
        }

        return $event;
    }

    private function resolveBoatShow(mixed $boatShow): BoatShow
    {
        if ($boatShow instanceof BoatShow) {
            return $boatShow;
        }

        return BoatShow::query()
            ->where('id', $boatShow)
            ->orWhere('slug', $boatShow)
            ->firstOrFail();
    }
}
