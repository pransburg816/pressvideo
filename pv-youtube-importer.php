<?php
/**
 * Plugin Name: PressVideo
 * Plugin URI:  https://pressvideo.com
 * Description: Auto-import YouTube videos into a custom post type with an offcanvas player, color tagging, shortcodes, and multiple display layouts.
 * Version:     1.0.8
 * Author:      Phillip Tyrone Ransburg
 * Author URI:  https://pressvideo.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pv-youtube-importer
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PV_VERSION',     '1.0.8' );
define( 'PV_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'PV_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'PV_PLUGIN_FILE', __FILE__ );

require_once PV_PLUGIN_DIR . 'includes/class-activator.php';
require_once PV_PLUGIN_DIR . 'includes/class-deactivator.php';

register_activation_hook( __FILE__, [ 'PV_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'PV_Deactivator', 'deactivate' ] );

require_once PV_PLUGIN_DIR . 'includes/class-plugin.php';

function pv_run(): void {
	( new PV_Plugin() )->run();
}
add_action( 'plugins_loaded', 'pv_run' );

/**
 * Decode HTML entity-encoded shortcode brackets before block/shortcode processing.
 * TinyMCE / Classic Editor can convert [ → &#91; and ] → &#93;.
 * WordPress's do_shortcode() bails immediately if no literal '[' is present.
 * Priority 8 runs before do_blocks (9) and do_shortcode (11).
 */
add_filter( 'the_content', function ( $content ) {
	if ( str_contains( $content, '&#91;' ) || str_contains( $content, '&#93;' ) ) {
		$content = str_replace( [ '&#91;', '&#93;' ], [ '[', ']' ], $content );
	}
	return $content;
}, 8 );
