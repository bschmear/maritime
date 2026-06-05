<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Restrict post-submit redirects to same-site URLs (relative paths or current tenant host).
 */
final class SafeRedirectUrl
{
    public static function isAllowed(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return false;
        }

        $url = trim($url);

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        $parsed = parse_url($url);
        if (! is_array($parsed) || empty($parsed['host'])) {
            return false;
        }

        $host = strtolower((string) $parsed['host']);
        $allowed = array_filter(array_unique([
            self::hostFromUrl((string) config('app.url')),
            self::currentRequestHost(),
            tenant() ? self::currentRequestHost() : null,
        ]));

        return in_array($host, $allowed, true);
    }

    public static function sanitize(?string $url): ?string
    {
        return self::isAllowed($url) ? trim((string) $url) : null;
    }

    private static function hostFromUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    private static function currentRequestHost(): ?string
    {
        $host = request()?->getHost();

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }
}
