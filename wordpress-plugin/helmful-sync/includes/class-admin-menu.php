<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Helmful_Sync_Admin_Menu
{
    public const MENU_SLUG = 'helmful-sync-hub';

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu'], 9);
        add_filter('parent_file', [self::class, 'highlight_parent']);
        add_filter('submenu_file', [self::class, 'highlight_submenu']);
    }

    public static function register_menu(): void
    {
        add_menu_page(
            __('Helmful', 'helmful-sync'),
            __('Helmful', 'helmful-sync'),
            'edit_posts',
            self::MENU_SLUG,
            [self::class, 'render_hub'],
            'dashicons-admin-site-alt3',
            25,
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Boat Shows', 'helmful-sync'),
            __('Boat Shows', 'helmful-sync'),
            'edit_posts',
            'edit.php?post_type='.Helmful_Sync_CPT::SHOW_POST_TYPE,
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Boat Show Events', 'helmful-sync'),
            __('Boat Show Events', 'helmful-sync'),
            'edit_posts',
            'edit.php?post_type='.Helmful_Sync_CPT::EVENT_POST_TYPE,
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Brands', 'helmful-sync'),
            __('Brands', 'helmful-sync'),
            'edit_posts',
            Helmful_Sync_CPT::brands_admin_menu_slug(),
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Inventory', 'helmful-sync'),
            __('Inventory', 'helmful-sync'),
            'edit_posts',
            'edit.php?post_type='.Helmful_Sync_CPT::INVENTORY_POST_TYPE,
        );

        remove_submenu_page(self::MENU_SLUG, self::MENU_SLUG);
    }

    public static function render_hub(): void
    {
        if (! current_user_can('edit_posts')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'helmful-sync'));
        }

        $links = [
            [
                'label' => __('Boat Shows', 'helmful-sync'),
                'url' => admin_url('edit.php?post_type='.Helmful_Sync_CPT::SHOW_POST_TYPE),
                'description' => __('Synced boat shows from Helmful.', 'helmful-sync'),
            ],
            [
                'label' => __('Boat Show Events', 'helmful-sync'),
                'url' => admin_url('edit.php?post_type='.Helmful_Sync_CPT::EVENT_POST_TYPE),
                'description' => __('Events linked to your boat shows.', 'helmful-sync'),
            ],
            [
                'label' => __('Brands', 'helmful-sync'),
                'url' => Helmful_Sync_CPT::brands_admin_url(),
                'description' => __('Boat makes and brand logos synced from Helmful.', 'helmful-sync'),
            ],
            [
                'label' => __('Inventory', 'helmful-sync'),
                'url' => admin_url('edit.php?post_type='.Helmful_Sync_CPT::INVENTORY_POST_TYPE),
                'description' => __('Inventory models synced from Helmful.', 'helmful-sync'),
            ],
        ];

        echo '<div class="wrap">';
        echo '<h1>'.esc_html__('Helmful', 'helmful-sync').'</h1>';
        echo '<p>'.esc_html__('Manage synced boat shows, brands, and inventory from Helmful.', 'helmful-sync').'</p>';
        echo '<div class="helmful-admin-hub" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;margin-top:1.5rem;">';

        foreach ($links as $link) {
            echo '<a class="card" href="'.esc_url($link['url']).'" style="padding:1.25rem;border:1px solid #c3c4c7;border-radius:8px;background:#fff;text-decoration:none;color:inherit;">';
            echo '<strong style="display:block;font-size:1.05rem;margin-bottom:.35rem;">'.esc_html($link['label']).'</strong>';
            echo '<span style="color:#50575e;">'.esc_html($link['description']).'</span>';
            echo '</a>';
        }

        if (current_user_can('manage_options')) {
            echo '<a class="card" href="'.esc_url(admin_url('admin.php?page=helmful-sync')).'" style="padding:1.25rem;border:1px solid #c3c4c7;border-radius:8px;background:#fff;text-decoration:none;color:inherit;">';
            echo '<strong style="display:block;font-size:1.05rem;margin-bottom:.35rem;">'.esc_html__('Settings', 'helmful-sync').'</strong>';
            echo '<span style="color:#50575e;">'.esc_html__('Connection, sync, design, and shortcodes.', 'helmful-sync').'</span>';
            echo '</a>';
        }

        echo '</div></div>';
    }

    public static function highlight_parent(?string $parentFile): ?string
    {
        global $pagenow, $typenow;

        if ($parentFile === null) {
            return null;
        }

        if (self::is_brand_taxonomy_screen()) {
            return self::MENU_SLUG;
        }

        if ($pagenow !== 'edit.php' && $pagenow !== 'post.php' && $pagenow !== 'post-new.php') {
            if ($pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === 'helmful-sync') {
                return self::MENU_SLUG;
            }

            return $parentFile;
        }

        $helmfulTypes = [
            Helmful_Sync_CPT::SHOW_POST_TYPE,
            Helmful_Sync_CPT::EVENT_POST_TYPE,
            Helmful_Sync_CPT::INVENTORY_POST_TYPE,
        ];

        if (in_array($typenow, $helmfulTypes, true)) {
            return self::MENU_SLUG;
        }

        return $parentFile;
    }

    public static function highlight_submenu(?string $submenuFile): ?string
    {
        global $pagenow, $typenow;

        if ($submenuFile === null) {
            return null;
        }

        if (self::is_brand_taxonomy_screen()) {
            return Helmful_Sync_CPT::brands_admin_menu_slug();
        }

        if ($pagenow !== 'edit.php' && $pagenow !== 'post.php' && $pagenow !== 'post-new.php') {
            if ($pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === 'helmful-sync') {
                return 'helmful-sync';
            }

            return $submenuFile;
        }

        return match ($typenow) {
            Helmful_Sync_CPT::SHOW_POST_TYPE => 'edit.php?post_type='.Helmful_Sync_CPT::SHOW_POST_TYPE,
            Helmful_Sync_CPT::EVENT_POST_TYPE => 'edit.php?post_type='.Helmful_Sync_CPT::EVENT_POST_TYPE,
            Helmful_Sync_CPT::INVENTORY_POST_TYPE => 'edit.php?post_type='.Helmful_Sync_CPT::INVENTORY_POST_TYPE,
            default => $submenuFile,
        };
    }

    private static function is_brand_taxonomy_screen(): bool
    {
        global $pagenow;

        if ($pagenow !== 'edit-tags.php' && $pagenow !== 'term.php') {
            return false;
        }

        $taxonomy = isset($_GET['taxonomy']) ? sanitize_key(wp_unslash((string) $_GET['taxonomy'])) : '';

        return $taxonomy === Helmful_Sync_CPT::BRAND_TAXONOMY;
    }
}
