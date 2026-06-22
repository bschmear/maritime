<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\BoatMake\Models\BoatMake;

final class AssetModelsGoogleSheetRowResolver
{
    /**
     * @param  array<string, string|null>  $row
     * @return array{0: ?Asset, 1: ?AssetVariant, 2: ?string}
     */
    public function resolve(array $row, int $sheetRowNumber): array
    {
        $makeName = trim((string) ($row[AssetModelsGoogleSheetColumnRegistry::HEADER_MAKE] ?? ''));
        $modelName = trim((string) ($row[AssetModelsGoogleSheetColumnRegistry::HEADER_MODEL] ?? ''));
        $variantLabel = trim((string) ($row[AssetModelsGoogleSheetColumnRegistry::HEADER_VARIANT] ?? ''));

        if ($makeName === '' || $modelName === '') {
            return [null, null, null];
        }

        $makeId = BoatMake::query()
            ->whereRaw('LOWER(display_name) = ?', [strtolower($makeName)])
            ->value('id');

        if (! $makeId) {
            return [null, null, 'Row '.$sheetRowNumber.": Make \"{$makeName}\" not found."];
        }

        $asset = Asset::query()
            ->where('make_id', $makeId)
            ->whereRaw('LOWER(COALESCE(model, \'\')) = ?', [strtolower($modelName)])
            ->first();

        if ($asset === null) {
            return [null, null, 'Row '.$sheetRowNumber.": Model \"{$modelName}\" not found for make \"{$makeName}\"."];
        }

        if ($variantLabel === '') {
            if ($asset->has_variants) {
                return [null, null, 'Row '.$sheetRowNumber.": Variant is required for model \"{$modelName}\"."];
            }

            return [$asset, null, null];
        }

        $variant = AssetVariant::query()
            ->where('asset_id', $asset->id)
            ->where(function ($q) use ($variantLabel) {
                $normalized = strtolower($variantLabel);
                $q->whereRaw('LOWER(display_name) = ?', [$normalized])
                    ->orWhereRaw('LOWER(name) = ?', [$normalized]);
            })
            ->first();

        if ($variant === null) {
            return [null, null, 'Row '.$sheetRowNumber.": Variant \"{$variantLabel}\" not found for {$makeName} {$modelName}."];
        }

        return [$asset, $variant, null];
    }
}
