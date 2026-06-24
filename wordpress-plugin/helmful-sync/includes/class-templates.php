<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Helmful_Sync_Templates
{
    public static function init(): void
    {
        add_filter('single_template', [self::class, 'load_single_template']);
        add_filter('template_include', [self::class, 'load_template_include'], 99);
        add_filter('body_class', [self::class, 'body_class']);
        add_action('template_redirect', [self::class, 'redirect_archives']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_action('wp', [self::class, 'enqueue_listing_assets']);
        add_filter('the_content', [self::class, 'filter_singular_content'], 1);
    }

    public static function filter_singular_content(string $content): string
    {
        if (! is_singular([Helmful_Sync_CPT::SHOW_POST_TYPE, Helmful_Sync_CPT::EVENT_POST_TYPE])) {
            return $content;
        }

        if (in_the_loop() && is_main_query()) {
            return '';
        }

        return $content;
    }

    /**
     * @param  list<string>  $classes
     * @return list<string>
     */
    public static function body_class(array $classes): array
    {
        if (! is_singular([Helmful_Sync_CPT::SHOW_POST_TYPE, Helmful_Sync_CPT::EVENT_POST_TYPE])) {
            return $classes;
        }

        $classes[] = 'helmful-sync-single';
        $classes[] = 'helmful-sync-no-sidebar';

        return $classes;
    }

    public static function load_single_template(string $template): string
    {
        if (! is_singular()) {
            return $template;
        }

        $post = get_queried_object();
        if (! $post instanceof WP_Post) {
            return $template;
        }

        $map = [
            Helmful_Sync_CPT::SHOW_POST_TYPE => 'single-helmful-boat-show.php',
            Helmful_Sync_CPT::EVENT_POST_TYPE => 'single-helmful-boat-show-event.php',
        ];

        $file = $map[$post->post_type] ?? null;
        if ($file === null) {
            return $template;
        }

        $pluginTemplate = HELMFUL_SYNC_PATH.'templates/'.$file;

        return file_exists($pluginTemplate) ? $pluginTemplate : $template;
    }

    public static function load_template_include(string $template): string
    {
        if (! is_singular([Helmful_Sync_CPT::SHOW_POST_TYPE, Helmful_Sync_CPT::EVENT_POST_TYPE])) {
            return $template;
        }

        $post = get_queried_object();
        if (! $post instanceof WP_Post) {
            return $template;
        }

        $map = [
            Helmful_Sync_CPT::SHOW_POST_TYPE => 'single-helmful-boat-show.php',
            Helmful_Sync_CPT::EVENT_POST_TYPE => 'single-helmful-boat-show-event.php',
        ];

        $file = $map[$post->post_type] ?? null;
        if ($file === null) {
            return $template;
        }

        $pluginTemplate = HELMFUL_SYNC_PATH.'templates/'.$file;

        return file_exists($pluginTemplate) ? $pluginTemplate : $template;
    }

    public static function enqueue_listing_assets(): void
    {
        if (! is_singular('page')) {
            return;
        }

        $post = get_queried_object();
        if (! $post instanceof WP_Post) {
            return;
        }

        if (
            ! has_shortcode($post->post_content, 'helmful_boat_shows')
            && ! has_shortcode($post->post_content, 'helmful_boat_show_events')
        ) {
            return;
        }

        Helmful_Sync_Display::enqueue_assets();
    }

    public static function redirect_archives(): void
    {
        if (is_post_type_archive(Helmful_Sync_CPT::SHOW_POST_TYPE)) {
            wp_safe_redirect(Helmful_Sync_Display::listing_page_url());
            exit;
        }

        if (is_post_type_archive(Helmful_Sync_CPT::EVENT_POST_TYPE)) {
            wp_safe_redirect(Helmful_Sync_Display::listing_page_url());
            exit;
        }
    }

    public static function enqueue_assets(): void
    {
        if (! is_singular([Helmful_Sync_CPT::SHOW_POST_TYPE, Helmful_Sync_CPT::EVENT_POST_TYPE])) {
            return;
        }

        Helmful_Sync_Display::enqueue_assets();

        wp_enqueue_style(
            'helmful-sync-templates',
            HELMFUL_SYNC_URL.'assets/css/templates.css',
            ['helmful-sync-display'],
            HELMFUL_SYNC_VERSION,
        );

        wp_enqueue_script(
            'helmful-sync-single',
            HELMFUL_SYNC_URL.'assets/js/single-countdown.js',
            [],
            HELMFUL_SYNC_VERSION,
            true,
        );
    }
}
