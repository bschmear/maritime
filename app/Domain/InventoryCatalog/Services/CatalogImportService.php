<?php

declare(strict_types=1);

namespace App\Domain\InventoryCatalog\Services;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\BoatMake\Models\BoatMake;
use App\Domain\InventoryCatalog\Models\InventoryBoatMake;
use App\Domain\InventoryCatalog\Models\InventoryCatalogAsset;
use App\Domain\InventoryCatalog\Models\InventoryCatalogAssetVariant;
use Illuminate\Support\Facades\DB;

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
        return [
            'type' => $src->type,
            'display_name' => $src->display_name,
            'slug' => $src->slug,
            'catalog_asset_key' => $src->slug,
            'inactive' => $src->inactive,
            'make_id' => $tenantMakeId,
            'model' => $src->model,
            'year' => $src->year,
            'length' => $src->length,
            'beam' => $src->beam,
            'persons' => $src->persons,
            'minimum_power' => $src->minimum_power,
            'maximum_power' => $src->maximum_power,
            'fuel_tank' => $src->fuel_tank,
            'engine_shaft' => $src->engine_shaft,
            'water_tank' => $src->water_tank,
            'category' => $src->category,
            'engine_details' => $src->engine_details,
            'attributes' => $src->attributes,
            'description' => $src->description,
            'default_cost' => $src->default_cost,
            'default_price' => $src->default_price,
            'has_variants' => $src->has_variants,
        ];
    }
}
