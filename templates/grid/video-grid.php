<?php
/**
 * Video grid template.
 * Override: place pv-youtube-importer/grid/video-grid.php in your theme folder.
 *
 * Available variables: $videos (array of WP_Post), $args (shortcode args array).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$grid = new PV_Video_Grid();
echo $grid->render( $videos, $args ); // phpcs:ignore WordPress.Security.EscapeOutput
