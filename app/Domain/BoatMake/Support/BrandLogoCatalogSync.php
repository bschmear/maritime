<?php

declare(strict_types=1);

namespace App\Domain\BoatMake\Support;

use App\Domain\InventoryCatalog\Models\InventoryBoatMake;

final class BrandLogoCatalogSync
{
    public static function defaultLogoForBrandKey(?string $brandKey): ?string
    {
        if ($brandKey === null || $brandKey === '') {
            return null;
        }

        $inventoryMake = InventoryBoatMake::query()
            ->where('slug', $brandKey)
            ->first(['logo_url']);

        $url = $inventoryMake?->logo_url;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * @return array{use_default_logo: bool, default_brand_image: ?string}
     */
    public static function importDefaults(?string $brandKey): array
    {
        $defaultLogo = self::defaultLogoForBrandKey($brandKey);

        return [
            'use_default_logo' => $defaultLogo !== null,
            'default_brand_image' => $defaultLogo,
        ];
    }
}
