<?php

declare(strict_types=1);

namespace App\Domain\BoatMake\Support;

use App\Domain\InventoryCatalog\Models\InventoryBoatMake;
use App\Support\ManufacturerCatalog;
use App\Support\ManufacturerDetailsCatalog;

final class BrandLogoCatalogSync
{
    public static function defaultLogoForBrandKey(?string $brandKey): ?string
    {
        if ($brandKey === null || $brandKey === '') {
            return null;
        }

        $inventoryMake = InventoryBoatMake::query()
            ->where('slug', $brandKey)
            ->first(['logo_url', 'website_url', 'description']);

        $url = $inventoryMake?->logo_url;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * @return array{website_url: ?string, description: ?string}
     */
    public static function metadataForBrandKey(?string $brandKey): array
    {
        if ($brandKey === null || $brandKey === '') {
            return [
                'website_url' => null,
                'description' => null,
            ];
        }

        $inventoryMake = InventoryBoatMake::query()
            ->where('slug', $brandKey)
            ->first(['website_url', 'description']);

        $websiteUrl = $inventoryMake?->website_url;
        $description = $inventoryMake?->description;

        return [
            'website_url' => is_string($websiteUrl) && $websiteUrl !== '' ? $websiteUrl : null,
            'description' => is_string($description) && trim($description) !== '' ? trim($description) : null,
        ];
    }

    /**
     * @return array{use_default_logo: bool, default_brand_image: ?string, website_url: ?string, description: ?string}
     */
    public static function importDefaults(?string $brandKey): array
    {
        $defaultLogo = self::defaultLogoForBrandKey($brandKey);
        $metadata = self::metadataForBrandKey($brandKey);

        return [
            'use_default_logo' => $defaultLogo !== null,
            'default_brand_image' => $defaultLogo,
            'website_url' => $metadata['website_url'],
            'description' => $metadata['description'],
        ];
    }

    /**
     * Full tenant refresh payload from shared catalog (inventory row + manufacturer_details fallback).
     *
     * @return array{
     *     display_name: string,
     *     website_url: ?string,
     *     description: ?string,
     *     use_default_logo: bool,
     *     default_brand_image: ?string
     * }|null
     */
    public static function refreshPayloadForBrandKey(?string $brandKey): ?array
    {
        if ($brandKey === null || $brandKey === '') {
            return null;
        }

        $inventoryMake = InventoryBoatMake::query()
            ->where('slug', $brandKey)
            ->first(['display_name', 'logo_url', 'website_url', 'description']);

        $details = ManufacturerDetailsCatalog::forSlug($brandKey);
        $catalogDisplayName = self::catalogDisplayNameForSlug($brandKey);

        if ($inventoryMake === null && $details === null && $catalogDisplayName === null) {
            return null;
        }

        $logoUrl = is_string($inventoryMake?->logo_url) && $inventoryMake->logo_url !== ''
            ? $inventoryMake->logo_url
            : null;

        $websiteUrl = is_string($inventoryMake?->website_url) && trim($inventoryMake->website_url) !== ''
            ? trim($inventoryMake->website_url)
            : ($details['url'] ?? null);
        $websiteUrl = is_string($websiteUrl) && $websiteUrl !== '' ? $websiteUrl : null;

        $description = is_string($inventoryMake?->description) && trim($inventoryMake->description) !== ''
            ? trim($inventoryMake->description)
            : ($details['description'] ?? null);
        $description = is_string($description) && $description !== '' ? $description : null;

        $displayName = is_string($inventoryMake?->display_name) && trim($inventoryMake->display_name) !== ''
            ? trim($inventoryMake->display_name)
            : $catalogDisplayName;

        if ($displayName === null && $logoUrl === null && $websiteUrl === null && $description === null) {
            return null;
        }

        return [
            'display_name' => $displayName ?? $brandKey,
            'website_url' => $websiteUrl,
            'description' => $description,
            'use_default_logo' => $logoUrl !== null,
            'default_brand_image' => $logoUrl,
        ];
    }

    private static function catalogDisplayNameForSlug(string $brandKey): ?string
    {
        foreach (ManufacturerCatalog::entries() as $row) {
            if ($row['slug'] === $brandKey) {
                return $row['display_name'];
            }
        }

        return null;
    }
}
