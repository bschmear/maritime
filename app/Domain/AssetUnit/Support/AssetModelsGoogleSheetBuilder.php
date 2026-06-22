<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\BoatMake\Models\BoatMake;
use App\Enums\Inventory\BoatType;
use App\Enums\Inventory\HullMaterial;
use App\Enums\Inventory\HullType;

class AssetModelsGoogleSheetBuilder
{
    public function __construct(
        private readonly AssetModelsGoogleSheetColumnRegistry $columns = new AssetModelsGoogleSheetColumnRegistry,
        private readonly GoogleSheetSpecSupport $specs = new GoogleSheetSpecSupport,
    ) {}

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return $this->columns->allHeaders();
    }

    /**
     * @return list<list<mixed>>
     */
    public function buildModelRows(): array
    {
        $specDefinitions = $this->columns->specDefinitions();
        $specIds = array_map(fn ($d) => $d->id, $specDefinitions);
        $rows = [$this->headers()];
        $specables = [];

        $assets = Asset::query()
            ->with(['make', 'variants'])
            ->orderBy('id')
            ->get();

        foreach ($assets as $asset) {
            if ($asset->has_variants) {
                foreach ($asset->variants as $variant) {
                    $specable = $this->specs->resolveSpecable($asset, $variant);
                    $specables[] = [$specable->getMorphClass(), (int) $specable->getKey()];
                }
            } else {
                $specables[] = [$asset->getMorphClass(), (int) $asset->getKey()];
            }
        }

        $specValuesByKey = $specIds !== [] && $specables !== []
            ? $this->specs->loadSpecValues($specDefinitions, $specIds, $specables)
            : [];

        foreach ($assets as $asset) {
            if ($asset->has_variants) {
                foreach ($asset->variants as $variant) {
                    $specable = $this->specs->resolveSpecable($asset, $variant);
                    $key = $this->specs->specableKey($specable);
                    $rows[] = $this->rowForModel(
                        $asset,
                        $variant,
                        $specDefinitions,
                        $specValuesByKey[$key] ?? [],
                    );
                }
            } else {
                $key = $this->specs->specableKey($asset);
                $rows[] = $this->rowForModel(
                    $asset,
                    null,
                    $specDefinitions,
                    $specValuesByKey[$key] ?? [],
                );
            }
        }

        return $rows;
    }

    /**
     * @return array{
     *   makes: list<string>,
     *   variants: list<string>,
     *   hull_types: list<string>,
     *   hull_materials: list<string>,
     *   boat_types: list<string>
     * }
     */
    public function referenceLists(): array
    {
        $makes = BoatMake::query()
            ->orderBy('display_name')
            ->pluck('display_name')
            ->filter()
            ->values()
            ->all();

        $variants = AssetVariant::query()
            ->with(['asset:id,model'])
            ->orderBy('display_name')
            ->get()
            ->map(function (AssetVariant $variant) {
                $model = $variant->asset?->model ?: 'Asset #'.$variant->asset_id;

                return trim($model.' — '.($variant->display_name ?: $variant->name));
            })
            ->filter()
            ->values()
            ->all();

        return [
            'makes' => $makes,
            'variants' => $variants,
            'hull_types' => GoogleSheetEnumLabels::hullTypeLabels(),
            'hull_materials' => GoogleSheetEnumLabels::hullMaterialLabels(),
            'boat_types' => GoogleSheetEnumLabels::boatTypeLabels(),
        ];
    }

    /**
     * @param  list<\App\Domain\AssetSpec\Models\AssetSpecDefinition>  $specDefinitions
     * @param  array<int, \App\Domain\AssetSpec\Models\AssetSpecValue>  $specValues
     * @return list<mixed>
     */
    private function rowForModel(
        Asset $asset,
        ?AssetVariant $variant,
        array $specDefinitions,
        array $specValues,
    ): array {
        $source = $variant ?? $asset;

        return array_merge([
            $asset->make?->display_name ?? '',
            $asset->model ?? '',
            $variant ? ($variant->display_name ?: $variant->name) : '',
            $asset->year ?? '',
            GoogleSheetEnumLabels::enumLabel($asset->hull_type, HullType::class),
            GoogleSheetEnumLabels::enumLabel($asset->hull_material, HullMaterial::class),
            GoogleSheetEnumLabels::enumLabel($asset->boat_type, BoatType::class),
            GoogleSheetEnumLabels::formatLengthMm($source->length),
            GoogleSheetEnumLabels::formatLengthMm($source->width),
        ], $this->specs->specCells($specDefinitions, $specValues));
    }
}
