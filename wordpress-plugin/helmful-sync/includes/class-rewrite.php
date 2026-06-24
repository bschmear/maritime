<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Helmful_Sync_Rewrite
{
    public static function init(): void
    {
        add_filter('request', [self::class, 'prefer_listing_page']);
        add_action('init', [self::class, 'maybe_flush_rewrites'], 20);
        add_action('init', [self::class, 'backfill_event_show_links'], 25);
        add_action('init', [self::class, 'repair_event_show_links'], 26);
    }

    /**
     * @param  array<string, mixed>  $queryVars
     * @return array<string, mixed>
     */
    public static function prefer_listing_page(array $queryVars): array
    {
        if (is_admin()) {
            return $queryVars;
        }

        $isShowArchiveRequest = isset($queryVars['post_type'])
            && $queryVars['post_type'] === Helmful_Sync_CPT::SHOW_POST_TYPE
            && empty($queryVars['name'])
            && empty($queryVars[Helmful_Sync_CPT::SHOW_POST_TYPE]);

        if (! $isShowArchiveRequest) {
            return $queryVars;
        }

        $page = Helmful_Sync_Display::listing_page();
        if (! $page instanceof WP_Post) {
            return $queryVars;
        }

        unset($queryVars['post_type']);

        $queryVars['page_id'] = (string) $page->ID;
        $queryVars['pagename'] = $page->post_name;

        return $queryVars;
    }

    public static function maybe_flush_rewrites(): void
    {
        $stored = (string) get_option('helmful_sync_rewrite_version', '');
        if ($stored === HELMFUL_SYNC_VERSION) {
            return;
        }

        flush_rewrite_rules(false);
        update_option('helmful_sync_rewrite_version', HELMFUL_SYNC_VERSION, false);
    }

    public static function backfill_event_show_links(): void
    {
        if (get_option('helmful_sync_backfilled_show_post_ids') === HELMFUL_SYNC_VERSION) {
            return;
        }

        $events = get_posts([
            'post_type' => Helmful_Sync_CPT::EVENT_POST_TYPE,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        foreach ($events as $eventId) {
            $eventId = (int) $eventId;
            $existing = (string) get_post_meta($eventId, 'helmful_boat_show_post_id', true);
            if ($existing !== '' && (int) $existing > 0) {
                continue;
            }

            $showUuid = (string) get_post_meta($eventId, 'helmful_boat_show_uuid', true);
            if ($showUuid === '') {
                continue;
            }

            $showPostId = Helmful_Sync_Handler::post_id_for_uuid(Helmful_Sync_CPT::SHOW_POST_TYPE, $showUuid);
            if ($showPostId > 0) {
                update_post_meta($eventId, 'helmful_boat_show_post_id', (string) $showPostId);
            }
        }

        update_option('helmful_sync_backfilled_show_post_ids', HELMFUL_SYNC_VERSION, false);
    }

    public static function repair_event_show_links(): void
    {
        if (get_option('helmful_sync_repaired_event_links', '') === HELMFUL_SYNC_VERSION) {
            return;
        }

        $events = get_posts([
            'post_type' => Helmful_Sync_CPT::EVENT_POST_TYPE,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        foreach ($events as $eventId) {
            $eventId = (int) $eventId;
            $showUuid = (string) get_post_meta($eventId, 'helmful_boat_show_uuid', true);
            if ($showUuid === '') {
                continue;
            }

            $showPostId = Helmful_Sync_Handler::post_id_for_uuid(Helmful_Sync_CPT::SHOW_POST_TYPE, $showUuid);
            if ($showPostId <= 0) {
                continue;
            }

            $existing = (string) get_post_meta($eventId, 'helmful_boat_show_post_id', true);
            if ($existing === (string) $showPostId) {
                continue;
            }

            update_post_meta($eventId, 'helmful_boat_show_post_id', (string) $showPostId);
        }

        update_option('helmful_sync_repaired_event_links', HELMFUL_SYNC_VERSION, false);
    }
}
