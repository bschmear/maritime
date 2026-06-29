<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Helmful_Sync_Brand_Admin
{
    public static function init(): void
    {
        $taxonomy = Helmful_Sync_CPT::BRAND_TAXONOMY;

        self::register_term_meta($taxonomy);

        add_action("{$taxonomy}_add_form_fields", [self::class, 'render_add_fields']);
        add_action("{$taxonomy}_edit_form_fields", [self::class, 'render_edit_fields'], 10, 2);
        add_action("created_{$taxonomy}", [self::class, 'save_term_fields']);
        add_action("edited_{$taxonomy}", [self::class, 'save_term_fields']);
    }

    private static function register_term_meta(string $taxonomy): void
    {
        $metaKeys = [
            'helmful_uuid' => 'string',
            'helmful_slug' => 'string',
            'helmful_brand_key' => 'string',
            'helmful_logo_url' => 'string',
            'helmful_website_url' => 'string',
            'helmful_app_brand_url' => 'string',
            'helmful_active' => 'string',
            'helmful_updated_at' => 'string',
            'helmful_last_synced_at' => 'string',
        ];

        foreach ($metaKeys as $key => $type) {
            register_term_meta($taxonomy, $key, [
                'type' => $type,
                'single' => true,
                'show_in_rest' => true,
                'sanitize_callback' => static function (mixed $value) use ($key): string {
                    if (! is_scalar($value)) {
                        return '';
                    }

                    $stringValue = trim((string) $value);

                    return str_contains($key, '_url') ? esc_url_raw($stringValue) : sanitize_text_field($stringValue);
                },
            ]);
        }
    }

    public static function render_add_fields(): void
    {
        self::render_website_field('');
    }

    public static function render_edit_fields(WP_Term $term): void
    {
        $websiteUrl = (string) get_term_meta($term->term_id, 'helmful_website_url', true);
        $logoUrl = (string) get_term_meta($term->term_id, 'helmful_logo_url', true);
        $brandKey = (string) get_term_meta($term->term_id, 'helmful_brand_key', true);
        $appBrandUrl = (string) get_term_meta($term->term_id, 'helmful_app_brand_url', true);
        $lastSyncedAt = (string) get_term_meta($term->term_id, 'helmful_last_synced_at', true);

        echo '<tr class="form-field helmful-brand-admin-meta">';
        echo '<th scope="row">'.esc_html__('Helmful sync', 'helmful-sync').'</th>';
        echo '<td>';
        echo '<p class="description" style="margin-top:0;">'.esc_html__('Description is stored in the standard Description field above. Pull brands again from Helmful to refresh synced values.', 'helmful-sync').'</p>';

        if ($logoUrl !== '') {
            echo '<p style="margin:0 0 .75rem;">';
            echo '<img src="'.esc_url($logoUrl).'" alt="" style="max-width:180px;height:auto;border:1px solid #dcdcde;border-radius:6px;display:block;">';
            echo '</p>';
        }

        if ($brandKey !== '') {
            echo '<p><strong>'.esc_html__('Brand key:', 'helmful-sync').'</strong> <code>'.esc_html($brandKey).'</code></p>';
        }

        if ($appBrandUrl !== '') {
            echo '<p><strong>'.esc_html__('Helmful brand page:', 'helmful-sync').'</strong> ';
            echo '<a href="'.esc_url($appBrandUrl).'" target="_blank" rel="noopener noreferrer">'.esc_html($appBrandUrl).'</a></p>';
        }

        if ($lastSyncedAt !== '') {
            echo '<p><strong>'.esc_html__('Last synced:', 'helmful-sync').'</strong> '.esc_html($lastSyncedAt).'</p>';
        }

        echo '</td>';
        echo '</tr>';

        echo '<tr class="form-field helmful-brand-admin-website">';
        echo '<th scope="row"><label for="helmful_website_url">'.esc_html__('Website URL', 'helmful-sync').'</label></th>';
        echo '<td>';
        self::render_website_input($websiteUrl);
        echo '</td>';
        echo '</tr>';
    }

    private static function render_website_field(string $websiteUrl): void
    {
        echo '<div class="form-field helmful-brand-admin-website">';
        echo '<label for="helmful_website_url">'.esc_html__('Website URL', 'helmful-sync').'</label>';
        self::render_website_input($websiteUrl);
        echo '</div>';
    }

    private static function render_website_input(string $websiteUrl): void
    {
        echo '<input type="url" name="helmful_website_url" id="helmful_website_url" value="'.esc_attr($websiteUrl).'" class="regular-text" placeholder="https://example.com">';
        echo '<p class="description">'.esc_html__('Manufacturer website synced from Helmful. Use the Description field for the brand overview.', 'helmful-sync').'</p>';
    }

    public static function save_term_fields(int $termId): void
    {
        if (! isset($_POST['helmful_website_url'])) {
            return;
        }

        if (! current_user_can('edit_posts')) {
            return;
        }

        $websiteUrl = esc_url_raw(wp_unslash((string) $_POST['helmful_website_url']));
        update_term_meta($termId, 'helmful_website_url', $websiteUrl);
    }
}
