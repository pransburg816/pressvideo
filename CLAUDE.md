# PressVideo — Claude Context

## What this is
PressVideo is an independent WordPress plugin developed by Phillip Ransburg (Lead Senior Full-Stack Dev at IAC International / pressvideo.com). It auto-imports YouTube videos into a custom post type (`pv_youtube`) with an offcanvas player, color tagging, shortcodes, and multiple watch-page layouts.

## Repo
- **This repo**: `https://github.com/pransburg816/pressvideo.git` — Phillip's personal repo, source of truth for this plugin.
- **Not part of the IAC repo**: The IAC site repo lives at `C:\Users\pransburg\iac-dev\Development Sites\devsite.iac-intl.com\public_html\wp-content\themes\storefront-child`. These are two separate git repos. Do not conflate them.

## Relationship to IAC
PressVideo is tested on the IAC devsite (`https://devsite.iac-intl.com`) but is an independent product. It runs inside the IAC WordPress install only as a test environment. Production IAC site is `https://iac-intl.com`.

## Local source
`C:\Users\pransburg\iac-dev\plugins\pressvideo`

## Deploying to devsite
No local WordPress install — always deploy via FTP to devsite for testing.

```bash
# Full deploy (all plugin files)
node deploy.js

# Targeted deploy (edit FILES array in deploy-ui.js first)
node deploy-ui.js
```

FTP credentials are read from (in priority order):
1. `.env` in this directory (not committed)
2. `../../Development Sites/devsite.iac-intl.com/public_html/wp-content/themes/storefront-child/.env`

Remote plugin path on server: `/devsite.iac-intl.com/public_html/wp-content/plugins/pv-youtube-importer`

## No build step
`assets/dist/` = `assets/src/` — edit dist files directly, no compile step.

## Key architecture
- CPT slug: `pv_youtube` | rewrite slug: `pv-videos`
- Shortcodes: `[pv_video]`, `[pv_video_grid]`, `[pv_video_latest]`, `[pv_launcher]`
- Watch page layouts: `hero-top`, `hero-split`, `theater` (in `templates/single/layouts/`)
- Content in layout templates **must use `the_content()`**, not `echo $post->post_content` — the latter bypasses `do_blocks()` and `do_shortcode()`, breaking shortcode rendering.
- Display mode + archive layout + content width are saved as AJAX from the Dashboard page (`Videos > Dashboard`), not the Settings form.

## WordPress admin (devsite)
`https://devsite.iac-intl.com/wp-admin` — deactivate/reactivate plugin after deploy if OPcache doesn't pick up changes.
