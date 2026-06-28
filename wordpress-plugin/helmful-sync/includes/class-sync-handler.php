<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Helmful_Sync_Handler
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function upsert_show(array $payload): int
    {
        $uuid = self::string($payload['uuid'] ?? null);
        if ($uuid === '') {
            throw new InvalidArgumentException('Boat show uuid is required.');
        }

        $postId = self::find_post_id(Helmful_Sync_CPT::SHOW_POST_TYPE, $uuid);
        $slug = self::string($payload['slug'] ?? '');
        $postData = [
            'post_type' => Helmful_Sync_CPT::SHOW_POST_TYPE,
            'post_title' => self::string($payload['display_name'] ?? 'Boat Show'),
            'post_content' => self::string($payload['description'] ?? ''),
            'post_status' => 'publish',
        ];

        if ($slug !== '') {
            $postData['post_name'] = sanitize_title($slug);
        }

        if ($postId > 0) {
            $postData['ID'] = $postId;
            $postId = (int) wp_update_post($postData, true);
        } else {
            $postId = (int) wp_insert_post($postData, true);
        }

        if (is_wp_error($postId)) {
            throw new RuntimeException($postId->get_error_message());
        }

        self::save_meta($postId, $payload, [
            'helmful_uuid' => $uuid,
            'helmful_slug' => self::string($payload['slug'] ?? ''),
            'helmful_website' => self::string($payload['website'] ?? ''),
            'helmful_logo_url' => esc_url_raw(self::string($payload['logo_url'] ?? '')),
            'helmful_app_show_url' => self::string($payload['app_show_url'] ?? ''),
            'helmful_updated_at' => self::string($payload['updated_at'] ?? ''),
            'helmful_last_synced_at' => gmdate('c'),
        ]);

        return $postId;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function upsert_event(array $payload): int
    {
        $uuid = self::string($payload['uuid'] ?? null);
        if ($uuid === '') {
            throw new InvalidArgumentException('Boat show event uuid is required.');
        }

        $postId = self::find_post_id(Helmful_Sync_CPT::EVENT_POST_TYPE, $uuid);
        $active = (bool) ($payload['active'] ?? true);
        $displayName = self::string($payload['display_name'] ?? 'Boat Show Event');
        $year = self::string($payload['year'] ?? '');
        $postData = [
            'post_type' => Helmful_Sync_CPT::EVENT_POST_TYPE,
            'post_title' => $displayName,
            'post_content' => self::build_event_content($payload),
            'post_status' => $active ? 'publish' : 'draft',
            'post_name' => sanitize_title($year !== '' ? $displayName.'-'.$year : $displayName),
        ];

        if ($postId > 0) {
            $postData['ID'] = $postId;
            $postId = (int) wp_update_post($postData, true);
        } else {
            $postId = (int) wp_insert_post($postData, true);
        }

        if (is_wp_error($postId)) {
            throw new RuntimeException($postId->get_error_message());
        }

        $showUuid = self::string($payload['boat_show_uuid'] ?? '');
        $showPostId = $showUuid !== ''
            ? self::post_id_for_uuid(Helmful_Sync_CPT::SHOW_POST_TYPE, $showUuid)
            : 0;

        self::save_meta($postId, $payload, [
            'helmful_uuid' => $uuid,
            'helmful_boat_show_uuid' => $showUuid,
            'helmful_boat_show_post_id' => $showPostId > 0 ? (string) $showPostId : '',
            'helmful_year' => self::string($payload['year'] ?? ''),
            'helmful_starts_at' => self::string($payload['starts_at'] ?? ''),
            'helmful_ends_at' => self::string($payload['ends_at'] ?? ''),
            'helmful_venue' => self::string($payload['venue'] ?? ''),
            'helmful_address_line_1' => self::string($payload['address_line_1'] ?? ''),
            'helmful_address_line_2' => self::string($payload['address_line_2'] ?? ''),
            'helmful_city' => self::string($payload['city'] ?? ''),
            'helmful_state' => self::string($payload['state'] ?? ''),
            'helmful_country' => self::string($payload['country'] ?? ''),
            'helmful_postal_code' => self::string($payload['postal_code'] ?? ''),
            'helmful_latitude' => self::string($payload['latitude'] ?? ''),
            'helmful_longitude' => self::string($payload['longitude'] ?? ''),
            'helmful_booth' => self::string($payload['booth'] ?? ''),
            'helmful_active' => $active ? '1' : '0',
            'helmful_logo_url' => esc_url_raw(self::string($payload['logo_url'] ?? '')),
            'helmful_app_event_url' => self::string($payload['app_event_url'] ?? ''),
            'helmful_public_event_url' => self::string($payload['public_event_url'] ?? ''),
            'helmful_updated_at' => self::string($payload['updated_at'] ?? ''),
            'helmful_last_synced_at' => gmdate('c'),
        ]);

        return $postId;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function upsert_brand(array $payload): int
    {
        $uuid = self::string($payload['uuid'] ?? null);
        if ($uuid === '') {
            throw new InvalidArgumentException('Brand uuid is required.');
        }

        $termId = self::term_id_for_uuid(Helmful_Sync_CPT::BRAND_TAXONOMY, $uuid);
        $active = (bool) ($payload['active'] ?? true);
        $slug = self::string($payload['slug'] ?? '');
        $name = self::string($payload['display_name'] ?? 'Brand');
        $termSlug = $slug !== '' ? sanitize_title($slug) : sanitize_title($name);

        $termArgs = [
            'name' => $name,
            'slug' => $termSlug,
        ];

        if ($termId > 0) {
            $result = wp_update_term($termId, Helmful_Sync_CPT::BRAND_TAXONOMY, $termArgs);
        } else {
            $result = wp_insert_term($name, Helmful_Sync_CPT::BRAND_TAXONOMY, [
                'slug' => $termSlug,
            ]);
        }

        if (is_wp_error($result)) {
            throw new RuntimeException($result->get_error_message());
        }

        $termId = $termId > 0 ? $termId : (int) ($result['term_id'] ?? 0);
        if ($termId <= 0) {
            throw new RuntimeException('Unable to save brand term.');
        }

        self::save_term_meta($termId, [
            'helmful_uuid' => $uuid,
            'helmful_slug' => $slug !== '' ? $slug : $termSlug,
            'helmful_brand_key' => self::string($payload['brand_key'] ?? ''),
            'helmful_logo_url' => esc_url_raw(self::string($payload['logo_url'] ?? '')),
            'helmful_app_brand_url' => self::string($payload['app_brand_url'] ?? ''),
            'helmful_active' => $active ? '1' : '0',
            'helmful_updated_at' => self::string($payload['updated_at'] ?? ''),
            'helmful_last_synced_at' => gmdate('c'),
        ]);

        return $termId;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function upsert_inventory(array $payload): int
    {
        $uuid = self::string($payload['uuid'] ?? null);
        if ($uuid === '') {
            throw new InvalidArgumentException('Inventory uuid is required.');
        }

        $postId = self::find_post_id(Helmful_Sync_CPT::INVENTORY_POST_TYPE, $uuid);
        $active = (bool) ($payload['active'] ?? true);
        $slug = self::string($payload['slug'] ?? '');
        $description = self::string($payload['description'] ?? '');
        $postData = [
            'post_type' => Helmful_Sync_CPT::INVENTORY_POST_TYPE,
            'post_title' => self::string($payload['display_name'] ?? 'Inventory Item'),
            'post_content' => $description,
            'post_status' => $active ? 'publish' : 'draft',
        ];

        if ($slug !== '') {
            $postData['post_name'] = sanitize_title($slug);
        }

        if ($postId > 0) {
            $postData['ID'] = $postId;
            $postId = (int) wp_update_post($postData, true);
        } else {
            $postId = (int) wp_insert_post($postData, true);
        }

        if (is_wp_error($postId)) {
            throw new RuntimeException($postId->get_error_message());
        }

        $brandUuid = self::string($payload['brand_uuid'] ?? '');
        $brandTermId = $brandUuid !== ''
            ? self::term_id_for_uuid(Helmful_Sync_CPT::BRAND_TAXONOMY, $brandUuid)
            : 0;

        self::save_meta($postId, $payload, [
            'helmful_uuid' => $uuid,
            'helmful_slug' => $slug,
            'helmful_brand_uuid' => $brandUuid,
            'helmful_brand_term_id' => $brandTermId > 0 ? (string) $brandTermId : '',
            'helmful_brand_name' => self::string($payload['brand_name'] ?? ''),
            'helmful_brand_slug' => self::string($payload['brand_slug'] ?? ''),
            'helmful_model' => self::string($payload['model'] ?? ''),
            'helmful_year' => self::string($payload['year'] ?? ''),
            'helmful_length' => self::string($payload['length'] ?? ''),
            'helmful_default_price' => self::string($payload['default_price'] ?? ''),
            'helmful_type' => self::string($payload['type'] ?? ''),
            'helmful_active' => $active ? '1' : '0',
            'helmful_app_asset_url' => self::string($payload['app_asset_url'] ?? ''),
            'helmful_updated_at' => self::string($payload['updated_at'] ?? ''),
            'helmful_last_synced_at' => gmdate('c'),
        ]);

        if ($brandTermId > 0) {
            wp_set_object_terms($postId, [$brandTermId], Helmful_Sync_CPT::BRAND_TAXONOMY, false);
        } else {
            wp_set_object_terms($postId, [], Helmful_Sync_CPT::BRAND_TAXONOMY, false);
        }

        return $postId;
    }

    public static function trash_by_uuid(string $postType, string $uuid): bool
    {
        $postId = self::find_post_id($postType, $uuid);
        if ($postId <= 0) {
            return false;
        }

        return (bool) wp_trash_post($postId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function build_event_content(array $payload): string
    {
        $parts = array_filter([
            self::string($payload['venue'] ?? ''),
            trim(implode(', ', array_filter([
                self::string($payload['city'] ?? ''),
                self::string($payload['state'] ?? ''),
            ]))),
            self::string($payload['booth'] ?? '') !== '' ? 'Booth: '.self::string($payload['booth'] ?? '') : '',
        ]);

        return implode("\n", $parts);
    }

    private static function find_post_id(string $postType, string $uuid): int
    {
        return self::post_id_for_uuid($postType, $uuid);
    }

    public static function post_id_for_uuid(string $postType, string $uuid): int
    {
        if ($uuid === '') {
            return 0;
        }

        $posts = get_posts([
            'post_type' => $postType,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'meta_key' => 'helmful_uuid',
            'meta_value' => $uuid,
            'numberposts' => 1,
            'fields' => 'ids',
        ]);

        return isset($posts[0]) ? (int) $posts[0] : 0;
    }

    public static function term_id_for_uuid(string $taxonomy, string $uuid): int
    {
        if ($uuid === '') {
            return 0;
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'number' => 1,
            'meta_query' => [
                [
                    'key' => 'helmful_uuid',
                    'value' => $uuid,
                ],
            ],
        ]);

        if (! is_array($terms) || $terms === [] || ! $terms[0] instanceof WP_Term) {
            return 0;
        }

        return (int) $terms[0]->term_id;
    }

    public static function copy_brand_meta_from_post(int $postId, int $termId): void
    {
        $keys = [
            'helmful_uuid',
            'helmful_slug',
            'helmful_brand_key',
            'helmful_logo_url',
            'helmful_app_brand_url',
            'helmful_active',
            'helmful_updated_at',
            'helmful_last_synced_at',
        ];

        foreach ($keys as $key) {
            $value = get_post_meta($postId, $key, true);
            if ($value !== '' && $value !== false) {
                update_term_meta($termId, $key, $value);
            }
        }
    }

    /**
     * @param  array<string, string>  $meta
     */
    private static function save_term_meta(int $termId, array $meta): void
    {
        foreach ($meta as $key => $value) {
            update_term_meta($termId, $key, $value);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $meta
     */
    private static function save_meta(int $postId, array $payload, array $meta): void
    {
        foreach ($meta as $key => $value) {
            update_post_meta($postId, $key, $value);
        }
    }

    private static function string(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
