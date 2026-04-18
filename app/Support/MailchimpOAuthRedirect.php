<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Canonical Mailchimp OAuth redirect_uri — must be byte-identical on authorize and token requests
 * and in the Mailchimp developer app.
 */
final class MailchimpOAuthRedirect
{
    public static function canonical(): string
    {
        $raw = trim((string) env('MAILCHIMP_REDIRECT_URI', ''));
        if ($raw === '') {
            $raw = rtrim((string) env('APP_URL', 'http://localhost'), '/').'/integrations/mailchimp/oauth/callback';
        }

        $parts = parse_url($raw);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $raw;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $path = $parts['path'] ?? '';
        $path = '/'.ltrim($path, '/');
        $path = rtrim($path, '/') ?: '/';

        // Mailchimp and production apps expect https; APP_URL often uses http locally by mistake.
        if (! self::isLocalHost($host) && $scheme === 'http') {
            $scheme = 'https';
        }

        $authority = $host;
        if (isset($parts['port'])) {
            $port = (int) $parts['port'];
            if (! self::isDefaultPort($scheme, $port)) {
                $authority .= ':'.$port;
            }
        }

        // Query/fragment are not valid for Mailchimp redirect registration; strip them.
        return $scheme.'://'.$authority.$path;
    }

    private static function isLocalHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
    }

    private static function isDefaultPort(string $scheme, int $port): bool
    {
        return ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
    }
}
