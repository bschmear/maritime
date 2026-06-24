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

    /** @var list<string> Legacy types created before the 20-char limit fix. */
    private const LEGACY_EVENT_POST_TYPES = [
        'helmful_boat_show_ev',
        'helmful_boat_show_event',
    ];

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

        self::migrate_legacy_event_posts();
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
}
