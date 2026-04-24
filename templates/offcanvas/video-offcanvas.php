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

		<button class="pv-close" aria-label="<?php esc_attr_e( 'Close player', 'pv-youtube-importer' ); ?>">&#x2715;</button>

		<div class="pv-video-wrap">
			<div class="pv-spinner" aria-hidden="true"></div>
			<div class="pv-iframe-holder"></div>
		</div>

		<div class="pv-meta">
			<p class="pv-badge pv-rail__heading"></p>
			<h3 class="pv-title"></h3>
			<p class="pv-desc"></p>
		</div>

		<div class="pv-rail" aria-label="<?php esc_attr_e( 'Playlist', 'pv-youtube-importer' ); ?>" role="list"></div>

		<div class="pv-nav" aria-label="<?php esc_attr_e( 'Video navigation', 'pv-youtube-importer' ); ?>">
			<button class="pv-nav-btn pv-prev" aria-label="<?php esc_attr_e( 'Previous video', 'pv-youtube-importer' ); ?>">&#8592; <?php esc_html_e( 'Prev', 'pv-youtube-importer' ); ?></button>
			<span class="pv-nav-count"></span>
			<button class="pv-nav-btn pv-next" aria-label="<?php esc_attr_e( 'Next video', 'pv-youtube-importer' ); ?>"><?php esc_html_e( 'Next', 'pv-youtube-importer' ); ?> &#8594;</button>
		</div>

	</div>
</div>
