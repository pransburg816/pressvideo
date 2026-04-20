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

---

## Workflow (REQUIRED every session)

### 1. Edit
Edit source files directly in `C:\Users\pransburg\iac-dev\plugins\pressvideo`.
No build step — `assets/dist/` = `assets/src/` — edit dist files directly.

### 2. Deploy to devsite (FTP)
```powershell
# From C:\Users\pransburg\iac-dev\plugins\pressvideo
node deploy.js        # full deploy (all plugin files)
node deploy-ui.js     # targeted deploy (edit FILES array first)
```
FTP credentials are read from (priority order):
1. `.env` in this directory (not committed)
2. `../../Development Sites/devsite.iac-intl.com/public_html/wp-content/themes/storefront-child/.env`

Remote plugin path: `/devsite.iac-intl.com/public_html/wp-content/plugins/pv-youtube-importer`

### 3. Test
Test on `https://devsite.iac-intl.com`. Deactivate/reactivate plugin in WP admin if OPcache doesn't pick up changes.

### 4. Commit and push to GitHub
Claude is authorized to commit and push to GitHub on Phillip's behalf.
```powershell
git add .
git commit -m "Descriptive message"
git push
```
- Branch: `main`
- Remote: `https://github.com/pransburg816/pressvideo.git`
- Never use vague commit messages ("updates", "changes", "fix stuff")
- Always co-author commits: `Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>`

### 5. Session wrap-up
At the end of every session, commit and push all changes with a descriptive message summarizing what was done.

---

## Key architecture
- CPT slug: `pv_youtube` | rewrite slug: `pv-videos`
- Shortcodes: `[pv_video]`, `[pv_video_grid]`, `[pv_video_latest]`, `[pv_launcher]`
- Watch page layouts: `hero-top`, `hero-split`, `theater` (in `templates/single/layouts/`)
- **Content in layout templates MUST use `the_content()`**, not `echo $post->post_content` — the latter bypasses `do_blocks()` and `do_shortcode()`, breaking shortcode rendering inside Gutenberg posts.
- Display mode, archive layout, and content width are saved via AJAX from the Dashboard page (`Videos > Dashboard`), not the Settings form.
- `pv_player_enqueued` action signals the offcanvas footer HTML to render — does NOT enqueue assets.

## WordPress admin (devsite)
`https://devsite.iac-intl.com/wp-admin`
