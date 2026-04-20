<?php
/**
 * Injects the offcanvas drawer HTML into wp_footer (once per page, only when needed).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Offcanvas {

	public function register(): void {
		// Priority 1: drawer HTML must be in the DOM before wp_print_footer_scripts (priority 20) prints the JS.
		add_action( 'wp_footer', [ $this, 'maybe_render' ], 1 );
	}

	public function maybe_render(): void {
		if ( ! did_action( 'pv_player_enqueued' ) ) return;

		$template = $this->locate_template( 'offcanvas/video-offcanvas.php' );
		if ( $template ) {
			include $template;
		}
	}

	public static function locate_template( string $name ): string {
		$theme_file  = get_stylesheet_directory() . '/pv-youtube-importer/' . $name;
		$plugin_file = PV_PLUGIN_DIR . 'templates/' . $name;
		return file_exists( $theme_file ) ? $theme_file : $plugin_file;
	}
}
