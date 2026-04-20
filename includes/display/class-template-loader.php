<?php
/**
 * Intercepts template_include for pv_youtube single and archive pages.
 * The plugin serves its own templates — zero IAC theme files touched.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Template_Loader {

	public function register(): void {
		add_filter( 'template_include', [ $this, 'load_template' ] );
	}

	public function load_template( string $template ): string {
		if ( is_singular( 'pv_youtube' ) ) {
			$override = get_stylesheet_directory() . '/pv-youtube-importer/single/single-video.php';
			return file_exists( $override ) ? $override : PV_PLUGIN_DIR . 'templates/single/single-video.php';
		}

		if ( is_post_type_archive( 'pv_youtube' ) || is_tax( [ 'pv_category', 'pv_tag', 'pv_series', 'pv_type' ] ) ) {
			$override = get_stylesheet_directory() . '/pv-youtube-importer/archive/archive-videos.php';
			return file_exists( $override ) ? $override : PV_PLUGIN_DIR . 'templates/archive/archive-videos.php';
		}

		return $template;
	}

}

/**
 * Resolve the watch page layout for a given post.
 * Priority: per-video meta → global setting → 'hero-top'.
 */
function pv_resolve_watch_layout( int $post_id ): string {
	$valid = [ 'hero-top', 'hero-split', 'theater' ];

	$per_video = get_post_meta( $post_id, '_pv_watch_layout', true );
	if ( $per_video && 'inherit' !== $per_video && in_array( $per_video, $valid, true ) ) {
		return $per_video;
	}

	$settings = get_option( 'pv_settings', [] );
	$global   = $settings['watch_page_layout'] ?? 'hero-top';
	if ( in_array( $global, $valid, true ) ) return $global;

	return 'hero-top';
}
