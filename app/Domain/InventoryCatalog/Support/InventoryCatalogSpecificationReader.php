<?php

declare(strict_types=1);

namespace App\Domain\InventoryCatalog\Support;

use App\Domain\InventoryCatalog\Models\InventoryCatalogAsset;
use App\Domain\InventoryCatalog\Models\InventoryCatalogAssetVariant;

/**
 * Reads merged catalog_data / attributes / specifications from inventory catalog rows.
 */
final class InventoryCatalogSpecificationReader
{
    /**
     * @return array<string, mixed>
     */
    public static function attributeLayer(InventoryCatalogAsset|InventoryCatalogAssetVariant $src): array
    {
        $fromCatalog = is_array($src->catalog_data) ? $src->catalog_data : [];
        $fromAttrs = is_array($src->attributes) ? $src->attributes : [];

        return array_merge($fromCatalog, $fromAttrs);
    }

    /**
     * Prefer inventory table column; fall back to nested `specifications` in catalog_data/attributes merge.
     */
    public static function effectiveUInt(
        InventoryCatalogAsset|InventoryCatalogAssetVariant $src,
        string $columnKey,
        string $specKey,
    ): ?int {
        $direct = $src->getAttribute($columnKey);
        if ($direct !== null && $direct !== '') {
            return self::nonNegativeInt($direct);
        }

        $spec = self::nestedSpecifications($src);
        if (! array_key_exists($specKey, $spec)) {
            return null;
        }

        return self::nonNegativeInt($spec[$specKey]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function nestedSpecifications(InventoryCatalogAsset|InventoryCatalogAssetVariant $src): array
    {
        $layer = self::attributeLayer($src);
        $nested = $layer['specifications'] ?? null;

        return is_array($nested) ? $nested : [];
    }

    public static function nonNegativeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }
        if (is_float($value)) {
            return $value >= 0.0 ? (int) round($value) : null;
        }
        if (is_string($value) && is_numeric(trim($value))) {
            $n = (int) trim($value);

            return $n >= 0 ? $n : null;
        }

        return null;
    }
}
