<?php

declare(strict_types=1);

namespace App\Support\Tenant;

use App\Services\Leads\LeadOverviewDataService;
use Illuminate\Support\Facades\Cache;

/**
 * Cached count of active pipeline leads (not converted, not disqualified).
 * Stancl scopes keys per tenant.
 */
final class LeadPipelineCountCache
{
    private const KEY = 'lead_pipeline_active_count';

    private const TTL_SECONDS = 300;

    private const STORE = 'redis';

    public static function get(): int
    {
        if (! tenancy()->initialized) {
            return self::resolveFromDatabase();
        }

        return (int) Cache::store(self::STORE)->remember(
            self::KEY,
            now()->addSeconds(self::TTL_SECONDS),
            fn () => self::resolveFromDatabase(),
        );
    }

    public static function forget(): void
    {
        if (! tenancy()->initialized) {
            return;
        }

        Cache::store(self::STORE)->forget(self::KEY);
    }

    private static function resolveFromDatabase(): int
    {
        return app(LeadOverviewDataService::class)->pipelineBaseQuery()->count();
    }
}
