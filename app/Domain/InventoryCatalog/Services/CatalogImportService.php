<?php

declare(strict_types=1);

namespace App\Domain\InventoryCatalog\Services;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\BoatMake\Models\BoatMake;
use App\Domain\InventoryCatalog\Models\InventoryBoatMake;
use App\Domain\InventoryCatalog\Models\InventoryCatalogAsset;
use App\Domain\InventoryCatalog\Models\InventoryCatalogAssetVariant;
use App\Domain\InventoryCatalog\Support\CatalogImportSpecSync;
use App\Domain\InventoryCatalog\Support\InventoryCatalogSpecificationReader;
use App\Enums\Inventory\BoatType;
use App\Enums\Inventory\HullMaterial;
use App\Enums\Inventory\HullType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

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

        try {
            return $this->buildPreview($tenantMake);
        } catch (Throwable $e) {
            if ($this->isInventoryConnectionFailure($e)) {
                Log::warning('Inventory catalog unavailable during preview', [
                    'brand_key' => $tenantMake->brand_key,
                    'error' => $e->getMessage(),
                ]);

                return ['catalog_rows' => [], 'imported_keys' => []];
            }

            throw $e;
        }
    }

    /**
     * @return array{catalog_rows: list<array<string, mixed>>, imported_keys: list<string>}
     */
    private function buildPreview(BoatMake $tenantMake): array
    {
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

        try {
            $invMake = InventoryBoatMake::query()->where('slug', $tenantMake->brand_key)->first();
        } catch (Throwable $e) {
            if ($this->isInventoryConnectionFailure($e)) {
                Log::warning('Inventory catalog unavailable during import', [
                    'brand_key' => $tenantMake->brand_key,
                    'error' => $e->getMessage(),
                ]);

                return ['imported' => 0, 'skipped' => 0];
            }

            throw $e;
        }

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

                $assetType = (int) $asset->type;
                CatalogImportSpecSync::syncForInventoryRow($asset, $assetType, $src);

                $parentLength = $payload['length'] ?? null;
                $parentWidth = $payload['width'] ?? null;

                if (! $src->has_variants) {
                    AssetVariant::query()->where('asset_id', $asset->id)->delete();
                } else {
                    $incomingVariantKeys = [];
                    foreach ($src->variants as $v) {
                        if ($v->key === null || $v->key === '') {
                            continue;
                        }
                        $incomingVariantKeys[] = $v->key;
                        $variantLength = InventoryCatalogSpecificationReader::effectiveUInt($v, 'length_mm', 'length_mm')
                            ?? $parentLength;
                        $variantWidth = InventoryCatalogSpecificationReader::effectiveUInt($v, 'width_mm', 'width_mm')
                            ?? $parentWidth;
                        /** @var AssetVariant $variant */
                        $variant = AssetVariant::query()->updateOrCreate(
                            [
                                'asset_id' => $asset->id,
                                'key' => $v->key,
                            ],
                            [
                                'name' => $v->name ?? $v->display_name,
                                'display_name' => $v->display_name,
                                'length' => $variantLength,
                                'width' => $variantWidth,
                                'default_cost' => $v->default_cost,
                                'default_price' => $v->default_price,
                                'description' => $v->description,
                                'inactive' => $v->inactive,
                            ]
                        );
                        CatalogImportSpecSync::syncForInventoryRow($variant, $assetType, $v);
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
     * Populate tenant spec values from inventory for assets already linked by `catalog_asset_key`.
     */
    public function resyncImportedSpecs(BoatMake $tenantMake): int
    {
        if ($tenantMake->brand_key === null || $tenantMake->brand_key === '') {
            return 0;
        }

        try {
            $invMake = InventoryBoatMake::query()->where('slug', $tenantMake->brand_key)->first();
        } catch (Throwable $e) {
            if ($this->isInventoryConnectionFailure($e)) {
                return 0;
            }

            throw $e;
        }

        if ($invMake === null) {
            return 0;
        }

        $synced = 0;

        $assets = Asset::query()
            ->where('make_id', $tenantMake->id)
            ->whereNotNull('catalog_asset_key')
            ->with('variants')
            ->get();

        foreach ($assets as $asset) {
            $src = InventoryCatalogAsset::query()
                ->where('make_id', $invMake->id)
                ->where('slug', $asset->catalog_asset_key)
                ->with('variants')
                ->first();

            if ($src === null) {
                continue;
            }

            $assetType = (int) $asset->type;
            CatalogImportSpecSync::syncForInventoryRow($asset, $assetType, $src);

            if ($src->has_variants) {
                foreach ($src->variants as $invVariant) {
                    if ($invVariant->key === null || $invVariant->key === '') {
                        continue;
                    }
                    $tenantVariant = $asset->variants->firstWhere('key', $invVariant->key);
                    if ($tenantVariant !== null) {
                        CatalogImportSpecSync::syncForInventoryRow($tenantVariant, $assetType, $invVariant);
                    }
                }
            }

            $synced++;
        }

        return $synced;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapInventoryAssetToTenantPayload(InventoryCatalogAsset $src, int $tenantMakeId): array
    {
        $enumLayer = InventoryCatalogSpecificationReader::attributeLayer($src);
        $enums = $this->enumColumnsFromCatalogKeys($enumLayer);

        $length = InventoryCatalogSpecificationReader::effectiveUInt($src, 'length_mm', 'length_mm');
        $width = InventoryCatalogSpecificationReader::effectiveUInt($src, 'width_mm', 'width_mm');
        $persons = InventoryCatalogSpecificationReader::effectiveUInt($src, 'capacity_persons', 'capacity_persons');
        $maxHp = InventoryCatalogSpecificationReader::effectiveUInt($src, 'max_hp', 'max_hp');
        $fuelL = InventoryCatalogSpecificationReader::effectiveUInt($src, 'fuel_capacity_l', 'fuel_capacity_l');

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
     * Tenant {@see Asset} `type` must be a valid {@see \App\Enums\Inventory\AssetType} value (1–4); invalid or missing values default to 1 (boat).
     */
    private function normalizedTenantAssetType(mixed $type): int
    {
        $t = is_numeric($type) ? (int) $type : 0;

        return in_array($t, [1, 2, 3, 4], true) ? $t : 1;
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
            $n = InventoryCatalogSpecificationReader::effectiveUInt($src, $key, $key);
            if ($n !== null) {
                $out[$key] = $n;
            }
        }

        return $out;
    }

    private function isInventoryConnectionFailure(Throwable $e): bool
    {
        $current = $e;
        while ($current instanceof Throwable) {
            if ($current instanceof QueryException && $current->getConnectionName() === 'inventory') {
                return true;
            }
            if (str_contains($current->getMessage(), 'Connection: inventory')) {
                return true;
            }
            $current = $current->getPrevious();
        }

        return false;
    }
}
