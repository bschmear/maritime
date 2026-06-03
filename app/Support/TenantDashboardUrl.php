<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Account;

final class TenantDashboardUrl
{
    /**
     * Absolute URL for the tenant workspace dashboard (tenant subdomain root).
     * Falls back to the central account picker when no tenant domain exists yet.
     */
    public static function forAccount(Account $account): string
    {
        $account->loadMissing('domains');

        $domain = $account->domains->first()?->domain;

        if (is_string($domain) && trim($domain) !== '') {
            $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

            return rtrim(sprintf('%s://%s', $scheme, trim($domain)), '/').'/';
        }

        return route('dashboard', [], absolute: true);
    }
}
