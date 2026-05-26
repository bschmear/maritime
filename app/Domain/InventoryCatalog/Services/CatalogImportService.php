<?php

declare(strict_types=1);

namespace App\Domain\InventoryCatalog\Services;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\BoatMake\Models\BoatMake;
use App\Domain\InventoryCatalog\Models\InventoryBoatMake;
use App\Domain\InventoryCatalog\Models\InventoryCatalogAsset;
use App\Domain\InventoryCatalog\Models\InventoryCatalogAssetVariant;
use App\Enums\Inventory\BoatType;
use App\Enums\Inventory\HullMaterial;
use App\Enums\Inventory\HullType;
use Illuminate\Support\Facades\DB;

/**
 * Imports inventory catalog assets into tenant {@see Asset} records for a {@see BoatMake} with a matching `brand_key`.
 *
 * @see docs/CATALOG_IMPORT.md
 */
class CatalogImportService
{
    /**
     * @return array{catalog_rows: list<array<string, mixed>>, imported_keys: list<string>}
     */
    public function preview(BoatMake $tenantMake): array
    {
        if ($tenantMake->brand_key === null || $tenantMake->brand_key === '') {
            return ['catalog_rows' => [], 'imported_keys' => []];
        }

        $invMake = InventoryBoatMake::query()->where('slug', $tenantMake->brand_key)->first();
        if ($invMake === null) {
            return ['catalog_rows' => [], 'imported_keys' => []];
        }

        $imported = Asset::query()
            ->where('make_id', $tenantMake->id)
            ->whereNotNull('catalog_asset_key')
            ->pluck('catalog_asset_key')
            ->filter()
            ->values()
            ->all();

        $assets = InventoryCatalogAsset::query()
            ->where('make_id', $invMake->id)
            ->with('variants')
            ->orderBy('display_name')
            ->get();

        $catalogRows = [];
        foreach ($assets as $a) {
            $key = $a->slug;
            if ($key === null || $key === '') {
                continue;
            }
            $catalogRows[] = [
                'catalog_asset_key' => $key,
                'display_name' => $a->display_name,
                'model' => $a->model,
                'has_variants' => (bool) $a->has_variants,
                'already_imported' => in_array($key, $imported, true),
                'variants' => $a->variants->map(fn (InventoryCatalogAssetVariant $v) => [
                    'key' => $v->key,
                    'display_name' => $v->display_name,
                ])->values()->all(),
            ];
        }

        return ['catalog_rows' => $catalogRows, 'imported_keys' => $imported];
    }

    /**
     * @param  list<string>|null  $catalogAssetKeys  null = all not yet imported
     * @return array{imported: int, skipped: int}
     */
    public function import(BoatMake $tenantMake, ?array $catalogAssetKeys = null): array
    {
        if ($tenantMake->brand_key === null || $tenantMake->brand_key === '') {
            return ['imported' => 0, 'skipped' => 0];
        }

        $invMake = InventoryBoatMake::query()->where('slug', $tenantMake->brand_key)->first();
        if ($invMake === null) {
            return ['imported' => 0, 'skipped' => 0];
        }

        $preview = $this->preview($tenantMake);
        $toImport = [];
        foreach ($preview['catalog_rows'] as $row) {
            $k = $row['catalog_asset_key'];
            if ($catalogAssetKeys !== null && ! in_array($k, $catalogAssetKeys, true)) {
                continue;
            }
            if ($row['already_imported']) {
                continue;
            }
            $toImport[] = $k;
        }

        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($invMake, $tenantMake, $toImport, &$imported, &$skipped): void {
            foreach ($toImport as $slug) {
                $src = InventoryCatalogAsset::query()
                    ->where('make_id', $invMake->id)
                    ->where('slug', $slug)
                    ->with('variants')
                    ->first();
                if ($src === null) {
                    $skipped++;

                    continue;
                }

                $payload = $this->mapInventoryAssetToTenantPayload($src, $tenantMake->id);
                /** @var Asset $asset */
                $asset = Asset::query()->updateOrCreate(
                    [
                        'make_id' => $tenantMake->id,
                        'catalog_asset_key' => $slug,
                    ],
                    $payload
                );

                if (! $src->has_variants) {
                    AssetVariant::query()->where('asset_id', $asset->id)->delete();
                } else {
                    $incomingVariantKeys = [];
                    foreach ($src->variants as $v) {
                        if ($v->key === null || $v->key === '') {
                            continue;
                        }
                        $incomingVariantKeys[] = $v->key;
                        AssetVariant::query()->updateOrCreate(
                            [
                                'asset_id' => $asset->id,
                                'key' => $v->key,
                            ],
                            [
                                'name' => $v->name ?? $v->display_name,
                                'display_name' => $v->display_name,
                                'default_cost' => $v->default_cost,
                                'default_price' => $v->default_price,
                                'description' => $v->description,
                                'inactive' => $v->inactive,
                            ]
                        );
                    }

                    if ($incomingVariantKeys === []) {
                        AssetVariant::query()->where('asset_id', $asset->id)->delete();
                    } else {
                        AssetVariant::query()
                            ->where('asset_id', $asset->id)
                            ->whereNotIn('key', $incomingVariantKeys)
                            ->delete();
                    }
                }

                $imported++;
            }
        });

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapInventoryAssetToTenantPayload(InventoryCatalogAsset $src, int $tenantMakeId): array
    {
        $enumLayer = $this->inventoryCatalogAttributeLayer($src);
        $enums = $this->enumColumnsFromCatalogKeys($enumLayer);

        $length = $this->effectiveUIntFromColumnOrSpecifications($src, 'length_mm', 'length_mm');
        $width = $this->effectiveUIntFromColumnOrSpecifications($src, 'width_mm', 'width_mm');
        $persons = $this->effectiveUIntFromColumnOrSpecifications($src, 'capacity_persons', 'capacity_persons');
        $maxHp = $this->effectiveUIntFromColumnOrSpecifications($src, 'max_hp', 'max_hp');
        $fuelL = $this->effectiveUIntFromColumnOrSpecifications($src, 'fuel_capacity_l', 'fuel_capacity_l');

        return [
            'type' => $this->normalizedTenantAssetType($src->type),
            'display_name' => $src->display_name,
            'slug' => $src->slug,
            'catalog_asset_key' => $src->slug,
            'inactive' => $src->inactive,
            'make_id' => $tenantMakeId,
            'model' => $src->model,
            'year' => $src->year,
            'length' => $length,
            'beam' => $width,
            'width' => $width,
            'boat_type' => $enums['boat_type'],
            'hull_type' => $enums['hull_type'],
            'hull_material' => $enums['hull_material'],
            'persons' => $persons,
            'maximum_power' => $maxHp,
            'fuel_tank' => $fuelL !== null ? (string) $fuelL : null,
            'engine_shaft' => $src->engine_shaft,
            'water_tank' => $src->water_tank,
            'category' => $src->category,
            'engine_details' => $src->engine_details,
            'attributes' => $this->mergedInventoryCatalogAttributes($src),
            'description' => $src->description,
            'default_cost' => $src->default_cost,
            'default_price' => $src->default_price,
            'has_variants' => $src->has_variants,
        ];
    }

    /**
     * Same merge order as {@see mergedInventoryCatalogAttributes}: catalog_data first, then attributes (overrides).
     *
     * @return array<string, mixed>
     */
    private function inventoryCatalogAttributeLayer(InventoryCatalogAsset $src): array
    {
        $fromCatalog = is_array($src->catalog_data) ? $src->catalog_data : [];
        $fromAttrs = is_array($src->attributes) ? $src->attributes : [];

        return array_merge($fromCatalog, $fromAttrs);
    }

    /**
     * Tenant {@see Asset} `type` must be a valid {@see \App\Enums\Inventory\AssetType} value (1–4); invalid or missing values default to 1 (boat).
     */
    private function normalizedTenantAssetType(mixed $type): int
    {
        $t = is_numeric($type) ? (int) $type : 0;

        return in_array($t, [1, 2, 3, 4], true) ? $t : 1;
    }

    /**
     * Prefer inventory table column; fall back to nested `specifications` in catalog_data/attributes merge.
     */
    private function effectiveUIntFromColumnOrSpecifications(InventoryCatalogAsset $src, string $columnKey, string $specKey): ?int
    {
        $direct = $src->getAttribute($columnKey);
        if ($direct !== null && $direct !== '') {
            return $this->nonNegativeInt($direct);
        }

        $spec = $this->inventoryNestedSpecifications($src);
        if (! array_key_exists($specKey, $spec)) {
            return null;
        }

        return $this->nonNegativeInt($spec[$specKey]);
    }

    /**
     * @return array<string, mixed>
     */
    private function inventoryNestedSpecifications(InventoryCatalogAsset $src): array
    {
        $layer = $this->inventoryCatalogAttributeLayer($src);
        $nested = $layer['specifications'] ?? null;

        return is_array($nested) ? $nested : [];
    }

    private function nonNegativeInt(mixed $value): ?int
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

    /**
     * Map catalog/attributes string keys to tenant asset enum column integers (1-based ordinal per enum).
     *
     * @param  array<string, mixed>  $layer  Merged catalog_data + attributes (catalog first).
     * @return array{boat_type: int|null, hull_type: int|null, hull_material: int|null}
     */
    private function enumColumnsFromCatalogKeys(array $layer): array
    {
        $boatSlug = isset($layer['boat_type_key']) ? trim((string) $layer['boat_type_key']) : '';
        $hullSlug = isset($layer['hull_type_key']) ? trim((string) $layer['hull_type_key']) : '';
        $materialSlug = isset($layer['hull_material_key']) ? trim((string) $layer['hull_material_key']) : '';

        $boat = $boatSlug !== '' ? BoatType::tryFrom($boatSlug) : null;
        $hull = $hullSlug !== '' ? HullType::tryFrom($hullSlug) : null;
        $material = $materialSlug !== '' ? HullMaterial::tryFrom($materialSlug) : null;

        return [
            'boat_type' => $boat !== null ? $boat->id() : null,
            'hull_type' => $hull !== null ? $hull->id() : null,
            'hull_material' => $material !== null ? $material->id() : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mergedInventoryCatalogAttributes(InventoryCatalogAsset $src): array
    {
        $fromCatalog = is_array($src->catalog_data) ? $src->catalog_data : [];
        $fromAttrs = is_array($src->attributes) ? $src->attributes : [];
        $merged = array_merge($fromCatalog, $fromAttrs);
        if (is_array($src->features) && $src->features !== []) {
            $merged['features'] = $src->features;
        }
        $spec = $this->specificationsFromInventoryAsset($src);
        if ($spec !== []) {
            $merged['specifications'] = $spec;
        }

        unset($merged['boat_type_key'], $merged['hull_type_key'], $merged['hull_material_key']);

        return $merged;
    }

    /**
     * @return array<string, int>
     */
    private function specificationsFromInventoryAsset(InventoryCatalogAsset $src): array
    {
        $out = [];
        foreach (['length_mm', 'width_mm', 'height_mm', 'weight_kg', 'capacity_persons', 'max_hp', 'fuel_capacity_l'] as $key) {
            $n = $this->effectiveUIntFromColumnOrSpecifications($src, $key, $key);
            if ($n !== null) {
                $out[$key] = $n;
            }
        }

        return $out;
    }
}
