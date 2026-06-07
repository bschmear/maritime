<?php

declare(strict_types=1);

namespace App\Support\Central;

use App\Models\Account;
use Illuminate\Support\Facades\Cache;

/**
 * Central (public schema) account lookup by Stancl tenant id.
 *
 * Cached while tenancy is initialized; keys are still tenant-prefixed by Stancl.
 */
final class TenantAccountCache
{
    private const TTL_SECONDS = 3600;

    private const STORE = 'redis';

    public static function findByTenantId(string $tenantId): ?Account
    {
        return Cache::store(self::STORE)->remember(
            self::key($tenantId),
            now()->addSeconds(self::TTL_SECONDS),
            fn () => Account::query()->where('tenant_id', $tenantId)->first(),
        );
    }

    public static function forget(string $tenantId): void
    {
        Cache::store(self::STORE)->forget(self::key($tenantId));
    }

    private static function key(string $tenantId): string
    {
        return 'central_account_by_tenant:'.$tenantId;
    }
}
