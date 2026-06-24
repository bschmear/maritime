<?php
/**
 * Plugin Name: Helmful Sync
 * Description: Sync boat shows and events from Helmful to WordPress custom post types.
 * Version: 1.0.0
 * Author: Helmful
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Text Domain: helmful-sync
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('HELMFUL_SYNC_VERSION', '1.0.0');
define('HELMFUL_SYNC_PATH', plugin_dir_path(__FILE__));
define('HELMFUL_SYNC_URL', plugin_dir_url(__FILE__));

require_once HELMFUL_SYNC_PATH.'includes/class-cpt.php';
require_once HELMFUL_SYNC_PATH.'includes/class-sync-handler.php';
require_once HELMFUL_SYNC_PATH.'includes/class-rest-api.php';
require_once HELMFUL_SYNC_PATH.'includes/class-importer.php';
require_once HELMFUL_SYNC_PATH.'includes/class-settings.php';

final class Helmful_Sync_Plugin
{
    public static function init(): void
    {
        add_action('init', [Helmful_Sync_CPT::class, 'register']);
        add_action('rest_api_init', [Helmful_Sync_REST_API::class, 'register_routes']);
        Helmful_Sync_Settings::init();
    }
}

Helmful_Sync_Plugin::init();
