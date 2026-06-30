<?php

declare(strict_types=1);

namespace App\Support\Tenant;

use Illuminate\Support\Facades\Cache;

/**
 * Caches resolved tenant navigation trees per role slug in Redis.
 *
 * Bump the version when menus change instead of tracking every key.
 */
final class TenantNavigationCache
{
    private const VERSION_KEY = 'tenant_navigation_version';

    private const TTL_SECONDS = 1800;

    private const STORE = 'redis';

    /**
     * @param  callable(): list<array<string, mixed>>  $resolver
     * @return list<array<string, mixed>>
     */
    public static function remember(?string $roleSlug, callable $resolver): array
    {
        if (! tenancy()->initialized) {
            return $resolver();
        }

        return Cache::store(self::STORE)->remember(
            self::menuKey($roleSlug),
            now()->addSeconds(self::TTL_SECONDS),
            $resolver,
        );
    }

    public static function bumpVersion(): void
    {
        if (! tenancy()->initialized) {
            return;
        }

        $store = Cache::store(self::STORE);
        if (! $store->has(self::VERSION_KEY)) {
            $store->put(self::VERSION_KEY, 2, now()->addYear());

            return;
        }

        $store->increment(self::VERSION_KEY);
    }

    private static function menuKey(?string $roleSlug): string
    {
        $version = (int) Cache::store(self::STORE)->get(self::VERSION_KEY, 1);
        $slug = $roleSlug !== null && $roleSlug !== '' ? $roleSlug : 'default';

        return 'tenant_nav:v'.$version.':'.$slug;
    }
}
