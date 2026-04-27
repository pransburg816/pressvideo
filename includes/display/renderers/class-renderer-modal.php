<?php
/**
 * Modal popup renderer. Gold+ tier.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Renderer_Modal implements PV_Renderer_Interface {

	public function render( array $videos, array $args ): string {
		if ( empty( $videos ) ) {
			return '<p class="pv-no-videos">' . esc_html__( 'No videos found.', 'pv-youtube-importer' ) . '</p>';
		}

		do_action( 'pv_player_enqueued' );

		$grid = new PV_Video_Grid();
		return $grid->render( $videos, $args );
	}

	public function enqueue_assets(): void {}

	public static function get_label(): string {
		return __( 'Modal Popup', 'pv-youtube-importer' );
	}

	public static function get_icon(): string {
		return 'dashicons-format-video';
	}

	public static function get_description(): string {
		return __( 'Cinematic full-screen overlay with filmstrip navigation.', 'pv-youtube-importer' );
	}

	public static function get_required_tier(): string {
		return 'gold';
	}
}
