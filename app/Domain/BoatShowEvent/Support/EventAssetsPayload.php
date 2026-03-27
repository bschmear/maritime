<?php

namespace App\Domain\BoatShowEvent\Support;

use App\Domain\Asset\Models\Asset;
use App\Domain\BoatShowEvent\Models\BoatShowEvent;
use App\Domain\BoatShowEvent\Models\BoatShowEventAsset;
use App\Enums\Inventory\AssetType;

final class EventAssetsPayload
{
    /**
     * Default floor-plan footprint (feet) from specs / columns.
     *
     * @return array{length_ft: float, width_ft: float}
     */
    public static function defaultLayoutFootprint(Asset $asset): array
    {
        $specs = self::specKeyMap($asset);

        $overallLength = self::coerceFloat($specs['overall_length'] ?? null);
        $overallWidth = self::coerceFloat($specs['overall_width'] ?? null);

        $lengthFt = $overallLength ?? self::parseNumericString($asset->length) ?? 20.0;
        $widthFt = $overallWidth ?? self::parseNumericString($asset->beam) ?? 8.0;

        return [
            'length_ft' => (float) $lengthFt,
            'width_ft' => (float) $widthFt,
        ];
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
     * @return array<string, mixed>
     */
    private static function serializeLink(BoatShowEventAsset $link): array
    {
        $asset = $link->asset;
        $specs = self::specKeyMap($asset);

        $overallLength = self::coerceFloat($specs['overall_length'] ?? null);
        $overallWidth = self::coerceFloat($specs['overall_width'] ?? null);

        $lengthForLayout = $overallLength ?? self::parseNumericString($asset->length);
        $widthForLayout = $overallWidth ?? self::parseNumericString($asset->beam);

        $lengthFt = $link->length_ft !== null ? (float) $link->length_ft : (float) ($lengthForLayout ?? 20);
        $widthFt = $link->width_ft !== null ? (float) $link->width_ft : (float) ($widthForLayout ?? 8);

        $unit = $link->assetUnit;
        $assetUnitPayload = null;
        if ($unit !== null) {
            $assetUnitPayload = [
                'id' => $unit->id,
                'display_name' => $unit->display_name,
            ];
        }

        $base = [
            'event_asset_id' => $link->id,
            'id' => $asset->id,
            'type' => (int) $asset->type,
            'display_name' => $asset->display_name,
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
            $base['length_display'] = $overallLength ?? self::parseNumericString($asset->length) ?? $asset->length;
        }

        if ((int) $asset->type === AssetType::Engine->value) {
            $base['horsepower'] = $asset->maximum_power ?? $asset->minimum_power;
        }

        if ((int) $asset->type === AssetType::Trailer->value) {
            $base['trailer_type'] = $asset->category;
        }

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    private static function specKeyMap(Asset $asset): array
    {
        $out = [];
        foreach ($asset->specValues as $sv) {
            $key = $sv->definition?->key;
            if ($key === null || $key === '') {
                continue;
            }
            $out[$key] = $sv->value_number ?? $sv->value_text ?? $sv->value_boolean;
        }

        return $out;
    }

    private static function coerceFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        return self::parseNumericString(is_string($value) ? $value : null);
    }

    private static function parseNumericString(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (preg_match('/[\d.]+/', $value, $m)) {
            return (float) $m[0];
        }

        return null;
    }
}
