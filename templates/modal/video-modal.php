<?php
/**
 * Modal popup player HTML. Injected once in wp_footer when display_mode is 'modal'.
 * Override: place pv-youtube-importer/modal/video-modal.php in your theme folder.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$_arrow_left  = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>';
$_arrow_right = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>';
?>
<div id="pv-modal-overlay" class="pv-modal-overlay"
     role="dialog" aria-modal="true" aria-hidden="true"
     aria-label="<?php esc_attr_e( 'Video Player', 'pv-youtube-importer' ); ?>">

	<div class="pv-modal-positioner">

		<button class="pv-modal-nav pv-modal-nav--prev" type="button" disabled hidden
		        aria-label="<?php esc_attr_e( 'Previous video', 'pv-youtube-importer' ); ?>">
			<?php echo $_arrow_left; // phpcs:ignore ?>
		</button>

		<div class="pv-modal-card">

			<button class="pv-modal-close" type="button"
			        aria-label="<?php esc_attr_e( 'Close player', 'pv-youtube-importer' ); ?>">&#x2715;</button>

			<div class="pv-modal-video">
				<iframe class="pv-modal-iframe" src="about:blank"
				        allow="autoplay; fullscreen; accelerometer; encrypted-media; gyroscope; picture-in-picture"
				        allowfullscreen
				        title="<?php esc_attr_e( 'Video', 'pv-youtube-importer' ); ?>"></iframe>
			</div>

			<div class="pv-modal-meta">
				<div class="pv-modal-counter" hidden></div>
				<h3 class="pv-modal-title"></h3>
				<p class="pv-modal-desc"></p>
			</div>

			<div class="pv-modal-strip" hidden></div>

			<div class="pv-modal-mobile-nav" hidden>
				<button class="pv-modal-mobile-nav-btn pv-modal-mobile-nav-btn--prev" type="button" disabled
				        aria-label="<?php esc_attr_e( 'Previous video', 'pv-youtube-importer' ); ?>">
					<?php echo $_arrow_left; // phpcs:ignore ?>
				</button>
				<span class="pv-modal-mobile-counter"></span>
				<button class="pv-modal-mobile-nav-btn pv-modal-mobile-nav-btn--next" type="button" disabled
				        aria-label="<?php esc_attr_e( 'Next video', 'pv-youtube-importer' ); ?>">
					<?php echo $_arrow_right; // phpcs:ignore ?>
				</button>
			</div>

		</div><!-- /.pv-modal-card -->

		<button class="pv-modal-nav pv-modal-nav--next" type="button" disabled hidden
		        aria-label="<?php esc_attr_e( 'Next video', 'pv-youtube-importer' ); ?>">
			<?php echo $_arrow_right; // phpcs:ignore ?>
		</button>

	</div><!-- /.pv-modal-positioner -->

</div><!-- /#pv-modal-overlay -->
