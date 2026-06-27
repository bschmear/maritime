<?php

declare(strict_types=1);

namespace App\Support\Central;

use Illuminate\Support\Facades\Cache;

/**
 * Caches whether a central user may access a workspace account (membership or owner).
 *
 * Invalidated when account membership changes (attach/detach, support session end).
 */
final class TenantMembershipCache
{
    private const TTL_SECONDS = 3600;

    private const STORE = 'redis';

    public static function get(int $accountId, int $userId): ?bool
    {
        $value = Cache::store(self::STORE)->get(self::key($accountId, $userId));

        if ($value === null) {
            return null;
        }

        return (bool) $value;
    }

    public static function put(int $accountId, int $userId, bool $hasAccess): void
    {
        Cache::store(self::STORE)->put(
            self::key($accountId, $userId),
            $hasAccess,
            now()->addSeconds(self::TTL_SECONDS),
        );
    }

    public static function forget(int $accountId, int $userId): void
    {
        Cache::store(self::STORE)->forget(self::key($accountId, $userId));
    }

    private static function key(int $accountId, int $userId): string
    {
        return 'tenant_membership:'.$accountId.':'.$userId;
    }
}
