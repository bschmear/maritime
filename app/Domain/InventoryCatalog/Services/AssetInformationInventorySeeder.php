<?php

declare(strict_types=1);

namespace App\Domain\InventoryCatalog\Services;

use App\Domain\InventoryCatalog\Models\InventoryBoatMake;
use App\Domain\InventoryCatalog\Models\InventoryBoatType;
use App\Domain\InventoryCatalog\Models\InventoryCatalogAsset;
use App\Domain\InventoryCatalog\Models\InventoryCatalogAssetVariant;
use App\Domain\InventoryCatalog\Models\InventoryHullMaterial;
use App\Domain\InventoryCatalog\Models\InventoryHullType;
use App\Enums\Inventory\BoatMake;
use App\Enums\Inventory\BoatType;
use App\Enums\Inventory\HullMaterial;
use App\Enums\Inventory\HullType;
use App\Support\ManufacturerCatalog;
use App\Support\ManufacturerDetailsCatalog;
use Illuminate\Support\Facades\DB;
use JsonException;

final class AssetInformationInventorySeeder
{
    private const ASSET_TYPE = 1;

    private const SOURCE_MARKER = 'asset_information';

    /**
     * @return list<array{
     *     brand: string,
     *     status: 'ok'|'skipped'|'error',
     *     message?: string,
     *     assets?: int,
     *     variants?: int,
     *     make_lookups?: 'updated'|'skipped_mixed',
     * }>
     */
    public function run(?string $brandFilter, bool $dryRun, bool $keepOrphanVariants): array
    {
        $results = [];
        $base = base_path('app/AssetInformation');
        if (! is_dir($base)) {
            $results[] = [
                'brand' => '*',
                'status' => 'error',
                'message' => "Missing directory: {$base}",
            ];

            return $results;
        }

        $displayBySlug = $this->manufacturerDisplayBySlug();

        foreach ($this->brandDirectorySlugs($base) as $slug) {
            if ($brandFilter !== null && $brandFilter !== '' && $slug !== $brandFilter) {
                continue;
            }

            if (BoatMake::tryFrom($slug) === null) {
                $results[] = [
                    'brand' => $slug,
                    'status' => 'skipped',
                    'message' => 'Directory name is not a BoatMake enum slug; skipped.',
                ];

                continue;
            }

            $metaPath = $base.'/'.$slug.'/meta.json';
            if (! is_readable($metaPath)) {
                $results[] = [
                    'brand' => $slug,
                    'status' => 'skipped',
                    'message' => 'meta.json missing or unreadable.',
                ];

                continue;
            }

            try {
                $meta = $this->decodeJsonFile($metaPath);
            } catch (JsonException $e) {
                $results[] = [
                    'brand' => $slug,
                    'status' => 'error',
                    'message' => 'Invalid meta.json: '.$e->getMessage(),
                ];

                continue;
            }

            if (! is_array($meta) || $meta === [] || array_is_list($meta) === false) {
                $results[] = [
                    'brand' => $slug,
                    'status' => 'error',
                    'message' => 'meta.json must be a non-empty JSON array of model objects.',
                ];

                continue;
            }

            $modelsUrlMap = $this->loadModelsUrlMap($base.'/'.$slug.'/models.json');

            $inspection = $this->inspectCatalogKeysPerModel($meta);
            if ($inspection['error'] !== null) {
                $results[] = [
                    'brand' => $slug,
                    'status' => 'error',
                    'message' => $inspection['error'],
                ];

                continue;
            }

            $uniformTriple = $inspection['uniform_for_make'];
            $makeLookupIds = null;
            if ($uniformTriple !== null) {
                $makeLookupIds = $this->resolveLookupIds($uniformTriple);
                if ($makeLookupIds['error'] !== null) {
                    $results[] = [
                        'brand' => $slug,
                        'status' => 'error',
                        'message' => $makeLookupIds['error'],
                    ];

                    continue;
                }
            }

            $makeLookupsMode = $uniformTriple !== null ? 'updated' : 'skipped_mixed';

            $assetCount = 0;
            $variantCount = 0;
            foreach ($meta as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $modelId = isset($row['id']) ? trim((string) $row['id']) : '';
                if ($modelId === '') {
                    continue;
                }
                $assetCount++;
                $variants = $row['variants'] ?? [];
                if (is_array($variants)) {
                    foreach ($variants as $v) {
                        if (is_array($v) && isset($v['id']) && trim((string) $v['id']) !== '') {
                            $variantCount++;
                        }
                    }
                }
            }

            if ($dryRun) {
                $msg = $makeLookupsMode === 'updated'
                    ? 'Dry run: validation passed; boat_make lookup FKs would be set (uniform keys).'
                    : 'Dry run: validation passed; boat_make lookup FKs skipped (mixed keys across models).';
                $results[] = [
                    'brand' => $slug,
                    'status' => 'ok',
                    'message' => $msg,
                    'assets' => $assetCount,
                    'variants' => $variantCount,
                    'make_lookups' => $makeLookupsMode,
                ];

                continue;
            }

            try {
                DB::connection('inventory')->transaction(function () use (
                    $slug,
                    $meta,
                    $makeLookupIds,
                    $displayBySlug,
                    $modelsUrlMap,
                    $keepOrphanVariants,
                    &$assetCount,
                    &$variantCount
                ): void {
                    $displayName = $displayBySlug[$slug] ?? BoatMake::from($slug)->label();
                    $makePayload = [
                        'display_name' => $displayName,
                        'active' => true,
                        ...ManufacturerDetailsCatalog::inventoryPayload(
                            $slug,
                            false,
                            InventoryBoatMake::query()->where('slug', $slug)->value('description')
                        ),
                    ];
                    if ($makeLookupIds !== null) {
                        $makePayload['boat_type_id'] = $makeLookupIds['boat_type_id'];
                        $makePayload['hull_type_id'] = $makeLookupIds['hull_type_id'];
                        $makePayload['hull_material_id'] = $makeLookupIds['hull_material_id'];
                    }
                    /** @var InventoryBoatMake $make */
                    $make = InventoryBoatMake::query()->updateOrCreate(
                        ['slug' => $slug],
                        $makePayload
                    );
                    ManufacturerDetailsCatalog::syncBoatTypesForMake($make, false);

                    $assetCount = 0;
                    $variantCount = 0;

                    foreach ($meta as $row) {
                        if (! is_array($row)) {
                            continue;
                        }
                        $modelId = isset($row['id']) ? trim((string) $row['id']) : '';
                        if ($modelId === '') {
                            continue;
                        }

                        $catalogSlug = $slug.'--'.$modelId;
                        $specs = is_array($row['specifications'] ?? null) ? $row['specifications'] : [];
                        $variants = is_array($row['variants'] ?? null) ? $row['variants'] : [];
                        $hasVariantsFlag = (isset($row['has_variants']) && (bool) $row['has_variants']) || $variants !== [];

                        $boatKey = trim((string) ($row['boat_type_key'] ?? ''));
                        $hullKey = trim((string) ($row['hull_type_key'] ?? ''));
                        $materialKey = trim((string) ($row['hull_material_key'] ?? ''));

                        $catalogData = [
                            'source' => self::SOURCE_MARKER,
                            'boat_type_key' => $boatKey,
                            'hull_type_key' => $hullKey,
                            'hull_material_key' => $materialKey,
                            'type_display' => isset($row['type_display']) ? (string) $row['type_display'] : null,
                            'construction' => is_array($row['construction'] ?? null) ? $row['construction'] : null,
                            'length_range_mm' => is_array($row['length_range_mm'] ?? null) ? $row['length_range_mm'] : null,
                        ];
                        if (isset($modelsUrlMap[$modelId])) {
                            $catalogData['source_url'] = $modelsUrlMap[$modelId];
                        }

                        $seriesFeatures = $this->normalizeFeaturesList($row['features'] ?? null);

                        /** @var InventoryCatalogAsset $asset */
                        $asset = InventoryCatalogAsset::query()->updateOrCreate(
                            [
                                'make_id' => $make->id,
                                'slug' => $catalogSlug,
                            ],
                            array_merge([
                                'type' => self::ASSET_TYPE,
                                'display_name' => isset($row['name']) ? (string) $row['name'] : $modelId,
                                'inactive' => false,
                                'model' => isset($row['name']) ? (string) $row['name'] : $modelId,
                                'description' => isset($row['description']) ? (string) $row['description'] : null,
                                'attributes' => null,
                                'features' => $seriesFeatures,
                                'catalog_data' => array_filter($catalogData, static fn ($v) => $v !== null),
                                'has_variants' => $hasVariantsFlag,
                            ], $this->metaSpecificationColumns($specs))
                        );
                        $assetCount++;

                        $incomingKeys = [];
                        if ($hasVariantsFlag && $variants !== []) {
                            foreach ($variants as $v) {
                                if (! is_array($v)) {
                                    continue;
                                }
                                $vid = isset($v['id']) ? trim((string) $v['id']) : '';
                                if ($vid === '') {
                                    continue;
                                }
                                $incomingKeys[] = $vid;
                                $vSpecs = is_array($v['specifications'] ?? null) ? $v['specifications'] : [];
                                $variantCatalog = [
                                    'source' => self::SOURCE_MARKER,
                                    'boat_type_key' => $boatKey,
                                    'hull_type_key' => $hullKey,
                                    'hull_material_key' => $materialKey,
                                    'variant' => ['id' => $vid, 'name' => $v['name'] ?? $vid],
                                ];
                                $vDesc = isset($v['description']) && is_string($v['description']) ? trim($v['description']) : null;
                                $vFeatures = $this->normalizeFeaturesList($v['features'] ?? null) ?? $seriesFeatures;

                                InventoryCatalogAssetVariant::query()->updateOrCreate(
                                    [
                                        'asset_id' => $asset->id,
                                        'key' => $vid,
                                    ],
                                    array_merge([
                                        'make_id' => $make->id,
                                        'type' => self::ASSET_TYPE,
                                        'display_name' => isset($v['name']) ? (string) $v['name'] : $vid,
                                        'slug' => null,
                                        'name' => isset($v['name']) ? (string) $v['name'] : $vid,
                                        'inactive' => false,
                                        'model' => isset($v['name']) ? (string) $v['name'] : $vid,
                                        'year' => null,
                                        'engine_shaft' => null,
                                        'water_tank' => null,
                                        'category' => null,
                                        'engine_details' => null,
                                        'attributes' => null,
                                        'features' => $vFeatures,
                                        'catalog_data' => array_filter($variantCatalog, static fn ($val) => $val !== null),
                                        'description' => $vDesc !== '' ? $vDesc : null,
                                        'default_cost' => null,
                                        'default_price' => null,
                                        'has_variants' => false,
                                    ], $this->metaSpecificationColumns($vSpecs))
                                );
                                $variantCount++;
                            }

                            if (! $keepOrphanVariants && $incomingKeys !== []) {
                                InventoryCatalogAssetVariant::query()
                                    ->where('asset_id', $asset->id)
                                    ->whereNotIn('key', $incomingKeys)
                                    ->delete();
                            }
                        } else {
                            InventoryCatalogAssetVariant::query()->where('asset_id', $asset->id)->delete();
                        }
                    }
                });
            } catch (\Throwable $e) {
                $results[] = [
                    'brand' => $slug,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];

                continue;
            }

            $results[] = [
                'brand' => $slug,
                'status' => 'ok',
                'assets' => $assetCount,
                'variants' => $variantCount,
                'make_lookups' => $makeLookupsMode,
            ];
        }

        if ($brandFilter !== null && $brandFilter !== '' && $results === []) {
            $results[] = [
                'brand' => $brandFilter,
                'status' => 'skipped',
                'message' => 'No matching brand directory found.',
            ];
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    private function brandDirectorySlugs(string $base): array
    {
        $slugs = [];
        foreach (scandir($base) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $base.'/'.$entry;
            if (! is_dir($path)) {
                continue;
            }
            $slugs[] = $entry;
        }
        sort($slugs);

        return $slugs;
    }

    /**
     * @return array<string, string> slug => display_name
     */
    private function manufacturerDisplayBySlug(): array
    {
        $map = [];
        foreach (ManufacturerCatalog::entries() as $row) {
            $map[$row['slug']] = $row['display_name'];
        }

        return $map;
    }

    /**
     * @return array<string, string> model id => url
     */
    private function loadModelsUrlMap(string $path): array
    {
        if (! is_readable($path)) {
            return [];
        }

        try {
            $raw = (string) file_get_contents($path);
            $raw = $this->stripTrailingLineCommentsAfterBraces($raw);
            $raw = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (! is_array($raw) || ! array_is_list($raw)) {
            return [];
        }

        $map = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = isset($item['id']) ? trim((string) $item['id']) : '';
            $url = isset($item['url']) ? trim((string) $item['url']) : '';
            if ($id !== '' && $url !== '') {
                $map[$id] = $url;
            }
        }

        return $map;
    }

    /**
     * Validate every top-level model row (non-empty `id`) has catalog keys, enums, and inventory lookup rows.
     *
     * @param  list<mixed>  $meta
     * @return array{error: string|null, uniform_for_make: array{boat: string, hull: string, material: string}|null}
     */
    private function inspectCatalogKeysPerModel(array $meta): array
    {
        $distinct = [];

        foreach ($meta as $row) {
            if (! is_array($row)) {
                continue;
            }

            $modelId = isset($row['id']) ? trim((string) $row['id']) : '';
            if ($modelId === '') {
                continue;
            }

            $boat = isset($row['boat_type_key']) ? trim((string) $row['boat_type_key']) : '';
            $hull = isset($row['hull_type_key']) ? trim((string) $row['hull_type_key']) : '';
            $mat = isset($row['hull_material_key']) ? trim((string) $row['hull_material_key']) : '';
            if ($boat === '' || $hull === '' || $mat === '') {
                return [
                    'error' => "Model \"{$modelId}\" must include non-empty boat_type_key, hull_type_key, and hull_material_key.",
                    'uniform_for_make' => null,
                ];
            }

            if (BoatType::tryFrom($boat) === null) {
                return [
                    'error' => "Invalid boat_type_key \"{$boat}\" on model \"{$modelId}\".",
                    'uniform_for_make' => null,
                ];
            }
            if (HullType::tryFrom($hull) === null) {
                return [
                    'error' => "Invalid hull_type_key \"{$hull}\" on model \"{$modelId}\".",
                    'uniform_for_make' => null,
                ];
            }
            if (HullMaterial::tryFrom($mat) === null) {
                return [
                    'error' => "Invalid hull_material_key \"{$mat}\" on model \"{$modelId}\".",
                    'uniform_for_make' => null,
                ];
            }

            $triple = ['boat' => $boat, 'hull' => $hull, 'material' => $mat];
            $resolved = $this->resolveLookupIds($triple);
            if ($resolved['error'] !== null) {
                return [
                    'error' => $resolved['error']." (model \"{$modelId}\").",
                    'uniform_for_make' => null,
                ];
            }

            $distinct[$boat."\n".$hull."\n".$mat] = $triple;
        }

        if ($distinct === []) {
            return [
                'error' => 'No model rows with a non-empty id found in meta.json.',
                'uniform_for_make' => null,
            ];
        }

        $uniform = count($distinct) === 1 ? reset($distinct) : null;

        return ['error' => null, 'uniform_for_make' => $uniform];
    }

    /**
     * @param  array{boat: string, hull: string, material: string}  $triple
     * @return array{error: string|null, boat_type_id?: int, hull_type_id?: int, hull_material_id?: int}
     */
    private function resolveLookupIds(array $triple): array
    {
        $boatTypeId = InventoryBoatType::query()->where('slug', $triple['boat'])->value('id');
        if ($boatTypeId === null) {
            return ['error' => "Lookup boat_type.slug not found in inventory DB: {$triple['boat']}"];
        }

        $hullTypeId = InventoryHullType::query()->where('slug', $triple['hull'])->value('id');
        if ($hullTypeId === null) {
            return ['error' => "Lookup hull_type.slug not found in inventory DB: {$triple['hull']}"];
        }

        $hullMaterialId = InventoryHullMaterial::query()->where('slug', $triple['material'])->value('id');
        if ($hullMaterialId === null) {
            return ['error' => "Lookup hull_material.slug not found in inventory DB: {$triple['material']}"];
        }

        return [
            'error' => null,
            'boat_type_id' => (int) $boatTypeId,
            'hull_type_id' => (int) $hullTypeId,
            'hull_material_id' => (int) $hullMaterialId,
        ];
    }

    private function decodeJsonFile(string $path): mixed
    {
        $raw = (string) file_get_contents($path);

        return json_decode($this->stripTrailingLineCommentsAfterBraces($raw), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * AssetInformation meta.json files may contain trailing // markers between array elements (invalid JSON).
     * Strip only line suffixes like "}, //label" or "} //label" so strict json_decode succeeds.
     */
    private function stripTrailingLineCommentsAfterBraces(string $json): string
    {
        $json = preg_replace('/}\s*,\s*\/\/[^\r\n]*/m', '},', $json) ?? $json;
        $json = preg_replace('/}\s*\/\/[^\r\n]*/m', '}', $json) ?? $json;

        return $json;
    }

    /**
     * @return list<string>|null
     */
    private function normalizeFeaturesList(mixed $features): ?array
    {
        if (! is_array($features) || $features === []) {
            return null;
        }

        $out = [];
        foreach ($features as $item) {
            if (! is_string($item)) {
                continue;
            }
            $t = trim($item);
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return $out === [] ? null : array_values($out);
    }

    /**
     * Column names match meta.json `specifications` keys; values stored as unsigned integers (null when absent or invalid).
     *
     * @param  array<string, mixed>  $specs
     * @return array{
     *     length_mm: int|null,
     *     width_mm: int|null,
     *     height_mm: int|null,
     *     weight_kg: int|null,
     *     capacity_persons: int|null,
     *     max_hp: int|null,
     *     fuel_capacity_l: int|null,
     * }
     */
    private function metaSpecificationColumns(array $specs): array
    {
        return [
            'length_mm' => $this->metaSpecUInt($specs['length_mm'] ?? null),
            'width_mm' => $this->metaSpecUInt($specs['width_mm'] ?? null),
            'height_mm' => $this->metaSpecUInt($specs['height_mm'] ?? null),
            'weight_kg' => $this->metaSpecUInt($specs['weight_kg'] ?? null),
            'capacity_persons' => $this->metaSpecUInt($specs['capacity_persons'] ?? null),
            'max_hp' => $this->metaSpecUInt($specs['max_hp'] ?? null),
            'fuel_capacity_l' => $this->metaSpecUInt($specs['fuel_capacity_l'] ?? null),
        ];
    }

    private function metaSpecUInt(mixed $value): ?int
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
