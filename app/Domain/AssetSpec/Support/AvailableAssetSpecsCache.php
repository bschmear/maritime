<?php

declare(strict_types=1);

namespace App\Domain\AssetSpec\Support;

use App\Domain\AssetSpec\Models\AssetSpecDefinition;
use App\Enums\Inventory\AssetType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Caches asset spec definitions (with group) per asset type for record forms.
 *
 * Uses the `redis` cache store so tenancy + tagged cache work (the default
 * `database` store does not support tagging). Stancl still scopes keys per tenant.
 */
final class AvailableAssetSpecsCache
{
    private const TTL_SECONDS = 86400;

    private const STORE = 'redis';

    public static function key(int $assetType): string
    {
        return 'available_asset_specs.' . $assetType;
    }

    /**
     * @return Collection<int, AssetSpecDefinition>
     */
    public static function get(int $assetType): Collection
    {
        return Cache::store(self::STORE)->remember(
            self::key($assetType),
            now()->addSeconds(self::TTL_SECONDS),
            fn () => AssetSpecDefinition::query()
                ->with('group')
                ->whereJsonContains('asset_types', $assetType)
                ->orderBy('position')
                ->get()
        );
    }

    /**
     * Clear cached lists for every known asset type (safe after any spec/group change).
     */
    public static function forgetAll(): void
    {
        $store = Cache::store(self::STORE);
        foreach (AssetType::cases() as $case) {
            $store->forget(self::key($case->value));
        }
    }
}
