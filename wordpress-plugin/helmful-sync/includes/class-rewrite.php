<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Helmful_Sync_Rewrite
{
    public static function init(): void
    {
        add_filter('query_vars', [self::class, 'register_query_vars']);
        add_action('init', [self::class, 'register_brand_rewrites'], 11);
        add_filter('request', [self::class, 'prefer_listing_page']);
        add_filter('request', [self::class, 'resolve_brand_inventory_page']);
        add_action('template_redirect', [self::class, 'redirect_legacy_brand_filter']);
        add_action('template_redirect', [self::class, 'redirect_brand_taxonomy_archives']);
        add_filter('document_title_parts', [self::class, 'brand_page_title_parts']);
        add_filter('rel_canonical', [self::class, 'brand_page_canonical']);
        add_filter('the_title', [self::class, 'brand_inventory_page_title'], 10, 2);
        add_filter('render_block', [self::class, 'brand_inventory_post_title_block'], 10, 2);
        add_action('init', [self::class, 'maybe_flush_rewrites'], 20);
        add_action('init', [self::class, 'backfill_event_show_links'], 25);
        add_action('init', [self::class, 'repair_event_show_links'], 26);
    }

    /**
     * @param  list<string>  $queryVars
     * @return list<string>
     */
    public static function register_query_vars(array $queryVars): array
    {
        $queryVars[] = 'helmful_brand_slug';

        return $queryVars;
    }

    public static function register_brand_rewrites(): void
    {
        $basePath = Helmful_Sync_Display::brands_page_path();
        if ($basePath === '') {
            return;
        }

        $reserved = array_map(
            static fn (string $segment): string => preg_quote($segment, '/').'(?:/|$)',
            Helmful_Sync_Display::reserved_brand_route_segments(),
        );
        $reservedPattern = $reserved !== [] ? '(?!'.implode('|', $reserved).')' : '';

        add_rewrite_rule(
            '^'.preg_quote($basePath, '/').'/'.$reservedPattern.'([^/]+)/?$',
            'index.php?helmful_brand_slug=$matches[1]',
            'top',
        );
    }

    /**
     * @param  array<string, mixed>  $queryVars
     * @return array<string, mixed>
     */
    public static function resolve_brand_inventory_page(array $queryVars): array
    {
        if (is_admin()) {
            return $queryVars;
        }

        $slug = isset($queryVars['helmful_brand_slug'])
            ? sanitize_title((string) $queryVars['helmful_brand_slug'])
            : '';

        if ($slug === '') {
            return $queryVars;
        }

        if (! Helmful_Sync_Display::is_valid_brand_slug($slug)) {
            $queryVars['error'] = '404';

            return $queryVars;
        }

        $inventoryPage = Helmful_Sync_Display::inventory_page();
        if (! $inventoryPage instanceof WP_Post) {
            $queryVars['error'] = '404';

            return $queryVars;
        }

        unset($queryVars['pagename'], $queryVars['name'], $queryVars['page']);
        $queryVars['page_id'] = (string) $inventoryPage->ID;
        $queryVars['helmful_brand_slug'] = $slug;

        return $queryVars;
    }

    public static function redirect_legacy_brand_filter(): void
    {
        if (! isset($_GET['helmful_brand'])) {
            return;
        }

        $inventoryPage = Helmful_Sync_Display::inventory_page();
        if (! $inventoryPage instanceof WP_Post || ! is_page($inventoryPage->ID)) {
            return;
        }

        $slug = sanitize_title(wp_unslash((string) $_GET['helmful_brand']));
        if ($slug === '' || ! Helmful_Sync_Display::is_valid_brand_slug($slug)) {
            return;
        }

        $target = Helmful_Sync_Display::brand_page_url($slug);

        if (isset($_GET['helmful_page'])) {
            $page = max(1, (int) $_GET['helmful_page']);
            if ($page > 1) {
                $target = add_query_arg('helmful_page', (string) $page, $target);
            }
        }

        wp_safe_redirect($target, 301);
        exit;
    }

    public static function redirect_brand_taxonomy_archives(): void
    {
        if (! is_tax(Helmful_Sync_CPT::BRAND_TAXONOMY)) {
            return;
        }

        $term = get_queried_object();
        if (! $term instanceof WP_Term) {
            return;
        }

        $storedSlug = (string) get_term_meta($term->term_id, 'helmful_slug', true);
        $slug = $storedSlug !== '' ? $storedSlug : $term->slug;

        if ($slug === '' || ! Helmful_Sync_Display::is_valid_brand_slug($slug)) {
            return;
        }

        wp_safe_redirect(Helmful_Sync_Display::brand_page_url($slug), 301);
        exit;
    }

    /**
     * @param  array<string, string>  $parts
     * @return array<string, string>
     */
    public static function brand_page_title_parts(array $parts): array
    {
        $slug = Helmful_Sync_Display::current_brand_slug();
        if ($slug === '') {
            return $parts;
        }

        $label = Helmful_Sync_Display::inventory_brand_label_by_slug($slug);
        if ($label !== '') {
            $parts['title'] = $label;
        }

        return $parts;
    }

    public static function brand_inventory_page_title(string $title, int $postId = 0): string
    {
        if (is_admin()) {
            return $title;
        }

        $brandTitle = self::current_brand_page_heading();
        if ($brandTitle === '') {
            return $title;
        }

        $inventoryPage = Helmful_Sync_Display::inventory_page();
        if (! $inventoryPage instanceof WP_Post) {
            return $title;
        }

        $inventoryPageId = (int) $inventoryPage->ID;
        $targetPostId = $postId > 0 ? $postId : (int) get_queried_object_id();

        if ($targetPostId !== $inventoryPageId) {
            return $title;
        }

        return $brandTitle;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    public static function brand_inventory_post_title_block(string $blockContent, array $block): string
    {
        if (($block['blockName'] ?? '') !== 'core/post-title') {
            return $blockContent;
        }

        $brandTitle = self::current_brand_page_heading();
        if ($brandTitle === '') {
            return $blockContent;
        }

        $inventoryPage = Helmful_Sync_Display::inventory_page();
        if (! $inventoryPage instanceof WP_Post || get_queried_object_id() !== (int) $inventoryPage->ID) {
            return $blockContent;
        }

        $replaced = preg_replace(
            '/(<[^>]+wp-block-post-title[^>]*>)(.*?)(<\/[^>]+>)/s',
            '$1'.esc_html($brandTitle).'$3',
            $blockContent,
            1,
        );

        return is_string($replaced) ? $replaced : $blockContent;
    }

    private static function current_brand_page_heading(): string
    {
        $slug = Helmful_Sync_Display::current_brand_slug();
        if ($slug === '') {
            return '';
        }

        return Helmful_Sync_Display::inventory_brand_label_by_slug($slug);
    }

    public static function brand_page_canonical(string $canonical): string
    {
        $slug = Helmful_Sync_Display::current_brand_slug();
        if ($slug === '') {
            return $canonical;
        }

        $page = isset($_GET['helmful_page']) ? max(1, (int) $_GET['helmful_page']) : 1;
        $url = Helmful_Sync_Display::brand_page_url($slug);

        if ($page > 1) {
            $url = add_query_arg('helmful_page', (string) $page, $url);
        }

        return $url;
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
