<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Helmful_Sync_REST_API
{
    public static function register_routes(): void
    {
        register_rest_route('helmful/v1', '/api-key', [
            'methods' => 'POST',
            'callback' => [self::class, 'register_api_key'],
            'permission_callback' => [self::class, 'authorize_helmful'],
        ]);

        register_rest_route('helmful/v1', '/status', [
            'methods' => 'GET',
            'callback' => [self::class, 'status'],
            'permission_callback' => [self::class, 'authorize'],
        ]);

        register_rest_route('helmful/v1', '/sync/boat-show', [
            'methods' => 'POST',
            'callback' => [self::class, 'sync_boat_show'],
            'permission_callback' => [self::class, 'authorize'],
        ]);

        register_rest_route('helmful/v1', '/sync/boat-show-event', [
            'methods' => 'POST',
            'callback' => [self::class, 'sync_boat_show_event'],
            'permission_callback' => [self::class, 'authorize'],
        ]);

        register_rest_route('helmful/v1', '/sync/brand', [
            'methods' => 'POST',
            'callback' => [self::class, 'sync_brand'],
            'permission_callback' => [self::class, 'authorize'],
        ]);

        register_rest_route('helmful/v1', '/sync/inventory', [
            'methods' => 'POST',
            'callback' => [self::class, 'sync_inventory'],
            'permission_callback' => [self::class, 'authorize'],
        ]);

        register_rest_route('helmful/v1', '/sync/boat-show/(?P<uuid>[a-f0-9\-]+)', [
            'methods' => 'DELETE',
            'callback' => [self::class, 'delete_boat_show'],
            'permission_callback' => [self::class, 'authorize'],
        ]);

        register_rest_route('helmful/v1', '/sync/boat-show-event/(?P<uuid>[a-f0-9\-]+)', [
            'methods' => 'DELETE',
            'callback' => [self::class, 'delete_boat_show_event'],
            'permission_callback' => [self::class, 'authorize'],
        ]);
    }

    public static function authorize(WP_REST_Request $request): bool|WP_Error
    {
        $stored = Helmful_Sync_Settings::api_key();
        if ($stored === '') {
            return new WP_Error('helmful_not_configured', 'WordPress API key is not configured.', ['status' => 401]);
        }

        $provided = self::extract_bearer_token($request);
        if ($provided === null || ! hash_equals($stored, $provided)) {
            return new WP_Error('helmful_unauthorized', 'Unauthorized.', ['status' => 401]);
        }

        return true;
    }

    public static function authorize_helmful(WP_REST_Request $request): bool|WP_Error
    {
        $stored = Helmful_Sync_Settings::helmful_api_key();
        if ($stored === '') {
            return new WP_Error('helmful_helmful_not_configured', 'Helmful API key is not configured on WordPress.', ['status' => 401]);
        }

        $provided = self::extract_bearer_token($request);
        if ($provided === null || ! hash_equals($stored, $provided)) {
            return new WP_Error('helmful_unauthorized', 'Unauthorized.', ['status' => 401]);
        }

        return true;
    }

    public static function register_api_key(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $params = (array) $request->get_json_params();
        $apiKey = sanitize_text_field((string) ($params['api_key'] ?? ''));

        if (strlen($apiKey) < 16) {
            return new WP_Error('helmful_invalid_key', 'WordPress API key must be at least 16 characters.', ['status' => 422]);
        }

        Helmful_Sync_Settings::set_api_key($apiKey);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'WordPress API key saved.',
        ]);
    }

    public static function status(): WP_REST_Response
    {
        return new WP_REST_Response([
            'status' => 'ok',
            'message' => 'Helmful Sync is connected.',
            'site' => home_url(),
        ]);
    }

    public static function sync_boat_show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $postId = Helmful_Sync_Handler::upsert_show((array) $request->get_json_params());

            return new WP_REST_Response([
                'success' => true,
                'post_id' => $postId,
            ]);
        } catch (Throwable $e) {
            return new WP_Error('helmful_sync_failed', $e->getMessage(), ['status' => 422]);
        }
    }

    public static function sync_boat_show_event(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $postId = Helmful_Sync_Handler::upsert_event((array) $request->get_json_params());

            return new WP_REST_Response([
                'success' => true,
                'post_id' => $postId,
            ]);
        } catch (Throwable $e) {
            return new WP_Error('helmful_sync_failed', $e->getMessage(), ['status' => 422]);
        }
    }

    public static function sync_brand(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $postId = Helmful_Sync_Handler::upsert_brand((array) $request->get_json_params());

            return new WP_REST_Response([
                'success' => true,
                'term_id' => $postId,
                'post_id' => $postId,
            ]);
        } catch (Throwable $e) {
            return new WP_Error('helmful_sync_failed', $e->getMessage(), ['status' => 422]);
        }
    }

    public static function sync_inventory(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $postId = Helmful_Sync_Handler::upsert_inventory((array) $request->get_json_params());

            return new WP_REST_Response([
                'success' => true,
                'post_id' => $postId,
            ]);
        } catch (Throwable $e) {
            return new WP_Error('helmful_sync_failed', $e->getMessage(), ['status' => 422]);
        }
    }

    public static function delete_boat_show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $uuid = sanitize_text_field((string) $request->get_param('uuid'));
        Helmful_Sync_Handler::trash_by_uuid(Helmful_Sync_CPT::SHOW_POST_TYPE, $uuid);

        return new WP_REST_Response(['success' => true]);
    }

    public static function delete_boat_show_event(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $uuid = sanitize_text_field((string) $request->get_param('uuid'));
        Helmful_Sync_Handler::trash_by_uuid(Helmful_Sync_CPT::EVENT_POST_TYPE, $uuid);

        return new WP_REST_Response(['success' => true]);
    }

    private static function extract_bearer_token(WP_REST_Request $request): ?string
    {
        $header = $request->get_header('authorization');
        if (! is_string($header) || ! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token !== '' ? $token : null;
    }
}
