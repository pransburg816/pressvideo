<?php
/**
 * AJAX handler for the "Run Import Now" button on the settings page.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Import_UI {

	public function register(): void {
		add_action( 'wp_ajax_pv_manual_import', [ $this, 'handle_import' ] );
		add_action( 'admin_footer',             [ $this, 'print_admin_js' ] );
	}

	public function handle_import(): void {
		check_ajax_referer( 'pv_manual_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'pv-youtube-importer' ) ], 403 );
		}

		$settings   = get_option( 'pv_settings', [] );
		$channel_id = sanitize_text_field( $settings['channel_id'] ?? '' );

		if ( ! $channel_id ) {
			wp_send_json_error( [ 'message' => __( 'No channel ID configured. Please set it in Videos > Settings.', 'pv-youtube-importer' ) ] );
		}

		// Normalise channel URL → ID (e.g. youtube.com/channel/UCxxx → UCxxx).
		if ( str_contains( $channel_id, 'youtube.com' ) ) {
			preg_match( '#channel/([A-Za-z0-9_\-]+)#', $channel_id, $m );
			$channel_id = $m[1] ?? $channel_id;
		}

		$importer = new PV_Channel_Importer();
		$result   = $importer->import_channel( $channel_id );

		$message = sprintf(
			/* translators: 1: imported count, 2: skipped count */
			__( 'Imported %1$d new video(s). %2$d already imported (skipped).', 'pv-youtube-importer' ),
			$result['imported'],
			$result['skipped']
		);

		if ( $result['limit_reached'] ) {
			$message .= ' ' . __( 'Video limit reached — upgrade to import more.', 'pv-youtube-importer' );
		}

		if ( ! empty( $result['errors'] ) ) {
			wp_send_json_error( [
				'message' => implode( ' ', $result['errors'] ) . ' | ' . $message,
				'result'  => $result,
			] );
		}

		wp_send_json_success( [
			'message' => $message,
			'result'  => $result,
		] );
	}

	/** Print the JS that wires up the "Run Import Now" button. */
	public function print_admin_js(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! str_contains( $screen->id ?? '', 'pv-youtube-importer-settings' ) ) return;
		?>
		<script>
		(function() {
			'use strict';
			const btn     = document.getElementById('pv-run-import');
			const spinner = document.getElementById('pv-import-spinner');
			const result  = document.getElementById('pv-import-result');
			const nonce   = document.getElementById('pv_import_nonce');

			if (!btn) return;

			btn.addEventListener('click', function() {
				btn.disabled = true;
				spinner.classList.add('is-active');
				result.innerHTML = '';

				const data = new FormData();
				data.append('action', 'pv_manual_import');
				data.append('nonce', nonce.value);

				fetch(ajaxurl, { method: 'POST', body: data })
					.then(r => r.json())
					.then(json => {
						const cls  = json.success ? 'notice-success' : 'notice-error';
						result.innerHTML = '<div class="notice ' + cls + ' inline"><p>' + json.data.message + '</p></div>';
					})
					.catch(() => {
						result.innerHTML = '<div class="notice notice-error inline"><p><?php echo esc_js( __( 'Request failed. Please try again.', 'pv-youtube-importer' ) ); ?></p></div>';
					})
					.finally(() => {
						btn.disabled = false;
						spinner.classList.remove('is-active');
					});
			});
		}());
		</script>
		<?php
	}
}
