<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Helmful_Sync_Settings
{
    private const OPTION_KEY = 'helmful_sync_settings';

    public static function init(): void
    {
        add_filter('plugin_action_links_'.HELMFUL_SYNC_BASENAME, [self::class, 'plugin_action_links']);
        add_action('admin_menu', [self::class, 'register_menu'], 11);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_init', [self::class, 'redirect_after_settings_save']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
        add_action('admin_post_helmful_sync_pull_boat_shows', [self::class, 'handle_pull_boat_shows']);
        add_action('admin_post_helmful_sync_pull_brands', [self::class, 'handle_pull_brands']);
        add_action('admin_post_helmful_sync_pull_inventory', [self::class, 'handle_pull_inventory']);
        add_action('admin_post_helmful_sync_test', [self::class, 'handle_test']);
        add_action('admin_post_helmful_sync_generate_key', [self::class, 'handle_generate_key']);
    }

    public static function enqueue_admin_assets(string $hook): void
    {
        if (! self::is_settings_screen($hook)) {
            return;
        }

        wp_enqueue_style(
            'helmful-sync-admin-settings',
            HELMFUL_SYNC_URL.'assets/css/admin-settings.css',
            [],
            HELMFUL_SYNC_VERSION,
        );

        wp_enqueue_style(
            'helmful-sync-display',
            HELMFUL_SYNC_URL.'assets/css/display.css',
            [],
            HELMFUL_SYNC_VERSION,
        );

        wp_add_inline_style('helmful-sync-display', Helmful_Sync_Display_Settings::css_variables());

        wp_enqueue_script(
            'helmful-sync-admin-settings',
            HELMFUL_SYNC_URL.'assets/js/admin-settings.js',
            ['jquery'],
            HELMFUL_SYNC_VERSION,
            true,
        );
    }

    private static function is_settings_screen(?string $hook = null): bool
    {
        if (! is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';

        if ($page === 'helmful-sync') {
            return true;
        }

        if ($hook === null) {
            return false;
        }

        return in_array($hook, [
            'helmful-sync-hub_page_helmful-sync',
            'toplevel_page_helmful-sync',
            'helmful-sync_page_helmful-sync',
        ], true);
    }

    /**
     * @param  array<string, string>  $links
     * @return array<string, string>
     */
    public static function plugin_action_links(array $links): array
    {
        if (! current_user_can('manage_options')) {
            return $links;
        }

        $settingsLink = sprintf(
            '<a href="%s">%s</a>',
            esc_url(self::settings_url()),
            esc_html__('Settings', 'helmful-sync'),
        );

        return array_merge(['settings' => $settingsLink], $links);
    }

    public static function settings_url(): string
    {
        return admin_url('admin.php?page=helmful-sync');
    }

    public static function register_menu(): void
    {
        add_submenu_page(
            Helmful_Sync_Admin_Menu::MENU_SLUG,
            __('Settings', 'helmful-sync'),
            __('Settings', 'helmful-sync'),
            'manage_options',
            'helmful-sync',
            [self::class, 'render_page'],
        );
    }

    public static function register_settings(): void
    {
        register_setting('helmful_sync', self::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize_settings'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function sanitize_settings(array $input): array
    {
        $current = self::all();

        $output = [
            'tenant_domain' => sanitize_text_field((string) ($input['tenant_domain'] ?? $current['tenant_domain'] ?? '')),
            'helmful_api_key' => self::sanitize_helmful_api_key($input, $current),
            'api_key' => (string) ($current['api_key'] ?? ''),
        ];

        if (isset($input['display']) && is_array($input['display'])) {
            $currentDisplay = Helmful_Sync_Display_Settings::get();
            $output['display'] = Helmful_Sync_Display_Settings::sanitize(array_merge($currentDisplay, $input['display']));
        } else {
            $output['display'] = Helmful_Sync_Display_Settings::get();
        }

        if (isset($input['tenant_domain']) || isset($input['helmful_api_key'])) {
            self::remember_active_tab('general');
        }

        if (isset($_POST['helmful_active_tab'])) {
            self::remember_active_tab(sanitize_key((string) wp_unslash($_POST['helmful_active_tab'])));
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $current
     */
    private static function sanitize_helmful_api_key(array $input, array $current): string
    {
        if (! array_key_exists('helmful_api_key', $input)) {
            return (string) ($current['helmful_api_key'] ?? '');
        }

        $submitted = trim(sanitize_text_field((string) $input['helmful_api_key']));
        if ($submitted === '') {
            return (string) ($current['helmful_api_key'] ?? '');
        }

        return $submitted;
    }

    public static function redirect_after_settings_save(): void
    {
        if (! is_admin() || ! isset($_GET['page']) || $_GET['page'] !== 'helmful-sync') {
            return;
        }

        if (! isset($_GET['settings-updated']) || isset($_GET['tab'])) {
            return;
        }

        $tab = get_transient(self::tab_transient_key());
        if (! is_string($tab) || $tab === '') {
            return;
        }

        delete_transient(self::tab_transient_key());

        wp_safe_redirect(add_query_arg([
            'page' => 'helmful-sync',
            'tab' => sanitize_key($tab),
            'settings-updated' => 'true',
        ], admin_url('admin.php?page=helmful-sync')));
        exit;
    }

    private static function tab_transient_key(): string
    {
        return 'helmful_sync_active_tab_'.get_current_user_id();
    }

    private static function remember_active_tab(string $tab): void
    {
        if (isset($_POST['helmful_active_tab'])) {
            $tab = sanitize_key((string) wp_unslash($_POST['helmful_active_tab']));
        }

        set_transient(self::tab_transient_key(), $tab, MINUTE_IN_SECONDS);
    }

    public static function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = self::all();
        $display = Helmful_Sync_Display_Settings::get();
        $notice = isset($_GET['helmful_notice']) ? sanitize_text_field(wp_unslash((string) $_GET['helmful_notice'])) : '';
        $error = isset($_GET['helmful_error']) ? sanitize_text_field(wp_unslash((string) $_GET['helmful_error'])) : '';
        $revealedKey = isset($_GET['helmful_new_key']) ? sanitize_text_field(wp_unslash((string) $_GET['helmful_new_key'])) : '';
        $lastPull = (string) get_option('helmful_sync_last_pull_at', '');
        $activeTab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'general';
        $tabAliases = [
            'connection' => 'general',
            'display' => 'boat-shows',
            'shortcodes' => 'general',
        ];
        if (isset($tabAliases[$activeTab])) {
            $activeTab = $tabAliases[$activeTab];
        }
        $allowedTabs = ['general', 'boat-shows', 'inventory', 'brands'];
        if (! in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'general';
        }

        include HELMFUL_SYNC_PATH.'admin/settings-page.php';
    }

    public static function handle_pull_boat_shows(): void
    {
        self::assert_admin_post('helmful_sync_pull_boat_shows');
        $result = Helmful_Sync_Importer::pull_boat_shows();
        self::redirect_with_message($result['message'], ! ($result['success'] ?? false));
    }

    public static function handle_pull_brands(): void
    {
        self::assert_admin_post('helmful_sync_pull_brands');
        $result = Helmful_Sync_Importer::pull_brands();
        self::redirect_with_message($result['message'], ! ($result['success'] ?? false));
    }

    public static function handle_pull_inventory(): void
    {
        self::assert_admin_post('helmful_sync_pull_inventory');
        $result = Helmful_Sync_Importer::pull_inventory();
        self::redirect_with_message($result['message'], ! ($result['success'] ?? false));
    }

    public static function handle_test(): void
    {
        self::assert_admin_post('helmful_sync_test');
        $result = Helmful_Sync_Importer::test_connection();
        self::redirect_with_message($result['message'], ! ($result['success'] ?? false));
    }

    public static function handle_generate_key(): void
    {
        self::assert_admin_post('helmful_sync_generate_key');

        $settings = self::all();
        $newKey = wp_generate_password(64, false, false);
        $settings['api_key'] = $newKey;
        update_option(self::OPTION_KEY, $settings);

        wp_safe_redirect(add_query_arg([
            'page' => 'helmful-sync',
            'helmful_notice' => rawurlencode('WordPress API key generated. Copy it into Helmful.'),
            'helmful_new_key' => rawurlencode($newKey),
            'tab' => sanitize_key((string) ($_POST['helmful_active_tab'] ?? 'general')),
        ], admin_url('admin.php?page=helmful-sync')));
        exit;
    }

    public static function api_key(): string
    {
        return (string) (self::all()['api_key'] ?? '');
    }

    public static function set_api_key(string $apiKey): void
    {
        $settings = self::all();
        $settings['api_key'] = $apiKey;
        update_option(self::OPTION_KEY, $settings);
    }

    public static function has_api_key(): bool
    {
        return self::api_key() !== '';
    }

    public static function helmful_api_key(): string
    {
        return (string) (self::all()['helmful_api_key'] ?? '');
    }

    public static function tenant_domain(): string
    {
        return (string) (self::all()['tenant_domain'] ?? '');
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        $settings = get_option(self::OPTION_KEY, []);

        return is_array($settings) ? $settings : [];
    }

    private static function assert_admin_post(string $action): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'helmful-sync'));
        }

        check_admin_referer($action);
    }

    private static function redirect_with_message(string $message, bool $isError): void
    {
        $arg = $isError ? 'helmful_error' : 'helmful_notice';

        wp_safe_redirect(add_query_arg([
            'page' => 'helmful-sync',
            $arg => rawurlencode($message),
            'tab' => sanitize_key((string) ($_POST['helmful_active_tab'] ?? $_GET['tab'] ?? 'general')),
        ], admin_url('admin.php?page=helmful-sync')));
        exit;
    }
}
