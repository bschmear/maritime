<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Helmful_Sync_Display_Settings
{
    /**
     * @return array<string, string>
     */
    public static function color_defaults(): array
    {
        return [
            'accent_color' => '#1e3a5f',
            'filter_bar_bg' => '#111111',
            'filter_bar_text' => '#ffffff',
            'surface_bg' => '#f7f7f7',
            'card_bg' => '#ffffff',
            'heading_color' => '#111827',
            'text_muted' => '#6b7280',
            'text_subtle' => '#9ca3af',
            'border_color' => '#e5e7eb',
            'placeholder_bg' => '#f9fafb',
            'placeholder_icon' => '#e5e7eb',
            'button_dark_bg' => '#111827',
            'button_light_bg' => '#f3f4f6',
            'button_light_text' => '#374151',
            'on_accent_text' => '#ffffff',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function boat_shows_color_labels(): array
    {
        return [
            'accent_color' => __('Accent color', 'helmful-sync'),
            'heading_color' => __('Heading color', 'helmful-sync'),
            'text_muted' => __('Muted text', 'helmful-sync'),
            'text_subtle' => __('Subtle text', 'helmful-sync'),
            'border_color' => __('Border color', 'helmful-sync'),
            'card_bg' => __('Card background', 'helmful-sync'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function inventory_color_labels(): array
    {
        return [
            'filter_bar_bg' => __('Sidebar background', 'helmful-sync'),
            'filter_bar_text' => __('Sidebar text', 'helmful-sync'),
            'surface_bg' => __('Page background', 'helmful-sync'),
            'placeholder_bg' => __('Image placeholder background', 'helmful-sync'),
            'placeholder_icon' => __('Image placeholder icon', 'helmful-sync'),
            'button_dark_bg' => __('Primary button background', 'helmful-sync'),
            'button_light_bg' => __('Secondary button background', 'helmful-sync'),
            'button_light_text' => __('Secondary button text', 'helmful-sync'),
            'on_accent_text' => __('Text on accent', 'helmful-sync'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function brands_color_labels(): array
    {
        return [
            'accent_color' => __('Accent color', 'helmful-sync'),
            'card_bg' => __('Card background', 'helmful-sync'),
            'border_color' => __('Border color', 'helmful-sync'),
            'heading_color' => __('Heading color', 'helmful-sync'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function color_labels(): array
    {
        return array_merge(
            self::boat_shows_color_labels(),
            self::inventory_color_labels(),
            array_diff_key(self::brands_color_labels(), self::boat_shows_color_labels()),
        );
    }

    /**
     * @return array{
     *     layout: string,
     *     columns: int,
     *     show_description: bool,
     *     accent_color: string,
     *     filter_bar_bg: string,
     *     filter_bar_text: string,
     *     surface_bg: string,
     *     card_bg: string,
     *     heading_color: string,
     *     text_muted: string,
     *     text_subtle: string,
     *     border_color: string,
     *     placeholder_bg: string,
     *     placeholder_icon: string,
     *     button_dark_bg: string,
     *     button_light_bg: string,
     *     button_light_text: string,
     *     on_accent_text: string,
     *     card_style: string,
     *     spacing: string
     * }
     */
    public static function defaults(): array
    {
        return array_merge([
            'layout' => Helmful_Sync_Display::LAYOUT_STACKED,
            'columns' => 2,
            'show_description' => true,
            'inventory_per_page' => 30,
            'inventory_columns' => 4,
            'brands_columns' => 4,
            'quote_email' => '',
            'card_style' => 'soft',
            'spacing' => 'comfortable',
        ], self::color_defaults());
    }

    /**
     * @return array{
     *     layout: string,
     *     columns: int,
     *     show_description: bool,
     *     accent_color: string,
     *     filter_bar_bg: string,
     *     filter_bar_text: string,
     *     surface_bg: string,
     *     card_bg: string,
     *     heading_color: string,
     *     text_muted: string,
     *     text_subtle: string,
     *     border_color: string,
     *     placeholder_bg: string,
     *     placeholder_icon: string,
     *     button_dark_bg: string,
     *     button_light_bg: string,
     *     button_light_text: string,
     *     on_accent_text: string,
     *     card_style: string,
     *     spacing: string
     * }
     */
    public static function get(): array
    {
        $stored = Helmful_Sync_Settings::all()['display'] ?? [];
        $stored = is_array($stored) ? $stored : [];

        return self::sanitize(array_merge(self::defaults(), $stored));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     layout: string,
     *     columns: int,
     *     show_description: bool,
     *     accent_color: string,
     *     filter_bar_bg: string,
     *     filter_bar_text: string,
     *     surface_bg: string,
     *     card_bg: string,
     *     heading_color: string,
     *     text_muted: string,
     *     text_subtle: string,
     *     border_color: string,
     *     placeholder_bg: string,
     *     placeholder_icon: string,
     *     button_dark_bg: string,
     *     button_light_bg: string,
     *     button_light_text: string,
     *     on_accent_text: string,
     *     card_style: string,
     *     spacing: string
     * }
     */
    public static function sanitize(array $input): array
    {
        $layout = sanitize_key((string) ($input['layout'] ?? Helmful_Sync_Display::LAYOUT_STACKED));
        if (! array_key_exists($layout, Helmful_Sync_Display::layout_options())) {
            $layout = Helmful_Sync_Display::LAYOUT_STACKED;
        }

        $columns = (int) ($input['columns'] ?? 2);

        $cardStyle = sanitize_key((string) ($input['card_style'] ?? 'soft'));
        if (! in_array($cardStyle, ['soft', 'flat', 'bordered'], true)) {
            $cardStyle = 'soft';
        }

        $spacing = sanitize_key((string) ($input['spacing'] ?? 'comfortable'));
        if (! in_array($spacing, ['compact', 'comfortable', 'spacious'], true)) {
            $spacing = 'comfortable';
        }

        $colors = [];
        foreach (self::color_defaults() as $key => $default) {
            $colors[$key] = self::sanitize_hex_color((string) ($input[$key] ?? $default), $default);
        }

        $inventoryPerPage = (int) ($input['inventory_per_page'] ?? 30);
        $inventoryColumns = (int) ($input['inventory_columns'] ?? 4);
        $brandsColumns = (int) ($input['brands_columns'] ?? 4);
        $quoteEmail = sanitize_email((string) ($input['quote_email'] ?? ''));

        return array_merge($colors, [
            'layout' => $layout,
            'columns' => in_array($columns, [2, 3], true) ? $columns : 2,
            'show_description' => ! empty($input['show_description']),
            'inventory_per_page' => max(1, min(100, $inventoryPerPage)),
            'inventory_columns' => in_array($inventoryColumns, [3, 4], true) ? $inventoryColumns : 4,
            'brands_columns' => max(2, min(6, $brandsColumns)),
            'quote_email' => $quoteEmail,
            'card_style' => $cardStyle,
            'spacing' => $spacing,
        ]);
    }

    public static function quote_email(): string
    {
        $email = sanitize_email((string) (self::get()['quote_email'] ?? ''));

        if ($email !== '') {
            return $email;
        }

        return sanitize_email((string) get_option('admin_email'));
    }

    /**
     * @return array<string, string>
     */
    public static function card_style_options(): array
    {
        return [
            'soft' => __('Soft shadow', 'helmful-sync'),
            'flat' => __('Flat', 'helmful-sync'),
            'bordered' => __('Bordered', 'helmful-sync'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function spacing_options(): array
    {
        return [
            'compact' => __('Compact', 'helmful-sync'),
            'comfortable' => __('Comfortable', 'helmful-sync'),
            'spacious' => __('Spacious', 'helmful-sync'),
        ];
    }

    public static function css_variables(): string
    {
        $settings = self::get();
        $accent = $settings['accent_color'];

        $radius = match ($settings['card_style']) {
            'flat' => '6px',
            'bordered' => '10px',
            default => '12px',
        };

        $shadow = match ($settings['card_style']) {
            'flat' => 'none',
            'bordered' => 'none',
            default => '0 2px 8px rgba(16,24,40,0.07),0 0 1px rgba(16,24,40,0.06)',
        };

        $border = match ($settings['card_style']) {
            'bordered' => '#b8bec4',
            default => $settings['border_color'],
        };

        $gap = match ($settings['spacing']) {
            'compact' => '0.85rem',
            'spacious' => '2rem',
            default => '1.25rem',
        };

        $padding = match ($settings['spacing']) {
            'compact' => '1rem',
            'spacious' => '2rem',
            default => '1.5rem',
        };

        $accentLight = self::alpha_hex($accent, 0.12);

        return sprintf(
            'body .helmful-boat-shows-shell{'
            . '--helmful-accent:%1$s;'
            . '--helmful-accent-hover:%2$s;'
            . '--helmful-accent-light:%3$s;'
            . '--helmful-filter-bar-bg:%4$s;'
            . '--helmful-filter-bar-text:%5$s;'
            . '--helmful-surface-bg:%6$s;'
            . '--helmful-surface:%6$s;'
            . '--helmful-card-bg:%7$s;'
            . '--helmful-heading:%8$s;'
            . '--helmful-muted:%9$s;'
            . '--helmful-subtle:%10$s;'
            . '--helmful-border:%11$s;'
            . '--helmful-placeholder-bg:%12$s;'
            . '--helmful-placeholder-icon:%13$s;'
            . '--helmful-button-dark:%14$s;'
            . '--helmful-button-light:%15$s;'
            . '--helmful-button-light-text:%16$s;'
            . '--helmful-on-accent:%17$s;'
            . '--helmful-card-radius:%18$s;'
            . '--helmful-card-shadow:%19$s;'
            . '--helmful-gap:%20$s;'
            . '--helmful-card-padding:%21$s;'
            . '--helmful-columns:%22$d;'
            . '--helmful-inventory-columns:%23$d;'
            . '--helmful-brands-columns:%24$d;'
            . '}',
            esc_attr($accent),
            esc_attr(self::darken_hex($accent, 0.12)),
            esc_attr($accentLight),
            esc_attr($settings['filter_bar_bg']),
            esc_attr($settings['filter_bar_text']),
            esc_attr($settings['surface_bg']),
            esc_attr($settings['card_bg']),
            esc_attr($settings['heading_color']),
            esc_attr($settings['text_muted']),
            esc_attr($settings['text_subtle']),
            esc_attr($border),
            esc_attr($settings['placeholder_bg']),
            esc_attr($settings['placeholder_icon']),
            esc_attr($settings['button_dark_bg']),
            esc_attr($settings['button_light_bg']),
            esc_attr($settings['button_light_text']),
            esc_attr($settings['on_accent_text']),
            esc_attr($radius),
            esc_attr($shadow),
            esc_attr($gap),
            esc_attr($padding),
            $settings['columns'],
            $settings['inventory_columns'],
            $settings['brands_columns'],
        );
    }

    private static function sanitize_hex_color(string $value, string $fallback): string
    {
        $color = sanitize_hex_color($value);

        return $color !== null && $color !== '' ? $color : $fallback;
    }

    private static function alpha_hex(string $hex, float $opacity): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return '#e8eef5';
        }

        $r = (int) round(hexdec(substr($hex, 0, 2)) * $opacity + 255 * (1 - $opacity));
        $g = (int) round(hexdec(substr($hex, 2, 2)) * $opacity + 255 * (1 - $opacity));
        $b = (int) round(hexdec(substr($hex, 4, 2)) * $opacity + 255 * (1 - $opacity));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private static function darken_hex(string $hex, float $amount): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return '#16304f';
        }

        $r = max(0, (int) round(hexdec(substr($hex, 0, 2)) * (1 - $amount)));
        $g = max(0, (int) round(hexdec(substr($hex, 2, 2)) * (1 - $amount)));
        $b = max(0, (int) round(hexdec(substr($hex, 4, 2)) * (1 - $amount)));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
