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
		if (
			isset( $_GET['pv_preview'], $_GET['pv_nonce'] ) // phpcs:ignore
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['pv_nonce'] ) ), 'pv_preview' ) // phpcs:ignore
			&& current_user_can( 'manage_options' )
		) {
			$_preview = get_transient( 'pv_preview_settings' );
			if ( is_array( $_preview ) ) {
				$settings = array_merge( $settings, $_preview );
			}
		}
		if ( ( $settings['display_mode'] ?? 'offcanvas' ) !== 'modal' ) return;

		$template = PV_Offcanvas::locate_template( 'modal/video-modal.php' );
		if ( $template ) {
			include $template;
		}
	}
}
