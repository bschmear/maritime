<?php

declare(strict_types=1);

namespace App\Domain\BoatMake\Support;

use App\Domain\BoatMake\Models\BoatMake;
use App\Support\TenantAbsoluteUrl;

final class BoatMakeWordPressPayload
{
    public static function uuidFor(BoatMake $make): string
    {
        if (filled($make->brand_key)) {
            return 'brand:'.$make->brand_key;
        }

        if (filled($make->slug)) {
            return 'brand:'.$make->slug;
        }

        return 'brand:id:'.$make->id;
    }

    /**
     * @return array<string, mixed>
     */
    public static function forBrand(BoatMake $make): array
    {
        return [
            'uuid' => self::uuidFor($make),
            'display_name' => $make->display_name,
            'slug' => $make->slug ?? $make->brand_key,
            'brand_key' => $make->brand_key,
            'logo_url' => $make->logo_url,
            'active' => (bool) $make->active,
            'app_brand_url' => TenantAbsoluteUrl::path('boatmakes/'.$make->id),
            'updated_at' => $make->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{brands: list<array<string, mixed>>}
     */
    public static function all(): array
    {
        $brands = BoatMake::query()
            ->where('active', true)
            ->orderBy('display_name')
            ->get();

        return [
            'brands' => $brands->map(fn (BoatMake $make) => self::forBrand($make))->values()->all(),
        ];
    }
}
