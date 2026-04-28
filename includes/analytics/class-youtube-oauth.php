<?php
/**
 * YouTube Analytics OAuth 2.0 flow.
 *
 * Handles the Google OAuth2 authorization code flow so the plugin can
 * call the YouTube Analytics API on behalf of the channel owner.
 *
 * Credentials (Client ID + Secret) are stored in pv_settings.
 * The refresh token is stored separately in pv_yt_oauth.
 *
 * Scopes requested:
 *   https://www.googleapis.com/auth/yt-analytics.readonly
 *   https://www.googleapis.com/auth/youtube.readonly
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_YouTube_OAuth {

	const OPTION_KEY   = 'pv_yt_oauth';
	const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
	const REVOKE_URL   = 'https://oauth2.googleapis.com/revoke';
	const AUTH_URL     = 'https://accounts.google.com/o/oauth2/v2/auth';

	const SCOPES = [
		'https://www.googleapis.com/auth/yt-analytics.readonly',
		'https://www.googleapis.com/auth/youtube.readonly',
	];

	// ── Credentials helpers ──────────────────────────────────────────────

	public static function get_client_id(): string {
		$settings = get_option( 'pv_settings', [] );
		return sanitize_text_field( $settings['yt_client_id'] ?? '' );
	}

	public static function get_client_secret(): string {
		$settings = get_option( 'pv_settings', [] );
		return sanitize_text_field( $settings['yt_client_secret'] ?? '' );
	}

	public static function has_credentials(): bool {
		return self::get_client_id() !== '' && self::get_client_secret() !== '';
	}

	// ── Redirect URI — always points to the analytics admin page ────────

	public static function redirect_uri(): string {
		return admin_url( 'edit.php?post_type=pv_youtube&page=pv-analytics&pv_yta_callback=1' );
	}

	// ── Build the Google consent-screen URL ─────────────────────────────

	public static function get_auth_url(): string {
		$state = wp_create_nonce( 'pv_yta_state' );
		update_option( 'pv_yta_state', $state, false );

		return add_query_arg( [
			'client_id'             => rawurlencode( self::get_client_id() ),
			'redirect_uri'          => rawurlencode( self::redirect_uri() ),
			'response_type'         => 'code',
			'scope'                 => rawurlencode( implode( ' ', self::SCOPES ) ),
			'access_type'           => 'offline',
			'prompt'                => 'consent',
			'state'                 => rawurlencode( $state ),
		], self::AUTH_URL );
	}

	// ── Exchange authorization code for tokens ───────────────────────────

	public static function handle_callback( string $code, string $state ): bool {
		$stored_state = get_option( 'pv_yta_state', '' );
		if ( ! $stored_state || ! hash_equals( $stored_state, $state ) ) {
			return false;
		}
		delete_option( 'pv_yta_state' );

		$response = wp_remote_post( self::TOKEN_URL, [
			'timeout' => 15,
			'body'    => [
				'code'          => $code,
				'client_id'     => self::get_client_id(),
				'client_secret' => self::get_client_secret(),
				'redirect_uri'  => self::redirect_uri(),
				'grant_type'    => 'authorization_code',
			],
		] );

		if ( is_wp_error( $response ) ) return false;

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['refresh_token'] ) ) return false;

		$token_data = [
			'refresh_token' => sanitize_text_field( $body['refresh_token'] ),
			'access_token'  => sanitize_text_field( $body['access_token'] ?? '' ),
			'expires_at'    => time() + (int) ( $body['expires_in'] ?? 3600 ) - 60,
		];

		update_option( self::OPTION_KEY, $token_data, false );
		return true;
	}

	// ── Get a valid access token (refreshes automatically) ──────────────

	public static function get_access_token(): string {
		$data = get_option( self::OPTION_KEY, [] );
		if ( empty( $data['refresh_token'] ) ) return '';

		// Still valid?
		if ( ! empty( $data['access_token'] ) && time() < (int) ( $data['expires_at'] ?? 0 ) ) {
			return $data['access_token'];
		}

		// Refresh it.
		$response = wp_remote_post( self::TOKEN_URL, [
			'timeout' => 15,
			'body'    => [
				'refresh_token' => $data['refresh_token'],
				'client_id'     => self::get_client_id(),
				'client_secret' => self::get_client_secret(),
				'grant_type'    => 'refresh_token',
			],
		] );

		if ( is_wp_error( $response ) ) return '';

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) return '';

		$data['access_token'] = sanitize_text_field( $body['access_token'] );
		$data['expires_at']   = time() + (int) ( $body['expires_in'] ?? 3600 ) - 60;
		update_option( self::OPTION_KEY, $data, false );

		return $data['access_token'];
	}

	// ── Connection state ─────────────────────────────────────────────────

	public static function is_connected(): bool {
		$data = get_option( self::OPTION_KEY, [] );
		return ! empty( $data['refresh_token'] );
	}

	// ── Revoke + clear tokens ────────────────────────────────────────────

	public static function disconnect(): void {
		$data = get_option( self::OPTION_KEY, [] );

		if ( ! empty( $data['refresh_token'] ) ) {
			wp_remote_post( self::REVOKE_URL, [
				'timeout' => 10,
				'body'    => [ 'token' => $data['refresh_token'] ],
			] );
		}

		delete_option( self::OPTION_KEY );
		delete_option( 'pv_yta_state' );
		delete_transient( 'pv_yta_channel_stats' );

		// Clear all per-video transients.
		global $wpdb;
		$wpdb->query( // phpcs:ignore
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pv_yta_%' OR option_name LIKE '_transient_timeout_pv_yta_%'"
		);
	}
}
