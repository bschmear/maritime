<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Helmful_Sync_Display_Settings
{
    /**
     * @return array{
     *     layout: string,
     *     columns: int,
     *     show_description: bool,
     *     accent_color: string,
     *     card_style: string,
     *     spacing: string
     * }
     */
    public static function defaults(): array
    {
        return [
            'layout' => Helmful_Sync_Display::LAYOUT_STACKED,
            'columns' => 2,
            'show_description' => true,
            'accent_color' => '#1e3a5f',
            'card_style' => 'soft',
            'spacing' => 'comfortable',
        ];
    }

    /**
     * @return array{
     *     layout: string,
     *     columns: int,
     *     show_description: bool,
     *     accent_color: string,
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

        $accent = sanitize_hex_color((string) ($input['accent_color'] ?? '#1e3a5f'));

        return [
            'layout' => $layout,
            'columns' => in_array($columns, [2, 3], true) ? $columns : 2,
            'show_description' => ! empty($input['show_description']),
            'accent_color' => $accent !== null && $accent !== '' ? $accent : '#1e3a5f',
            'card_style' => $cardStyle,
            'spacing' => $spacing,
        ];
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
        $accent   = $settings['accent_color'];

        // card_style controls radius, shadow, and border treatment
        $radius = match ($settings['card_style']) {
            'flat'     => '6px',
            'bordered' => '10px',
            default    => '12px',   // soft
        };

        $shadow = match ($settings['card_style']) {
            'flat'     => 'none',
            'bordered' => 'none',
            default    => '0 2px 8px rgba(16,24,40,0.07),0 0 1px rgba(16,24,40,0.06)',
        };

        // bordered style gets a slightly more visible border
        $border = match ($settings['card_style']) {
            'bordered' => '#b8bec4',
            default    => '#e2e5e9',
        };

        // spacing controls gap between cards and internal padding
        $gap = match ($settings['spacing']) {
            'compact'  => '0.85rem',
            'spacious' => '2rem',
            default    => '1.25rem',  // comfortable
        };

        $padding = match ($settings['spacing']) {
            'compact'  => '1rem',
            'spacious' => '2rem',
            default    => '1.5rem',
        };

        // Derive accent-light (10 % opacity tint) for timeline track / marker ring
        $accentLight = self::alpha_hex($accent, 0.12);

        return sprintf(
            'body .helmful-boat-shows-shell .helmful-boat-shows,'
            . 'body .helmful-boat-shows-shell .helmful-boat-show-events,'
            . 'body .helmful-boat-shows-shell .helmful-template{'
            . '--helmful-accent:%1$s;'
            . '--helmful-accent-hover:%2$s;'
            . '--helmful-accent-light:%3$s;'
            . '--helmful-card-radius:%4$s;'
            . '--helmful-card-shadow:%5$s;'
            . '--helmful-border:%6$s;'
            . '--helmful-surface:#f8f9fb;'
            . '--helmful-muted:#50575e;'
            . '--helmful-gap:%7$s;'
            . '--helmful-card-padding:%8$s;'
            . '--helmful-columns:%9$d;'
            . '}',
            esc_attr($accent),
            esc_attr(self::darken_hex($accent, 0.12)),
            esc_attr($accentLight),
            esc_attr($radius),
            esc_attr($shadow),
            esc_attr($border),
            esc_attr($gap),
            esc_attr($padding),
            $settings['columns'],
        );
    }

    /**
     * Returns a CSS color-mix() approximation of the hex at $opacity over white.
     * Falls back to a safe light blue-grey if the hex is invalid.
     */
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
