<?php

namespace App\Domain\BoatShowEvent\Support;

use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Support\AssetLayoutFootprint;
use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\BoatShowEvent\Models\BoatShowEvent;
use App\Domain\BoatShowEvent\Models\BoatShowEventAsset;
use App\Domain\InventoryImage\Models\InventoryImage;
use App\Enums\Inventory\AssetType;
use App\Support\LengthMillimeters;

final class EventAssetsPayload
{
    public static function unitShortLabel(?AssetUnit $unit, ?Asset $asset = null): ?string
    {
        return AssetLayoutFootprint::unitShortLabel($unit, $asset);
    }

    /**
     * Default floor-plan footprint (feet) from asset / variant columns (not dynamic specs).
     *
     * @return array{length_ft: float, width_ft: float}
     */
    public static function defaultLayoutFootprint(Asset $asset, ?AssetUnit $unit = null): array
    {
        return AssetLayoutFootprint::defaultFor($asset, $unit);
    }

    /**
     * @return array{boats: array<int, array<string, mixed>>, engines: array<int, array<string, mixed>>, trailers: array<int, array<string, mixed>>}
     */
    public static function grouped(BoatShowEvent $event): array
    {
        $rows = $event->eventAssets()
            ->with([
                'asset.make',
                'asset.specValues.definition',
                'assetUnit.asset:id,display_name',
                'assetUnit.assetVariant',
            ])
            ->orderBy('id')
            ->get();

        $boats = [];
        $engines = [];
        $trailers = [];

        foreach ($rows as $link) {
            $payload = self::serializeLink($link);
            $type = (int) $link->asset->type;
            if ($type === AssetType::Boat->value) {
                $boats[] = $payload;
            } elseif ($type === AssetType::Engine->value) {
                $engines[] = $payload;
            } elseif ($type === AssetType::Trailer->value) {
                $trailers[] = $payload;
            }
        }

        return [
            'boats' => $boats,
            'engines' => $engines,
            'trailers' => $trailers,
        ];
    }

    /**
     * Same grouping as {@see grouped()} with primary inventory image URL per asset (for public showcase).
     *
     * @return array{boats: array<int, array<string, mixed>>, engines: array<int, array<string, mixed>>, trailers: array<int, array<string, mixed>>}
     */
    public static function groupedForPublic(BoatShowEvent $event): array
    {
        $rows = $event->eventAssets()
            ->with([
                'asset.make',
                'asset.specValues.definition',
                'asset.images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
                'assetUnit.asset:id,display_name',
                'assetUnit.assetVariant',
            ])
            ->orderBy('id')
            ->get();

        $boats = [];
        $engines = [];
        $trailers = [];

        foreach ($rows as $link) {
            $payload = self::serializeLinkForPublic($link);
            $type = (int) $link->asset->type;
            if ($type === AssetType::Boat->value) {
                $boats[] = $payload;
            } elseif ($type === AssetType::Engine->value) {
                $engines[] = $payload;
            } elseif ($type === AssetType::Trailer->value) {
                $trailers[] = $payload;
            }
        }

        return [
            'boats' => $boats,
            'engines' => $engines,
            'trailers' => $trailers,
        ];
    }

    /**
     * Full public listing payload for one asset linked to the event (gallery, description, specs).
     *
     * @return array<string, mixed>|null
     */
    public static function detailForPublic(BoatShowEvent $event, int $assetId): ?array
    {
        $link = BoatShowEventAsset::query()
            ->where('boat_show_event_id', $event->id)
            ->where('asset_id', $assetId)
            ->with([
                'asset.make',
                'asset.specValues.definition',
                'asset.images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
                'assetUnit.asset:id,display_name',
                'assetUnit.assetVariant',
            ])
            ->first();

        if ($link === null || $link->asset === null) {
            return null;
        }

        $base = self::serializeLinkForPublic($link);
        $asset = $link->asset;

        $gallery = [];
        foreach ($asset->images as $img) {
            if (! $img->file) {
                continue;
            }
            $gallery[] = [
                'url' => $img->url,
                'alt' => $img->display_name ?: $asset->display_name,
            ];
        }

        $specs = [];
        foreach ($asset->specValues as $sv) {
            $def = $sv->definition;
            if ($def === null) {
                continue;
            }
            $raw = $sv->value_number ?? $sv->value_text ?? $sv->value_boolean;
            if ($raw === null || $raw === '') {
                continue;
            }
            if (is_bool($raw)) {
                $valueStr = $raw ? 'Yes' : 'No';
            } elseif (is_numeric($raw)) {
                $valueStr = (string) $raw;
            } else {
                $valueStr = (string) $raw;
            }
            if ($sv->unit) {
                $valueStr .= ' '.$sv->unit;
            }
            $label = $def->label !== null && $def->label !== '' ? $def->label : ($def->key ?? 'Spec');
            $specs[] = [
                'label' => $label,
                'value' => $valueStr,
            ];
        }

        $base['description'] = $asset->description;
        $base['image_gallery'] = $gallery;
        $base['specs'] = $specs;

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializeLinkForPublic(BoatShowEventAsset $link): array
    {
        $base = self::serializeLink($link);
        $base['primary_image_url'] = self::primaryImageUrlForAsset($link->asset);

        return $base;
    }

    private static function primaryImageUrlForAsset(Asset $asset): ?string
    {
        /** @var InventoryImage|null $img */
        $img = $asset->images->firstWhere('is_primary', true)
            ?? $asset->images->first();

        if (! $img || ! $img->file) {
            return null;
        }

        return $img->url;
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializeLink(BoatShowEventAsset $link): array
    {
        $asset = $link->asset;
        $unit = $link->assetUnit;

        [$lengthMm, $widthMm] = AssetLayoutFootprint::resolveLengthWidthMillimeters($asset, $unit);

        $lengthForLayout = LengthMillimeters::toFeetFloat($lengthMm);
        $widthForLayout = LengthMillimeters::toFeetFloat($widthMm);

        $lengthFt = $link->length_ft !== null ? (float) $link->length_ft : (float) ($lengthForLayout ?? 20);
        $widthFt = $link->width_ft !== null ? (float) $link->width_ft : (float) ($widthForLayout ?? 8);

        $overallLength = $lengthForLayout;
        $overallWidth = $widthForLayout;

        $unitLabel = self::unitShortLabel($unit, $asset);

        $assetUnitPayload = null;
        if ($unit !== null) {
            $assetUnitPayload = [
                'id' => $unit->id,
                'display_name' => $unit->display_name,
                'unit_label' => $unitLabel,
            ];
        }

        $base = [
            'event_asset_id' => $link->id,
            'asset_unit_id' => $unit?->id,
            'id' => $asset->id,
            'type' => (int) $asset->type,
            'display_name' => $asset->display_name,
            'unit_label' => $unitLabel,
            'model' => $asset->model,
            'make' => $asset->make?->display_name,
            'year' => $asset->year,
            'overall_length' => $overallLength,
            'overall_width' => $overallWidth,
            'length' => $lengthFt,
            'width' => $widthFt,
            'length_ft' => $lengthFt,
            'width_ft' => $widthFt,
            'asset_unit' => $assetUnitPayload,
            'include_in_layout' => (bool) $link->include_in_layout,
            'x' => (float) $link->x,
            'y' => (float) $link->y,
            'rotation' => (int) $link->rotation,
            'z_index' => (int) $link->z_index,
            'color' => $link->color,
            'layout_label' => $link->name,
        ];

        if ((int) $asset->type === AssetType::Boat->value) {
            $base['length_display'] = $lengthForLayout === null
                ? null
                : self::formatFeetForDisplay($lengthForLayout);
        }

        if ((int) $asset->type === AssetType::Engine->value) {
            $base['horsepower'] = $asset->maximum_power ?? $asset->minimum_power;
        }

        if ((int) $asset->type === AssetType::Trailer->value) {
            $base['trailer_type'] = $asset->category;
        }

        return $base;
    }

    private static function formatFeetForDisplay(float $feet): string
    {
        $n = fmod($feet, 1.0) === 0.0
            ? (string) (int) $feet
            : (string) round($feet, 2);

        return $n.' ft';
    }
}
