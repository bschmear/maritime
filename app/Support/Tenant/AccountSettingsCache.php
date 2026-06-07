<?php

declare(strict_types=1);

namespace App\Support\Tenant;

use App\Models\AccountSettings;
use Illuminate\Support\Facades\Cache;

/**
 * Redis cache for the singleton account_settings row (Stancl scopes keys per tenant).
 */
final class AccountSettingsCache
{
    private const KEY = 'account_settings';

    private const TTL_SECONDS = 86400;

    private const STORE = 'redis';

    public static function get(): AccountSettings
    {
        if (! tenancy()->initialized) {
            return self::resolveFromDatabase();
        }

        return Cache::store(self::STORE)->remember(
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

    private static function resolveFromDatabase(): AccountSettings
    {
        $settings = AccountSettings::query()->first();

        if ($settings !== null) {
            return $settings;
        }

        return AccountSettings::query()->create([
            'timezone' => 'America/Chicago',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'currency' => 'USD',
            'week_starts_on_monday' => false,
            'auto_assign_work_orders' => false,
            'brand_color' => '#3B82F6',
            'workday_hours' => 6,
            'start_time' => '08:00:00',
            'allow_overlap' => false,
            'consignment_fee_percent' => 20,
            'onboarding_complete' => false,
            'account_overviewed' => false,
            'account_setup_complete' => false,
        ]);
    }
}
