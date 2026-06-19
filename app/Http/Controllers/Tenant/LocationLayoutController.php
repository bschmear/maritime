<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Asset\Support\AssetLayoutFootprint;
use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Location\Models\Location;
use App\Domain\Location\Models\LocationLayout;
use App\Domain\Location\Models\LocationLayoutUnit;
use App\Domain\Location\Support\LocationUnitsPayload;
use App\Enums\Inventory\AssetType;
use App\Enums\Inventory\UnitStatus;
use App\Http\Controllers\Controller;
use App\Models\AccountSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;

class LocationLayoutController extends Controller
{
    public function store(Request $request, Location $location): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'width_ft' => ['sometimes', 'integer', 'min:10', 'max:200'],
            'height_ft' => ['sometimes', 'integer', 'min:10', 'max:200'],
        ]);

        $layout = LocationLayout::query()->create([
            'location_id' => $location->id,
            'name' => $validated['name'],
            'width_ft' => $validated['width_ft'] ?? 60,
            'height_ft' => $validated['height_ft'] ?? 40,
            'grid_size' => 1,
            'scale' => 10,
            'meta' => [],
        ]);

        return response()->json([
            'success' => true,
            'layout' => $this->serializeLayoutSummary($layout),
        ], 201);
    }

    public function update(Request $request, Location $location, LocationLayout $layout): JsonResponse
    {
        $this->assertLayoutBelongsToLocation($layout, $location);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'width_ft' => ['sometimes', 'integer', 'min:10', 'max:200'],
            'height_ft' => ['sometimes', 'integer', 'min:10', 'max:200'],
        ]);

        $layout->update($validated);

        return response()->json([
            'success' => true,
            'layout' => $this->serializeLayoutSummary($layout->fresh()),
        ]);
    }

    public function destroy(Location $location, LocationLayout $layout): JsonResponse
    {
        $this->assertLayoutBelongsToLocation($layout, $location);

        $layout->delete();

        return response()->json(['success' => true]);
    }

    public function syncLayout(Request $request, Location $location, LocationLayout $layout): JsonResponse
    {
        $this->assertLayoutBelongsToLocation($layout, $location);

        $validated = $request->validate([
            'width_ft' => ['required', 'integer', 'min:10', 'max:200'],
            'height_ft' => ['required', 'integer', 'min:10', 'max:200'],
            'perimeter' => ['nullable', 'array', 'min:3', 'max:32'],
            'perimeter.*.x' => ['required', 'numeric', 'min:0', 'max:500'],
            'perimeter.*.y' => ['required', 'numeric', 'min:0', 'max:500'],
            'fixtures' => ['nullable', 'array', 'max:100'],
            'fixtures.*.id' => ['required', 'string', 'max:64'],
            'fixtures.*.shape' => ['required', 'string', 'in:rectangle,square,circle'],
            'fixtures.*.label' => ['required', 'string', 'max:255'],
            'fixtures.*.include_in_layout' => ['sometimes', 'boolean'],
            'fixtures.*.x' => ['required', 'numeric'],
            'fixtures.*.y' => ['required', 'numeric'],
            'fixtures.*.rotation' => ['required', 'integer', 'min:0', 'max:359'],
            'fixtures.*.z_index' => ['sometimes', 'integer'],
            'fixtures.*.length_ft' => ['required', 'numeric', 'min:0.01', 'max:500'],
            'fixtures.*.width_ft' => ['required', 'numeric', 'min:0.01', 'max:500'],
            'items' => ['present', 'array'],
            'items.*.placement_id' => ['required', 'integer', 'exists:location_layout_units,id'],
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

        $rows = LocationLayoutUnit::query()
            ->where('location_layout_id', $layout->id)
            ->whereHas('assetUnit.asset', fn ($q) => $q->whereIn('type', $layoutableTypes))
            ->with(['assetUnit.asset:id,type'])
            ->get();

        $byId = collect($validated['items'])->keyBy('placement_id');

        foreach ($rows as $row) {
            $p = $byId->get($row->id);
            if ($p !== null) {
                $assetType = (int) $row->assetUnit->asset->type;
                $row->update([
                    'include_in_layout' => (bool) ($p['include_in_layout'] ?? true),
                    'x' => $p['x'],
                    'y' => $p['y'],
                    'rotation' => (int) $p['rotation'],
                    'z_index' => (int) ($p['z_index'] ?? 0),
                    'name' => $p['name'] ?? null,
                    'length_ft' => $p['length_ft'],
                    'width_ft' => $p['width_ft'],
                    'color' => LocationUnitsPayload::canonicalLayoutColorForAssetType($assetType),
                ]);
            } else {
                $row->update(['include_in_layout' => false]);
            }
        }

        $meta = is_array($layout->meta) ? $layout->meta : [];
        if (array_key_exists('perimeter', $validated)) {
            $meta['perimeter'] = $validated['perimeter'];
        }
        if (array_key_exists('fixtures', $validated)) {
            $meta['fixtures'] = $validated['fixtures'];
        }

        $layout->update([
            'width_ft' => $validated['width_ft'],
            'height_ft' => $validated['height_ft'],
            'meta' => $meta,
        ]);

        return response()->json([
            'success' => true,
            'layoutSpace' => LocationUnitsPayload::layoutSpaceFrom($layout->fresh()),
            'layoutUnits' => LocationUnitsPayload::forLayout($layout->fresh(), $location),
        ]);
    }

    public function storeUnit(Request $request, Location $location, LocationLayout $layout): JsonResponse
    {
        $this->assertLayoutBelongsToLocation($layout, $location);

        $validated = $request->validate([
            'asset_unit_id' => ['required', 'integer', 'exists:asset_units,id'],
            'transfer' => ['sometimes', 'boolean'],
        ]);

        $unit = AssetUnit::query()
            ->with(['asset', 'assetVariant'])
            ->findOrFail($validated['asset_unit_id']);

        if ($validated['transfer'] ?? false) {
            $unit->update(['location_id' => $location->id]);
        }

        if (LocationLayoutUnit::query()
            ->where('location_layout_id', $layout->id)
            ->where('asset_unit_id', $unit->id)
            ->exists()) {
            return response()->json([
                'message' => 'This unit is already on the layout.',
            ], 422);
        }

        $asset = $unit->asset;
        if ($asset === null) {
            return response()->json(['message' => 'Unit has no linked asset.'], 422);
        }

        if (! in_array((int) $asset->type, [
            AssetType::Boat->value,
            AssetType::Engine->value,
            AssetType::Trailer->value,
        ], true)) {
            return response()->json([
                'message' => 'Only boat, engine, and trailer units can be placed on a floor plan.',
            ], 422);
        }

        $footprint = AssetLayoutFootprint::defaultFor($asset, $unit);

        $placement = LocationLayoutUnit::query()->create([
            'location_layout_id' => $layout->id,
            'asset_unit_id' => $unit->id,
            'include_in_layout' => false,
            'x' => 0,
            'y' => 0,
            'rotation' => 0,
            'z_index' => 0,
            'length_ft' => $footprint['length_ft'],
            'width_ft' => $footprint['width_ft'],
            'color' => LocationUnitsPayload::canonicalLayoutColorForAssetType((int) $asset->type),
        ]);

        $placement->load([
            'assetUnit.asset',
            'assetUnit.location',
        ]);

        return response()->json([
            'success' => true,
            'placement' => LocationUnitsPayload::forLayoutSidebar($layout->fresh(), $location),
            'layoutUnits' => LocationUnitsPayload::forLayoutSidebar($layout->fresh(), $location),
        ]);
    }

    public function destroyUnit(Location $location, LocationLayout $layout, LocationLayoutUnit $placement): JsonResponse
    {
        $this->assertLayoutBelongsToLocation($layout, $location);

        if ((int) $placement->location_layout_id !== (int) $layout->id) {
            abort(404);
        }

        $placement->delete();

        return response()->json(['success' => true]);
    }

    public function printLayout(Request $request, Location $location, LocationLayout $layout): InertiaResponse
    {
        $this->assertLayoutBelongsToLocation($layout, $location);

        return inertia('Tenant/Location/LayoutPrint', [
            'record' => [
                'id' => $location->id,
                'display_name' => $location->display_name,
            ],
            'layout' => [
                'id' => $layout->id,
                'name' => $layout->name,
            ],
            'assets' => LocationUnitsPayload::groupedForPrint($layout, $location),
            'layoutSpace' => LocationUnitsPayload::layoutSpaceFrom($layout),
            'backUrl' => route('locations.show', [
                'location' => $location->id,
                'tab' => 'floor_plans',
                'layout' => $layout->id,
            ]),
            'companyName' => $this->companyDisplayName(),
            'subtitle' => $this->locationPrintSubtitle($location),
        ]);
    }

    public function pickerUnits(Request $request, Location $location, LocationLayout $layout): JsonResponse
    {
        $this->assertLayoutBelongsToLocation($layout, $location);

        $validated = $request->validate([
            'status' => ['sometimes', 'array'],
            'status.*' => ['integer', 'in:1,2,3,4,5,6,7'],
            'scope' => ['sometimes', 'string', 'in:at_location,other,all'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $statusIds = $validated['status'] ?? LocationUnitsPayload::defaultStatusFilterIds();
        $scope = $validated['scope'] ?? 'all';
        $search = $validated['search'] ?? null;

        return response()->json([
            'units' => LocationUnitsPayload::pickerUnits($location, $layout, $statusIds, $scope, $search),
            'unitStatusOptions' => UnitStatus::options(),
        ]);
    }

    /**
     * @return array{id: int, name: string|null, width_ft: int, height_ft: int}
     */
    private function serializeLayoutSummary(LocationLayout $layout): array
    {
        return [
            'id' => $layout->id,
            'name' => $layout->name,
            'width_ft' => (int) $layout->width_ft,
            'height_ft' => (int) $layout->height_ft,
        ];
    }

    private function assertLayoutBelongsToLocation(LocationLayout $layout, Location $location): void
    {
        abort_unless((int) $layout->location_id === (int) $location->id, 404);
    }

    private function companyDisplayName(): string
    {
        $account = AccountSettings::getCurrent();
        $settings = $account->settings;
        if (is_array($settings) && ! empty($settings['business_name'])) {
            return (string) $settings['business_name'];
        }

        return (string) ($account->name ?? '');
    }

    private function locationPrintSubtitle(Location $location): ?string
    {
        $parts = array_filter([
            $location->address_line_1,
            $location->address_line_2,
            collect([$location->city, $location->state, $location->postal_code])->filter()->implode(', '),
            $location->country,
        ]);

        return $parts !== [] ? implode(' · ', $parts) : null;
    }
}
