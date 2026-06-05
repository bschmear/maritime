<?php

declare(strict_types=1);

namespace App\Support;

final class KioskUrl
{
    public static function base(): string
    {
        $adminUrl = config('app.admin_url');
        if (is_string($adminUrl) && trim($adminUrl) !== '') {
            return rtrim(trim($adminUrl), '/');
        }

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $domain = trim((string) config('app.domain', 'localhost'));

        return sprintf('%s://kiosk.%s', $scheme, $domain);
    }

    public static function dashboard(): string
    {
        return self::base().'/';
    }

    public static function accountShow(int|string $accountId): string
    {
        return self::base().'/accounts/'.urlencode((string) $accountId);
    }
}
