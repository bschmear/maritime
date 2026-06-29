<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Helmful_Sync_Inventory_Admin
{
    public static function init(): void
    {
        add_action('add_meta_boxes', [self::class, 'register_meta_boxes']);
        add_filter('manage_'.Helmful_Sync_CPT::INVENTORY_POST_TYPE.'_posts_columns', [self::class, 'list_columns']);
        add_action('manage_'.Helmful_Sync_CPT::INVENTORY_POST_TYPE.'_posts_custom_column', [self::class, 'render_list_column'], 10, 2);
    }

    public static function register_meta_boxes(): void
    {
        add_meta_box(
            'helmful-inventory-sync-data',
            __('Helmful Inventory Data', 'helmful-sync'),
            [self::class, 'render_meta_box'],
            Helmful_Sync_CPT::INVENTORY_POST_TYPE,
            'normal',
            'high',
        );
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    public static function list_columns(array $columns): array
    {
        $insert = [
            'helmful_primary_image' => __('Image', 'helmful-sync'),
        ];

        $offset = array_search('title', array_keys($columns), true);
        if ($offset === false) {
            return $insert + $columns;
        }

        return array_slice($columns, 0, $offset + 1, true)
            + $insert
            + array_slice($columns, $offset + 1, null, true);
    }

    public static function render_list_column(string $column, int $postId): void
    {
        if ($column !== 'helmful_primary_image') {
            return;
        }

        $imageUrl = Helmful_Sync_Handler::inventory_primary_image_for_post($postId);
        if ($imageUrl === '') {
            echo '<span aria-hidden="true">—</span><span class="screen-reader-text">'.esc_html__('No image', 'helmful-sync').'</span>';

            return;
        }

        echo '<img src="'.esc_url($imageUrl).'" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:4px;border:1px solid #dcdcde;">';
    }

    public static function render_meta_box(WP_Post $post): void
    {
        $imageUrl = Helmful_Sync_Handler::inventory_primary_image_for_post($post->ID);
        $appAssetUrl = esc_url((string) get_post_meta($post->ID, 'helmful_app_asset_url', true));
        $specs = Helmful_Sync_Handler::inventory_specs_for_post($post->ID);
        $importPayload = (string) get_post_meta($post->ID, 'helmful_import_payload', true);

        echo '<div class="helmful-inventory-admin-meta">';

        echo '<h3 style="margin:0 0 .75rem;">'.esc_html__('Primary Image', 'helmful-sync').'</h3>';

        if ($imageUrl !== '') {
            echo '<p style="margin:0 0 .75rem;">';
            echo '<img src="'.esc_url($imageUrl).'" alt="" style="max-width:240px;height:auto;border:1px solid #dcdcde;border-radius:6px;display:block;">';
            echo '</p>';
            echo '<p style="margin:0 0 1rem;word-break:break-all;"><code>'.esc_html($imageUrl).'</code></p>';
        } else {
            echo '<p style="margin:0 0 1rem;color:#646970;">'.esc_html__('No primary image URL saved yet. Pull inventory again after Helmful sends primary_image_url.', 'helmful-sync').'</p>';
        }

        if ($appAssetUrl !== '') {
            echo '<p style="margin:0 0 1.25rem;"><strong>'.esc_html__('Helmful asset page:', 'helmful-sync').'</strong> ';
            echo '<a href="'.esc_url($appAssetUrl).'" target="_blank" rel="noopener noreferrer">'.esc_html($appAssetUrl).'</a></p>';
        }

        echo '<h3 style="margin:0 0 .75rem;">'.esc_html__('Specifications', 'helmful-sync').'</h3>';

        if ($specs !== []) {
            echo '<table class="widefat striped" style="margin:0 0 1.25rem;">';
            echo '<thead><tr><th>'.esc_html__('Label', 'helmful-sync').'</th><th>'.esc_html__('Value', 'helmful-sync').'</th></tr></thead><tbody>';

            foreach ($specs as $spec) {
                $value = $spec['value'];
                if (isset($spec['unit']) && $spec['unit'] !== '' && ! str_contains($value, $spec['unit'])) {
                    $value .= ' '.$spec['unit'];
                }

                echo '<tr>';
                echo '<td><strong>'.esc_html($spec['label']).'</strong></td>';
                echo '<td>'.esc_html($value).'</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        } else {
            echo '<p style="margin:0 0 1.25rem;color:#646970;">'.esc_html__('No specs saved yet. Pull inventory again after Helmful sends specs in the API payload.', 'helmful-sync').'</p>';
        }

        echo '<details>';
        echo '<summary style="cursor:pointer;font-weight:600;">'.esc_html__('Raw API import JSON', 'helmful-sync').'</summary>';

        if ($importPayload !== '') {
            echo '<textarea readonly rows="16" style="width:100%;margin-top:.75rem;font-family:monospace;font-size:12px;">';
            echo esc_textarea($importPayload);
            echo '</textarea>';
        } else {
            echo '<p style="margin:.75rem 0 0;color:#646970;">'.esc_html__('No import payload stored for this item yet.', 'helmful-sync').'</p>';
        }

        echo '</details>';
        echo '</div>';
    }
}
