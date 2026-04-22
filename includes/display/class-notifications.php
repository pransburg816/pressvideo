<?php
/**
 * PressVideo — Sitewide notifications: live flash banner + new video toast.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Notifications {

	private array $settings;
	private bool  $is_force_live = false;

	public function register(): void {
		$this->settings = get_option( 'pv_settings', [] );

		// In preview context, merge preview settings so customizer toggles take effect immediately
		$this->is_force_live = false;
		if (
			isset( $_GET['pv_preview'], $_GET['pv_nonce'] ) // phpcs:ignore
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['pv_nonce'] ) ), 'pv_preview' ) // phpcs:ignore
			&& current_user_can( 'manage_options' )
		) {
			$_preview = get_transient( 'pv_preview_settings' );
			if ( is_array( $_preview ) ) {
				$this->settings = array_merge( $this->settings, $_preview );
			}
			if ( isset( $_GET['pv_force_live'] ) ) { // phpcs:ignore
				$this->is_force_live = true;
			}
		}

		$live_banner_on = ! empty( $this->settings['live_banner_enabled'] ) || $this->is_force_live;
		$new_video_on   = ! empty( $this->settings['new_video_notify'] )   || $this->is_force_live;

		if ( ! $live_banner_on && ! $new_video_on ) return;

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_body_open',       [ $this, 'render_live_banner' ] );
		add_action( 'wp_footer',          [ $this, 'render_live_banner_fallback' ] );
	}

	public function enqueue_assets(): void {
		wp_enqueue_style(
			'pv-notifications',
			PV_PLUGIN_URL . 'assets/dist/css/pv-notifications.css',
			[],
			PV_VERSION
		);

		$archive_url    = get_post_type_archive_link( 'pv_youtube' ) ?: home_url( '/' );
		$new_video_on   = ! empty( $this->settings['new_video_notify'] );
		$latest_ts      = (int) get_option( 'pv_latest_video_ts', 0 );
		$notify_message = sanitize_text_field( $this->settings['new_video_notify_msg'] ?? __( 'New videos have been added!', 'pv-youtube-importer' ) );

		wp_enqueue_script(
			'pv-notifications',
			PV_PLUGIN_URL . 'assets/dist/js/pv-notifications.js',
			[],
			PV_VERSION,
			true
		);
		wp_localize_script( 'pv-notifications', 'pvNotify', [
			'newVideoOn'  => $new_video_on,
			'latestTs'    => $latest_ts,
			'message'     => $notify_message,
			'archiveUrl'  => esc_url( $archive_url ),
			'forceTest'   => $this->is_force_live,
		] );
	}

	public function render_live_banner(): void {
		if ( empty( $this->settings['live_banner_enabled'] ) && ! $this->is_force_live ) return;
		$this->output_live_banner();
		remove_action( 'wp_footer', [ $this, 'render_live_banner_fallback' ] );
	}

	public function render_live_banner_fallback(): void {
		if ( empty( $this->settings['live_banner_enabled'] ) && ! $this->is_force_live ) return;
		$this->output_live_banner();
	}

	private function output_live_banner(): void {
		$stream = null;

		if ( $this->is_force_live ) {
			$stream = [ 'video_id' => 'jNQXAC9IVRw', 'title' => 'Preview: Your Live Stream Title Goes Here' ];
		} else {
			$api_key    = $this->settings['api_key']    ?? '';
			$channel_id = $this->settings['channel_id'] ?? '';
			if ( ! $api_key || ! $channel_id ) return;

			$api    = new PV_YouTube_API( $api_key );
			$stream = $api->get_live_stream( $channel_id );
		}

		if ( ! $stream || empty( $stream['video_id'] ) ) return;

		$archive_url = get_post_type_archive_link( 'pv_youtube' ) ?: home_url( '/' );
		$accent      = $this->settings['default_accent'] ?? '#4f46e5';
		?>
		<div id="pv-live-banner" class="pv-live-banner" data-stream-id="<?php echo esc_attr( $stream['video_id'] ); ?>" style="--pv-banner-accent:<?php echo esc_attr( $accent ); ?>">
			<span class="pv-live-banner__dot" aria-hidden="true"></span>
			<span class="pv-live-banner__label"><?php esc_html_e( 'Live Now:', 'pv-youtube-importer' ); ?></span>
			<span class="pv-live-banner__title"><?php echo esc_html( $stream['title'] ); ?></span>
			<a href="<?php echo esc_url( $archive_url ); ?>" class="pv-live-banner__watch"><?php esc_html_e( 'Watch Live', 'pv-youtube-importer' ); ?> &rarr;</a>
			<button class="pv-live-banner__close" aria-label="<?php esc_attr_e( 'Dismiss', 'pv-youtube-importer' ); ?>">&#x2715;</button>
		</div>
		<?php
	}
}
