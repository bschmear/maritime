<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Helmful_Sync_Display
{
    public const LAYOUT_STACKED = 'stacked';

    public const LAYOUT_GRID = 'grid';

    public const LAYOUT_TIMELINE = 'timeline';

    public const LAYOUT_COMPACT = 'compact';

    private static function wrap_in_shell(string $html): string
    {
        if ($html === '') {
            return '';
        }

        return '<div class="helmful-boat-shows-shell">'.$html.'</div>';
    }

    /**
     * @return array<string, string>
     */
    public static function layout_options(): array
    {
        return [
            self::LAYOUT_STACKED => __('Stacked cards', 'helmful-sync'),
            self::LAYOUT_GRID => __('Grid cards', 'helmful-sync'),
            self::LAYOUT_TIMELINE => __('Event timeline', 'helmful-sync'),
            self::LAYOUT_COMPACT => __('Compact list', 'helmful-sync'),
        ];
    }

    /**
     * @param  array<string, string>|string  $atts
     */
    public static function render_shortcode_boat_shows($atts = []): string
    {
        self::enqueue_assets();

        $atts = shortcode_atts([
            'slug' => '',
            'uuid' => '',
            'limit' => '0',
            'layout' => '',
        ], is_array($atts) ? $atts : [], 'helmful_boat_shows');

        $shows = self::query_shows($atts);
        if ($shows === []) {
            return self::empty_message(__('No boat shows found. Sync from Helmful first.', 'helmful-sync'));
        }

        $display = Helmful_Sync_Display_Settings::get();
        $options = [
            'columns' => $display['columns'],
            'show_description' => $display['show_description'],
        ];

        $layout = sanitize_key($atts['layout']);
        if ($layout === '' || ! array_key_exists($layout, self::layout_options())) {
            $layout = $display['layout'];
        }

        return match ($layout) {
            self::LAYOUT_GRID => self::render_grid($shows, $options),
            self::LAYOUT_TIMELINE => self::render_timeline($shows, $options),
            self::LAYOUT_COMPACT => self::render_compact($shows, $options),
            default => self::render_stacked($shows, $options),
        };
    }

    /**
     * @param  array<string, string>|string  $atts
     */
    public static function render_shortcode_events($atts = []): string
    {
        self::enqueue_assets();

        $atts = shortcode_atts([
            'show' => '',
            'year' => '',
            'limit' => '0',
        ], is_array($atts) ? $atts : [], 'helmful_boat_show_events');

        $events = self::query_events([
            'show' => $atts['show'],
            'year' => $atts['year'],
            'limit' => $atts['limit'],
        ]);

        if ($events === []) {
            return self::empty_message(__('No boat show events found. Sync from Helmful first.', 'helmful-sync'));
        }

        $options = [];

        ob_start();
        echo '<div class="helmful-boat-shows-shell"><div class="helmful-boat-show-events helmful-layout-stacked">';
        echo '<ul class="helmful-event-list">';

        foreach ($events as $event) {
            self::render_event_item($event, $options);
        }

        echo '</ul></div></div>';

        return (string) ob_get_clean();
    }

    /**
     * @param  list<WP_Post>  $shows
     * @param  array{columns: int, show_description: bool}  $options
     */
    private static function render_stacked(array $shows, array $options): string
    {
        ob_start();
        echo '<div class="helmful-boat-shows-shell"><div class="helmful-boat-shows helmful-layout-stacked">';

        foreach ($shows as $show) {
            self::render_show_card($show, $options);
        }

        echo '</div></div>';

        return (string) ob_get_clean();
    }

    /**
     * @param  list<WP_Post>  $shows
     * @param  array{columns: int, show_description: bool}  $options
     */
    private static function render_grid(array $shows, array $options): string
    {
        $columns = $options['columns'] ?? 2;

        ob_start();
        echo '<div class="helmful-boat-shows-shell">';
        printf(
            '<div class="helmful-boat-shows helmful-layout-grid" style="--helmful-columns:%d">',
            $columns,
        );

        foreach ($shows as $show) {
            self::render_grid_show_card($show, $options);
        }

        echo '</div></div>';

        return (string) ob_get_clean();
    }

    /**
     * @param  list<WP_Post>  $shows
     * @param  array{columns: int, show_description: bool}  $options
     */
    private static function render_timeline(array $shows, array $options): string
    {
        $events = [];

        foreach ($shows as $show) {
            foreach (self::events_for_show($show) as $event) {
                $events[] = [
                    'event' => $event,
                    'show' => $show,
                ];
            }
        }

        usort($events, static function (array $a, array $b): int {
            $aDate = (string) get_post_meta($a['event']->ID, 'helmful_starts_at', true);
            $bDate = (string) get_post_meta($b['event']->ID, 'helmful_starts_at', true);

            return strcmp($bDate, $aDate);
        });

        ob_start();
        echo '<div class="helmful-boat-shows-shell"><div class="helmful-boat-shows helmful-layout-timeline"><ol class="helmful-timeline">';

        foreach ($events as $row) {
            self::render_timeline_item($row['show'], $row['event'], $options);
        }

        echo '</ol></div></div>';

        return (string) ob_get_clean();
    }

    /**
     * @param  list<WP_Post>  $shows
     * @param  array{columns: int, show_description: bool}  $options
     */
    private static function render_compact(array $shows, array $options): string
    {
        ob_start();
        echo '<div class="helmful-boat-shows-shell"><div class="helmful-boat-shows helmful-layout-compact">';
        echo '<div class="helmful-compact-table" role="table">';
        echo '<div class="helmful-compact-row helmful-compact-row--head" role="row">';
        echo '<div role="columnheader">'.esc_html__('Show', 'helmful-sync').'</div>';
        echo '<div role="columnheader">'.esc_html__('Event', 'helmful-sync').'</div>';
        echo '<div role="columnheader">'.esc_html__('When', 'helmful-sync').'</div>';
        echo '<div role="columnheader">'.esc_html__('Where', 'helmful-sync').'</div>';
        echo '<div role="columnheader">'.esc_html__('Details', 'helmful-sync').'</div>';
        echo '</div>';

        foreach ($shows as $show) {
            $events = self::events_for_show($show);

            if ($events === []) {
                self::render_compact_row($show, null, $options);

                continue;
            }

            foreach ($events as $event) {
                self::render_compact_row($show, $event, $options);
            }
        }

        echo '</div></div></div>';

        return (string) ob_get_clean();
    }

    /**
     * @param  array{columns: int, show_description: bool}  $options
     */
    private static function render_show_card(WP_Post $show, array $options, bool $compact = false): void
    {
        $website = (string) get_post_meta($show->ID, 'helmful_website', true);
        $events = self::events_for_show($show);

        $cardClass = 'helmful-boat-show'.($compact ? ' helmful-boat-show--grid-card' : '');
        $showMeta = self::show_meta($show);

        echo '<article class="'.esc_attr($cardClass).'">';
        echo '<header class="helmful-boat-show__header'.($showMeta['logo_url'] !== '' ? ' helmful-boat-show__header--with-logo' : '').'">';

        if ($showMeta['logo_url'] !== '') {
            echo '<div class="helmful-logo-chip helmful-logo-chip--lg" aria-hidden="true">';
            self::render_image($showMeta['logo_url'], '', 'helmful-logo-chip__img');
            echo '</div>';
        }

        echo '<div class="helmful-boat-show__summary">';
        echo '<h3 class="helmful-boat-show__title">';
        self::render_post_link($show, 'helmful-boat-show__title-link');
        echo '</h3>';

        if (($options['show_description'] ?? true) && $show->post_content !== '') {
            echo '<div class="helmful-boat-show__description">'.wp_kses_post(wpautop($show->post_content)).'</div>';
        }

        if ($website !== '') {
            echo '<div class="helmful-boat-show__links">';
            echo '<a class="helmful-link helmful-link--primary" href="'.esc_url($website).'" target="_blank" rel="noopener noreferrer">'.esc_html__('Official website', 'helmful-sync').'</a>';
            echo '</div>';
        }

        echo '</div></header>';

        if ($events !== []) {
            echo '<ul class="helmful-event-list helmful-event-list--nested">';
            foreach ($events as $event) {
                self::render_event_item($event, $options);
            }
            echo '</ul>';
        }

        echo '</article>';
    }

    /**
     * @param  array{columns: int, show_description: bool}  $options
     */
    private static function render_grid_show_card(WP_Post $show, array $options): void
    {
        $website = (string) get_post_meta($show->ID, 'helmful_website', true);
        $events = self::events_for_show($show);
        $showMeta = self::show_meta($show);
        $permalink = self::post_permalink($show);

        echo '<article class="helmful-boat-show helmful-boat-show--grid-card">';

        if ($permalink !== '') {
            echo '<a class="helmful-grid-card__cover" href="'.esc_url($permalink).'">';
        } else {
            echo '<div class="helmful-grid-card__cover">';
        }

        if ($showMeta['logo_url'] !== '') {
            echo '<div class="helmful-grid-card__logo">';
            self::render_image($showMeta['logo_url'], get_the_title($show), 'helmful-grid-card__logo-img');
            echo '</div>';
        }

        echo '<h3 class="helmful-grid-card__title">'.esc_html(get_the_title($show)).'</h3>';

        if (($options['show_description'] ?? true) && $show->post_content !== '') {
            echo '<div class="helmful-grid-card__description">'.wp_kses_post(wpautop($show->post_content)).'</div>';
        }

        if ($permalink !== '') {
            echo '</a>';
        } else {
            echo '</div>';
        }

        if ($events !== []) {
            echo '<ul class="helmful-grid-card__events">';
            foreach (array_slice($events, 0, 3) as $event) {
                $meta = self::event_meta($event);
                $dates = self::format_date_range($meta['starts_at'], $meta['ends_at'], $meta['year']);
                echo '<li class="helmful-grid-card__event">';
                if ($meta['permalink'] !== '') {
                    echo '<a class="helmful-grid-card__event-link" href="'.esc_url($meta['permalink']).'">';
                    echo '<span class="helmful-grid-card__event-name">'.esc_html(get_the_title($event)).'</span>';
                    if ($dates !== '') {
                        echo '<span class="helmful-grid-card__event-date">'.esc_html($dates).'</span>';
                    }
                    echo '</a>';
                } else {
                    echo '<span class="helmful-grid-card__event-name">'.esc_html(get_the_title($event)).'</span>';
                }
                echo '</li>';
            }
            if (count($events) > 3) {
                echo '<li class="helmful-grid-card__event-more">';
                if ($permalink !== '') {
                    printf(
                        '<a href="%s">%s</a>',
                        esc_url($permalink),
                        esc_html(sprintf(__('+%d more events', 'helmful-sync'), count($events) - 3)),
                    );
                }
                echo '</li>';
            }
            echo '</ul>';
        }

        echo '<footer class="helmful-grid-card__footer">';
        if ($permalink !== '') {
            echo '<a class="helmful-grid-card__cta" href="'.esc_url($permalink).'">'.esc_html__('View show', 'helmful-sync').'</a>';
        }
        if ($website !== '') {
            echo '<a class="helmful-grid-card__website" href="'.esc_url($website).'" target="_blank" rel="noopener noreferrer">'.esc_html__('Official website', 'helmful-sync').'</a>';
        }
        echo '</footer>';

        echo '</article>';
    }

    /**
     * @param  array{columns: int, show_description: bool}  $options
     */
    private static function render_event_item(WP_Post $event, array $options): void
    {
        $meta = self::event_meta($event);
        $dates = self::format_date_range($meta['starts_at'], $meta['ends_at'], $meta['year']);

        echo '<li class="helmful-event">';

        if ($meta['logo_url'] !== '') {
            echo '<div class="helmful-logo-chip" aria-hidden="true">';
            self::render_image($meta['logo_url'], '', 'helmful-logo-chip__img');
            echo '</div>';
        }

        echo '<div class="helmful-event__body">';
        echo '<div class="helmful-event__title">';
        self::render_post_link($event, 'helmful-event__title-link');
        echo '</div>';

        if ($dates !== '') {
            echo '<div class="helmful-event__dates"><span class="helmful-icon" aria-hidden="true">📅</span> '.esc_html($dates).'</div>';
        }
        if ($meta['location'] !== '') {
            echo '<div class="helmful-event__location"><span class="helmful-icon" aria-hidden="true">📍</span> '.esc_html($meta['location']).'</div>';
        }
        if ($meta['booth'] !== '') {
            echo '<div class="helmful-event__booth">'.esc_html(sprintf(__('Booth %s', 'helmful-sync'), $meta['booth'])).'</div>';
        }

        if ($meta['permalink'] !== '') {
            echo '<div class="helmful-event__links">';
            echo '<a class="helmful-link helmful-link--primary" href="'.esc_url($meta['permalink']).'">'.esc_html__('View event', 'helmful-sync').'</a>';
            echo '</div>';
        }

        echo '</div></li>';
    }

    /**
     * @param  array{columns: int, show_description: bool}  $options
     */
    private static function render_timeline_item(WP_Post $show, WP_Post $event, array $options): void
    {
        $meta = self::event_meta($event);
        $dates = self::format_date_range($meta['starts_at'], $meta['ends_at'], $meta['year']);

        echo '<li class="helmful-timeline__item">';
        echo '<div class="helmful-timeline__marker" aria-hidden="true"></div>';
        echo '<div class="helmful-timeline__content">';

        if ($meta['logo_url'] !== '') {
            echo '<div class="helmful-timeline__media">';
            echo '<div class="helmful-logo-chip" aria-hidden="true">';
            self::render_image($meta['logo_url'], '', 'helmful-logo-chip__img');
            echo '</div>';
            echo '</div>';
        }

        echo '<div class="helmful-timeline__body">';
        echo '<p class="helmful-timeline__show">';
        self::render_post_link($show, 'helmful-timeline__show-link');
        echo '</p>';
        echo '<h3 class="helmful-timeline__title">';
        self::render_post_link($event, 'helmful-timeline__title-link');
        echo '</h3>';

        if ($dates !== '') {
            echo '<p class="helmful-timeline__meta">'.esc_html($dates).'</p>';
        }
        if ($meta['location'] !== '') {
            echo '<p class="helmful-timeline__meta">'.esc_html($meta['location']).'</p>';
        }
        if ($meta['booth'] !== '') {
            echo '<p class="helmful-timeline__meta">'.esc_html(sprintf(__('Booth %s', 'helmful-sync'), $meta['booth'])).'</p>';
        }

        if ($meta['permalink'] !== '') {
            echo '<div class="helmful-event__links">';
            echo '<a class="helmful-link helmful-link--primary" href="'.esc_url($meta['permalink']).'">'.esc_html__('View event', 'helmful-sync').'</a>';
            echo '</div>';
        }

        echo '</div></div></li>';
    }

    /**
     * @param  array{columns: int, show_description: bool}  $options
     */
    private static function render_compact_row(WP_Post $show, ?WP_Post $event, array $options): void
    {
        echo '<div class="helmful-compact-row" role="row">';
        echo '<div role="cell" class="helmful-compact-show">';
        if ($event === null) {
            $showMeta = self::show_meta($show);
            if ($showMeta['logo_url'] !== '') {
                echo '<div class="helmful-logo-chip" style="width:1.6rem;height:1.6rem;border-radius:4px;" aria-hidden="true">';
                self::render_image($showMeta['logo_url'], '', 'helmful-logo-chip__img');
                echo '</div>';
            }
        }
        self::render_post_link($show, 'helmful-compact-show-link');
        echo '</div>';
        echo '<div role="cell" class="helmful-compact-event">';
        if ($event) {
            $meta = self::event_meta($event);
            if ($meta['logo_url'] !== '') {
                echo '<div class="helmful-logo-chip" style="width:1.6rem;height:1.6rem;border-radius:4px;" aria-hidden="true">';
                self::render_image($meta['logo_url'], '', 'helmful-logo-chip__img');
                echo '</div>';
            }
            self::render_post_link($event, 'helmful-compact-event-link');
        } else {
            echo '—';
        }
        echo '</div>';

        if ($event) {
            $meta = $meta ?? self::event_meta($event);
            echo '<div role="cell">'.esc_html(self::format_date_range($meta['starts_at'], $meta['ends_at'], $meta['year']) ?: '—').'</div>';
            echo '<div role="cell">'.esc_html($meta['location'] !== '' ? $meta['location'] : '—').'</div>';
            echo '<div role="cell" class="helmful-compact-links">';
            if ($meta['permalink'] !== '') {
                echo '<a class="helmful-link" href="'.esc_url($meta['permalink']).'">'.esc_html__('View event', 'helmful-sync').'</a>';
            } else {
                echo '—';
            }
            echo '</div>';
        } else {
            echo '<div role="cell">—</div><div role="cell">—</div><div role="cell">—</div>';
        }

        echo '</div>';
    }

    /**
     * @return array{logo_url: string}
     */
    private static function show_meta(WP_Post $show): array
    {
        return [
            'logo_url' => esc_url((string) get_post_meta($show->ID, 'helmful_logo_url', true)),
        ];
    }

    /**
     * @return array{venue: string, city: string, state: string, booth: string, starts_at: string, ends_at: string, year: string, location: string, permalink: string, logo_url: string}
     */
    private static function event_meta(WP_Post $event): array
    {
        $venue = (string) get_post_meta($event->ID, 'helmful_venue', true);
        $city = (string) get_post_meta($event->ID, 'helmful_city', true);
        $state = (string) get_post_meta($event->ID, 'helmful_state', true);

        return [
            'venue' => $venue,
            'city' => $city,
            'state' => $state,
            'booth' => (string) get_post_meta($event->ID, 'helmful_booth', true),
            'starts_at' => (string) get_post_meta($event->ID, 'helmful_starts_at', true),
            'ends_at' => (string) get_post_meta($event->ID, 'helmful_ends_at', true),
            'year' => (string) get_post_meta($event->ID, 'helmful_year', true),
            'location' => trim(implode(', ', array_filter([$venue, $city, $state]))),
            'permalink' => self::post_permalink($event),
            'logo_url' => esc_url((string) get_post_meta($event->ID, 'helmful_logo_url', true)),
        ];
    }

    private static function render_image(string $url, string $alt, string $class): void
    {
        if ($url === '') {
            return;
        }

        printf(
            '<img class="%s" src="%s" alt="%s" loading="lazy" decoding="async">',
            esc_attr($class),
            esc_url($url),
            esc_attr($alt),
        );
    }

    private static function post_permalink(WP_Post $post): string
    {
        $permalink = get_permalink($post);

        return is_string($permalink) ? $permalink : '';
    }

    private static function render_post_link(WP_Post $post, string $class = ''): void
    {
        $permalink = self::post_permalink($post);
        $title = get_the_title($post);

        if ($permalink === '') {
            echo esc_html($title);

            return;
        }

        printf(
            '<a class="%s" href="%s">%s</a>',
            esc_attr($class),
            esc_url($permalink),
            esc_html($title),
        );
    }

    /**
     * @param  array<string, string>  $atts
     * @return list<WP_Post>
     */
    public static function query_shows(array $atts): array
    {
        $args = [
            'post_type' => Helmful_Sync_CPT::SHOW_POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ];

        $limit = (int) ($atts['limit'] ?? 0);
        if ($limit > 0) {
            $args['numberposts'] = $limit;
        }

        $uuid = sanitize_text_field($atts['uuid'] ?? '');
        $slug = sanitize_text_field($atts['slug'] ?? '');

        if ($uuid !== '') {
            $args['meta_key'] = 'helmful_uuid';
            $args['meta_value'] = $uuid;
        } elseif ($slug !== '') {
            $args['meta_key'] = 'helmful_slug';
            $args['meta_value'] = $slug;
        }

        $posts = get_posts($args);

        return is_array($posts) ? $posts : [];
    }

    /**
     * @return list<WP_Post>
     */
    public static function events_for_show(WP_Post $show): array
    {
        $uuid = (string) get_post_meta($show->ID, 'helmful_uuid', true);
        $events = [];

        if ($uuid !== '') {
            $events = self::query_events(['show_uuid' => $uuid]);
        }

        if ($events === []) {
            $events = self::query_events(['show_post_id' => (string) $show->ID]);
        }

        return $events;
    }

    /**
     * @param  array<string, string>  $filters
     * @return list<WP_Post>
     */
    public static function query_events(array $filters): array
    {
        $args = [
            'post_type' => Helmful_Sync_CPT::EVENT_POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ];

        $limit = (int) ($filters['limit'] ?? 0);
        if ($limit > 0) {
            $args['numberposts'] = $limit;
        }

        $metaQuery = [];

        $showUuid = sanitize_text_field($filters['show_uuid'] ?? $filters['show'] ?? '');
        $showPostId = sanitize_text_field($filters['show_post_id'] ?? '');

        if ($showPostId !== '') {
            $metaQuery[] = [
                'key' => 'helmful_boat_show_post_id',
                'value' => $showPostId,
            ];
        } elseif ($showUuid !== '') {
            $metaQuery[] = [
                'key' => 'helmful_boat_show_uuid',
                'value' => $showUuid,
            ];
        }

        $year = sanitize_text_field($filters['year'] ?? '');
        if ($year !== '') {
            $metaQuery[] = [
                'key' => 'helmful_year',
                'value' => $year,
            ];
        }

        if (count($metaQuery) > 1) {
            $args['meta_query'] = array_merge(['relation' => 'AND'], $metaQuery);
        } elseif ($metaQuery !== []) {
            $args['meta_query'] = $metaQuery;
        }

        $posts = get_posts($args);
        $posts = is_array($posts) ? $posts : [];

        usort($posts, static function (WP_Post $a, WP_Post $b): int {
            $aDate = (string) get_post_meta($a->ID, 'helmful_starts_at', true);
            $bDate = (string) get_post_meta($b->ID, 'helmful_starts_at', true);

            if ($aDate !== '' || $bDate !== '') {
                return strcmp($bDate, $aDate);
            }

            $aYear = (string) get_post_meta($a->ID, 'helmful_year', true);
            $bYear = (string) get_post_meta($b->ID, 'helmful_year', true);

            return strcmp($bYear, $aYear);
        });

        return $posts;
    }

    public static function listing_page(): ?WP_Post
    {
        static $cached = false;
        static $page = null;

        if ($cached) {
            return $page;
        }

        $cached = true;

        $byPath = get_page_by_path('boat-shows');
        if ($byPath instanceof WP_Post) {
            $page = $byPath;

            return $page;
        }

        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => 100,
        ]);

        foreach ($pages as $candidate) {
            if (has_shortcode($candidate->post_content, 'helmful_boat_shows')) {
                $page = $candidate;

                return $page;
            }
        }

        return null;
    }

    public static function listing_page_url(): string
    {
        $page = self::listing_page();
        if ($page instanceof WP_Post) {
            $permalink = get_permalink($page);

            return is_string($permalink) ? $permalink : home_url('/boat-shows/');
        }

        return home_url('/boat-shows/');
    }

    public static function format_date_range(string $startsAt, string $endsAt, string $year): string
    {
        if ($startsAt === '' && $endsAt === '') {
            return $year !== '' ? $year : '';
        }

        $start = $startsAt !== '' ? date_i18n(get_option('date_format'), strtotime($startsAt)) : '';
        $end = $endsAt !== '' ? date_i18n(get_option('date_format'), strtotime($endsAt)) : '';

        if ($start !== '' && $end !== '' && $start !== $end) {
            return $start.' – '.$end;
        }

        return $start !== '' ? $start : $end;
    }

    public static function empty_message(string $message, bool $wrapShell = true): string
    {
        $html = '<p class="helmful-empty">'.esc_html($message).'</p>';

        return $wrapShell ? self::wrap_in_shell($html) : $html;
    }

    /**
     * @param  list<WP_Post>  $events
     * @return array{upcoming: list<WP_Post>, past: list<WP_Post>}
     */
    public static function partition_events_by_timing(array $events): array
    {
        $upcoming = [];
        $past = [];

        foreach ($events as $event) {
            if (self::is_event_upcoming($event)) {
                $upcoming[] = $event;
            } else {
                $past[] = $event;
            }
        }

        usort($upcoming, static fn (WP_Post $a, WP_Post $b): int => self::compare_events_chronologically($a, $b, false));
        usort($past, static fn (WP_Post $a, WP_Post $b): int => self::compare_events_chronologically($a, $b, true));

        return [
            'upcoming' => $upcoming,
            'past' => $past,
        ];
    }

    public static function is_event_upcoming(WP_Post $event): bool
    {
        $endTimestamp = self::event_end_timestamp($event);

        if ($endTimestamp === null) {
            return true;
        }

        return $endTimestamp >= current_time('timestamp');
    }

    public static function next_upcoming_event(array $events): ?WP_Post
    {
        $partitioned = self::partition_events_by_timing($events);

        return $partitioned['upcoming'][0] ?? null;
    }

    private static function compare_events_chronologically(WP_Post $a, WP_Post $b, bool $descending): int
    {
        $aTimestamp = self::event_start_timestamp($a) ?? 0;
        $bTimestamp = self::event_start_timestamp($b) ?? 0;

        if ($aTimestamp !== $bTimestamp) {
            return $descending ? $bTimestamp <=> $aTimestamp : $aTimestamp <=> $bTimestamp;
        }

        return strcmp(get_the_title($a), get_the_title($b));
    }

    private static function event_start_timestamp(WP_Post $event): ?int
    {
        $meta = self::event_meta($event);

        if ($meta['starts_at'] !== '') {
            $dateTime = date_create($meta['starts_at'].' 00:00:00', wp_timezone());

            return $dateTime instanceof DateTimeInterface ? $dateTime->getTimestamp() : null;
        }

        if ($meta['year'] !== '') {
            $dateTime = date_create($meta['year'].'-01-01 00:00:00', wp_timezone());

            return $dateTime instanceof DateTimeInterface ? $dateTime->getTimestamp() : null;
        }

        return null;
    }

    private static function event_end_timestamp(WP_Post $event): ?int
    {
        $meta = self::event_meta($event);

        if ($meta['ends_at'] !== '') {
            $dateTime = date_create($meta['ends_at'].' 23:59:59', wp_timezone());

            return $dateTime instanceof DateTimeInterface ? $dateTime->getTimestamp() : null;
        }

        if ($meta['starts_at'] !== '') {
            $dateTime = date_create($meta['starts_at'].' 23:59:59', wp_timezone());

            return $dateTime instanceof DateTimeInterface ? $dateTime->getTimestamp() : null;
        }

        if ($meta['year'] !== '') {
            $dateTime = date_create($meta['year'].'-12-31 23:59:59', wp_timezone());

            return $dateTime instanceof DateTimeInterface ? $dateTime->getTimestamp() : null;
        }

        return null;
    }

    private static function event_countdown_iso(WP_Post $event): ?string
    {
        $timestamp = self::event_start_timestamp($event);
        if ($timestamp === null) {
            return null;
        }

        $dateTime = (new DateTimeImmutable('@'.$timestamp))->setTimezone(wp_timezone());

        return $dateTime->format(DateTimeInterface::ATOM);
    }

    private static function render_countdown(WP_Post $event, string $eyebrow): void
    {
        if (! self::is_event_upcoming($event)) {
            return;
        }

        $iso = self::event_countdown_iso($event);
        if ($iso === null) {
            return;
        }

        $meta = self::event_meta($event);
        $dates = self::format_date_range($meta['starts_at'], $meta['ends_at'], $meta['year']);

        echo '<aside class="helmful-countdown" data-helmful-countdown data-target="'.esc_attr($iso).'">';
        echo '<p class="helmful-countdown__eyebrow">'.esc_html($eyebrow).'</p>';
        echo '<p class="helmful-countdown__event-name">'.esc_html(get_the_title($event)).'</p>';

        if ($dates !== '') {
            echo '<p class="helmful-countdown__event-date">'.esc_html($dates).'</p>';
        }

        echo '<div class="helmful-countdown__grid" aria-live="polite">';

        foreach ([
            'days' => __('Days', 'helmful-sync'),
            'hours' => __('Hours', 'helmful-sync'),
            'minutes' => __('Minutes', 'helmful-sync'),
            'seconds' => __('Seconds', 'helmful-sync'),
        ] as $unit => $label) {
            echo '<div class="helmful-countdown__cell">';
            echo '<span class="helmful-countdown__value" data-unit="'.esc_attr($unit).'">—</span>';
            echo '<span class="helmful-countdown__label">'.esc_html($label).'</span>';
            echo '</div>';
        }

        echo '</div>';
        echo '<p class="helmful-countdown__elapsed" hidden>'.esc_html__('This event is underway or has already started.', 'helmful-sync').'</p>';
        echo '</aside>';
    }

    /**
     * @param  list<WP_Post>  $events
     */
    private static function render_partitioned_event_cards(array $events): void
    {
        $partitioned = self::partition_events_by_timing($events);

        if ($partitioned['upcoming'] !== []) {
            self::render_event_cards_group(__('Upcoming events', 'helmful-sync'), $partitioned['upcoming'], false);
        }

        if ($partitioned['past'] !== []) {
            self::render_event_cards_group(__('Past events', 'helmful-sync'), $partitioned['past'], true);
        }
    }

    /**
     * @param  list<WP_Post>  $events
     */
    private static function render_event_cards_group(string $heading, array $events, bool $isPast): void
    {
        echo '<div class="helmful-event-group'.($isPast ? ' helmful-event-group--past' : ' helmful-event-group--upcoming').'">';
        echo '<h3 class="helmful-event-group__title">'.esc_html($heading).'</h3>';
        echo '<div class="helmful-event-cards">';

        foreach ($events as $event) {
            self::render_event_card($event, $isPast);
        }

        echo '</div></div>';
    }

    public static function render_single_show(WP_Post $show): string
    {
        self::enqueue_assets();

        $showMeta = self::show_meta($show);
        $website = (string) get_post_meta($show->ID, 'helmful_website', true);
        $events = self::events_for_show($show);

        ob_start();
        echo '<div class="helmful-boat-shows-shell"><div class="helmful-template helmful-template--show">';

        echo '<nav class="helmful-breadcrumb" aria-label="'.esc_attr__('Breadcrumb', 'helmful-sync').'">';
        echo '<a href="'.esc_url(self::listing_page_url()).'">'.esc_html__('Boat Shows', 'helmful-sync').'</a>';
        echo '<span aria-hidden="true">/</span>';
        echo '<span>'.esc_html(get_the_title($show)).'</span>';
        echo '</nav>';

        echo '<header class="helmful-single-hero">';
        if ($showMeta['logo_url'] !== '') {
            echo '<div class="helmful-single-hero__logo">';
            self::render_image($showMeta['logo_url'], get_the_title($show), 'helmful-single-hero__logo-img');
            echo '</div>';
        }
        echo '<div class="helmful-single-hero__content">';
        echo '<p class="helmful-single-eyebrow">'.esc_html__('Boat Show', 'helmful-sync').'</p>';
        echo '<h1 class="helmful-single-title">'.esc_html(get_the_title($show)).'</h1>';
        if ($show->post_content !== '') {
            echo '<div class="helmful-single-intro">'.wp_kses_post(wpautop($show->post_content)).'</div>';
        }
        if ($website !== '') {
            echo '<div class="helmful-boat-show__links">';
            echo '<a class="helmful-link helmful-link--primary" href="'.esc_url($website).'" target="_blank" rel="noopener noreferrer">'.esc_html__('Official website', 'helmful-sync').'</a>';
            echo '</div>';
        }
        echo '</div></header>';

        $partitioned = self::partition_events_by_timing($events);
        $nextEvent = $partitioned['upcoming'][0] ?? null;

        if ($nextEvent instanceof WP_Post) {
            self::render_countdown($nextEvent, __('Next upcoming event', 'helmful-sync'));
        }

        echo '<section class="helmful-single-section">';
        echo '<div class="helmful-single-section__head">';
        echo '<h2>'.esc_html__('Events', 'helmful-sync').'</h2>';

        $summaryParts = [];
        if ($partitioned['upcoming'] !== []) {
            $summaryParts[] = sprintf(
                _n('%d upcoming', '%d upcoming', count($partitioned['upcoming']), 'helmful-sync'),
                count($partitioned['upcoming']),
            );
        }
        if ($partitioned['past'] !== []) {
            $summaryParts[] = sprintf(
                _n('%d past', '%d past', count($partitioned['past']), 'helmful-sync'),
                count($partitioned['past']),
            );
        }

        echo '<p>'.esc_html($summaryParts !== [] ? implode(' · ', $summaryParts) : __('No events yet', 'helmful-sync')).'</p>';
        echo '</div>';

        if ($events === []) {
            echo self::empty_message(__('No events synced for this show yet.', 'helmful-sync'), false);
        } else {
            self::render_partitioned_event_cards($events);
        }

        echo '</section></div></div>';

        return (string) ob_get_clean();
    }

    public static function render_single_event(WP_Post $event): string
    {
        self::enqueue_assets();

        $meta = self::event_meta($event);
        $dates = self::format_date_range($meta['starts_at'], $meta['ends_at'], $meta['year']);
        $parentShow = self::find_parent_show_for_event($event);
        $address = trim(implode(', ', array_filter([
            (string) get_post_meta($event->ID, 'helmful_address_line_1', true),
            (string) get_post_meta($event->ID, 'helmful_address_line_2', true),
            $meta['city'],
            $meta['state'],
            (string) get_post_meta($event->ID, 'helmful_postal_code', true),
            (string) get_post_meta($event->ID, 'helmful_country', true),
        ])));
        $latitude = (string) get_post_meta($event->ID, 'helmful_latitude', true);
        $longitude = (string) get_post_meta($event->ID, 'helmful_longitude', true);
        $mapsUrl = ($latitude !== '' && $longitude !== '')
            ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($latitude.','.$longitude)
            : ($address !== '' ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address) : '');

        ob_start();
        echo '<div class="helmful-boat-shows-shell"><div class="helmful-template helmful-template--event">';

        echo '<nav class="helmful-breadcrumb" aria-label="'.esc_attr__('Breadcrumb', 'helmful-sync').'">';
        echo '<a href="'.esc_url(self::listing_page_url()).'">'.esc_html__('Boat Shows', 'helmful-sync').'</a>';
        echo '<span aria-hidden="true">/</span>';
        if ($parentShow instanceof WP_Post) {
            echo '<a href="'.esc_url(self::post_permalink($parentShow)).'">'.esc_html(get_the_title($parentShow)).'</a>';
            echo '<span aria-hidden="true">/</span>';
        }
        echo '<span>'.esc_html(get_the_title($event)).'</span>';
        echo '</nav>';

        echo '<header class="helmful-single-hero helmful-single-hero--event">';
        if ($meta['logo_url'] !== '') {
            echo '<div class="helmful-single-hero__logo">';
            self::render_image($meta['logo_url'], get_the_title($event), 'helmful-single-hero__logo-img');
            echo '</div>';
        }
        echo '<div class="helmful-single-hero__content">';
        if ($parentShow instanceof WP_Post) {
            echo '<p class="helmful-single-eyebrow"><a href="'.esc_url(self::post_permalink($parentShow)).'">'.esc_html(get_the_title($parentShow)).'</a></p>';
        }
        echo '<h1 class="helmful-single-title">'.esc_html(get_the_title($event)).'</h1>';
        if ($event->post_content !== '') {
            echo '<div class="helmful-single-intro">'.wp_kses_post(wpautop($event->post_content)).'</div>';
        }
        echo '</div></header>';

        if (self::is_event_upcoming($event)) {
            self::render_countdown($event, __('Event starts in', 'helmful-sync'));
        } else {
            echo '<p class="helmful-event-status helmful-event-status--past">'.esc_html__('Past event', 'helmful-sync').'</p>';
        }

        echo '<section class="helmful-single-section">';
        echo '<h2 class="helmful-single-section__title">'.esc_html__('Event details', 'helmful-sync').'</h2>';
        echo '<dl class="helmful-detail-grid">';

        if ($dates !== '') {
            self::render_detail_row(__('Dates', 'helmful-sync'), $dates);
        }
        if ($meta['year'] !== '') {
            self::render_detail_row(__('Year', 'helmful-sync'), $meta['year']);
        }
        if ($meta['venue'] !== '') {
            self::render_detail_row(__('Venue', 'helmful-sync'), $meta['venue']);
        }
        if ($meta['location'] !== '') {
            self::render_detail_row(__('Location', 'helmful-sync'), $meta['location']);
        }
        if ($address !== '' && $address !== $meta['location']) {
            self::render_detail_row(__('Address', 'helmful-sync'), $address);
        }
        if ($meta['booth'] !== '') {
            self::render_detail_row(__('Booth', 'helmful-sync'), $meta['booth']);
        }

        echo '</dl>';

        if ($mapsUrl !== '') {
            echo '<div class="helmful-boat-show__links">';
            echo '<a class="helmful-link helmful-link--primary" href="'.esc_url($mapsUrl).'" target="_blank" rel="noopener noreferrer">'.esc_html__('Open in maps', 'helmful-sync').'</a>';
            echo '</div>';
        }

        if ($parentShow instanceof WP_Post) {
            echo '<div class="helmful-single-back">';
            echo '<a class="helmful-link" href="'.esc_url(self::post_permalink($parentShow)).'">'.esc_html__('← Back to show', 'helmful-sync').'</a>';
            echo '</div>';
        }

        echo '</section></div></div>';

        return (string) ob_get_clean();
    }

    public static function find_parent_show_for_event(WP_Post $event): ?WP_Post
    {
        $showUuid = (string) get_post_meta($event->ID, 'helmful_boat_show_uuid', true);
        if ($showUuid === '') {
            return null;
        }

        $shows = self::query_shows(['uuid' => $showUuid]);

        return $shows[0] ?? null;
    }

    private static function render_event_card(WP_Post $event, bool $isPast = false): void
    {
        $meta = self::event_meta($event);
        $dates = self::format_date_range($meta['starts_at'], $meta['ends_at'], $meta['year']);
        $isUpcoming = ! $isPast && self::is_event_upcoming($event);

        echo '<article class="helmful-event-card'.($isPast ? ' helmful-event-card--past' : '').($isUpcoming ? ' helmful-event-card--upcoming' : '').'">';
        echo '<div class="helmful-event-card__top">';
        if ($meta['logo_url'] !== '') {
            echo '<div class="helmful-logo-chip helmful-logo-chip--lg" aria-hidden="true">';
            self::render_image($meta['logo_url'], '', 'helmful-logo-chip__img');
            echo '</div>';
        }
        echo '<div class="helmful-event-card__headline">';
        echo '<div class="helmful-event-card__badges">';
        if ($isUpcoming) {
            echo '<span class="helmful-event-card__badge helmful-event-card__badge--upcoming">'.esc_html__('Upcoming', 'helmful-sync').'</span>';
        } elseif ($isPast) {
            echo '<span class="helmful-event-card__badge helmful-event-card__badge--past">'.esc_html__('Past', 'helmful-sync').'</span>';
        }
        echo '</div>';
        echo '<h3 class="helmful-event-card__title">';
        if ($meta['permalink'] !== '') {
            printf(
                '<a class="helmful-event__title-link" href="%s">%s</a>',
                esc_url($meta['permalink']),
                esc_html(get_the_title($event)),
            );
        } else {
            echo esc_html(get_the_title($event));
        }
        echo '</h3>';
        if ($meta['year'] !== '') {
            echo '<p class="helmful-event-card__year">'.esc_html($meta['year']).'</p>';
        }
        echo '</div></div>';

        if ($dates !== '') {
            echo '<p class="helmful-event-card__meta"><span class="helmful-icon" aria-hidden="true">📅</span> '.esc_html($dates).'</p>';
        }
        if ($meta['location'] !== '') {
            echo '<p class="helmful-event-card__meta"><span class="helmful-icon" aria-hidden="true">📍</span> '.esc_html($meta['location']).'</p>';
        }
        if ($meta['booth'] !== '') {
            echo '<p class="helmful-event-card__meta">'.esc_html(sprintf(__('Booth %s', 'helmful-sync'), $meta['booth'])).'</p>';
        }

        if ($meta['permalink'] !== '') {
            echo '<div class="helmful-event__links">';
            echo '<a class="helmful-link helmful-link--primary" href="'.esc_url($meta['permalink']).'">'.esc_html__('View event details', 'helmful-sync').'</a>';
            echo '</div>';
        }

        echo '</article>';
    }

    private static function render_detail_row(string $label, string $value): void
    {
        echo '<div class="helmful-detail-grid__row">';
        echo '<dt>'.esc_html($label).'</dt>';
        echo '<dd>'.esc_html($value).'</dd>';
        echo '</div>';
    }

    public static function enqueue_assets(): void
    {
        wp_enqueue_style(
            'helmful-sync-display',
            HELMFUL_SYNC_URL.'assets/css/display.css',
            [],
            HELMFUL_SYNC_VERSION,
        );

        wp_add_inline_style('helmful-sync-display', Helmful_Sync_Display_Settings::css_variables());
    }

    /**
     * @param  array<string, string>|string  $atts
     */
    public static function render_shortcode_brands($atts = []): string
    {
        self::enqueue_assets();

        $atts = shortcode_atts([
            'columns' => '4',
            'limit' => '0',
        ], is_array($atts) ? $atts : [], 'helmful_brands');

        $brands = self::query_brands([
            'limit' => $atts['limit'],
        ]);

        if ($brands === []) {
            return self::empty_message(__('No brands found. Sync from Helmful first.', 'helmful-sync'));
        }

        $columns = max(2, min(6, (int) $atts['columns']));
        $inventoryBaseUrl = self::inventory_page_url();

        ob_start();
        echo '<div class="helmful-boat-shows-shell"><div class="helmful-brands helmful-layout-grid" style="--helmful-columns:'.$columns.'">';

        foreach ($brands as $brand) {
            $meta = self::brand_meta($brand);
            $filterUrl = $meta['slug'] !== ''
                ? add_query_arg('helmful_brand', $meta['slug'], $inventoryBaseUrl)
                : '';

            echo '<article class="helmful-brand-card">';
            if ($filterUrl !== '') {
                echo '<a class="helmful-brand-card__link" href="'.esc_url($filterUrl).'">';
            } else {
                echo '<div class="helmful-brand-card__link">';
            }

            if ($meta['logo_url'] !== '') {
                echo '<div class="helmful-brand-card__logo">';
                self::render_image($meta['logo_url'], $brand->name, 'helmful-brand-card__logo-img');
                echo '</div>';
            }

            echo '<h3 class="helmful-brand-card__title">'.esc_html($brand->name).'</h3>';
            echo $filterUrl !== '' ? '</a>' : '</div>';
            echo '</article>';
        }

        echo '</div></div>';

        return (string) ob_get_clean();
    }

    /**
     * @param  array<string, string>|string  $atts
     */
    public static function render_shortcode_inventory($atts = []): string
    {
        self::enqueue_assets();

        $atts = shortcode_atts([
            'brand' => '',
            'limit' => '0',
            'show_filter' => '1',
        ], is_array($atts) ? $atts : [], 'helmful_inventory');

        $brandFilter = sanitize_text_field($atts['brand']);
        if ($brandFilter === '' && isset($_GET['helmful_brand'])) {
            $brandFilter = sanitize_text_field(wp_unslash((string) $_GET['helmful_brand']));
        }

        $items = self::query_inventory([
            'brand' => $brandFilter,
            'limit' => $atts['limit'],
        ]);

        $brands = self::query_brands([]);
        $showFilter = filter_var($atts['show_filter'], FILTER_VALIDATE_BOOLEAN);

        ob_start();
        echo '<div class="helmful-boat-shows-shell"><div class="helmful-inventory">';

        if ($showFilter && $brands !== []) {
            $baseUrl = self::current_page_url();
            echo '<nav class="helmful-inventory-filter" aria-label="'.esc_attr__('Filter by brand', 'helmful-sync').'">';
            echo '<ul class="helmful-inventory-filter__list">';

            $allActive = $brandFilter === '';
            echo '<li class="helmful-inventory-filter__item'.($allActive ? ' is-active' : '').'">';
            echo '<a href="'.esc_url(remove_query_arg('helmful_brand', $baseUrl)).'">'.esc_html__('All brands', 'helmful-sync').'</a>';
            echo '</li>';

            foreach ($brands as $brand) {
                $meta = self::brand_meta($brand);
                if ($meta['slug'] === '') {
                    continue;
                }

                $isActive = $brandFilter === $meta['slug'] || $brandFilter === $meta['uuid'];
                $url = add_query_arg('helmful_brand', $meta['slug'], remove_query_arg('helmful_brand', $baseUrl));

                echo '<li class="helmful-inventory-filter__item'.($isActive ? ' is-active' : '').'">';
                echo '<a href="'.esc_url($url).'">'.esc_html($brand->name).'</a>';
                echo '</li>';
            }

            echo '</ul></nav>';
        }

        if ($items === []) {
            echo self::empty_message(__('No inventory found. Sync from Helmful first.', 'helmful-sync'), false);
            echo '</div></div>';

            return (string) ob_get_clean();
        }

        echo '<div class="helmful-inventory-grid">';

        foreach ($items as $item) {
            self::render_inventory_card($item);
        }

        echo '</div></div></div>';

        return (string) ob_get_clean();
    }

    /**
     * @param  array<string, string>  $filters
     * @return list<WP_Term>
     */
    public static function query_brands(array $filters): array
    {
        $args = [
            'taxonomy' => Helmful_Sync_CPT::BRAND_TAXONOMY,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'helmful_active',
                    'value' => '1',
                ],
                [
                    'key' => 'helmful_active',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ];

        $limit = (int) ($filters['limit'] ?? 0);
        if ($limit > 0) {
            $args['number'] = $limit;
        }

        $terms = get_terms($args);

        if (! is_array($terms)) {
            return [];
        }

        return array_values(array_filter(
            $terms,
            static fn ($term): bool => $term instanceof WP_Term,
        ));
    }

    /**
     * @param  array<string, string>  $filters
     * @return list<WP_Post>
     */
    public static function query_inventory(array $filters): array
    {
        $args = [
            'post_type' => Helmful_Sync_CPT::INVENTORY_POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ];

        $limit = (int) ($filters['limit'] ?? 0);
        if ($limit > 0) {
            $args['numberposts'] = $limit;
        }

        $brand = sanitize_text_field($filters['brand'] ?? '');
        if ($brand !== '') {
            $termIds = self::brand_term_ids_for_filter($brand);
            if ($termIds !== []) {
                $args['tax_query'] = [
                    [
                        'taxonomy' => Helmful_Sync_CPT::BRAND_TAXONOMY,
                        'field' => 'term_id',
                        'terms' => $termIds,
                    ],
                ];
            } else {
                $args['meta_query'] = [
                    'relation' => 'OR',
                    [
                        'key' => 'helmful_brand_slug',
                        'value' => $brand,
                    ],
                    [
                        'key' => 'helmful_brand_uuid',
                        'value' => $brand,
                    ],
                ];
            }
        }

        $posts = get_posts($args);

        return is_array($posts) ? $posts : [];
    }

    /**
     * @return array{uuid: string, slug: string, logo_url: string}
     */
    private static function brand_meta(WP_Term $brand): array
    {
        $storedSlug = (string) get_term_meta($brand->term_id, 'helmful_slug', true);

        return [
            'uuid' => (string) get_term_meta($brand->term_id, 'helmful_uuid', true),
            'slug' => $storedSlug !== '' ? $storedSlug : $brand->slug,
            'logo_url' => esc_url((string) get_term_meta($brand->term_id, 'helmful_logo_url', true)),
        ];
    }

    /**
     * @return list<int>
     */
    private static function brand_term_ids_for_filter(string $brand): array
    {
        $term = get_term_by('slug', $brand, Helmful_Sync_CPT::BRAND_TAXONOMY);
        if ($term instanceof WP_Term) {
            return [(int) $term->term_id];
        }

        $termId = Helmful_Sync_Handler::term_id_for_uuid(Helmful_Sync_CPT::BRAND_TAXONOMY, $brand);

        return $termId > 0 ? [$termId] : [];
    }

    /**
     * @return array{brand_name: string, model: string, year: string, length: string, default_price: string, permalink: string}
     */
    private static function inventory_meta(WP_Post $item): array
    {
        return [
            'brand_name' => (string) get_post_meta($item->ID, 'helmful_brand_name', true),
            'model' => (string) get_post_meta($item->ID, 'helmful_model', true),
            'year' => (string) get_post_meta($item->ID, 'helmful_year', true),
            'length' => (string) get_post_meta($item->ID, 'helmful_length', true),
            'default_price' => (string) get_post_meta($item->ID, 'helmful_default_price', true),
            'permalink' => self::post_permalink($item),
        ];
    }

    private static function render_inventory_card(WP_Post $item): void
    {
        $meta = self::inventory_meta($item);

        echo '<article class="helmful-inventory-card">';

        if ($meta['permalink'] !== '') {
            echo '<a class="helmful-inventory-card__link" href="'.esc_url($meta['permalink']).'">';
        }

        echo '<h3 class="helmful-inventory-card__title">'.esc_html(get_the_title($item)).'</h3>';

        if ($meta['brand_name'] !== '') {
            echo '<p class="helmful-inventory-card__brand">'.esc_html($meta['brand_name']).'</p>';
        }

        $details = array_filter([
            $meta['year'] !== '' ? sprintf(__('Year %s', 'helmful-sync'), $meta['year']) : '',
            $meta['length'] !== '' ? sprintf(__('%s ft', 'helmful-sync'), $meta['length']) : '',
        ]);

        if ($details !== []) {
            echo '<p class="helmful-inventory-card__meta">'.esc_html(implode(' · ', $details)).'</p>';
        }

        if ($meta['default_price'] !== '' && is_numeric($meta['default_price'])) {
            echo '<p class="helmful-inventory-card__price">'.esc_html(self::format_price((float) $meta['default_price'])).'</p>';
        }

        if ($item->post_content !== '') {
            echo '<div class="helmful-inventory-card__description">'.wp_kses_post(wpautop($item->post_content)).'</div>';
        }

        if ($meta['permalink'] !== '') {
            echo '</a>';
        }

        echo '</article>';
    }

    private static function format_price(float $amount): string
    {
        return '$'.number_format_i18n($amount, 0);
    }

    public static function inventory_page_url(): string
    {
        static $cached = false;
        static $url = '';

        if ($cached) {
            return $url;
        }

        $cached = true;

        $byPath = get_page_by_path('inventory');
        if ($byPath instanceof WP_Post) {
            $permalink = get_permalink($byPath);
            $url = is_string($permalink) ? $permalink : home_url('/inventory/');

            return $url;
        }

        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => 100,
        ]);

        foreach ($pages as $candidate) {
            if (has_shortcode($candidate->post_content, 'helmful_inventory')) {
                $permalink = get_permalink($candidate);
                $url = is_string($permalink) ? $permalink : home_url('/inventory/');

                return $url;
            }
        }

        $url = home_url('/inventory/');

        return $url;
    }

    private static function current_page_url(): string
    {
        $queried = get_queried_object();
        if ($queried instanceof WP_Post) {
            $permalink = get_permalink($queried);
            if (is_string($permalink)) {
                return $permalink;
            }
        }

        return home_url('/');
    }
}
