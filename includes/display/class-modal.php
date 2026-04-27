<?php
/**
 * Injects the modal player HTML into wp_footer when display_mode is 'modal'.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Modal {

	public function register(): void {
		add_action( 'wp_footer', [ $this, 'maybe_render' ], 1 );
	}

	public function maybe_render(): void {
		if ( ! did_action( 'pv_player_enqueued' ) ) return;

		$settings = get_option( 'pv_settings', [] );
		if ( ( $settings['display_mode'] ?? 'offcanvas' ) !== 'modal' ) return;

		$template = PV_Offcanvas::locate_template( 'modal/video-modal.php' );
		if ( $template ) {
			include $template;
		}
	}
}
