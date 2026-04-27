<?php

namespace App\Domain\BoatMake\Actions;

use App\Domain\Asset\Models\Asset;
use App\Domain\BoatMake\Models\BoatMake;
use App\Domain\InventoryCatalog\Models\InventoryBoatMake;
use App\Domain\InventoryCatalog\Models\InventoryCatalogAsset;
use App\Domain\InventoryCatalog\Services\CatalogImportService;
use App\Services\BoatMetaAIService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportDiscoveredBoatModels
{
    /**
     * Import one model line by catalog slug + label. Idempotent when the tenant asset already exists.
     *
     * @return 'imported'|'skipped_already_list'|'failed'
     */
    public function importOne(BoatMake $make, string $slug, string $label): string
    {
        if ($make->brand_key === null || $make->brand_key === '') {
            Log::warning('ImportDiscoveredBoatModels: missing brand_key', ['boat_make_id' => $make->id]);

            return 'failed';
        }

        $brandKey = $make->brand_key;
        $catalogKey = $brandKey.'--'.$slug;

        if (Asset::query()->where('make_id', $make->id)->where('catalog_asset_key', $catalogKey)->exists()) {
            return 'skipped_already_list';
        }

        $ai = app(BoatMetaAIService::class);
        $catalog = app(CatalogImportService::class);

        $invMake = InventoryBoatMake::query()->where('slug', $brandKey)->first();
        $alreadyInInventory = $invMake !== null
            && InventoryCatalogAsset::query()
                ->where('make_id', $invMake->id)
                ->where('slug', $catalogKey)
                ->exists();

        if (! $alreadyInInventory) {
            try {
                $ai->generate($brandKey, $slug, $make->display_name, $label);
            } catch (\Throwable $e) {
                Log::warning('ImportDiscoveredBoatModels: model build failed', [
                    'catalog_key' => $catalogKey,
                    'message' => $e->getMessage(),
                ]);

                return 'failed';
            }
        }

        try {
            $result = $catalog->import($make, [$catalogKey]);
            if (($result['imported'] ?? 0) > 0) {
                return 'imported';
            }

            if (Asset::query()->where('make_id', $make->id)->where('catalog_asset_key', $catalogKey)->exists()) {
                return 'skipped_already_list';
            }

            return 'failed';
        } catch (\Throwable $e) {
            Log::warning('ImportDiscoveredBoatModels: import failed', [
                'catalog_key' => $catalogKey,
                'message' => $e->getMessage(),
            ]);

            return 'failed';
        }
    }

    /**
     * For each suggested model: ensure upstream metadata exists (AI when needed), then create/update the tenant asset.
     *
     * @param  list<array{model_slug: string, model_label: string}>  $models
     * @return array{imported: int, skipped_already_list: int, build_failed: int}
     */
    public function __invoke(BoatMake $make, array $models): array
    {
        if ($make->brand_key === null || $make->brand_key === '') {
            Log::warning('ImportDiscoveredBoatModels: missing brand_key', ['boat_make_id' => $make->id]);

            return ['imported' => 0, 'skipped_already_list' => 0, 'build_failed' => 0];
        }

        $imported = 0;
        $skippedAlreadyList = 0;
        $buildFailed = 0;

        foreach ($models as $row) {
            $slug = Str::slug($row['model_slug'] ?? '');
            $label = trim((string) ($row['model_label'] ?? ''));
            if ($slug === '' || $label === '') {
                continue;
            }

            match ($this->importOne($make, $slug, $label)) {
                'imported' => $imported++,
                'skipped_already_list' => $skippedAlreadyList++,
                'failed' => $buildFailed++,
            };
        }

        return [
            'imported' => $imported,
            'skipped_already_list' => $skippedAlreadyList,
            'build_failed' => $buildFailed,
        ];
    }
}
