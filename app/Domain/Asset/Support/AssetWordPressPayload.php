<?php

declare(strict_types=1);

namespace App\Domain\Asset\Support;

use App\Domain\Asset\Models\Asset;
use App\Domain\BoatMake\Support\BoatMakeWordPressPayload;
use App\Support\TenantAbsoluteUrl;

final class AssetWordPressPayload
{
    public static function uuidFor(Asset $asset): string
    {
        if (filled($asset->catalog_asset_key)) {
            return 'inventory:'.$asset->catalog_asset_key;
        }

        if (filled($asset->slug)) {
            return 'inventory:'.$asset->slug;
        }

        return 'inventory:id:'.$asset->id;
    }

    /**
     * @return array<string, mixed>
     */
    public static function forAsset(Asset $asset): array
    {
        $asset->loadMissing('make');

        $brandUuid = $asset->make !== null
            ? BoatMakeWordPressPayload::uuidFor($asset->make)
            : null;

        return [
            'uuid' => self::uuidFor($asset),
            'display_name' => $asset->display_name,
            'slug' => $asset->slug ?? $asset->catalog_asset_key,
            'brand_uuid' => $brandUuid,
            'brand_name' => $asset->make?->display_name,
            'brand_slug' => $asset->make?->slug ?? $asset->make?->brand_key,
            'model' => $asset->model,
            'year' => $asset->year,
            'length' => $asset->length,
            'description' => $asset->description,
            'default_price' => $asset->default_price,
            'type' => $asset->type,
            'active' => ! (bool) $asset->inactive,
            'app_asset_url' => TenantAbsoluteUrl::path('assets/'.$asset->id),
            'updated_at' => $asset->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{inventory: list<array<string, mixed>>}
     */
    public static function all(): array
    {
        $assets = Asset::query()
            ->where('inactive', false)
            ->whereNotNull('make_id')
            ->with(['make'])
            ->orderBy('display_name')
            ->get();

        return [
            'inventory' => $assets->map(fn (Asset $asset) => self::forAsset($asset))->values()->all(),
        ];
    }
}
