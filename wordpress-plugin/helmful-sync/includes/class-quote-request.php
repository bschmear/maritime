<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Helmful_Sync_Quote_Request
{
    public static function init(): void
    {
        add_action('wp_ajax_helmful_inventory_quote', [self::class, 'handle']);
        add_action('wp_ajax_nopriv_helmful_inventory_quote', [self::class, 'handle']);
    }

    public static function handle(): void
    {
        check_ajax_referer('helmful_inventory_quote', 'nonce');

        $itemId = (int) ($_POST['item_id'] ?? 0);
        $item = $itemId > 0 ? get_post($itemId) : null;

        if (! $item instanceof WP_Post || $item->post_type !== Helmful_Sync_CPT::INVENTORY_POST_TYPE) {
            wp_send_json_error([
                'message' => __('Invalid inventory item.', 'helmful-sync'),
            ], 400);
        }

        $name = sanitize_text_field(wp_unslash((string) ($_POST['name'] ?? '')));
        $email = sanitize_email(wp_unslash((string) ($_POST['email'] ?? '')));
        $phone = sanitize_text_field(wp_unslash((string) ($_POST['phone'] ?? '')));
        $message = sanitize_textarea_field(wp_unslash((string) ($_POST['message'] ?? '')));

        if ($name === '' || $email === '' || ! is_email($email)) {
            wp_send_json_error([
                'message' => __('Please enter your name and a valid email address.', 'helmful-sync'),
            ], 400);
        }

        $recipient = Helmful_Sync_Display_Settings::quote_email();
        if ($recipient === '') {
            wp_send_json_error([
                'message' => __('Quote requests are not configured. Please contact the site administrator.', 'helmful-sync'),
            ], 500);
        }

        $meta = [
            'brand' => (string) get_post_meta($itemId, 'helmful_brand_name', true),
            'model' => (string) get_post_meta($itemId, 'helmful_model', true),
            'year' => (string) get_post_meta($itemId, 'helmful_year', true),
            'length' => (string) get_post_meta($itemId, 'helmful_length', true),
            'price' => (string) get_post_meta($itemId, 'helmful_default_price', true),
            'type' => (string) get_post_meta($itemId, 'helmful_type', true),
        ];

        $permalink = get_permalink($item);
        $itemTitle = get_the_title($item);
        $siteName = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

        $subject = sprintf(
            __('[%s] Inventory quote request: %s', 'helmful-sync'),
            $siteName,
            $itemTitle,
        );

        $bodyLines = [
            sprintf(__('Inventory item: %s', 'helmful-sync'), $itemTitle),
            sprintf(__('Item ID: %d', 'helmful-sync'), $itemId),
        ];

        if ($meta['brand'] !== '') {
            $bodyLines[] = sprintf(__('Brand: %s', 'helmful-sync'), $meta['brand']);
        }
        if ($meta['model'] !== '') {
            $bodyLines[] = sprintf(__('Model: %s', 'helmful-sync'), $meta['model']);
        }
        if ($meta['year'] !== '') {
            $bodyLines[] = sprintf(__('Year: %s', 'helmful-sync'), $meta['year']);
        }
        if ($meta['length'] !== '') {
            $bodyLines[] = sprintf(__('Length: %s ft', 'helmful-sync'), $meta['length']);
        }
        if ($meta['price'] !== '' && is_numeric($meta['price'])) {
            $bodyLines[] = sprintf(__('Price: $%s', 'helmful-sync'), number_format_i18n((float) $meta['price'], 0));
        }
        if (is_string($permalink) && $permalink !== '') {
            $bodyLines[] = sprintf(__('Listing URL: %s', 'helmful-sync'), $permalink);
        }

        $bodyLines[] = '';
        $bodyLines[] = sprintf(__('Name: %s', 'helmful-sync'), $name);
        $bodyLines[] = sprintf(__('Email: %s', 'helmful-sync'), $email);

        if ($phone !== '') {
            $bodyLines[] = sprintf(__('Phone: %s', 'helmful-sync'), $phone);
        }

        if ($message !== '') {
            $bodyLines[] = '';
            $bodyLines[] = __('Message:', 'helmful-sync');
            $bodyLines[] = $message;
        }

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            sprintf('Reply-To: %s <%s>', $name, $email),
        ];

        $sent = wp_mail($recipient, $subject, implode("\n", $bodyLines), $headers);

        if (! $sent) {
            wp_send_json_error([
                'message' => __('Unable to send your request right now. Please try again later.', 'helmful-sync'),
            ], 500);
        }

        wp_send_json_success([
            'message' => __('Your quote request has been sent. We will be in touch soon.', 'helmful-sync'),
        ]);
    }
}
