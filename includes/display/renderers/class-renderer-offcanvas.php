<?php
/**
 * Offcanvas drawer renderer. Silver+ (available on free tier).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Renderer_Offcanvas implements PV_Renderer_Interface {

	public function render( array $videos, array $args ): string {
		if ( empty( $videos ) ) {
			return '<p class="pv-no-videos">' . esc_html__( 'No videos found.', 'pv-youtube-importer' ) . '</p>';
		}

		// Signal that the offcanvas HTML and assets should be loaded.
		do_action( 'pv_player_enqueued' );

		$grid = new PV_Video_Grid();
		return $grid->render( $videos, $args );
	}

	public function enqueue_assets(): void {
		// Assets are enqueued in PV_Plugin::enqueue_frontend_assets() via the pv_player_enqueued action.
	}

	public static function get_label(): string {
		return __( 'Offcanvas Drawer', 'pv-youtube-importer' );
	}

	public static function get_icon(): string {
		return 'dashicons-slides';
	}

	public static function get_description(): string {
		return __( 'Video opens in a branded slide-in drawer — no page reload.', 'pv-youtube-importer' );
	}

	public static function get_required_tier(): string {
		return 'silver';
	}
}
