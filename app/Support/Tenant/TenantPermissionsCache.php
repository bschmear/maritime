<?php

declare(strict_types=1);

namespace App\Support\Tenant;

use Illuminate\Support\Facades\Cache;

/**
 * Caches tenant permission keys per staff user (users.id) in Redis.
 *
 * Bump the version when roles or permissions change instead of tracking every user key.
 */
final class TenantPermissionsCache
{
    private const VERSION_KEY = 'tenant_permissions_version';

    private const TTL_SECONDS = 1800;

    private const STORE = 'redis';

    /**
     * @param  callable(): list<string>  $resolver
     * @return list<string>
     */
    public static function remember(int $profileId, callable $resolver): array
    {
        if (! tenancy()->initialized) {
            return $resolver();
        }

        return Cache::store(self::STORE)->remember(
            self::userKey($profileId),
            now()->addSeconds(self::TTL_SECONDS),
            $resolver,
        );
    }

    public static function forgetUser(int $profileId): void
    {
        if (! tenancy()->initialized) {
            return;
        }

        Cache::store(self::STORE)->forget(self::userKey($profileId));
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

    private static function userKey(int $profileId): string
    {
        $version = (int) Cache::store(self::STORE)->get(self::VERSION_KEY, 1);

        return 'tenant_user_permissions:v'.$version.':'.$profileId;
    }
}
