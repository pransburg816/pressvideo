<?php
/**
 * Offcanvas drawer HTML. Injected once in wp_footer.
 * Override: place pv-youtube-importer/offcanvas/video-offcanvas.php in your theme folder.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div id="pv-canvas" class="pv-player pv-offcanvas" aria-hidden="true" role="dialog"
     aria-label="<?php esc_attr_e( 'Video Player', 'pv-youtube-importer' ); ?>"
     aria-modal="true">

	<div class="pv-backdrop" tabindex="-1"></div>

	<div class="pv-panel" tabindex="-1">

		<div class="pv-panel-hd">
			<button class="pv-close" aria-label="<?php esc_attr_e( 'Close player', 'pv-youtube-importer' ); ?>">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
			</button>
		</div>

		<div class="pv-video-wrap">
			<div class="pv-spinner" aria-hidden="true"></div>
			<div class="pv-iframe-holder"></div>
		</div>

		<div class="pv-meta">
			<h3 class="pv-title"></h3>
			<p class="pv-desc"></p>
		</div>

		<div class="pv-player-controls">
			<button class="pv-speed-btn" type="button" aria-label="<?php esc_attr_e( 'Playback speed', 'pv-youtube-importer' ); ?>" aria-haspopup="true">
				<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.38 8.57l-1.23 1.85a8 8 0 0 1-.22 7.58H5.07A8 8 0 0 1 15.58 6.85l1.85-1.23A10 10 0 0 0 3.35 19a2 2 0 0 0 1.72 1h13.85a2 2 0 0 0 1.74-1 10 10 0 0 0-.27-10.44zm-9.79 6.84a2 2 0 0 0 2.83 0l5.66-8.49-8.49 5.66a2 2 0 0 0 0 2.83z"/></svg>
				<span class="pv-speed-btn__label">1×</span>
				<svg width="9" height="9" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 10l5 5 5-5z"/></svg>
			</button>
			<div class="pv-social-btns">
				<a class="pv-social-btn pv-social-btn--yt" href="#" target="_blank" rel="noopener noreferrer"
				   aria-label="<?php esc_attr_e( 'Watch on YouTube', 'pv-youtube-importer' ); ?>"
				   title="<?php esc_attr_e( 'Watch on YouTube', 'pv-youtube-importer' ); ?>">
					<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.58 7.19c-.23-.87-.91-1.56-1.79-1.79C18.25 5 12 5 12 5s-6.25 0-7.79.4c-.88.23-1.56.92-1.79 1.79C2 8.73 2 12 2 12s0 3.27.42 4.81c.23.87.91 1.56 1.79 1.79C5.75 19 12 19 12 19s6.25 0 7.79-.4c.88-.23 1.56-.92 1.79-1.79C22 15.27 22 12 22 12s0-3.27-.42-4.81zM10 15V9l5.2 3-5.2 3z"/></svg>
				</a>
				<a class="pv-social-btn pv-social-btn--x" href="#" target="_blank" rel="noopener noreferrer"
				   aria-label="<?php esc_attr_e( 'Share on X', 'pv-youtube-importer' ); ?>"
				   title="<?php esc_attr_e( 'Share on X', 'pv-youtube-importer' ); ?>">
					<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.737-8.835L1.254 2.25H8.08l4.262 5.636 5.902-5.636zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
				</a>
				<a class="pv-social-btn pv-social-btn--fb" href="#" target="_blank" rel="noopener noreferrer"
				   aria-label="<?php esc_attr_e( 'Share on Facebook', 'pv-youtube-importer' ); ?>"
				   title="<?php esc_attr_e( 'Share on Facebook', 'pv-youtube-importer' ); ?>">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
				</a>
			</div>
			<div class="pv-player-controls__right">
				<button class="pv-loop-btn" type="button" aria-pressed="false"
				        title="<?php esc_attr_e( 'Loop video', 'pv-youtube-importer' ); ?>"
				        aria-label="<?php esc_attr_e( 'Loop', 'pv-youtube-importer' ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4z"/></svg>
				</button>
				<button class="pv-shuffle-btn" type="button" aria-pressed="false"
				        title="<?php esc_attr_e( 'Shuffle playlist', 'pv-youtube-importer' ); ?>"
				        aria-label="<?php esc_attr_e( 'Shuffle', 'pv-youtube-importer' ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10.59 9.17L5.41 4 4 5.41l5.17 5.17 1.42-1.41zM14.5 4l2.04 2.04L4 18.59 5.41 20 17.96 7.46 20 9.5V4h-5.5zm.33 9.41l-1.41 1.41 3.13 3.13L14.5 20H20v-5.5l-2.04 2.04-3.13-3.13z"/></svg>
				</button>
			</div>
		</div>

		<div class="pv-rail-hd">
			<p class="pv-rail__heading pv-playlist-label"></p>
			<button class="pv-autoplay-toggle" type="button" aria-pressed="true"
			        title="<?php esc_attr_e( 'Toggle continuous play', 'pv-youtube-importer' ); ?>">
				<span class="pv-autoplay-toggle__label"><?php esc_html_e( 'Autoplay', 'pv-youtube-importer' ); ?></span>
				<span class="pv-autoplay-toggle__track" aria-hidden="true">
					<span class="pv-autoplay-toggle__thumb"></span>
				</span>
			</button>
		</div>

		<div class="pv-rail-search" style="display:none">
			<svg class="pv-rail-search__icon" width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
			<input type="search" class="pv-rail-search__input"
			       placeholder="<?php esc_attr_e( 'Search playlist…', 'pv-youtube-importer' ); ?>"
			       aria-label="<?php esc_attr_e( 'Search playlist', 'pv-youtube-importer' ); ?>">
			<button class="pv-rail-search__clear" type="button" aria-label="<?php esc_attr_e( 'Clear search', 'pv-youtube-importer' ); ?>" hidden>
				<svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
			</button>
			<button class="pv-rail-search__mic" type="button" aria-label="<?php esc_attr_e( 'Voice search', 'pv-youtube-importer' ); ?>">
				<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.91-3c-.49 0-.9.36-.98.85C16.52 14.2 14.47 16 12 16s-4.52-1.8-4.93-4.15c-.08-.49-.49-.85-.98-.85-.61 0-1.09.54-1 1.14.49 3 2.89 5.35 5.91 5.78V20c0 .55.45 1 1 1s1-.45 1-1v-2.08c3.02-.43 5.42-2.78 5.91-5.78.1-.6-.39-1.14-1-1.14z"/></svg>
			</button>
		</div>

		<div class="pv-rail" aria-label="<?php esc_attr_e( 'Playlist', 'pv-youtube-importer' ); ?>" role="list"></div>

		<div class="pv-nav" aria-label="<?php esc_attr_e( 'Video navigation', 'pv-youtube-importer' ); ?>">
			<button class="pv-nav-btn pv-prev" aria-label="<?php esc_attr_e( 'Previous video', 'pv-youtube-importer' ); ?>">
				<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 6h2v12H6zm3.5 6 8.5 6V6z"/></svg>
				<?php esc_html_e( 'Prev', 'pv-youtube-importer' ); ?>
			</button>
			<span class="pv-nav-count"></span>
			<button class="pv-nav-btn pv-next" aria-label="<?php esc_attr_e( 'Next video', 'pv-youtube-importer' ); ?>">
				<?php esc_html_e( 'Next', 'pv-youtube-importer' ); ?>
				<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
			</button>
		</div>

	</div>
</div>

<!-- PressVideo Music Player — standalone, rendered after offcanvas -->
<?php
$_pvm_s       = get_option( 'pv_settings', [] );
$_pvm_eq_bars = ! empty( $_pvm_s['music_eq_bars'] );
?>
<div id="pv-music-player" class="pvm-player<?php echo $_pvm_eq_bars ? ' pvm-player--eq' : ''; ?>" aria-hidden="true" role="dialog"
     aria-label="<?php esc_attr_e( 'Music Player', 'pv-youtube-importer' ); ?>"
     aria-modal="true">

	<div class="pvm-backdrop"></div>

	<div class="pvm-eq-bars" aria-hidden="true">
		<span class="pvm-eq-bar"></span>
		<span class="pvm-eq-bar"></span>
		<span class="pvm-eq-bar"></span>
		<span class="pvm-eq-bar"></span>
		<span class="pvm-eq-bar"></span>
		<span class="pvm-eq-bar"></span>
		<span class="pvm-eq-bar"></span>
		<span class="pvm-eq-bar"></span>
		<span class="pvm-eq-bar"></span>
		<span class="pvm-eq-bar"></span>
		<span class="pvm-eq-bar"></span>
		<span class="pvm-eq-bar"></span>
		<span class="pvm-eq-bar"></span>
		<span class="pvm-eq-bar"></span>
		<span class="pvm-eq-bar"></span>
		<span class="pvm-eq-bar"></span>
		<span class="pvm-eq-bar"></span>
		<span class="pvm-eq-bar"></span>
	</div>

	<div class="pvm-panel">

		<div class="pvm-hd">
			<button class="pvm-minimize" type="button" aria-label="<?php esc_attr_e( 'Minimize player', 'pv-youtube-importer' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6z"/></svg>
			</button>
			<span class="pvm-hd-label"><?php esc_html_e( 'Now Playing', 'pv-youtube-importer' ); ?></span>
			<button class="pvm-close-btn" type="button" aria-label="<?php esc_attr_e( 'Close player', 'pv-youtube-importer' ); ?>">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
			</button>
		</div>

		<div class="pvm-art-section">
			<div class="pvm-art-wrap">
				<div class="pvm-spinner" aria-hidden="true" hidden></div>
				<div class="pvm-iframe-holder"></div>
			</div>
		</div>

		<div class="pvm-info">
			<h3 class="pvm-title"></h3>
			<p class="pvm-artist"></p>
			<span class="pvm-album-pill" hidden></span>
		</div>

		<div class="pvm-progress-row">
			<span class="pvm-time-cur">0:00</span>
			<div class="pvm-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
				<div class="pvm-progress-fill"></div>
			</div>
			<span class="pvm-time-dur">0:00</span>
		</div>

		<div class="pvm-volume-row">
			<button class="pvm-mute-btn" type="button" aria-label="<?php esc_attr_e( 'Mute', 'pv-youtube-importer' ); ?>">
				<!-- full volume -->
				<svg class="pvm-vol-icon pvm-vol-icon--full" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
				<!-- low volume -->
				<svg class="pvm-vol-icon pvm-vol-icon--low" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.5 12c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM5 9v6h4l5 5V4L9 9H5z"/></svg>
				<!-- muted -->
				<svg class="pvm-vol-icon pvm-vol-icon--mute" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>
			</button>
			<div class="pvm-volume-track" role="slider" aria-label="<?php esc_attr_e( 'Volume', 'pv-youtube-importer' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="80">
				<div class="pvm-volume-fill" style="width:80%"></div>
			</div>
		</div>

		<div class="pvm-controls">
			<button class="pvm-shuffle-btn" type="button" aria-pressed="false"
			        title="<?php esc_attr_e( 'Shuffle', 'pv-youtube-importer' ); ?>"
			        aria-label="<?php esc_attr_e( 'Shuffle', 'pv-youtube-importer' ); ?>">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10.59 9.17L5.41 4 4 5.41l5.17 5.17 1.42-1.41zM14.5 4l2.04 2.04L4 18.59 5.41 20 17.96 7.46 20 9.5V4h-5.5zm.33 9.41l-1.41 1.41 3.13 3.13L14.5 20H20v-5.5l-2.04 2.04-3.13-3.13z"/></svg>
			</button>
			<button class="pvm-prev-btn" type="button" aria-label="<?php esc_attr_e( 'Previous track', 'pv-youtube-importer' ); ?>">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 6h2v12H6zm3.5 6 8.5 6V6z"/></svg>
			</button>
			<button class="pvm-play-btn" type="button" aria-label="<?php esc_attr_e( 'Play / Pause', 'pv-youtube-importer' ); ?>">
				<svg class="pvm-icon--play" width="28" height="28" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
				<svg class="pvm-icon--pause" width="28" height="28" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
			</button>
			<button class="pvm-next-btn" type="button" aria-label="<?php esc_attr_e( 'Next track', 'pv-youtube-importer' ); ?>">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
			</button>
			<button class="pvm-loop-btn" type="button" data-loop-mode="none" aria-pressed="false"
			        title="<?php esc_attr_e( 'Repeat', 'pv-youtube-importer' ); ?>"
			        aria-label="<?php esc_attr_e( 'Repeat off', 'pv-youtube-importer' ); ?>">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4z"/></svg>
				<span class="pvm-loop-badge" aria-hidden="true"></span>
			</button>
		</div>

		<div class="pvm-queue-section">
			<div class="pvm-queue-hd">
				<span><?php esc_html_e( 'Up Next', 'pv-youtube-importer' ); ?></span>
				<span class="pvm-queue-count"></span>
			</div>
			<div class="pvm-queue-list" role="list"></div>
		</div>

	</div>

	<!-- Mini-bar — visible when pvm-player has pvm-minimized class -->
	<div class="pvm-mini-bar" aria-hidden="true">
		<div class="pvm-mini-progress"><div class="pvm-mini-progress-fill"></div></div>
		<div class="pvm-mini-body">
			<div class="pvm-mini-art"><img class="pvm-mini-thumb" src="" alt=""></div>
			<div class="pvm-mini-info">
				<span class="pvm-mini-title"></span>
				<span class="pvm-mini-artist"></span>
			</div>
			<div class="pvm-mini-controls">
				<button class="pvm-mini-prev" type="button" aria-label="<?php esc_attr_e( 'Previous', 'pv-youtube-importer' ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 6h2v12H6zm3.5 6 8.5 6V6z"/></svg>
				</button>
				<button class="pvm-mini-play" type="button" aria-label="<?php esc_attr_e( 'Play / Pause', 'pv-youtube-importer' ); ?>">
					<svg class="pvm-mini-icon--play" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
					<svg class="pvm-mini-icon--pause" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
				</button>
				<button class="pvm-mini-next" type="button" aria-label="<?php esc_attr_e( 'Next', 'pv-youtube-importer' ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
				</button>
			</div>
			<button class="pvm-mini-expand" type="button" aria-label="<?php esc_attr_e( 'Expand player', 'pv-youtube-importer' ); ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 8l-6 6 1.41 1.41L12 10.83l4.59 4.58L18 14z"/></svg>
			</button>
			<button class="pvm-mini-close" type="button" aria-label="<?php esc_attr_e( 'Stop and close', 'pv-youtube-importer' ); ?>">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
			</button>
		</div>
	</div>

</div>
