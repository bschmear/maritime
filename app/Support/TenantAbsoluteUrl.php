<?php

declare(strict_types=1);

namespace App\Support;

final class TenantAbsoluteUrl
{
    public static function root(): ?string
    {
        $tenant = tenant();
        if (! $tenant) {
            return null;
        }

        $domainModel = method_exists($tenant, 'domains')
            ? $tenant->domains()->first()
            : null;
        $domain = $domainModel?->domain;
        if (! is_string($domain) || trim($domain) === '') {
            return null;
        }

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return rtrim(sprintf('%s://%s', $scheme, trim($domain)), '/');
    }

    public static function path(string $path): ?string
    {
        $root = self::root();
        if ($root === null) {
            return null;
        }

        return $root.'/'.ltrim($path, '/');
    }
}
