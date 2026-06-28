<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Helmful_Sync_Importer
{
    /**
     * @return array{success: bool, shows_synced: int, events_synced: int, brands_synced: int, inventory_synced: int, errors: list<string>, message: string}
     */
    public static function pull_all(): array
    {
        $domain = Helmful_Sync_Settings::tenant_domain();
        $apiKey = Helmful_Sync_Settings::helmful_api_key();

        if ($domain === '' || $apiKey === '') {
            return self::failure('Tenant domain and Helmful API key are required.', 'Helmful credentials are not configured.');
        }

        $showsSynced = 0;
        $eventsSynced = 0;
        $brandsSynced = 0;
        $inventorySynced = 0;
        $errors = [];

        $boatShows = self::fetch_json('/api/wordpress/boat-shows');
        if ($boatShows['success']) {
            foreach (($boatShows['body']['shows'] ?? []) as $show) {
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

            foreach (($boatShows['body']['events'] ?? []) as $event) {
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
        } else {
            $errors[] = $boatShows['message'];
        }

        $brands = self::fetch_json('/api/wordpress/brands');
        if ($brands['success']) {
            foreach (($brands['body']['brands'] ?? []) as $brand) {
                if (! is_array($brand)) {
                    continue;
                }
                try {
                    Helmful_Sync_Handler::upsert_brand($brand);
                    $brandsSynced++;
                } catch (Throwable $e) {
                    $errors[] = 'Brand: '.$e->getMessage();
                }
            }
        } else {
            $errors[] = $brands['message'];
        }

        $inventory = self::fetch_json('/api/wordpress/inventory');
        if ($inventory['success']) {
            foreach (($inventory['body']['inventory'] ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                try {
                    Helmful_Sync_Handler::upsert_inventory($item);
                    $inventorySynced++;
                } catch (Throwable $e) {
                    $errors[] = 'Inventory: '.$e->getMessage();
                }
            }
        } else {
            $errors[] = $inventory['message'];
        }

        update_option('helmful_sync_last_pull_at', gmdate('c'));

        $message = sprintf(
            'Pulled %d shows, %d events, %d brands, and %d inventory items from Helmful.',
            $showsSynced,
            $eventsSynced,
            $brandsSynced,
            $inventorySynced,
        );

        return [
            'success' => $errors === [],
            'shows_synced' => $showsSynced,
            'events_synced' => $eventsSynced,
            'brands_synced' => $brandsSynced,
            'inventory_synced' => $inventorySynced,
            'errors' => $errors,
            'message' => $errors === [] ? $message : 'Pull completed with errors.',
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

        $result = self::fetch_json('/api/wordpress/status');
        if (! $result['success']) {
            return [
                'success' => false,
                'message' => $result['message'],
            ];
        }

        $tenantName = (string) ($result['body']['tenant_name'] ?? '');

        return [
            'success' => true,
            'message' => $tenantName !== ''
                ? 'Connected to Helmful workspace: '.$tenantName
                : 'Connected to Helmful.',
        ];
    }

    /**
     * @return array{success: bool, body: array<string, mixed>, message: string}
     */
    private static function fetch_json(string $path): array
    {
        $url = self::tenant_base_url().$path;
        $response = wp_remote_get($url, self::request_args([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.Helmful_Sync_Settings::helmful_api_key(),
        ], 60));

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'body' => [],
                'message' => $response->get_error_message(),
            ];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($status < 200 || $status >= 300 || ! is_array($body)) {
            $message = is_array($body) ? (string) ($body['message'] ?? 'Invalid response from Helmful.') : 'Invalid response from Helmful.';

            return [
                'success' => false,
                'body' => [],
                'message' => $message,
            ];
        }

        return [
            'success' => true,
            'body' => $body,
            'message' => '',
        ];
    }

    /**
     * @return array{success: bool, shows_synced: int, events_synced: int, brands_synced: int, inventory_synced: int, errors: list<string>, message: string}
     */
    private static function failure(string $error, string $message): array
    {
        return [
            'success' => false,
            'shows_synced' => 0,
            'events_synced' => 0,
            'brands_synced' => 0,
            'inventory_synced' => 0,
            'errors' => [$error],
            'message' => $message,
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
