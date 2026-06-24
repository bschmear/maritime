<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Helmful_Sync_Importer
{
    /**
     * @return array{success: bool, shows_synced: int, events_synced: int, errors: list<string>, message: string}
     */
    public static function pull_all(): array
    {
        $domain = Helmful_Sync_Settings::tenant_domain();
        $apiKey = Helmful_Sync_Settings::helmful_api_key();

        if ($domain === '' || $apiKey === '') {
            return [
                'success' => false,
                'shows_synced' => 0,
                'events_synced' => 0,
                'errors' => ['Tenant domain and Helmful API key are required.'],
                'message' => 'Helmful credentials are not configured.',
            ];
        }

        $base = self::tenant_base_url();
        $url = $base.'/api/wordpress/boat-shows';
        $response = wp_remote_get($url, self::request_args([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$apiKey,
        ], 60));

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'shows_synced' => 0,
                'events_synced' => 0,
                'errors' => [$response->get_error_message()],
                'message' => 'Pull from Helmful failed.',
            ];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($status < 200 || $status >= 300 || ! is_array($body)) {
            $message = is_array($body) ? (string) ($body['message'] ?? 'Invalid response from Helmful.') : 'Invalid response from Helmful.';

            return [
                'success' => false,
                'shows_synced' => 0,
                'events_synced' => 0,
                'errors' => [$message],
                'message' => 'Pull from Helmful failed.',
            ];
        }

        $showsSynced = 0;
        $eventsSynced = 0;
        $errors = [];

        foreach (($body['shows'] ?? []) as $show) {
            if (! is_array($show)) {
                continue;
            }
            try {
                Helmful_Sync_Handler::upsert_show($show);
                $showsSynced++;
            } catch (Throwable $e) {
                $errors[] = 'Show: '.$e->getMessage();
            }
        }

        foreach (($body['events'] ?? []) as $event) {
            if (! is_array($event)) {
                continue;
            }
            try {
                Helmful_Sync_Handler::upsert_event($event);
                $eventsSynced++;
            } catch (Throwable $e) {
                $errors[] = 'Event: '.$e->getMessage();
            }
        }

        update_option('helmful_sync_last_pull_at', gmdate('c'));

        return [
            'success' => $errors === [],
            'shows_synced' => $showsSynced,
            'events_synced' => $eventsSynced,
            'errors' => $errors,
            'message' => $errors === []
                ? sprintf('Pulled %d shows and %d events from Helmful.', $showsSynced, $eventsSynced)
                : 'Pull completed with errors.',
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public static function test_connection(): array
    {
        $domain = Helmful_Sync_Settings::tenant_domain();
        $apiKey = Helmful_Sync_Settings::helmful_api_key();

        if ($domain === '' || $apiKey === '') {
            return [
                'success' => false,
                'message' => 'Tenant domain and Helmful API key are required.',
            ];
        }

        $url = self::tenant_base_url().'/api/wordpress/status';
        $response = wp_remote_get($url, self::request_args([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$apiKey,
        ]));

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($status < 200 || $status >= 300) {
            return [
                'success' => false,
                'message' => is_array($body) ? (string) ($body['message'] ?? 'Connection failed.') : 'Connection failed.',
            ];
        }

        $tenantName = is_array($body) ? (string) ($body['tenant_name'] ?? '') : '';

        return [
            'success' => true,
            'message' => $tenantName !== ''
                ? 'Connected to Helmful workspace: '.$tenantName
                : 'Connected to Helmful.',
        ];
    }

    private static function tenant_base_url(): string
    {
        $domain = trim(Helmful_Sync_Settings::tenant_domain());
        $host = $domain;

        if (str_contains($domain, '://')) {
            $parsed = wp_parse_url($domain);
            $host = (string) ($parsed['host'] ?? '');
        }

        $host = trim($host, '/');
        if ($host === '') {
            return '';
        }

        if (self::is_local_helmful_host($host)) {
            return 'http://'.$host;
        }

        if (str_contains($domain, '://')) {
            return rtrim($domain, '/');
        }

        return 'https://'.$host;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private static function request_args(array $headers, int $timeout = 30): array
    {
        $args = [
            'timeout' => $timeout,
            'headers' => $headers,
        ];

        $host = (string) wp_parse_url(self::tenant_base_url(), PHP_URL_HOST);
        if (self::is_local_helmful_host($host)) {
            $args['sslverify'] = false;
        }

        /**
         * @param  array<string, mixed>  $args
         */
        return apply_filters('helmful_sync_request_args', $args);
    }

    private static function is_local_helmful_host(string $host): bool
    {
        if ($host === 'localhost' || $host === '127.0.0.1') {
            return true;
        }

        return str_ends_with($host, '.test')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.localhost');
    }
}
