<?php
/**
 * AJAX handler for the "Run Import Now" button on the settings page.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Import_UI {

	public function register(): void {
		add_action( 'wp_ajax_pv_manual_import', [ $this, 'handle_import' ] );
		add_action( 'wp_ajax_pv_api_test',      [ $this, 'handle_api_test' ] );
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

		// Normalise various YouTube URL / handle formats → bare ID or @handle.
		// youtube.com/channel/UCxxx  → UCxxx
		// youtube.com/@handle        → @handle  (resolved via forHandle in API)
		// youtube.com/c/customname   → @customname
		// youtube.com/user/username  → username (resolved via forUsername in API)
		if ( str_contains( $channel_id, 'youtube.com' ) ) {
			if ( preg_match( '#/channel/([A-Za-z0-9_\-]+)#', $channel_id, $m ) ) {
				$channel_id = $m[1];
			} elseif ( preg_match( '#/@([A-Za-z0-9_\-\.]+)#', $channel_id, $m ) ) {
				$channel_id = '@' . $m[1];
			} elseif ( preg_match( '#/c/([A-Za-z0-9_\-\.]+)#', $channel_id, $m ) ) {
				$channel_id = '@' . $m[1];
			} elseif ( preg_match( '#/user/([A-Za-z0-9_\-\.]+)#', $channel_id, $m ) ) {
				$channel_id = $m[1]; // passed as forUsername
			}
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
			$message .= ' ' . __( 'Video limit reached. Upgrade to import more.', 'pv-youtube-importer' );
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

	/** Step-by-step API diagnostic — returns exactly where the call chain breaks. */
	public function handle_api_test(): void {
		check_ajax_referer( 'pv_manual_import_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'steps' => [] ], 403 );
		}

		$settings   = get_option( 'pv_settings', [] );
		$api_key    = $settings['api_key']    ?? '';
		$channel_id = sanitize_text_field( $settings['channel_id'] ?? '' );
		$steps      = [];

		// Step 1 — credentials present?
		$key_display = $api_key ? ( substr( $api_key, 0, 8 ) . str_repeat( '•', max( 0, strlen( $api_key ) - 8 ) ) ) : '(empty)';
		$steps[] = [
			'label' => 'Credentials in DB',
			'ok'    => $api_key && $channel_id,
			'detail'=> "API key: {$key_display} | Channel ID: " . ( $channel_id ?: '(empty)' ),
		];

		if ( ! $api_key || ! $channel_id ) {
			wp_send_json_success( [ 'steps' => $steps ] );
		}

		// Step 2 — channels.list lookup
		$lookup_param = str_starts_with( $channel_id, 'UC' ) ? [ 'id' => $channel_id ]
			: ( str_starts_with( $channel_id, '@' ) ? [ 'forHandle' => $channel_id ] : [ 'forUsername' => $channel_id ] );

		$ch_url  = 'https://www.googleapis.com/youtube/v3/channels?' . http_build_query( array_merge( [ 'part' => 'contentDetails,snippet', 'key' => $api_key ], $lookup_param ), '', '&', PHP_QUERY_RFC3986 );
		$ch_resp = wp_remote_get( $ch_url, [ 'timeout' => 15, 'user-agent' => 'PressVideoPlugin/1.0', 'headers' => [ 'Accept' => 'application/json', 'Accept-Encoding' => 'identity' ] ] );

		if ( is_wp_error( $ch_resp ) ) {
			$steps[] = [ 'label' => 'channels.list API call', 'ok' => false, 'detail' => $ch_resp->get_error_message() ];
			wp_send_json_success( [ 'steps' => $steps ] );
		}

		$ch_code = wp_remote_retrieve_response_code( $ch_resp );
		$ch_body = json_decode( wp_remote_retrieve_body( $ch_resp ), true );

		if ( $ch_code !== 200 || ! is_array( $ch_body ) ) {
			$steps[] = [ 'label' => 'channels.list API call', 'ok' => false, 'detail' => "HTTP {$ch_code}: " . ( $ch_body['error']['message'] ?? 'invalid response' ) ];
			wp_send_json_success( [ 'steps' => $steps ] );
		}

		$ch_items   = $ch_body['items'] ?? [];
		$ch_title   = $ch_items[0]['snippet']['title'] ?? '(no title)';
		$uploads_pl = $ch_items[0]['contentDetails']['relatedPlaylists']['uploads'] ?? '';

		$steps[] = [
			'label'  => 'channels.list API call',
			'ok'     => ! empty( $ch_items ) && $uploads_pl,
			'detail' => empty( $ch_items )
				? 'Channel not found — check channel ID'
				: "Channel: \"{$ch_title}\" | Uploads playlist: " . ( $uploads_pl ?: '(none)' ),
		];

		if ( empty( $ch_items ) || ! $uploads_pl ) {
			wp_send_json_success( [ 'steps' => $steps ] );
		}

		// Step 3 — playlistItems.list for uploads playlist
		$pl_url  = 'https://www.googleapis.com/youtube/v3/playlistItems?' . http_build_query( [ 'part' => 'snippet', 'playlistId' => $uploads_pl, 'maxResults' => 1, 'key' => $api_key ], '', '&', PHP_QUERY_RFC3986 );
		$pl_resp = wp_remote_get( $pl_url, [ 'timeout' => 15, 'user-agent' => 'PressVideoPlugin/1.0', 'headers' => [ 'Accept' => 'application/json', 'Accept-Encoding' => 'identity' ] ] );

		if ( is_wp_error( $pl_resp ) ) {
			$steps[] = [ 'label' => 'playlistItems.list API call', 'ok' => false, 'detail' => $pl_resp->get_error_message() ];
			wp_send_json_success( [ 'steps' => $steps ] );
		}

		$pl_code  = wp_remote_retrieve_response_code( $pl_resp );
		$pl_body  = json_decode( wp_remote_retrieve_body( $pl_resp ), true );

		if ( $pl_code !== 200 || ! is_array( $pl_body ) ) {
			$steps[] = [ 'label' => 'playlistItems.list API call', 'ok' => false, 'detail' => "HTTP {$pl_code}: " . ( $pl_body['error']['message'] ?? 'invalid response' ) . ' — will use search.list fallback' ];

			// Test the search.list fallback.
			$sr_url  = 'https://www.googleapis.com/youtube/v3/search?' . http_build_query( [ 'part' => 'snippet', 'channelId' => $channel_id, 'type' => 'video', 'order' => 'date', 'maxResults' => 1, 'key' => $api_key ], '', '&', PHP_QUERY_RFC3986 );
			$sr_resp = wp_remote_get( $sr_url, [ 'timeout' => 15, 'user-agent' => 'PressVideoPlugin/1.0', 'headers' => [ 'Accept' => 'application/json', 'Accept-Encoding' => 'identity' ] ] );
			$sr_code = is_wp_error( $sr_resp ) ? 0 : wp_remote_retrieve_response_code( $sr_resp );
			$sr_body = is_wp_error( $sr_resp ) ? [] : json_decode( wp_remote_retrieve_body( $sr_resp ), true );
			$sr_title = $sr_body['items'][0]['snippet']['title'] ?? null;
			$steps[] = [
				'label'  => 'search.list fallback',
				'ok'     => $sr_code === 200 && $sr_title,
				'detail' => $sr_code === 200 && $sr_title
					? "OK — first video: \"{$sr_title}\""
					: ( is_wp_error( $sr_resp ) ? $sr_resp->get_error_message() : "HTTP {$sr_code}: " . ( $sr_body['error']['message'] ?? 'no results' ) ),
			];
			wp_send_json_success( [ 'steps' => $steps ] );
		}

		$pl_items    = $pl_body['items'] ?? [];
		$first_title = $pl_items[0]['snippet']['title'] ?? null;
		$steps[] = [
			'label'  => 'playlistItems.list API call',
			'ok'     => true,
			'detail' => $first_title ? "OK — first video: \"{$first_title}\"" : 'OK — playlist accessible (0 videos)',
		];

		wp_send_json_success( [ 'steps' => $steps ] );
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

			const testBtn = document.getElementById('pv-test-connection');
			if (testBtn && result && nonce) {
				testBtn.addEventListener('click', function() {
					testBtn.disabled = true;
					result.innerHTML = '<div class="notice notice-info inline"><p>Testing…</p></div>';
					const d = new FormData();
					d.append('action', 'pv_api_test');
					d.append('nonce', nonce.value);
					fetch(ajaxurl, { method: 'POST', body: d })
						.then(r => r.json())
						.then(json => {
							const steps = json.data?.steps ?? [];
							const rows = steps.map(s => {
								const icon = s.ok ? '✅' : '❌';
								return `<tr><td style="padding:4px 8px 4px 0">${icon}</td><td style="padding:4px 8px"><strong>${s.label}</strong></td><td style="padding:4px 0;color:#aaa;font-size:12px">${s.detail}</td></tr>`;
							}).join('');
							result.innerHTML = `<div class="notice notice-info inline" style="padding:12px 16px"><strong>API Diagnostic</strong><table style="border-collapse:collapse;width:100%;margin-top:8px">${rows}</table></div>`;
						})
						.catch(() => { result.innerHTML = '<div class="notice notice-error inline"><p>Request failed.</p></div>'; })
						.finally(() => { testBtn.disabled = false; });
				});
			}

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
