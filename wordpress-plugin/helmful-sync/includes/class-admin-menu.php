<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Helmful_Sync_Admin_Menu
{
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_event_submenu'], 20);
        add_action('admin_menu', [self::class, 'reorder_boat_show_submenu'], 999);
        add_filter('parent_file', [self::class, 'highlight_boat_shows_parent']);
        add_filter('submenu_file', [self::class, 'highlight_events_submenu']);
    }

    public static function register_event_submenu(): void
    {
        $eventType = get_post_type_object(Helmful_Sync_CPT::EVENT_POST_TYPE);
        if ($eventType === null) {
            return;
        }

        $parentSlug = 'edit.php?post_type='.Helmful_Sync_CPT::SHOW_POST_TYPE;

        add_submenu_page(
            $parentSlug,
            $eventType->labels->name,
            $eventType->labels->menu_name,
            $eventType->cap->edit_posts,
            'edit.php?post_type='.Helmful_Sync_CPT::EVENT_POST_TYPE,
        );
    }

    public static function reorder_boat_show_submenu(): void
    {
        global $submenu;

        $parentSlug = 'edit.php?post_type='.Helmful_Sync_CPT::SHOW_POST_TYPE;
        $eventsSlug = 'edit.php?post_type='.Helmful_Sync_CPT::EVENT_POST_TYPE;

        if (! isset($submenu[$parentSlug]) || ! is_array($submenu[$parentSlug])) {
            return;
        }

        $eventsItem = null;
        $remaining = [];

        foreach ($submenu[$parentSlug] as $item) {
            if (($item[2] ?? '') === $eventsSlug) {
                $eventsItem = $item;

                continue;
            }

            $remaining[] = $item;
        }

        if ($eventsItem === null) {
            return;
        }

        $ordered = [];
        $inserted = false;

        foreach ($remaining as $item) {
            $ordered[] = $item;

            if (! $inserted && str_contains((string) ($item[2] ?? ''), 'post-new.php?post_type='.Helmful_Sync_CPT::SHOW_POST_TYPE)) {
                $ordered[] = $eventsItem;
                $inserted = true;
            }
        }

        if (! $inserted) {
            $ordered[] = $eventsItem;
        }

        $submenu[$parentSlug] = $ordered;
    }

    public static function highlight_boat_shows_parent(?string $parentFile): ?string
    {
        global $pagenow, $typenow;

        if ($parentFile === null) {
            return null;
        }

        if ($pagenow !== 'edit.php' && $pagenow !== 'post.php' && $pagenow !== 'post-new.php') {
            return $parentFile;
        }

        if ($typenow === Helmful_Sync_CPT::EVENT_POST_TYPE) {
            return 'edit.php?post_type='.Helmful_Sync_CPT::SHOW_POST_TYPE;
        }

        return $parentFile;
    }

    public static function highlight_events_submenu(?string $submenuFile): ?string
    {
        global $pagenow, $typenow;

        if ($submenuFile === null) {
            return null;
        }

        if ($pagenow !== 'edit.php' && $pagenow !== 'post.php' && $pagenow !== 'post-new.php') {
            return $submenuFile;
        }

        if ($typenow === Helmful_Sync_CPT::EVENT_POST_TYPE) {
            return 'edit.php?post_type='.Helmful_Sync_CPT::EVENT_POST_TYPE;
        }

        return $submenuFile;
    }
}
