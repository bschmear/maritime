<?php

declare(strict_types=1);

namespace App\Support\Tenant;

use App\Domain\Payment\Models\PaymentConfiguration;
use Illuminate\Support\Facades\Cache;

/**
 * Redis cache for payment processor configuration rows and enabled method lists.
 */
final class PaymentConfigurationCache
{
    private const TTL_SECONDS = 3600;

    private const STORE = 'redis';

    /**
     * @param  callable(): PaymentConfiguration  $resolver
     */
    public static function rememberStripe(int $accountSettingsId, callable $resolver): PaymentConfiguration
    {
        if (! tenancy()->initialized) {
            return $resolver();
        }

        return Cache::store(self::STORE)->remember(
            self::stripeKey($accountSettingsId),
            now()->addSeconds(self::TTL_SECONDS),
            $resolver,
        );
    }

    /**
     * @param  callable(): list<array{code: string, label: string}>  $resolver
     * @return list<array{code: string, label: string}>
     */
    public static function rememberEnabledMethods(int $accountSettingsId, callable $resolver): array
    {
        if (! tenancy()->initialized) {
            return $resolver();
        }

        return Cache::store(self::STORE)->remember(
            self::enabledMethodsKey($accountSettingsId),
            now()->addSeconds(self::TTL_SECONDS),
            $resolver,
        );
    }

    public static function forgetForAccount(int $accountSettingsId): void
    {
        if (! tenancy()->initialized) {
            return;
        }

        $store = Cache::store(self::STORE);
        $store->forget(self::stripeKey($accountSettingsId));
        $store->forget(self::enabledMethodsKey($accountSettingsId));
    }

    private static function stripeKey(int $accountSettingsId): string
    {
        return 'payment_config:stripe:'.$accountSettingsId;
    }

    private static function enabledMethodsKey(int $accountSettingsId): string
    {
        return 'payment_methods:enabled:'.$accountSettingsId;
    }
}
