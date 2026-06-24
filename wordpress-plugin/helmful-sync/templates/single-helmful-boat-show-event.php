<?php
/**
 * Single boat show event template.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$post = get_queried_object();
if (! $post instanceof WP_Post) {
    return;
}

get_header();
?>
<main id="primary" class="site-main helmful-sync-main">
    <?php echo Helmful_Sync_Display::render_single_event($post); ?>
</main>
<?php
get_footer();
