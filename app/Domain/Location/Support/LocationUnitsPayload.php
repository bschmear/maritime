<?php

declare(strict_types=1);

namespace App\Domain\Location\Support;

use App\Domain\Asset\Support\AssetLayoutFootprint;
use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Location\Models\Location;
use App\Domain\Location\Models\LocationLayout;
use App\Domain\Location\Models\LocationLayoutUnit;
use App\Enums\Inventory\AssetType;
use App\Enums\Inventory\UnitStatus;
use App\Support\LengthMillimeters;
use Illuminate\Database\Eloquent\Builder;

final class LocationUnitsPayload
{
    /**
     * Default status filter ids: Available, Consignment, Inbound, Reserved.
     *
     * @return list<int>
     */
    public static function defaultStatusFilterIds(): array
    {
        return [
            UnitStatus::Available->id(),
            UnitStatus::Consignment->id(),
            UnitStatus::Inbound->id(),
            UnitStatus::Reserved->id(),
        ];
    }

    /**
     * @return array{width_ft: int, height_ft: int, perimeter: mixed, fixtures: array<int, mixed>}
     */
    public static function layoutSpaceFrom(?LocationLayout $layout): array
    {
        if ($layout === null) {
            return [
                'width_ft' => 60,
                'height_ft' => 40,
                'perimeter' => null,
                'fixtures' => [],
            ];
        }

        $meta = is_array($layout->meta) ? $layout->meta : [];

        return [
            'width_ft' => (int) $layout->width_ft,
            'height_ft' => (int) $layout->height_ft,
            'perimeter' => $meta['perimeter'] ?? null,
            'fixtures' => $meta['fixtures'] ?? [],
        ];
    }

    /**
     * Layout placements plus at-location units not yet on this layout (sidebar pool).
     *
     * @return list<array<string, mixed>>
     */
    public static function forLayoutSidebar(LocationLayout $layout, Location $location): array
    {
        $items = self::forLayout($layout, $location);
        $onLayoutUnitIds = collect($items)->pluck('asset_unit_id')->filter()->unique();

        $candidates = AssetUnit::query()
            ->where('location_id', $location->id)
            ->where('inactive', false)
            ->whereIn('status', self::defaultStatusFilterIds())
            ->when(
                $onLayoutUnitIds->isNotEmpty(),
                fn (Builder $q) => $q->whereNotIn('id', $onLayoutUnitIds),
            )
            ->whereHas(
                'asset',
                fn (Builder $q) => $q->whereIn('type', [
                    AssetType::Boat->value,
                    AssetType::Engine->value,
                    AssetType::Trailer->value,
                ]),
            )
            ->with([
                'asset.make',
                'assetVariant',
                'location:id,display_name',
            ])
            ->orderBy('id')
            ->get();

        foreach ($candidates as $unit) {
            $serialized = self::serializePoolCandidate($unit, $location);
            if ($serialized !== null) {
                $items[] = $serialized;
            }
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forLayout(LocationLayout $layout, Location $location): array
    {
        $rows = $layout->layoutUnits()
            ->with([
                'assetUnit.asset.make',
                'assetUnit.assetVariant',
                'assetUnit.location:id,display_name',
            ])
            ->orderBy('id')
            ->get();

        $items = [];

        foreach ($rows as $link) {
            $serialized = self::serializePlacement($link, $location);
            if ($serialized !== null) {
                $items[] = $serialized;
            }
        }

        return $items;
    }

    /**
     * Placements grouped by asset type for layout print / ledger.
     *
     * @return array{boats: list<array<string, mixed>>, engines: list<array<string, mixed>>, trailers: list<array<string, mixed>>}
     */
    public static function groupedForPrint(LocationLayout $layout, Location $location): array
    {
        $boats = [];
        $engines = [];
        $trailers = [];

        foreach (self::forLayout($layout, $location) as $item) {
            $type = (int) ($item['type'] ?? 0);
            if ($type === AssetType::Boat->value) {
                $boats[] = $item;
            } elseif ($type === AssetType::Engine->value) {
                $engines[] = $item;
            } elseif ($type === AssetType::Trailer->value) {
                $trailers[] = $item;
            }
        }

        return [
            'boats' => $boats,
            'engines' => $engines,
            'trailers' => $trailers,
        ];
    }

    /**
     * Units available for picker (not already on this layout).
     *
     * @param  list<int>|null  $statusIds
     * @return list<array<string, mixed>>
     */
    public static function pickerUnits(
        Location $location,
        LocationLayout $layout,
        ?array $statusIds = null,
        string $scope = 'all',
        ?string $search = null,
    ): array {
        $statusIds = $statusIds ?? self::defaultStatusFilterIds();
        $onLayoutUnitIds = $layout->layoutUnits()->pluck('asset_unit_id');

        $subsidiaryIds = $location->subsidiaries()->pluck('subsidiaries.id');

        $query = AssetUnit::query()
            ->where('inactive', false)
            ->whereIn('status', $statusIds)
            ->when($onLayoutUnitIds->isNotEmpty(), fn (Builder $q) => $q->whereNotIn('id', $onLayoutUnitIds))
            ->when(
                $subsidiaryIds->isNotEmpty(),
                fn (Builder $q) => $q->whereIn('subsidiary_id', $subsidiaryIds),
            )
            ->with([
                'asset:id,display_name,type',
                'assetVariant:id,display_name,name',
                'location:id,display_name',
            ]);

        if ($scope === 'at_location') {
            $query->where('location_id', $location->id);
        } elseif ($scope === 'other') {
            $query->where(function (Builder $q) use ($location) {
                $q->whereNull('location_id')
                    ->orWhere('location_id', '!=', $location->id);
            });
        }

        if ($search !== null && trim($search) !== '') {
            $term = '%'.strtolower(trim($search)).'%';
            $query->where(function (Builder $q) use ($term) {
                $q->whereRaw('LOWER(hin) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(serial_number) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(sku) LIKE ?', [$term])
                    ->orWhereHas('asset', fn (Builder $aq) => $aq->whereRaw('LOWER(display_name) LIKE ?', [$term]));
            });
        }

        return $query->orderBy('id')->limit(200)->get()
            ->map(fn (AssetUnit $unit) => self::serializePickerUnit($unit, $location))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function serializePickerUnit(AssetUnit $unit, Location $location): array
    {
        $asset = $unit->asset;
        $footprint = $asset !== null
            ? AssetLayoutFootprint::defaultFor($asset, $unit)
            : ['length_ft' => 20.0, 'width_ft' => 8.0];

        return [
            'asset_unit_id' => $unit->id,
            'asset_id' => $unit->asset_id,
            'type' => $asset !== null ? (int) $asset->type : null,
            'display_name' => $unit->display_name ?? $asset?->display_name ?? "Unit #{$unit->id}",
            'unit_label' => AssetLayoutFootprint::unitShortLabel($unit, $asset),
            'status' => (int) $unit->status,
            'location_id' => $unit->location_id,
            'current_location_name' => $unit->location?->display_name,
            'is_at_location' => (int) $unit->location_id === (int) $location->id,
            'length_ft' => $footprint['length_ft'],
            'width_ft' => $footprint['width_ft'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function serializePlacement(LocationLayoutUnit $link, Location $location): ?array
    {
        $unit = $link->assetUnit;
        if ($unit === null) {
            return null;
        }

        $asset = $unit->asset;
        if ($asset === null) {
            return null;
        }

        [$lengthMm, $widthMm] = AssetLayoutFootprint::resolveLengthWidthMillimeters($asset, $unit);
        $lengthForLayout = LengthMillimeters::toFeetFloat($lengthMm);
        $widthForLayout = LengthMillimeters::toFeetFloat($widthMm);

        $lengthFt = $link->length_ft !== null ? (float) $link->length_ft : (float) ($lengthForLayout ?? 20);
        $widthFt = $link->width_ft !== null ? (float) $link->width_ft : (float) ($widthForLayout ?? 8);

        $unitLabel = AssetLayoutFootprint::unitShortLabel($unit, $asset);

        $base = [
            'placement_id' => $link->id,
            'asset_unit_id' => $unit->id,
            'id' => $asset->id,
            'type' => (int) $asset->type,
            'display_name' => $asset->display_name,
            'unit_label' => $unitLabel,
            'status' => (int) $unit->status,
            'location_id' => $unit->location_id,
            'current_location_name' => $unit->location?->display_name,
            'is_at_location' => (int) $unit->location_id === (int) $location->id,
            'length_ft' => $lengthFt,
            'width_ft' => $widthFt,
            'layout_label' => $link->name,
            'include_in_layout' => (bool) $link->include_in_layout,
            'x' => (float) $link->x,
            'y' => (float) $link->y,
            'rotation' => (int) $link->rotation,
            'z_index' => (int) $link->z_index,
            'color' => $link->color,
            'asset_unit' => [
                'id' => $unit->id,
                'display_name' => $unit->display_name,
                'unit_label' => $unitLabel,
            ],
        ];

        if ((int) $asset->type === AssetType::Boat->value && $lengthForLayout !== null) {
            $base['length_display'] = round($lengthForLayout, 2).' ft';
        }

        return $base;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function serializePoolCandidate(AssetUnit $unit, Location $location): ?array
    {
        $asset = $unit->asset;
        if ($asset === null) {
            return null;
        }

        if (! in_array((int) $asset->type, [
            AssetType::Boat->value,
            AssetType::Engine->value,
            AssetType::Trailer->value,
        ], true)) {
            return null;
        }

        $footprint = AssetLayoutFootprint::defaultFor($asset, $unit);
        $unitLabel = AssetLayoutFootprint::unitShortLabel($unit, $asset);

        $base = [
            'pool_only' => true,
            'placement_id' => null,
            'asset_unit_id' => $unit->id,
            'id' => $asset->id,
            'type' => (int) $asset->type,
            'display_name' => $asset->display_name,
            'unit_label' => $unitLabel,
            'status' => (int) $unit->status,
            'location_id' => $unit->location_id,
            'current_location_name' => $unit->location?->display_name,
            'is_at_location' => true,
            'length_ft' => $footprint['length_ft'],
            'width_ft' => $footprint['width_ft'],
            'layout_label' => null,
            'include_in_layout' => false,
            'x' => 0,
            'y' => 0,
            'rotation' => 0,
            'z_index' => 0,
            'color' => self::canonicalLayoutColorForAssetType((int) $asset->type),
            'asset_unit' => [
                'id' => $unit->id,
                'display_name' => $unit->display_name,
                'unit_label' => $unitLabel,
            ],
        ];

        [$lengthMm] = AssetLayoutFootprint::resolveLengthWidthMillimeters($asset, $unit);
        $lengthForLayout = LengthMillimeters::toFeetFloat($lengthMm);
        if ((int) $asset->type === AssetType::Boat->value && $lengthForLayout !== null) {
            $base['length_display'] = round($lengthForLayout, 2).' ft';
        }

        return $base;
    }

    public static function canonicalLayoutColorForAssetType(int $assetType): string
    {
        return match ($assetType) {
            AssetType::Boat->value => '#3B82F6',
            AssetType::Engine->value => '#F97316',
            AssetType::Trailer->value => '#22C55E',
            default => '#64748B',
        };
    }
}
