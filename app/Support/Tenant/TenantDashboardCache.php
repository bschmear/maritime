<?php

declare(strict_types=1);

namespace App\Support\Tenant;

use App\Support\Dashboard\DashboardFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Short-TTL cache for the tenant dashboard payload (per user + filter scope).
 */
final class TenantDashboardCache
{
    private const STORE = 'redis';

    /**
     * @param  callable(): array<string, mixed>  $resolver
     * @return array<string, mixed>
     */
    public static function remember(Request $request, DashboardFilters $filters, ?int $tenantUserId, callable $resolver): array
    {
        if (! config('dashboard.cache_enabled', true) || ! tenancy()->initialized) {
            return $resolver();
        }

        $ttl = max(30, (int) config('dashboard.cache_ttl_seconds', 90));

        /** @var array<string, mixed> */
        return Cache::store(self::STORE)->remember(
            self::key($request, $filters, $tenantUserId),
            now()->addSeconds($ttl),
            $resolver,
        );
    }

    private static function key(Request $request, DashboardFilters $filters, ?int $tenantUserId): string
    {
        $period = strtolower(trim((string) $request->query('period', 'all')));

        return 'tenant_dashboard:'.($tenantUserId ?? 0)
            .':'.($filters->subsidiaryId ?? 'all')
            .':'.($filters->locationId ?? 'all')
            .':'.($period !== '' ? $period : 'all');
    }
}
