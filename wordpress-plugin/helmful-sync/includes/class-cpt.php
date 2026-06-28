<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Helmful_Sync_CPT
{
    public const SHOW_POST_TYPE = 'helmful_boat_show';

    /** Max 20 characters — WordPress truncates longer post type names. */
    public const EVENT_POST_TYPE = 'helmful_show_event';

    /** Taxonomy for synced boat makes — scoped to inventory only to avoid clashing with site-wide "brand" taxonomies. */
    public const BRAND_TAXONOMY = 'helmful_brand';

    public const INVENTORY_POST_TYPE = 'helmful_inventory';

    /** @var list<string> Legacy types created before the 20-char limit fix. */
    private const LEGACY_EVENT_POST_TYPES = [
        'helmful_boat_show_ev',
        'helmful_boat_show_event',
    ];

    /** @deprecated Brands are stored as terms; kept for one-time migration from older plugin versions. */
    private const LEGACY_BRAND_POST_TYPE = 'helmful_brand';

    public static function register(): void
    {
        register_post_type(self::SHOW_POST_TYPE, [
            'labels' => [
                'name' => __('Boat Shows', 'helmful-sync'),
                'singular_name' => __('Boat Show', 'helmful-sync'),
            ],
            'public' => true,
            'publicly_queryable' => true,
            'show_in_rest' => true,
            'has_archive' => false,
            'rewrite' => [
                'slug' => 'boat-shows',
                'with_front' => false,
            ],
            'supports' => ['title', 'editor', 'custom-fields'],
            'menu_icon' => 'dashicons-location-alt',
            'show_in_menu' => false,
        ]);

        register_post_type(self::EVENT_POST_TYPE, [
            'labels' => [
                'name' => __('Events', 'helmful-sync'),
                'singular_name' => __('Boat Show Event', 'helmful-sync'),
                'menu_name' => __('Events', 'helmful-sync'),
                'all_items' => __('Events', 'helmful-sync'),
                'add_new' => __('Add New', 'helmful-sync'),
                'add_new_item' => __('Add New Event', 'helmful-sync'),
                'edit_item' => __('Edit Event', 'helmful-sync'),
                'new_item' => __('New Event', 'helmful-sync'),
                'view_item' => __('View Event', 'helmful-sync'),
                'search_items' => __('Search Events', 'helmful-sync'),
                'not_found' => __('No events found.', 'helmful-sync'),
                'not_found_in_trash' => __('No events found in Trash.', 'helmful-sync'),
            ],
            'public' => true,
            'publicly_queryable' => true,
            'show_in_rest' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'has_archive' => false,
            'rewrite' => [
                'slug' => 'boat-show-event',
                'with_front' => false,
            ],
            'supports' => ['title', 'editor', 'custom-fields'],
        ]);

        register_post_type(self::INVENTORY_POST_TYPE, [
            'labels' => [
                'name' => __('Inventory', 'helmful-sync'),
                'singular_name' => __('Inventory Item', 'helmful-sync'),
                'menu_name' => __('Inventory', 'helmful-sync'),
                'all_items' => __('Inventory', 'helmful-sync'),
            ],
            'public' => true,
            'publicly_queryable' => true,
            'show_in_rest' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'has_archive' => false,
            'rewrite' => [
                'slug' => 'inventory',
                'with_front' => false,
            ],
            'supports' => ['title', 'editor', 'custom-fields'],
        ]);

        register_taxonomy(self::BRAND_TAXONOMY, [self::INVENTORY_POST_TYPE], [
            'labels' => [
                'name' => __('Brands', 'helmful-sync'),
                'singular_name' => __('Brand', 'helmful-sync'),
                'menu_name' => __('Brands', 'helmful-sync'),
                'all_items' => __('Brands', 'helmful-sync'),
                'edit_item' => __('Edit Brand', 'helmful-sync'),
                'view_item' => __('View Brand', 'helmful-sync'),
                'search_items' => __('Search Brands', 'helmful-sync'),
                'not_found' => __('No brands found.', 'helmful-sync'),
            ],
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_admin_column' => true,
            'show_in_menu' => false,
            'hierarchical' => false,
            'rewrite' => [
                'slug' => 'brand',
                'with_front' => false,
            ],
        ]);

        self::migrate_legacy_event_posts();
        self::migrate_brand_posts_to_taxonomy();
    }

    public static function brands_admin_menu_slug(): string
    {
        return 'edit-tags.php?taxonomy='.self::BRAND_TAXONOMY.'&post_type='.self::INVENTORY_POST_TYPE;
    }

    public static function brands_admin_url(): string
    {
        return admin_url(self::brands_admin_menu_slug());
    }

    private static function migrate_legacy_event_posts(): void
    {
        if (get_option('helmful_sync_event_post_type_migrated', '') === '1') {
            return;
        }

        global $wpdb;

        $placeholders = implode(', ', array_fill(0, count(self::LEGACY_EVENT_POST_TYPES), '%s'));
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type IN ({$placeholders})",
            array_merge([self::EVENT_POST_TYPE], self::LEGACY_EVENT_POST_TYPES),
        ));

        update_option('helmful_sync_event_post_type_migrated', '1', false);
    }

    private static function migrate_brand_posts_to_taxonomy(): void
    {
        if (get_option('helmful_sync_brand_taxonomy_migrated', '') === '1') {
            return;
        }

        $brandPosts = get_posts([
            'post_type' => self::LEGACY_BRAND_POST_TYPE,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'numberposts' => -1,
        ]);

        $postIdToTermId = [];

        foreach ($brandPosts as $brandPost) {
            if (! $brandPost instanceof WP_Post) {
                continue;
            }

            $uuid = (string) get_post_meta($brandPost->ID, 'helmful_uuid', true);
            if ($uuid === '') {
                continue;
            }

            $termId = Helmful_Sync_Handler::term_id_for_uuid(self::BRAND_TAXONOMY, $uuid);
            $slug = (string) get_post_meta($brandPost->ID, 'helmful_slug', true);
            $termSlug = $slug !== '' ? sanitize_title($slug) : $brandPost->post_name;

            if ($termId <= 0) {
                $inserted = wp_insert_term($brandPost->post_title, self::BRAND_TAXONOMY, [
                    'slug' => $termSlug !== '' ? $termSlug : sanitize_title($brandPost->post_title),
                ]);

                if (is_wp_error($inserted)) {
                    continue;
                }

                $termId = (int) $inserted['term_id'];
            } else {
                $updateArgs = ['name' => $brandPost->post_title];
                if ($termSlug !== '') {
                    $updateArgs['slug'] = $termSlug;
                }
                wp_update_term($termId, self::BRAND_TAXONOMY, $updateArgs);
            }

            Helmful_Sync_Handler::copy_brand_meta_from_post($brandPost->ID, $termId);
            $postIdToTermId[$brandPost->ID] = $termId;
            wp_trash_post($brandPost->ID);
        }

        $inventoryPosts = get_posts([
            'post_type' => self::INVENTORY_POST_TYPE,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'numberposts' => -1,
            'fields' => 'ids',
        ]);

        foreach ($inventoryPosts as $inventoryPostId) {
            $inventoryPostId = (int) $inventoryPostId;
            $brandPostId = (int) get_post_meta($inventoryPostId, 'helmful_brand_post_id', true);
            $brandUuid = (string) get_post_meta($inventoryPostId, 'helmful_brand_uuid', true);

            $termId = $brandPostId > 0 && isset($postIdToTermId[$brandPostId])
                ? $postIdToTermId[$brandPostId]
                : ($brandUuid !== '' ? Helmful_Sync_Handler::term_id_for_uuid(self::BRAND_TAXONOMY, $brandUuid) : 0);

            if ($termId > 0) {
                wp_set_object_terms($inventoryPostId, [$termId], self::BRAND_TAXONOMY, false);
                update_post_meta($inventoryPostId, 'helmful_brand_term_id', (string) $termId);
            }
        }

        update_option('helmful_sync_brand_taxonomy_migrated', '1', false);
    }
}
