<?php

declare(strict_types=1);

namespace App\Support\Survey;

use Illuminate\Support\Facades\URL;

/**
 * Public survey URLs for emails/SMS.
 *
 * Tenant named routes are only registered for HTTP requests on the tenant subdomain
 * (see bootstrap/app.php). Queue workers and console lack surveysPublicShow, so we
 * build the same path manually and sign it with Laravel's signed-route algorithm.
 */
final class SurveyPublicUrl
{
    public const SHOW_PATH = '/survey/view';

    /**
     * @param  array<string, scalar|null>  $query
     */
    public static function signedShowUrl(array $query): string
    {
        $query = self::normalizedQuery($query);

        $root = self::tenantAbsoluteRoot();
        if ($root !== null) {
            return self::signAbsoluteUrl($root, $query);
        }

        return URL::signedRoute('surveysPublicShow', $query);
    }

    public static function unsignedShowUrl(string $surveyUuid, ?int $agentId = null): string
    {
        $query = ['id' => $surveyUuid];
        if ($agentId !== null) {
            $query['aid'] = $agentId;
        }

        $root = self::tenantAbsoluteRoot();
        if ($root !== null) {
            ksort($query);

            return $root.self::SHOW_PATH.'?'.http_build_query($query);
        }

        $url = route('surveysPublicShow', ['id' => $surveyUuid], absolute: true);
        if ($agentId !== null) {
            $url .= (str_contains($url, '?') ? '&' : '?').'aid='.$agentId;
        }

        return $url;
    }

    public static function tenantAbsoluteRoot(): ?string
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

    /**
     * @param  array<string, scalar|null>  $query
     * @return array<string, scalar>
     */
    private static function normalizedQuery(array $query): array
    {
        $out = [];
        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $out[(string) $key] = $value;
        }

        return $out;
    }

    /**
     * @param  array<string, scalar>  $query
     */
    private static function signAbsoluteUrl(string $root, array $query): string
    {
        ksort($query);

        $unsigned = $root.self::SHOW_PATH.'?'.http_build_query($query);
        $signature = hash_hmac('sha256', $unsigned, (string) config('app.key'));

        $query['signature'] = $signature;
        ksort($query);

        return $root.self::SHOW_PATH.'?'.http_build_query($query);
    }
}
