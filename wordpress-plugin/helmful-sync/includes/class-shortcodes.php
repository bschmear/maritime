<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Helmful_Sync_Shortcodes
{
    public static function init(): void
    {
        add_shortcode('helmful_boat_shows', [self::class, 'render_boat_shows']);
        add_shortcode('helmful_boat_show_events', [self::class, 'render_boat_show_events']);
        add_shortcode('helmful_brands', [self::class, 'render_brands']);
        add_shortcode('helmful_inventory', [self::class, 'render_inventory']);
    }

    /**
     * @param  array<string, string>|string  $atts
     */
    public static function render_boat_shows($atts = []): string
    {
        return Helmful_Sync_Display::render_shortcode_boat_shows($atts);
    }

    /**
     * @param  array<string, string>|string  $atts
     */
    public static function render_boat_show_events($atts = []): string
    {
        return Helmful_Sync_Display::render_shortcode_events($atts);
    }

    /**
     * @param  array<string, string>|string  $atts
     */
    public static function render_brands($atts = []): string
    {
        return Helmful_Sync_Display::render_shortcode_brands($atts);
    }

    /**
     * @param  array<string, string>|string  $atts
     */
    public static function render_inventory($atts = []): string
    {
        return Helmful_Sync_Display::render_shortcode_inventory($atts);
    }
}
