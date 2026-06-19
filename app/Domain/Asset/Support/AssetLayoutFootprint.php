<?php

declare(strict_types=1);

namespace App\Domain\Asset\Support;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetUnit\Models\AssetUnit;
use App\Support\LengthMillimeters;

final class AssetLayoutFootprint
{
    /**
     * Default floor-plan footprint (feet) from asset / variant columns (not dynamic specs).
     *
     * @return array{length_ft: float, width_ft: float}
     */
    public static function defaultFor(Asset $asset, ?AssetUnit $unit = null): array
    {
        [$lengthMm, $widthMm] = self::resolveLengthWidthMillimeters($asset, $unit);

        $lengthFt = LengthMillimeters::toFeetFloat($lengthMm) ?? 20.0;
        $widthFt = LengthMillimeters::toFeetFloat($widthMm) ?? 8.0;

        return [
            'length_ft' => (float) $lengthFt,
            'width_ft' => (float) $widthFt,
        ];
    }

    /**
     * @return array{0: int|null, 1: int|null} [length_mm, width_mm]
     */
    public static function resolveLengthWidthMillimeters(Asset $asset, ?AssetUnit $unit = null): array
    {
        $variant = $unit?->assetVariant;

        $lengthMm = $variant?->length ?? $asset->length;
        if ($lengthMm !== null) {
            $lengthMm = (int) $lengthMm;
        }

        $widthMm = $variant?->width ?? $asset->width;
        if ($widthMm !== null) {
            $widthMm = (int) $widthMm;
        } elseif (is_string($asset->beam) && trim($asset->beam) !== '') {
            $widthMm = LengthMillimeters::fromLegacyString($asset->beam);
        } else {
            $widthMm = null;
        }

        return [$lengthMm, $widthMm];
    }

    public static function unitShortLabel(?AssetUnit $unit, ?Asset $asset = null): ?string
    {
        if ($unit !== null) {
            if (! empty($unit->hin)) {
                return "Hull: {$unit->hin}";
            }
            if (! empty($unit->serial_number)) {
                return "SN: {$unit->serial_number}";
            }
            if (! empty($unit->sku)) {
                return "SKU: {$unit->sku}";
            }
        }

        if ($asset !== null) {
            if (! empty($asset->hin)) {
                return "Hull: {$asset->hin}";
            }
            if (! empty($asset->serial_number)) {
                return "SN: {$asset->serial_number}";
            }
        }

        if ($unit !== null) {
            return "Unit #{$unit->id}";
        }

        return null;
    }

    /**
     * Persist length/width (feet) to the unit's variant or parent asset (millimetres).
     */
    public static function applyFootprintFeet(Asset $asset, ?AssetUnit $unit, float $lengthFt, float $widthFt): void
    {
        $lengthMm = LengthMillimeters::fromFeetFloat($lengthFt);
        $widthMm = LengthMillimeters::fromFeetFloat($widthFt);

        $variant = $unit?->assetVariant;
        if ($variant !== null) {
            $variant->update([
                'length' => $lengthMm,
                'width' => $widthMm,
            ]);

            return;
        }

        $asset->update([
            'length' => $lengthMm,
            'width' => $widthMm,
        ]);
    }
}
