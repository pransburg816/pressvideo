<?php
/**
 * Template tag functions for use in theme templates.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Output a single video trigger button.
 *
 * @param int    $post_id  pv_video post ID.
 * @param string $label    Button label text.
 */
function pv_video_player( int $post_id, string $label = '' ): void {
	$sc = new PV_Shortcodes();
	echo $sc->render_single_video( [ // phpcs:ignore WordPress.Security.EscapeOutput
		'id'    => $post_id,
		'label' => $label ?: __( 'Watch Video', 'pv-youtube-importer' ),
	] );
}

/**
 * Output a video grid.
 *
 * @param array $args  Shortcode-compatible args array.
 */
function pv_video_grid( array $args = [] ): void {
	$sc = new PV_Shortcodes();
	echo $sc->render_grid( $args ); // phpcs:ignore WordPress.Security.EscapeOutput
}
