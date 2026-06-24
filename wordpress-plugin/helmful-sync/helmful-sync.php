<?php
/**
 * Plugin Name: Helmful Sync
 * Description: Sync boat shows and events from Helmful to WordPress custom post types.
 * Version: 1.5.9
 * Author: Helmful
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Text Domain: helmful-sync
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('HELMFUL_SYNC_VERSION', '1.5.9');
define('HELMFUL_SYNC_PATH', plugin_dir_path(__FILE__));
define('HELMFUL_SYNC_URL', plugin_dir_url(__FILE__));
define('HELMFUL_SYNC_BASENAME', plugin_basename(__FILE__));

require_once HELMFUL_SYNC_PATH.'includes/class-cpt.php';
require_once HELMFUL_SYNC_PATH.'includes/class-sync-handler.php';
require_once HELMFUL_SYNC_PATH.'includes/class-rest-api.php';
require_once HELMFUL_SYNC_PATH.'includes/class-importer.php';
require_once HELMFUL_SYNC_PATH.'includes/class-settings.php';
require_once HELMFUL_SYNC_PATH.'includes/class-display.php';
require_once HELMFUL_SYNC_PATH.'includes/class-display-settings.php';
require_once HELMFUL_SYNC_PATH.'includes/class-shortcodes.php';
require_once HELMFUL_SYNC_PATH.'includes/class-templates.php';
require_once HELMFUL_SYNC_PATH.'includes/class-rewrite.php';
require_once HELMFUL_SYNC_PATH.'includes/class-admin-menu.php';

final class Helmful_Sync_Plugin
{
    public static function init(): void
    {
        add_action('init', [Helmful_Sync_CPT::class, 'register']);
        add_action('rest_api_init', [Helmful_Sync_REST_API::class, 'register_routes']);
        Helmful_Sync_Settings::init();
        Helmful_Sync_Shortcodes::init();
        Helmful_Sync_Templates::init();
        Helmful_Sync_Rewrite::init();
        Helmful_Sync_Admin_Menu::init();
    }
}

Helmful_Sync_Plugin::init();

register_activation_hook(__FILE__, static function (): void {
    Helmful_Sync_CPT::register();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, static function (): void {
    flush_rewrite_rules();
});
