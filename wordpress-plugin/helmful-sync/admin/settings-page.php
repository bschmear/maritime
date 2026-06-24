<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$layoutDescriptions = [
    Helmful_Sync_Display::LAYOUT_STACKED   => __('Full-width cards with nested events.', 'helmful-sync'),
    Helmful_Sync_Display::LAYOUT_GRID      => __('Responsive multi-column card grid.', 'helmful-sync'),
    Helmful_Sync_Display::LAYOUT_TIMELINE  => __('Events ordered by date on a timeline.', 'helmful-sync'),
    Helmful_Sync_Display::LAYOUT_COMPACT   => __('Dense table-style rows for scanning.', 'helmful-sync'),
];

$tabUrl = static function (string $tab) use ($notice, $error, $revealedKey): string {
    $args = ['page' => 'helmful-sync', 'tab' => $tab];
    if ($notice !== '') {
        $args['helmful_notice'] = $notice;
    }
    if ($error !== '') {
        $args['helmful_error'] = $error;
    }
    if ($revealedKey !== '') {
        $args['helmful_new_key'] = $revealedKey;
    }

    return add_query_arg($args, admin_url('options-general.php'));
};

?>
<div class="wrap helmful-sync-settings">
    <h1><?php esc_html_e('Helmful Sync', 'helmful-sync'); ?></h1>
    <p><?php esc_html_e('Connect WordPress to Helmful, sync boat shows, and control how they appear on your site.', 'helmful-sync'); ?></p>

    <?php if ($notice !== '') : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>

    <?php if ($error !== '') : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper helmful-admin-tabs" aria-label="<?php esc_attr_e('Helmful Sync sections', 'helmful-sync'); ?>">
        <a href="<?php echo esc_url($tabUrl('connection')); ?>" class="nav-tab<?php echo $activeTab === 'connection' ? ' nav-tab-active' : ''; ?>" data-tab="connection">
            <?php esc_html_e('Connection', 'helmful-sync'); ?>
        </a>
        <a href="<?php echo esc_url($tabUrl('display')); ?>" class="nav-tab<?php echo $activeTab === 'display' ? ' nav-tab-active' : ''; ?>" data-tab="display">
            <?php esc_html_e('Display', 'helmful-sync'); ?>
        </a>
        <a href="<?php echo esc_url($tabUrl('shortcodes')); ?>" class="nav-tab<?php echo $activeTab === 'shortcodes' ? ' nav-tab-active' : ''; ?>" data-tab="shortcodes">
            <?php esc_html_e('Shortcodes', 'helmful-sync'); ?>
        </a>
    </nav>

    <!-- ====================================================
         CONNECTION TAB
         ==================================================== -->
    <div class="helmful-admin-panel<?php echo $activeTab === 'connection' ? ' is-active' : ''; ?>" data-panel="connection">

        <!-- Helmful credentials -->
        <div class="helmful-section">
            <h2><?php esc_html_e('Helmful credentials', 'helmful-sync'); ?></h2>
            <p><?php esc_html_e('Your workspace domain and API key from the Helmful integrations page.', 'helmful-sync'); ?></p>

            <form method="post" action="options.php">
                <?php settings_fields('helmful_sync'); ?>
                <input type="hidden" name="helmful_active_tab" value="connection">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="tenant_domain"><?php esc_html_e('Tenant domain', 'helmful-sync'); ?></label></th>
                        <td>
                            <input name="helmful_sync_settings[tenant_domain]" id="tenant_domain" type="text" class="regular-text" value="<?php echo esc_attr($settings['tenant_domain'] ?? ''); ?>" placeholder="762332.maritime.test" />
                            <p class="description"><?php esc_html_e('For local sites, use the domain only — no https:// prefix.', 'helmful-sync'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="helmful_api_key"><?php esc_html_e('API key', 'helmful-sync'); ?></label></th>
                        <td>
                            <input name="helmful_sync_settings[helmful_api_key]" id="helmful_api_key" type="password" class="regular-text" value="<?php echo esc_attr($settings['helmful_api_key'] ?? ''); ?>" autocomplete="off" />
                            <p class="description"><?php esc_html_e('Generated on the Helmful WordPress integration page.', 'helmful-sync'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save credentials', 'helmful-sync')); ?>
            </form>
        </div>

        <!-- WordPress API key -->
        <div class="helmful-section">
            <h2><?php esc_html_e('WordPress API key', 'helmful-sync'); ?></h2>
            <p><?php esc_html_e('Generate a key here and paste it into Helmful so it can push data to this site.', 'helmful-sync'); ?></p>

            <?php if (Helmful_Sync_Settings::has_api_key()) : ?>
                <div class="helmful-status-badge"><?php esc_html_e('API key configured', 'helmful-sync'); ?></div>
            <?php endif; ?>

            <?php if ($revealedKey !== '') : ?>
                <code class="helmful-key-reveal"><?php echo esc_html($revealedKey); ?></code>
                <p class="description" style="margin-bottom:1rem;"><?php esc_html_e('Copy this key and paste it into Helmful. It won\'t be shown again.', 'helmful-sync'); ?></p>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('helmful_sync_generate_key'); ?>
                <input type="hidden" name="action" value="helmful_sync_generate_key">
                <input type="hidden" name="helmful_active_tab" value="connection">
                <?php submit_button(__('Generate new API key', 'helmful-sync'), 'secondary'); ?>
            </form>
        </div>

        <!-- Sync -->
        <div class="helmful-section">
            <h2><?php esc_html_e('Sync', 'helmful-sync'); ?></h2>
            <p><?php esc_html_e('Pull all boat shows and events from Helmful into WordPress, or test that the connection is working.', 'helmful-sync'); ?></p>

            <?php if ($lastPull !== '') : ?>
                <p class="description" style="margin-bottom:1rem;"><?php echo esc_html(sprintf(__('Last pull: %s', 'helmful-sync'), $lastPull)); ?></p>
            <?php endif; ?>

            <div class="helmful-action-row">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('helmful_sync_test'); ?>
                    <input type="hidden" name="action" value="helmful_sync_test">
                    <input type="hidden" name="helmful_active_tab" value="connection">
                    <?php submit_button(__('Test connection', 'helmful-sync'), 'secondary', 'submit', false); ?>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('helmful_sync_pull'); ?>
                    <input type="hidden" name="action" value="helmful_sync_pull">
                    <input type="hidden" name="helmful_active_tab" value="connection">
                    <?php submit_button(__('Pull from Helmful', 'helmful-sync'), 'primary', 'submit', false); ?>
                </form>
            </div>
        </div>

    </div><!-- /connection -->

    <!-- ====================================================
         DISPLAY TAB
         ==================================================== -->
    <div class="helmful-admin-panel<?php echo $activeTab === 'display' ? ' is-active' : ''; ?>" data-panel="display">

        <div class="helmful-section">
            <form method="post" action="options.php">
                <?php settings_fields('helmful_sync'); ?>
                <input type="hidden" name="helmful_active_tab" value="display">

                <!-- Layout -->
                <h3><?php esc_html_e('Layout', 'helmful-sync'); ?></h3>
                <div class="helmful-layout-picker" role="radiogroup" aria-label="<?php esc_attr_e('Boat show layout', 'helmful-sync'); ?>">
                    <?php foreach (Helmful_Sync_Display::layout_options() as $value => $label) : ?>
                        <?php
                        $thumbClass = match ($value) {
                            Helmful_Sync_Display::LAYOUT_GRID     => 'helmful-thumb-grid',
                            Helmful_Sync_Display::LAYOUT_TIMELINE => 'helmful-thumb-timeline',
                            Helmful_Sync_Display::LAYOUT_COMPACT  => 'helmful-thumb-compact',
                            default                               => 'helmful-thumb-stacked',
                        };
                        ?>
                        <label class="helmful-layout-option">
                            <input
                                type="radio"
                                name="helmful_sync_settings[display][layout]"
                                value="<?php echo esc_attr($value); ?>"
                                <?php checked($display['layout'], $value); ?>
                            >
                            <span class="helmful-layout-card">
                                <span class="helmful-layout-card__thumb <?php echo esc_attr($thumbClass); ?>">
                                    <?php if ($value === Helmful_Sync_Display::LAYOUT_STACKED) : ?>
                                        <span class="bar bar--lg"></span><span class="bar"></span><span class="bar"></span>
                                    <?php elseif ($value === Helmful_Sync_Display::LAYOUT_GRID) : ?>
                                        <span></span><span></span><span></span><span></span>
                                    <?php elseif ($value === Helmful_Sync_Display::LAYOUT_TIMELINE) : ?>
                                        <span></span><span></span><span></span>
                                    <?php else : ?>
                                        <span></span><span></span><span></span>
                                    <?php endif; ?>
                                </span>
                                <span class="helmful-layout-card__title"><?php echo esc_html($label); ?></span>
                                <span class="helmful-layout-card__desc"><?php echo esc_html($layoutDescriptions[$value] ?? ''); ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <!-- Content options -->
                <h3><?php esc_html_e('Content', 'helmful-sync'); ?></h3>
                <table class="form-table" role="presentation">
                    <tr class="helmful-field--columns<?php echo $display['layout'] !== Helmful_Sync_Display::LAYOUT_GRID ? ' is-hidden' : ''; ?>">
                        <th scope="row"><label for="helmful_columns"><?php esc_html_e('Grid columns', 'helmful-sync'); ?></label></th>
                        <td>
                            <select name="helmful_sync_settings[display][columns]" id="helmful_columns">
                                <option value="2" <?php selected($display['columns'], 2); ?>><?php esc_html_e('2 columns', 'helmful-sync'); ?></option>
                                <option value="3" <?php selected($display['columns'], 3); ?>><?php esc_html_e('3 columns', 'helmful-sync'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Descriptions', 'helmful-sync'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="helmful_sync_settings[display][show_description]" value="1" <?php checked($display['show_description']); ?>>
                                    <?php esc_html_e('Show boat show descriptions', 'helmful-sync'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <!-- Design -->
                <h3><?php esc_html_e('Design', 'helmful-sync'); ?></h3>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="helmful_accent_color"><?php esc_html_e('Accent color', 'helmful-sync'); ?></label></th>
                        <td>
                            <div class="helmful-color-field">
                                <input type="color" id="helmful_accent_color" name="helmful_sync_settings[display][accent_color]" value="<?php echo esc_attr($display['accent_color']); ?>">
                                <input type="text" id="helmful_accent_color_text" value="<?php echo esc_attr($display['accent_color']); ?>" pattern="^#[0-9a-fA-F]{6}$" aria-label="<?php esc_attr_e('Accent color hex value', 'helmful-sync'); ?>">
                            </div>
                            <p class="description"><?php esc_html_e('Used for buttons, timeline markers, and table headers.', 'helmful-sync'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="helmful_card_style"><?php esc_html_e('Card style', 'helmful-sync'); ?></label></th>
                        <td>
                            <select name="helmful_sync_settings[display][card_style]" id="helmful_card_style">
                                <?php foreach (Helmful_Sync_Display_Settings::card_style_options() as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($display['card_style'], $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="helmful_spacing"><?php esc_html_e('Spacing', 'helmful-sync'); ?></label></th>
                        <td>
                            <select name="helmful_sync_settings[display][spacing]" id="helmful_spacing">
                                <?php foreach (Helmful_Sync_Display_Settings::spacing_options() as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($display['spacing'], $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <!-- Preview -->
                <div class="helmful-display-preview">
                    <h3><?php esc_html_e('Style preview', 'helmful-sync'); ?></h3>
                    <div class="helmful-display-preview__frame" data-preview-layout="<?php echo esc_attr($display['layout']); ?>">
                        <div class="helmful-boat-shows helmful-layout-<?php echo esc_attr($display['layout']); ?>">
                            <article class="helmful-boat-show">
                                <header class="helmful-boat-show__header">
                                    <h3 class="helmful-boat-show__title"><a class="helmful-boat-show__title-link" href="#"><?php esc_html_e('Sample Boat Show', 'helmful-sync'); ?></a></h3>
                                    <?php if ($display['show_description']) : ?>
                                        <div class="helmful-boat-show__description"><?php esc_html_e('Preview of how synced boat shows will look on your site.', 'helmful-sync'); ?></div>
                                    <?php endif; ?>
                                </header>
                                <ul class="helmful-event-list helmful-event-list--nested">
                                    <li class="helmful-event">
                                        <div class="helmful-event__body">
                                            <div class="helmful-event__title"><a class="helmful-event__title-link" href="#"><?php esc_html_e('Opening Day', 'helmful-sync'); ?></a></div>
                                            <div class="helmful-event__dates"><span class="helmful-icon" aria-hidden="true">📅</span><?php esc_html_e('Jun 12 – Jun 14, 2026', 'helmful-sync'); ?></div>
                                            <div class="helmful-event__location"><span class="helmful-icon" aria-hidden="true">📍</span><?php esc_html_e('Miami Beach, FL', 'helmful-sync'); ?></div>
                                            <div class="helmful-event__links">
                                                <a class="helmful-link helmful-link--primary" href="#"><?php esc_html_e('View event', 'helmful-sync'); ?></a>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </article>
                        </div>
                    </div>
                </div>

                <?php submit_button(__('Save display settings', 'helmful-sync')); ?>
            </form>
        </div>

    </div><!-- /display -->

    <!-- ====================================================
         SHORTCODES TAB
         ==================================================== -->
    <div class="helmful-admin-panel<?php echo $activeTab === 'shortcodes' ? ' is-active' : ''; ?>" data-panel="shortcodes">

        <div class="helmful-section">
            <h2><?php esc_html_e('Shortcodes', 'helmful-sync'); ?></h2>
            <p><?php esc_html_e('Add these to any page or post. They use your Display settings by default — individual attributes override them.', 'helmful-sync'); ?></p>
            <p class="description"><?php esc_html_e('Create a Page at /boat-shows/ with [helmful_boat_shows] for the listing. Single show and event URLs use custom Helmful templates automatically (not blog posts). After updating, save Permalinks once.', 'helmful-sync'); ?></p>

            <div class="helmful-shortcode-grid">
                <div class="helmful-shortcode-card helmful-shortcode-card--featured">
                    <p class="helmful-shortcode-card__label"><?php esc_html_e('Recommended', 'helmful-sync'); ?></p>
                    <code>[helmful_boat_shows]</code>
                    <p><?php esc_html_e('Displays all boat shows using your saved layout and design settings.', 'helmful-sync'); ?></p>
                </div>

                <div class="helmful-shortcode-card">
                    <p class="helmful-shortcode-card__label"><?php esc_html_e('Override layout', 'helmful-sync'); ?></p>
                    <code>[helmful_boat_shows layout="grid"]</code>
                    <p><?php esc_html_e('Force a specific layout: stacked, grid, timeline, or compact.', 'helmful-sync'); ?></p>
                </div>

                <div class="helmful-shortcode-card">
                    <p class="helmful-shortcode-card__label"><?php esc_html_e('Single show', 'helmful-sync'); ?></p>
                    <code>[helmful_boat_shows slug="miami-boat-show"]</code>
                    <p><?php esc_html_e('Display one boat show by its Helmful slug.', 'helmful-sync'); ?></p>
                </div>

                <div class="helmful-shortcode-card">
                    <p class="helmful-shortcode-card__label"><?php esc_html_e('Events only', 'helmful-sync'); ?></p>
                    <code>[helmful_boat_show_events]</code>
                    <p><?php esc_html_e('List boat show events without the parent show wrapper.', 'helmful-sync'); ?></p>
                </div>

                <div class="helmful-shortcode-card">
                    <p class="helmful-shortcode-card__label"><?php esc_html_e('Filter by year', 'helmful-sync'); ?></p>
                    <code>[helmful_boat_show_events year="2026"]</code>
                    <p><?php esc_html_e('Show only events from a specific year.', 'helmful-sync'); ?></p>
                </div>
            </div>
        </div>

    </div><!-- /shortcodes -->

</div>
