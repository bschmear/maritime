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
        $shows = self::pull_boat_shows();
        $brands = self::pull_brands();
        $inventory = self::pull_inventory();

        $errors = array_merge(
            $shows['errors'],
            $brands['errors'],
            $inventory['errors'],
        );

        $message = sprintf(
            'Pulled %d shows, %d events, %d brands, and %d inventory items from Helmful.',
            $shows['shows_synced'],
            $shows['events_synced'],
            $brands['brands_synced'],
            $inventory['inventory_synced'],
        );

        return [
            'success' => $errors === [],
            'shows_synced' => $shows['shows_synced'],
            'events_synced' => $shows['events_synced'],
            'brands_synced' => $brands['brands_synced'],
            'inventory_synced' => $inventory['inventory_synced'],
            'errors' => $errors,
            'message' => $errors === [] ? $message : 'Pull completed with errors.',
        ];
    }

    /**
     * @return array{success: bool, shows_synced: int, events_synced: int, brands_synced: int, inventory_synced: int, errors: list<string>, message: string}
     */
    public static function pull_boat_shows(): array
    {
        $failure = self::credentials_failure();
        if ($failure !== null) {
            return $failure;
        }

        $showsSynced = 0;
        $eventsSynced = 0;
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

        if ($boatShows['success']) {
            self::touch_last_pull();
        }

        $message = sprintf(
            'Pulled %d boat shows and %d events from Helmful.',
            $showsSynced,
            $eventsSynced,
        );

        return self::result(
            $errors,
            $message,
            showsSynced: $showsSynced,
            eventsSynced: $eventsSynced,
        );
    }

    /**
     * @return array{success: bool, shows_synced: int, events_synced: int, brands_synced: int, inventory_synced: int, errors: list<string>, message: string}
     */
    public static function pull_brands(): array
    {
        $failure = self::credentials_failure();
        if ($failure !== null) {
            return $failure;
        }

        $brandsSynced = 0;
        $errors = [];

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

        if ($brands['success']) {
            self::touch_last_pull();
        }

        $message = sprintf(
            'Pulled %d brands from Helmful.',
            $brandsSynced,
        );

        return self::result(
            $errors,
            $message,
            brandsSynced: $brandsSynced,
        );
    }

    /**
     * @return array{success: bool, shows_synced: int, events_synced: int, brands_synced: int, inventory_synced: int, errors: list<string>, message: string}
     */
    public static function pull_inventory(): array
    {
        $failure = self::credentials_failure();
        if ($failure !== null) {
            return $failure;
        }

        $inventorySynced = 0;
        $errors = [];

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

        if ($inventory['success']) {
            self::touch_last_pull();
        }

        $message = sprintf(
            'Pulled %d inventory items from Helmful.',
            $inventorySynced,
        );

        return self::result(
            $errors,
            $message,
            inventorySynced: $inventorySynced,
        );
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
     * @param  list<string>  $errors
     * @return array{success: bool, shows_synced: int, events_synced: int, brands_synced: int, inventory_synced: int, errors: list<string>, message: string}
     */
    private static function result(
        array $errors,
        string $message,
        int $showsSynced = 0,
        int $eventsSynced = 0,
        int $brandsSynced = 0,
        int $inventorySynced = 0,
    ): array {
        return [
            'success' => $errors === [],
            'shows_synced' => $showsSynced,
            'events_synced' => $eventsSynced,
            'brands_synced' => $brandsSynced,
            'inventory_synced' => $inventorySynced,
            'errors' => $errors,
            'message' => $errors === [] ? $message : $message.' Some items could not be synced.',
        ];
    }

    /**
     * @return array{success: bool, shows_synced: int, events_synced: int, brands_synced: int, inventory_synced: int, errors: list<string>, message: string}|null
     */
    private static function credentials_failure(): ?array
    {
        $domain = Helmful_Sync_Settings::tenant_domain();
        $apiKey = Helmful_Sync_Settings::helmful_api_key();

        if ($domain === '' || $apiKey === '') {
            return self::failure('Tenant domain and Helmful API key are required.', 'Helmful credentials are not configured.');
        }

        return null;
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

    private static function touch_last_pull(): void
    {
        update_option('helmful_sync_last_pull_at', gmdate('c'));
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
