<?php
/**
 * PressVideo Live Customizer — admin page template.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$s             = $settings;
$layout        = $s['archive_layout']      ?? 'grid';
$width         = $s['content_width']       ?? '';
$mode          = $s['display_mode']        ?? 'offcanvas';
$btn_shape     = $s['button_shape']        ?? '';
$watch_layout  = $s['watch_page_layout']   ?? 'hero-top';
$accent        = $s['default_accent']      ?? '#4f46e5';
$page_bg       = $s['page_bg_color']       ?? '';
$sidebar_bg    = $s['sidebar_bg_color']    ?? '';
$h_show        = isset( $s['hero_show'] ) ? (bool) $s['hero_show'] : true;
$h_align       = $s['hero_text_align']    ?? 'center';
$h_inner_w     = $s['hero_inner_width']   ?? 'full';
$h_height_desk = max( 100, min( 800, (int) ( $s['hero_height_desktop'] ?? 440 ) ) );
$h_height_mob  = max( 80,  min( 500, (int) ( $s['hero_height_mobile']  ?? 280 ) ) );
$h_title       = $s['hero_title']          ?? '';
$h_sub         = $s['hero_subtitle']       ?? '';
$h_bg          = $s['hero_bg_image']       ?? '';
$h_overlay     = $s['hero_overlay']        ?? 'medium';
$h_title_size  = $s['hero_title_size']     ?? '';
$h_title_color = $s['hero_title_color']    ?? '#ffffff';
$h_sub_color   = $s['hero_subtitle_color'] ?? '#9ca3af';

$nr_on    = isset( $s['aside_new_releases'] ) ? (bool) $s['aside_new_releases'] : true;
$nr_label = $s['aside_new_releases_label'] ?? 'New Releases';
$nr_count = (int) ( $s['aside_new_releases_count'] ?? 5 );
$tp_on    = isset( $s['aside_topics'] )    ? (bool) $s['aside_topics']    : true;
$tp_label = $s['aside_topics_label']       ?? 'Browse Topics';
$tg_on    = isset( $s['aside_tags'] )      ? (bool) $s['aside_tags']      : true;
$tg_label = $s['aside_tags_label']         ?? 'Explore Tags';
$tg_count = (int) ( $s['aside_tags_count'] ?? 12 );

$cat_on    = isset( $s['aside_cat_on'] ) ? (bool) $s['aside_cat_on'] : false;
$cat_label = $s['aside_cat_label']       ?? 'From the Collection';
$cat_term  = $s['aside_cat_term']        ?? '';
$cat_count = (int) ( $s['aside_cat_count'] ?? 5 );
$tag_on    = isset( $s['aside_tag_on'] ) ? (bool) $s['aside_tag_on'] : false;
$tag_label = $s['aside_tag_label']       ?? 'Staff Picks';
$tag_term  = $s['aside_tag_term']        ?? '';
$tag_count = (int) ( $s['aside_tag_count'] ?? 5 );

$cards_excerpt  = isset( $s['cards_show_excerpt'] )  ? (bool) $s['cards_show_excerpt']  : true;
$cards_cat      = isset( $s['cards_show_category'] ) ? (bool) $s['cards_show_category'] : true;
$search_align   = $s['search_bar_align'] ?? 'center';
$grid_label_show = isset( $s['grid_label_show'] ) ? (bool) $s['grid_label_show'] : false;
$grid_label_text = $s['grid_label_text'] ?? 'Latest Videos';

// Broadcast playlist settings
$bc_playlists_raw = $s['bc_playlists'] ?? '[]';
$bc_playlists_arr = json_decode( is_string( $bc_playlists_raw ) ? $bc_playlists_raw : '[]', true );
$bc_playlists_arr = is_array( $bc_playlists_arr ) ? $bc_playlists_arr : [];
$bc_pl_titles_raw = is_string( $s['bc_playlist_titles'] ?? '' ) ? ( $s['bc_playlist_titles'] ?? '{}' ) : '{}';
$all_series = get_terms( [ 'taxonomy' => 'pv_series', 'hide_empty' => true, 'orderby' => 'name' ] );
$all_series = is_wp_error( $all_series ) ? [] : $all_series;

// Terms for sidebar selects
$all_cats = get_terms( [ 'taxonomy' => 'pv_category', 'hide_empty' => true, 'orderby' => 'name' ] );
$all_tags = get_terms( [ 'taxonomy' => 'pv_tag',      'hide_empty' => true, 'orderby' => 'name' ] );
$all_cats = is_wp_error( $all_cats ) ? [] : $all_cats;
$all_tags = is_wp_error( $all_tags ) ? [] : $all_tags;
?>
<div class="pvc-wrap">

	<!-- ── Settings Sidebar ───────────────────────────────── -->
	<div class="pvc-sidebar">

		<div class="pvc-header">
			<div class="pvc-logo">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
				PressVideo
			</div>
			<div class="pvc-header-actions">
				<button id="pvc-refresh-btn" class="pvc-icon-btn" title="Refresh preview">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35A7.958 7.958 0 0012 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0112 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
				</button>
				<button id="pvc-publish-btn" class="pvc-publish-btn">Publish</button>
			</div>
		</div>

		<!-- Test mode indicator bar -->
		<div class="pvc-test-mode-indicator" id="pvc-test-mode-indicator">
			<span class="pvc-test-mode-indicator__dot"></span>
			Test Mode Active &mdash; Preview only
		</div>

		<div class="pvc-body">

		<nav class="pvc-nav-rail" id="pvc-nav-rail" aria-label="Settings sections">
			<button class="pvc-nav-toggle" id="pvc-nav-toggle" aria-label="Toggle navigation">
				<svg class="pvc-nav-toggle__open" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
				<svg class="pvc-nav-toggle__close" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
			</button>
			<button class="pvc-nav-btn pvc-nav-btn--active" data-tab="layout" aria-label="Layout">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 3h7v7H3zm11 0h7v7h-7zM3 14h7v7H3zm11 0h7v7h-7z"/></svg>
				<span class="pvc-nav-label">Layout</span>
			</button>
			<button class="pvc-nav-btn" data-tab="hero" aria-label="Hero">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21 3H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H3V5h18v14zm-5-7l-3 3.72L10 13l-4 5h12l-4-5z"/></svg>
				<span class="pvc-nav-label">Hero</span>
			</button>
			<button class="pvc-nav-btn" data-tab="sidebar" aria-label="Sidebar">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zM7 7h3v10H7z"/></svg>
				<span class="pvc-nav-label">Sidebar</span>
			</button>
			<button class="pvc-nav-btn" data-tab="style" aria-label="Style">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9c.83 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.01-.23-.26-.38-.61-.38-.99 0-.83.67-1.5 1.5-1.5H16c2.76 0 5-2.24 5-5 0-4.42-4.03-8-9-8zm-5.5 9c-.83 0-1.5-.67-1.5-1.5S5.67 9 6.5 9 8 9.67 8 10.5 7.33 12 6.5 12zm3-4C8.67 8 8 7.33 8 6.5S8.67 5 9.5 5s1.5.67 1.5 1.5S10.33 8 9.5 8zm5 0c-.83 0-1.5-.67-1.5-1.5S13.67 5 14.5 5s1.5.67 1.5 1.5S15.33 8 14.5 8zm3 4c-.83 0-1.5-.67-1.5-1.5S16.67 9 17.5 9s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
				<span class="pvc-nav-label">Style</span>
			</button>
			<button class="pvc-nav-btn" data-tab="notifications" aria-label="Alerts">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
				<span class="pvc-nav-label">Alerts</span>
			</button>
		</nav>

		<div class="pvc-settings">

			<!-- ── Layout ── -->
			<div class="pvc-panel pvc-panel--active" id="pvc-panel-layout">

				<div class="pvc-field">
					<span class="pvc-label">Archive Layout</span>
					<div class="pvc-layout-cards">

						<div class="pvc-layout-card">
							<input type="radio" name="pvc_layout" id="pvcl-grid" value="grid" data-setting="archive_layout" <?php checked( $layout, 'grid' ); ?>>
							<label class="pvc-layout-card__label" for="pvcl-grid">
								<span class="pvc-layout-card__icon"><svg width="38" height="26" viewBox="0 0 38 26"><rect x="0" y="0" width="11" height="11" rx="1.5" fill="currentColor" opacity=".9"/><rect x="13.5" y="0" width="11" height="11" rx="1.5" fill="currentColor" opacity=".9"/><rect x="27" y="0" width="11" height="11" rx="1.5" fill="currentColor" opacity=".9"/><rect x="0" y="14" width="11" height="11" rx="1.5" fill="currentColor" opacity=".5"/><rect x="13.5" y="14" width="11" height="11" rx="1.5" fill="currentColor" opacity=".5"/><rect x="27" y="14" width="11" height="11" rx="1.5" fill="currentColor" opacity=".5"/></svg></span>
								<span class="pvc-layout-card__name">Grid</span>
							</label>
						</div>

						<div class="pvc-layout-card">
							<input type="radio" name="pvc_layout" id="pvcl-list" value="list" data-setting="archive_layout" <?php checked( $layout, 'list' ); ?>>
							<label class="pvc-layout-card__label" for="pvcl-list">
								<span class="pvc-layout-card__icon"><svg width="38" height="26" viewBox="0 0 38 26"><rect x="0" y="0" width="10" height="7" rx="1.5" fill="currentColor" opacity=".9"/><rect x="12" y="1" width="26" height="2" rx="1" fill="currentColor" opacity=".6"/><rect x="12" y="4.5" width="18" height="2" rx="1" fill="currentColor" opacity=".35"/><rect x="0" y="9.5" width="10" height="7" rx="1.5" fill="currentColor" opacity=".9"/><rect x="12" y="10.5" width="26" height="2" rx="1" fill="currentColor" opacity=".6"/><rect x="12" y="14" width="18" height="2" rx="1" fill="currentColor" opacity=".35"/><rect x="0" y="19" width="10" height="7" rx="1.5" fill="currentColor" opacity=".9"/><rect x="12" y="20" width="26" height="2" rx="1" fill="currentColor" opacity=".6"/><rect x="12" y="23.5" width="18" height="2" rx="1" fill="currentColor" opacity=".35"/></svg></span>
								<span class="pvc-layout-card__name">List</span>
							</label>
						</div>

						<div class="pvc-layout-card">
							<input type="radio" name="pvc_layout" id="pvcl-featured" value="featured" data-setting="archive_layout" <?php checked( $layout, 'featured' ); ?>>
							<label class="pvc-layout-card__label" for="pvcl-featured">
								<span class="pvc-layout-card__icon"><svg width="38" height="26" viewBox="0 0 38 26"><rect x="0" y="0" width="24" height="16" rx="1.5" fill="currentColor" opacity=".9"/><rect x="26" y="0" width="12" height="7" rx="1.5" fill="currentColor" opacity=".5"/><rect x="26" y="9" width="12" height="7" rx="1.5" fill="currentColor" opacity=".5"/><rect x="0" y="18" width="12" height="7" rx="1.5" fill="currentColor" opacity=".5"/><rect x="13" y="18" width="12" height="7" rx="1.5" fill="currentColor" opacity=".5"/><rect x="26" y="18" width="12" height="7" rx="1.5" fill="currentColor" opacity=".5"/></svg></span>
								<span class="pvc-layout-card__name">Featured</span>
							</label>
						</div>

						<div class="pvc-layout-card">
							<input type="radio" name="pvc_layout" id="pvcl-compact" value="compact" data-setting="archive_layout" <?php checked( $layout, 'compact' ); ?>>
							<label class="pvc-layout-card__label" for="pvcl-compact">
								<span class="pvc-layout-card__icon"><svg width="38" height="26" viewBox="0 0 38 26"><rect x="0" y="0" width="8" height="8" rx="1.5" fill="currentColor" opacity=".9"/><rect x="10" y="0" width="8" height="8" rx="1.5" fill="currentColor" opacity=".9"/><rect x="20" y="0" width="8" height="8" rx="1.5" fill="currentColor" opacity=".9"/><rect x="30" y="0" width="8" height="8" rx="1.5" fill="currentColor" opacity=".9"/><rect x="0" y="10" width="8" height="8" rx="1.5" fill="currentColor" opacity=".5"/><rect x="10" y="10" width="8" height="8" rx="1.5" fill="currentColor" opacity=".5"/><rect x="20" y="10" width="8" height="8" rx="1.5" fill="currentColor" opacity=".5"/><rect x="30" y="10" width="8" height="8" rx="1.5" fill="currentColor" opacity=".5"/></svg></span>
								<span class="pvc-layout-card__name">Compact</span>
							</label>
						</div>

						<!-- Video Wall: bento mosaic with varying tile sizes -->
						<div class="pvc-layout-card">
							<input type="radio" name="pvc_layout" id="pvcl-wall" value="wall" data-setting="archive_layout" <?php checked( $layout, 'wall' ); ?>>
							<label class="pvc-layout-card__label" for="pvcl-wall">
								<span class="pvc-layout-card__icon"><svg width="38" height="26" viewBox="0 0 38 26"><rect x="0" y="0" width="17" height="25" rx="1.5" fill="currentColor" opacity=".9"/><rect x="19" y="0" width="8" height="11" rx="1.5" fill="currentColor" opacity=".65"/><rect x="29" y="0" width="9" height="11" rx="1.5" fill="currentColor" opacity=".65"/><rect x="19" y="13" width="8" height="12" rx="1.5" fill="currentColor" opacity=".45"/><rect x="29" y="13" width="9" height="12" rx="1.5" fill="currentColor" opacity=".45"/></svg></span>
								<span class="pvc-layout-card__name">Video Wall</span>
							</label>
						</div>

						<!-- Spotlight: cinematic hero + side rail -->
						<div class="pvc-layout-card">
							<input type="radio" name="pvc_layout" id="pvcl-spotlight" value="spotlight" data-setting="archive_layout" <?php checked( $layout, 'spotlight' ); ?>>
							<label class="pvc-layout-card__label" for="pvcl-spotlight">
								<span class="pvc-layout-card__icon"><svg width="38" height="26" viewBox="0 0 38 26"><rect x="0" y="0" width="22" height="25" rx="1.5" fill="currentColor" opacity=".9"/><rect x="24" y="0" width="14" height="5" rx="1" fill="currentColor" opacity=".65"/><rect x="24" y="7" width="14" height="5" rx="1" fill="currentColor" opacity=".55"/><rect x="24" y="14" width="14" height="5" rx="1" fill="currentColor" opacity=".45"/><rect x="24" y="21" width="14" height="4" rx="1" fill="currentColor" opacity=".35"/></svg></span>
								<span class="pvc-layout-card__name">Spotlight</span>
							</label>
						</div>

						<!-- Broadcast: YouTube-style tabs + chips + playlists -->
						<div class="pvc-layout-card">
							<input type="radio" name="pvc_layout" id="pvcl-broadcast" value="broadcast" data-setting="archive_layout" <?php checked( $layout, 'broadcast' ); ?>>
							<label class="pvc-layout-card__label" for="pvcl-broadcast">
								<span class="pvc-layout-card__icon"><svg width="38" height="26" viewBox="0 0 38 26"><rect x="0" y="0" width="9" height="3" rx="1.5" fill="currentColor" opacity=".9"/><rect x="11" y="0" width="9" height="3" rx="1" fill="currentColor" opacity=".5"/><rect x="22" y="0" width="9" height="3" rx="1" fill="currentColor" opacity=".35"/><rect x="0" y="3.5" width="9" height="1" rx=".5" fill="currentColor" opacity=".9"/><rect x="0" y="7" width="18" height="10" rx="1.5" fill="currentColor" opacity=".8"/><rect x="20" y="7" width="18" height="10" rx="1.5" fill="currentColor" opacity=".65"/><rect x="0" y="19" width="11" height="7" rx="1" fill="currentColor" opacity=".45"/><rect x="13" y="19" width="11" height="7" rx="1" fill="currentColor" opacity=".45"/><rect x="26" y="19" width="12" height="7" rx="1" fill="currentColor" opacity=".45"/></svg></span>
								<span class="pvc-layout-card__name">Broadcast</span>
							</label>
						</div>

					</div>
				</div>

				<!-- Playlist selector: always visible — drives the playlist nav in all layouts -->
				<div class="pvc-field pvc-sublayout" id="pvc-broadcast-field">
					<span class="pvc-label">Featured Playlists</span>
					<span class="pvc-hint" style="display:block;margin:4px 0 14px;">Choose which playlists and series appear in the playlist navigation bar across all layouts.</span>

					<!-- YouTube playlists (fetched dynamically) -->
					<span class="pvc-bc-source-label">Playlists From YouTube</span>
					<div id="pvc-yt-playlists" class="pvc-bc-playlists">
						<span class="pvc-bc-loading">Loading playlists&hellip;</span>
					</div>

					<!-- Series (manual) -->
					<span class="pvc-bc-source-label" style="margin-top:14px;">By Series</span>
					<?php if ( ! empty( $all_series ) ) : ?>
					<div class="pvc-bc-playlists">
						<?php foreach ( $all_series as $_series ) : ?>
						<label class="pvc-bc-playlist-item">
							<input type="checkbox" class="pvc-bc-playlist-cb" value="<?php echo esc_attr( $_series->slug ); ?>"
							       <?php checked( in_array( $_series->slug, $bc_playlists_arr, true ) ); ?>>
							<span class="pvc-bc-playlist-name"><?php echo esc_html( $_series->name . ' (' . $_series->count . ')' ); ?></span>
						</label>
						<?php endforeach; ?>
					</div>
					<?php else : ?>
					<p class="pvc-hint" style="margin:2px 0 0;">No series found. Tag videos with the <strong>Series</strong> taxonomy to use this option.</p>
					<?php endif; ?>

					<input type="hidden" id="pvc-bc-playlists-val" data-setting="bc_playlists" value="<?php echo esc_attr( wp_json_encode( $bc_playlists_arr ) ); ?>">
					<input type="hidden" id="pvc-bc-playlist-titles-val" data-setting="bc_playlist_titles" value="<?php echo esc_attr( $bc_pl_titles_raw ); ?>">
				</div>

				<div class="pvc-field">
					<span class="pvc-label">Content Width</span>
					<div class="pvc-segment" data-for="pvc-content-width-val">
						<button class="pvc-seg-btn <?php echo '' === $width ? 'pvc-seg-btn--active' : ''; ?>" data-value="">Default</button>
						<button class="pvc-seg-btn <?php echo 'narrow' === $width ? 'pvc-seg-btn--active' : ''; ?>" data-value="narrow">Narrow</button>
						<button class="pvc-seg-btn <?php echo 'medium' === $width ? 'pvc-seg-btn--active' : ''; ?>" data-value="medium">Medium</button>
						<button class="pvc-seg-btn <?php echo 'wide' === $width ? 'pvc-seg-btn--active' : ''; ?>" data-value="wide">Wide</button>
					</div>
					<input type="hidden" data-setting="content_width" value="<?php echo esc_attr( $width ); ?>" id="pvc-content-width-val">
				</div>

				<div class="pvc-field">
					<span class="pvc-label">Playback Mode</span>
					<div class="pvc-mode-cards">
						<div class="pvc-mode-card">
							<input type="radio" name="pvc_mode" id="pvcm-offcanvas" value="offcanvas" data-setting="display_mode" <?php checked( $mode, 'offcanvas' ); ?>>
							<label class="pvc-mode-card__label" for="pvcm-offcanvas">
								<span class="pvc-mode-card__preview"><?php echo PV_Settings_Page::svg_offcanvas(); // phpcs:ignore ?></span>
								<span class="pvc-mode-card__name">Offcanvas Drawer<span class="pvc-mode-card__desc">Slides in without leaving the page</span></span>
							</label>
						</div>
						<div class="pvc-mode-card">
							<input type="radio" name="pvc_mode" id="pvcm-page" value="page" data-setting="display_mode" <?php checked( $mode, 'page' ); ?>>
							<label class="pvc-mode-card__label" for="pvcm-page">
								<span class="pvc-mode-card__preview"><?php echo PV_Settings_Page::svg_watch_page(); // phpcs:ignore ?></span>
								<span class="pvc-mode-card__name">Watch Page<span class="pvc-mode-card__desc">Navigates to a dedicated video page</span></span>
							</label>
						</div>
				</div>
			</div>

				<div class="pvc-field pvc-sublayout <?php echo 'page' !== $mode ? 'pvc-collapsed' : ''; ?>" id="pvc-watch-layout-field">
					<span class="pvc-label">Watch Page Layout</span>
					<div class="pvc-mode-cards pvc-mode-cards--sm">
						<div class="pvc-mode-card">
							<input type="radio" name="pvc_watch_layout" id="pvcwl-hero-top" value="hero-top" data-setting="watch_page_layout" <?php checked( $watch_layout, 'hero-top' ); ?>>
							<label class="pvc-mode-card__label" for="pvcwl-hero-top"><span class="pvc-mode-card__preview"><?php echo PV_Settings_Page::svg_hero_top(); // phpcs:ignore ?></span><span class="pvc-mode-card__name">Hero Top</span></label>
						</div>
						<div class="pvc-mode-card">
							<input type="radio" name="pvc_watch_layout" id="pvcwl-hero-split" value="hero-split" data-setting="watch_page_layout" <?php checked( $watch_layout, 'hero-split' ); ?>>
							<label class="pvc-mode-card__label" for="pvcwl-hero-split"><span class="pvc-mode-card__preview"><?php echo PV_Settings_Page::svg_hero_split(); // phpcs:ignore ?></span><span class="pvc-mode-card__name">Hero Split</span></label>
						</div>
						<div class="pvc-mode-card">
							<input type="radio" name="pvc_watch_layout" id="pvcwl-theater" value="theater" data-setting="watch_page_layout" <?php checked( $watch_layout, 'theater' ); ?>>
							<label class="pvc-mode-card__label" for="pvcwl-theater"><span class="pvc-mode-card__preview"><?php echo PV_Settings_Page::svg_theater(); // phpcs:ignore ?></span><span class="pvc-mode-card__name">Theater</span></label>
						</div>
					</div>
				</div>

				<div class="pvc-divider"></div>

				<div class="pvc-field">
					<span class="pvc-label">Card Options</span>
					<div class="pvc-aside-section">
						<div class="pvc-aside-section__head">
							<div><span style="font-size:.78rem;color:rgba(255,255,255,.65);font-weight:600;display:block;">Hover Excerpt</span><span class="pvc-hint" style="margin:2px 0 0;">Show description on card hover</span></div>
							<label class="pvc-toggle"><input type="checkbox" data-setting="cards_show_excerpt" <?php checked( $cards_excerpt ); ?>><span class="pvc-toggle__track"></span></label>
						</div>
					</div>
					<div class="pvc-aside-section" style="margin-top:8px;">
						<div class="pvc-aside-section__head">
							<div><span style="font-size:.78rem;color:rgba(255,255,255,.65);font-weight:600;display:block;">Category Badge</span><span class="pvc-hint" style="margin:2px 0 0;">Show category label on each card</span></div>
							<label class="pvc-toggle"><input type="checkbox" data-setting="cards_show_category" <?php checked( $cards_cat ); ?>><span class="pvc-toggle__track"></span></label>
						</div>
					</div>
					<div class="pvc-aside-section" style="margin-top:8px;">
						<div class="pvc-aside-section__head">
							<div><span style="font-size:.78rem;color:rgba(255,255,255,.65);font-weight:600;display:block;">Section Label</span><span class="pvc-hint" style="margin:2px 0 0;">Show a heading above the video grid</span></div>
							<label class="pvc-toggle"><input type="checkbox" data-setting="grid_label_show" id="pvc-grid-label-toggle" <?php checked( $grid_label_show ); ?>><span class="pvc-toggle__track"></span></label>
						</div>
						<div class="pvc-sub-fields <?php echo $grid_label_show ? '' : 'pvc-collapsed'; ?>" id="pvc-grid-label-sub">
							<div class="pvc-field" style="margin:8px 0 0;">
								<input type="text" class="pvc-text-input" data-setting="grid_label_text"
								       placeholder="Latest Videos"
								       value="<?php echo esc_attr( $grid_label_text ); ?>">
							</div>
						</div>
					</div>
				</div>

				<div class="pvc-divider"></div>

				<div class="pvc-field">
					<span class="pvc-label">Search Bar Alignment</span>
					<div class="pvc-segment" data-for="pvc-search-align-val">
						<button class="pvc-seg-btn <?php echo 'left'   === $search_align ? 'pvc-seg-btn--active' : ''; ?>" data-value="left">Left</button>
						<button class="pvc-seg-btn <?php echo 'center' === $search_align ? 'pvc-seg-btn--active' : ''; ?>" data-value="center">Center</button>
						<button class="pvc-seg-btn <?php echo 'right'  === $search_align ? 'pvc-seg-btn--active' : ''; ?>" data-value="right">Right</button>
					</div>
					<input type="hidden" data-setting="search_bar_align" id="pvc-search-align-val" value="<?php echo esc_attr( $search_align ); ?>">
				</div>

			</div><!-- /#pvc-panel-layout -->

			<!-- ── Hero ── -->
			<div class="pvc-panel" id="pvc-panel-hero">

				<div class="pvc-aside-section">
					<div class="pvc-aside-section__head">
						<div><span style="font-size:.78rem;color:rgba(255,255,255,.65);font-weight:600;display:block;">Show Hero</span><span class="pvc-hint" style="margin:2px 0 0;">Display the hero banner on the archive page</span></div>
						<label class="pvc-toggle"><input type="checkbox" data-setting="hero_show" <?php checked( $h_show ); ?>><span class="pvc-toggle__track"></span></label>
					</div>
					<div class="pvc-sub-fields <?php echo $h_show ? '' : 'pvc-collapsed'; ?>">

						<div class="pvc-field" style="margin:0 0 12px;">
							<label class="pvc-sub-label" for="pvc-hero-title">Archive Title</label>
							<input type="text" id="pvc-hero-title" class="pvc-input pvc-input--sm" data-setting="hero_title" value="<?php echo esc_attr( $h_title ); ?>" placeholder="Video Library">
							<span class="pvc-hint">Leave blank to use the default post type archive label.</span>
						</div>

						<div class="pvc-field" style="margin:0 0 12px;">
							<label class="pvc-sub-label" for="pvc-hero-sub">Subtitle</label>
							<input type="text" id="pvc-hero-sub" class="pvc-input pvc-input--sm" data-setting="hero_subtitle" value="<?php echo esc_attr( $h_sub ); ?>" placeholder="e.g. Explore our full video collection">
							<span class="pvc-hint">Leave blank to auto-display the video count.</span>
						</div>

						<div style="display:flex;gap:12px;margin:0 0 12px;">
							<div style="flex:1;">
								<span class="pvc-sub-label">Desktop height (px)</span>
								<div class="pvc-stepper">
									<button class="pvc-step-btn" data-direction="-20" data-target="hero_height_desktop">&#8722;</button>
									<input type="number" class="pvc-step-input" data-setting="hero_height_desktop" min="100" max="800" step="20" value="<?php echo esc_attr( $h_height_desk ); ?>">
									<button class="pvc-step-btn" data-direction="20" data-target="hero_height_desktop">+</button>
								</div>
							</div>
							<div style="flex:1;">
								<span class="pvc-sub-label">Mobile height (px)</span>
								<div class="pvc-stepper">
									<button class="pvc-step-btn" data-direction="-20" data-target="hero_height_mobile">&#8722;</button>
									<input type="number" class="pvc-step-input" data-setting="hero_height_mobile" min="80" max="500" step="20" value="<?php echo esc_attr( $h_height_mob ); ?>">
									<button class="pvc-step-btn" data-direction="20" data-target="hero_height_mobile">+</button>
								</div>
							</div>
						</div>

						<div style="margin:0 0 10px;">
							<span class="pvc-sub-label">Text Alignment</span>
							<div class="pvc-segment" data-for="pvc-hero-align-val">
								<button class="pvc-seg-btn <?php echo 'left'   === $h_align ? 'pvc-seg-btn--active' : ''; ?>" data-value="left">Left</button>
								<button class="pvc-seg-btn <?php echo 'center' === $h_align ? 'pvc-seg-btn--active' : ''; ?>" data-value="center">Center</button>
								<button class="pvc-seg-btn <?php echo 'right'  === $h_align ? 'pvc-seg-btn--active' : ''; ?>" data-value="right">Right</button>
							</div>
							<input type="hidden" data-setting="hero_text_align" id="pvc-hero-align-val" value="<?php echo esc_attr( $h_align ); ?>">
						</div>

						<div>
							<span class="pvc-sub-label">Text Container</span>
							<div class="pvc-segment" data-for="pvc-hero-inner-w-val">
								<button class="pvc-seg-btn <?php echo 'full'      === $h_inner_w ? 'pvc-seg-btn--active' : ''; ?>" data-value="full">Full Width</button>
								<button class="pvc-seg-btn <?php echo 'contained' === $h_inner_w ? 'pvc-seg-btn--active' : ''; ?>" data-value="contained">Contained</button>
							</div>
							<input type="hidden" data-setting="hero_inner_width" id="pvc-hero-inner-w-val" value="<?php echo esc_attr( $h_inner_w ); ?>">
							<span class="pvc-hint" style="margin-top:4px;">Contained aligns text with the content grid below.</span>
						</div>

					</div>
				</div>

				<div class="pvc-divider"></div>

				<div class="pvc-field">
					<span class="pvc-label">Background Image</span>
					<div class="pvc-media-picker">
						<div class="pvc-media-preview <?php echo $h_bg ? '' : 'pvc-media-preview--empty'; ?>" id="pvc-hero-bg-preview"
						     <?php if ( $h_bg ) : ?>style="background-image:url(<?php echo esc_url( $h_bg ); ?>)"<?php endif; ?>>
							<?php if ( ! $h_bg ) : ?><svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg><?php endif; ?>
						</div>
						<div class="pvc-media-actions">
							<button type="button" class="pvc-media-select-btn" id="pvc-hero-bg-select"><?php echo $h_bg ? 'Change Image' : 'Upload / Select'; ?></button>
							<button type="button" class="pvc-media-clear-btn <?php echo $h_bg ? '' : 'pvc-hidden'; ?>" id="pvc-hero-bg-clear">Remove</button>
						</div>
						<input type="hidden" data-setting="hero_bg_image" id="pvc-hero-bg-input" value="<?php echo esc_attr( $h_bg ); ?>">
					</div>
					<span class="pvc-hint">Overlays the default gradient. Dark landscape images work best.</span>
				</div>

				<div class="pvc-field" id="pvc-hero-overlay-field" <?php echo ! $h_bg ? 'style="display:none"' : ''; ?>>
					<span class="pvc-label">Image Overlay</span>
					<div class="pvc-segment" data-for="pvc-hero-overlay-val">
						<button class="pvc-seg-btn <?php echo 'light'  === $h_overlay ? 'pvc-seg-btn--active' : ''; ?>" data-value="light">Light</button>
						<button class="pvc-seg-btn <?php echo 'medium' === $h_overlay ? 'pvc-seg-btn--active' : ''; ?>" data-value="medium">Medium</button>
						<button class="pvc-seg-btn <?php echo 'dark'   === $h_overlay ? 'pvc-seg-btn--active' : ''; ?>" data-value="dark">Dark</button>
					</div>
					<input type="hidden" data-setting="hero_overlay" id="pvc-hero-overlay-val" value="<?php echo esc_attr( $h_overlay ); ?>">
				</div>

				<div class="pvc-divider"></div>

				<div class="pvc-field">
					<span class="pvc-label">Title Size</span>
					<div class="pvc-segment" data-for="pvc-hero-title-size-val">
						<button class="pvc-seg-btn <?php echo '' === $h_title_size ? 'pvc-seg-btn--active' : ''; ?>" data-value="">Default</button>
						<button class="pvc-seg-btn <?php echo 'lg' === $h_title_size ? 'pvc-seg-btn--active' : ''; ?>" data-value="lg">Large</button>
						<button class="pvc-seg-btn <?php echo 'xl' === $h_title_size ? 'pvc-seg-btn--active' : ''; ?>" data-value="xl">X-Large</button>
					</div>
					<input type="hidden" data-setting="hero_title_size" id="pvc-hero-title-size-val" value="<?php echo esc_attr( $h_title_size ); ?>">
				</div>

				<div class="pvc-field">
					<span class="pvc-label">Title Color</span>
					<input type="text" class="pvc-color-picker" data-setting="hero_title_color" value="<?php echo esc_attr( $h_title_color ); ?>">
					<span class="pvc-hint">Default is white. Change to match your brand.</span>
				</div>

				<div class="pvc-field">
					<span class="pvc-label">Subtitle Color</span>
					<input type="text" class="pvc-color-picker" data-setting="hero_subtitle_color" value="<?php echo esc_attr( $h_sub_color ); ?>">
				</div>

			</div><!-- /#pvc-panel-hero -->

			<!-- ── Sidebar ── -->
			<div class="pvc-panel" id="pvc-panel-sidebar">

				<!-- New Releases -->
				<div class="pvc-aside-section">
					<div class="pvc-aside-section__head">
						<span class="pvc-label">New Releases</span>
						<label class="pvc-toggle"><input type="checkbox" data-setting="aside_new_releases" <?php checked( $nr_on ); ?>><span class="pvc-toggle__track"></span></label>
					</div>
					<div class="pvc-sub-fields <?php echo $nr_on ? '' : 'pvc-collapsed'; ?>">
						<div><span class="pvc-sub-label">Heading label</span><input type="text" class="pvc-input pvc-input--sm" data-setting="aside_new_releases_label" value="<?php echo esc_attr( $nr_label ); ?>" placeholder="New Releases"></div>
						<div>
							<span class="pvc-sub-label">Number of videos</span>
							<div class="pvc-stepper">
								<button class="pvc-step-btn" data-direction="-1" data-target="aside_new_releases_count">&#8722;</button>
								<input type="number" class="pvc-step-input" data-setting="aside_new_releases_count" min="3" max="10" value="<?php echo esc_attr( $nr_count ); ?>">
								<button class="pvc-step-btn" data-direction="1" data-target="aside_new_releases_count">+</button>
							</div>
						</div>
					</div>
				</div>

				<!-- Category Spotlight -->
				<div class="pvc-aside-section">
					<div class="pvc-aside-section__head">
						<span class="pvc-label">Category Spotlight</span>
						<label class="pvc-toggle"><input type="checkbox" data-setting="aside_cat_on" <?php checked( $cat_on ); ?>><span class="pvc-toggle__track"></span></label>
					</div>
					<div class="pvc-sub-fields <?php echo $cat_on ? '' : 'pvc-collapsed'; ?>">
						<div><span class="pvc-sub-label">Heading label</span><input type="text" class="pvc-input pvc-input--sm" data-setting="aside_cat_label" value="<?php echo esc_attr( $cat_label ); ?>" placeholder="From the Collection"></div>
						<div>
							<span class="pvc-sub-label">Category</span>
							<?php if ( ! empty( $all_cats ) ) : ?>
								<select class="pvc-select" data-setting="aside_cat_term">
									<option value="">&#8212; Select category &#8212;</option>
									<?php foreach ( $all_cats as $_c ) : ?>
										<option value="<?php echo esc_attr( $_c->slug ); ?>" <?php selected( $cat_term, $_c->slug ); ?>><?php echo esc_html( $_c->name . ' (' . $_c->count . ')' ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php else : ?>
								<span class="pvc-hint">No categories found. Add some first.</span>
							<?php endif; ?>
						</div>
						<div>
							<span class="pvc-sub-label">Videos to show</span>
							<div class="pvc-stepper">
								<button class="pvc-step-btn" data-direction="-1" data-target="aside_cat_count">&#8722;</button>
								<input type="number" class="pvc-step-input" data-setting="aside_cat_count" min="3" max="10" value="<?php echo esc_attr( $cat_count ); ?>">
								<button class="pvc-step-btn" data-direction="1" data-target="aside_cat_count">+</button>
							</div>
						</div>
					</div>
				</div>

				<!-- Tag Collection (Staff Picks / Curated) -->
				<div class="pvc-aside-section">
					<div class="pvc-aside-section__head">
						<span class="pvc-label">Tag Collection</span>
						<label class="pvc-toggle"><input type="checkbox" data-setting="aside_tag_on" <?php checked( $tag_on ); ?>><span class="pvc-toggle__track"></span></label>
					</div>
					<div class="pvc-sub-fields <?php echo $tag_on ? '' : 'pvc-collapsed'; ?>">
						<div><span class="pvc-sub-label">Heading label</span><input type="text" class="pvc-input pvc-input--sm" data-setting="aside_tag_label" value="<?php echo esc_attr( $tag_label ); ?>" placeholder="Staff Picks"></div>
						<div>
							<span class="pvc-sub-label">Tag</span>
							<?php if ( ! empty( $all_tags ) ) : ?>
								<select class="pvc-select" data-setting="aside_tag_term">
									<option value="">&#8212; Select tag &#8212;</option>
									<?php foreach ( $all_tags as $_t ) : ?>
										<option value="<?php echo esc_attr( $_t->slug ); ?>" <?php selected( $tag_term, $_t->slug ); ?>><?php echo esc_html( $_t->name . ' (' . $_t->count . ')' ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php else : ?>
								<span class="pvc-hint">No tags found. Add some first.</span>
							<?php endif; ?>
						</div>
						<div>
							<span class="pvc-sub-label">Videos to show</span>
							<div class="pvc-stepper">
								<button class="pvc-step-btn" data-direction="-1" data-target="aside_tag_count">&#8722;</button>
								<input type="number" class="pvc-step-input" data-setting="aside_tag_count" min="3" max="10" value="<?php echo esc_attr( $tag_count ); ?>">
								<button class="pvc-step-btn" data-direction="1" data-target="aside_tag_count">+</button>
							</div>
						</div>
					</div>
				</div>

				<!-- Browse Topics -->
				<div class="pvc-aside-section">
					<div class="pvc-aside-section__head">
						<span class="pvc-label">Browse Topics</span>
						<label class="pvc-toggle"><input type="checkbox" data-setting="aside_topics" <?php checked( $tp_on ); ?>><span class="pvc-toggle__track"></span></label>
					</div>
					<div class="pvc-sub-fields <?php echo $tp_on ? '' : 'pvc-collapsed'; ?>">
						<div><span class="pvc-sub-label">Heading label</span><input type="text" class="pvc-input pvc-input--sm" data-setting="aside_topics_label" value="<?php echo esc_attr( $tp_label ); ?>" placeholder="Browse Topics"></div>
					</div>
				</div>

				<!-- Explore Tags -->
				<div class="pvc-aside-section">
					<div class="pvc-aside-section__head">
						<span class="pvc-label">Explore Tags</span>
						<label class="pvc-toggle"><input type="checkbox" data-setting="aside_tags" <?php checked( $tg_on ); ?>><span class="pvc-toggle__track"></span></label>
					</div>
					<div class="pvc-sub-fields <?php echo $tg_on ? '' : 'pvc-collapsed'; ?>">
						<div><span class="pvc-sub-label">Heading label</span><input type="text" class="pvc-input pvc-input--sm" data-setting="aside_tags_label" value="<?php echo esc_attr( $tg_label ); ?>" placeholder="Explore Tags"></div>
						<div>
							<span class="pvc-sub-label">Max tags shown</span>
							<div class="pvc-stepper">
								<button class="pvc-step-btn" data-direction="-1" data-target="aside_tags_count">&#8722;</button>
								<input type="number" class="pvc-step-input" data-setting="aside_tags_count" min="6" max="24" value="<?php echo esc_attr( $tg_count ); ?>">
								<button class="pvc-step-btn" data-direction="1" data-target="aside_tags_count">+</button>
							</div>
						</div>
					</div>
				</div>

			</div><!-- /#pvc-panel-sidebar -->

			<!-- ── Style ── -->
			<div class="pvc-panel" id="pvc-panel-style">
				<div class="pvc-field">
					<span class="pvc-label">Accent Color</span>
					<input type="text" class="pvc-color-picker" data-setting="default_accent" value="<?php echo esc_attr( $accent ); ?>">
					<button type="button" class="pvc-detect-colors-btn" id="pvc-detect-colors-btn">
						<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.71 5.63l-2.34-2.34a1 1 0 00-1.41 0l-3.12 3.12-1.41-1.42-1.42 1.42 1.41 1.41-6.6 6.6A2 2 0 005 16v3h3a2 2 0 001.42-.59l6.6-6.6 1.41 1.42 1.42-1.42-1.42-1.41 3.12-3.12a1 1 0 000-1.65z"/></svg>
						Detect from theme
					</button>
					<div class="pvc-color-swatches" id="pvc-color-swatches" hidden></div>
					<span class="pvc-hint">Used for buttons, highlights, and the sidebar border.</span>
				</div>
				<div class="pvc-divider"></div>
				<div class="pvc-field">
					<span class="pvc-label">Page Background Color</span>
					<input type="text" class="pvc-color-picker" data-setting="page_bg_color" value="<?php echo esc_attr( $page_bg ?: '#0c0c18' ); ?>">
					<span class="pvc-hint">Default is <code style="color:rgba(255,255,255,.55);font-size:.78rem;">#0c0c18</code>. Type a hex value for instant preview, or click the refresh button after choosing a swatch.</span>
				</div>
				<div class="pvc-divider"></div>
				<div class="pvc-field">
					<span class="pvc-label">Sidebar Panel Background</span>
					<input type="text" class="pvc-color-picker" data-setting="sidebar_bg_color" value="<?php echo esc_attr( $sidebar_bg ?: '#0f0f1e' ); ?>">
					<span class="pvc-hint">Background color for the New Releases, Browse Topics, and Explore Tags panels. Default is <code style="color:rgba(255,255,255,.55);font-size:.78rem;">#0f0f1e</code>. Type a hex value for instant preview, or click the refresh button after choosing a swatch.</span>
				</div>
			<div class="pvc-divider"></div>
				<div class="pvc-field">
					<span class="pvc-label">Button Shape</span>
					<div class="pvc-segment" data-for="pvc-btn-shape-val">
						<button class="pvc-seg-btn <?php echo 'pill'   === $btn_shape ? 'pvc-seg-btn--active' : ''; ?>" data-value="pill">Pill</button>
						<button class="pvc-seg-btn <?php echo 'radius' === $btn_shape ? 'pvc-seg-btn--active' : ''; ?>" data-value="radius">Simple Radius</button>
						<button class="pvc-seg-btn <?php echo 'square' === $btn_shape ? 'pvc-seg-btn--active' : ''; ?>" data-value="square">No Radius</button>
					</div>
					<input type="hidden" data-setting="button_shape" id="pvc-btn-shape-val" value="<?php echo esc_attr( $btn_shape ); ?>">
					<span class="pvc-hint">Applies to filter chips, sort buttons, playlist nav, and per-page selectors.</span>
				</div>
			</div><!-- /#pvc-panel-style -->

			<!-- ── Notifications ── -->
			<?php
			$live_feed_on   = isset( $s['live_feed_enabled'] )   ? (bool) $s['live_feed_enabled']   : false;
			$live_banner_on = isset( $s['live_banner_enabled'] )  ? (bool) $s['live_banner_enabled']  : false;
			$nv_on          = isset( $s['new_video_notify'] )     ? (bool) $s['new_video_notify']     : false;
			$nv_msg         = $s['new_video_notify_msg'] ?? '';
			?>
			<div class="pvc-panel" id="pvc-panel-notifications">

				<!-- Live status indicator -->
				<div class="pvc-live-status" id="pvc-live-status">
					<span class="pvc-live-status__dot" id="pvc-live-dot"></span>
					<span class="pvc-live-status__text" id="pvc-live-text">Checking live status&hellip;</span>
					<button class="pvc-live-status__refresh" id="pvc-live-check-btn" title="Check now">
						<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35A7.958 7.958 0 0012 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0112 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
					</button>
				</div>
				<button class="pvc-test-mode-btn" id="pvc-test-mode-btn" data-active="0">
					<span class="pvc-test-mode-btn__dot"></span>
					<span class="pvc-test-mode-btn__label">Enable Test Mode</span>
				</button>

				<div class="pvc-test-video-field" id="pvc-test-video-field">
					<span class="pvc-sub-label">Test Video ID <span class="pvc-sub-label-opt">(optional)</span></span>
					<input type="text" class="pvc-input pvc-input--sm" id="pvc-test-video-id"
						placeholder="e.g. dQw4w9WgXcQ" maxlength="11" autocomplete="off" spellcheck="false">
					<span class="pvc-hint">Paste a live YouTube video ID to load the real chat. Leave blank to use the layout placeholder.</span>
				</div>

				<div class="pvc-aside-section" style="margin-top:12px;">
					<label class="pvc-aside-toggle">
						<input type="checkbox" data-setting="live_hide_content" <?php checked( ! empty( $s['live_hide_content'] ) ); ?>>
						<span class="pvc-aside-toggle__label">Hide Videos &amp; Sidebar While Live</span>
					</label>
					<span class="pvc-hint">Broadcast layout only. When live (or in Test Mode), hides the video library and sidebar &mdash; only the stream and chat are visible.</span>
				</div>

				<div class="pvc-divider"></div>

				<div class="pvc-aside-section" id="pvc-section-live-feed">
					<label class="pvc-aside-toggle">
						<input type="checkbox" data-setting="live_feed_enabled" <?php checked( $live_feed_on ); ?>>
						<span class="pvc-aside-toggle__label">Enable Live Feed</span>
					</label>
					<span class="pvc-hint">Automatically shows a live stream embed at the top of your archive page when your YouTube channel is broadcasting live. Checked every 2 minutes.</span>
					<div class="pvc-sub-fields<?php echo $live_feed_on ? '' : ' pvc-collapsed'; ?>" style="margin-top:10px;">
						<label class="pvc-aside-toggle pvc-aside-toggle--sub">
							<input type="checkbox" data-setting="live_chat_enabled" <?php checked( ! empty( $s['live_chat_enabled'] ) ); ?>>
							<span class="pvc-aside-toggle__label">Show Live Chat</span>
						</label>
						<span class="pvc-hint">Embeds YouTube&rsquo;s live chat panel alongside the video, sized to the container. Visitors can participate without leaving your site.</span>
					</div>
				</div>

				<div class="pvc-divider"></div>

				<div class="pvc-aside-section" id="pvc-section-live-banner">
					<label class="pvc-aside-toggle">
						<input type="checkbox" data-setting="live_banner_enabled" <?php checked( $live_banner_on ); ?>>
						<span class="pvc-aside-toggle__label">Sitewide Live Banner</span>
					</label>
					<span class="pvc-hint">Shows a dismissable banner at the top of every page on your site when your channel is live. Visitors can close it and it won&rsquo;t reappear for that stream.</span>
				</div>

				<div class="pvc-divider"></div>

				<div class="pvc-aside-section" id="pvc-section-new-video">
					<label class="pvc-aside-toggle">
						<input type="checkbox" data-setting="new_video_notify" <?php checked( $nv_on ); ?>>
						<span class="pvc-aside-toggle__label">New Video Notification</span>
					</label>
					<div class="pvc-sub-fields<?php echo $nv_on ? '' : ' pvc-collapsed'; ?>">
						<div class="pvc-field" style="margin-top:12px;">
							<span class="pvc-label">Notification Message</span>
							<input type="text" class="pvc-text-input" data-setting="new_video_notify_msg"
								value="<?php echo esc_attr( $nv_msg ?: __( 'New videos have been added!', 'pv-youtube-importer' ) ); ?>"
								placeholder="<?php esc_attr_e( 'New videos have been added!', 'pv-youtube-importer' ); ?>">
							<span class="pvc-hint">Shown once per browser as a small toast when new content is published. Visitors dismiss it themselves.</span>
						</div>
					</div>
				</div>

				<button class="pvc-tour-replay-btn" id="pvc-tour-replay-btn" type="button">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 5V1L7 6l5 5V7c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z"/></svg>
					Replay feature tour
				</button>

			</div><!-- /#pvc-panel-notifications -->

		</div><!-- /.pvc-settings -->

		</div><!-- /.pvc-body -->
	</div><!-- /.pvc-sidebar -->

	<!-- ── Preview Pane ───────────────────────────────────── -->
	<div class="pvc-preview">
		<div class="pvc-preview-bar">
			<div class="pvc-devices">
				<button class="pvc-device-btn pvc-device-btn--active" data-device="desktop" title="Desktop"><svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M20 3H4c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h3l-1 1v1h12v-1l-1-1h3c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 13H4V5h16v11z"/></svg></button>
				<button class="pvc-device-btn" data-device="tablet" title="Tablet"><svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M18.5 0h-14C3.12 0 2 1.12 2 2.5v19C2 22.88 3.12 24 4.5 24h14c1.38 0 2.5-1.12 2.5-2.5v-19C21 1.12 19.88 0 18.5 0zm-7 23c-.83 0-1.5-.67-1.5-1.5S10.67 20 11.5 20s1.5.67 1.5 1.5S12.33 23 11.5 23zm7.5-4H4V3h15v16z"/></svg></button>
				<button class="pvc-device-btn" data-device="mobile" title="Mobile"><svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 1h-8C6.12 1 5 2.12 5 3.5v17C5 21.88 6.12 23 7.5 23h8c1.38 0 2.5-1.12 2.5-2.5v-17C18 2.12 16.88 1 15.5 1zm-4 21c-.83 0-1.5-.67-1.5-1.5S10.67 19 11.5 19s1.5.67 1.5 1.5S12.33 22 11.5 22zm4.5-4H7V4h9v14z"/></svg></button>
			</div>
			<div class="pvc-preview-url" id="pvc-url-display"><?php echo esc_html( $archive_url ); ?></div>
			<div class="pvc-preview-status" id="pvc-status"><span class="pvc-preview-status__dot"></span><span class="pvc-preview-status__text">Ready</span></div>
		</div>
		<div class="pvc-frame-outer">
			<div class="pvc-frame-wrap" id="pvc-frame-wrap" data-device="desktop">
				<iframe id="pvc-preview-iframe" src="about:blank" title="Archive preview"></iframe>
				<div class="pvc-frame-loading" id="pvc-frame-loading"><div class="pvc-frame-loading__spinner"></div></div>
				<!-- Tour preview callout — points to where banner/toast appear in the iframe -->
				<div class="pvc-preview-callout" id="pvc-preview-callout">
					<span class="pvc-preview-callout__label" id="pvc-preview-callout-label"></span>
				</div>
			</div>
		</div>
	</div><!-- /.pvc-preview -->

</div><!-- /.pvc-wrap -->
<div class="pvc-toast" id="pvc-toast"></div>
