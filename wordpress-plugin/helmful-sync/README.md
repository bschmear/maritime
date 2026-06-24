# Helmful Sync WordPress Plugin

Sync boat shows and events from Helmful to WordPress custom post types.

## Install

1. In Helmful, open **Integrations → WordPress** and download `helmful-sync.zip`
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, upload the zip, and activate **Helmful Sync**
3. In Helmful, save your WordPress site URL + API key
4. In WordPress, open **Settings → Helmful Sync** and save your tenant domain + Helmful API key

## Sync options

- **Push all to WordPress** — from Helmful integrations page
- **Pull from Helmful** — from WordPress Settings → Helmful Sync

Both options store data locally in the `helmful_boat_show` and `helmful_show_event` post types.

## Pages & templates

**Boat shows index:** Create a WordPress **Page** (e.g. slug `boat-shows`) and add `[helmful_boat_shows]`. The plugin does **not** register a post-type archive, so your page design is used for the listing.

**Single boat show:** `/boat-shows/your-show-slug/` uses a custom Helmful template (hero, logo, events grid).

**Single event:** `/boat-show-event/your-event-slug/` uses a custom Helmful template (details, maps link, back to show).

After updating the plugin, visit **Settings → Permalinks** and click **Save** once to refresh rewrite rules.

## Display settings

Open **Settings → Helmful Sync → Display** to choose:

- Default layout (stacked, grid, timeline, compact)
- Grid columns and descriptions
- Accent color, card style, and spacing

Shortcodes link to local WordPress pages (`/boat-shows/` and `/boat-show-event/`). Show and event logos sync as `helmful_logo_url` post meta and display in shortcode layouts when available.

These settings apply to `[helmful_boat_shows]` automatically.

## Shortcodes

Add to any WordPress page or post:

```
[helmful_boat_shows]
```

Layout options:

- `[helmful_boat_shows layout="stacked"]` — full-width show cards (default)
- `[helmful_boat_shows layout="grid"]` — card grid
- `[helmful_boat_shows layout="timeline"]` — events in date order
- `[helmful_boat_shows layout="compact"]` — table-style rows

Other options:

- `[helmful_boat_show_events]` — events only
- `[helmful_boat_shows slug="your-show-slug"]` — single show
- `[helmful_boat_show_events year="2026"]` — filter by year

Shortcodes read from synced local posts (no live API calls on page load).

## Post meta

Synced records store Helmful metadata for reference. Public shortcodes link to WordPress permalinks, not Helmful URLs.
