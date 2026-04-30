<?php
/**
 * YouTube Analytics API v2 — fetch channel and per-video metrics.
 *
 * All results are cached in transients (6 hours).
 * Requires a valid OAuth2 access token from PV_YouTube_OAuth.
 *
 * Metrics pulled:
 *   views, estimatedMinutesWatched, averageViewDuration,
 *   averageViewPercentage, likes, comments, shares,
 *   subscribersGained, subscribersLost, impressions,
 *   impressionClickThroughRate
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_YouTube_Analytics_API {

	const API_BASE  = 'https://youtubeanalytics.googleapis.com/v2/reports';
	const DATA_API  = 'https://www.googleapis.com/youtube/v3';
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	private string $access_token;
	private string $last_error = '';

	public function __construct( string $access_token ) {
		$this->access_token = $access_token;
	}

	public function get_last_error(): string {
		return $this->last_error;
	}

	// ── Channel-level stats (current period) ────────────────────────────

	public function get_channel_stats( int $days = 30 ): array {
		$cache_key = 'pv_yta_channel_' . $days;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) return $cached;

		$end_date   = gmdate( 'Y-m-d' );
		$start_date = $days >= 9999 ? '2005-04-23' : gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		$response = $this->api_get( [
			'ids'        => 'channel==MINE',
			'startDate'  => $start_date,
			'endDate'    => $end_date,
			'metrics'    => 'views,estimatedMinutesWatched,averageViewDuration,averageViewPercentage,likes,comments,shares,subscribersGained,subscribersLost',
		] );

		if ( empty( $response['rows'][0] ) ) return [];

		$row    = $response['rows'][0];
		$result = [
			'views'             => (int)   ( $row[0] ?? 0 ),
			'watch_minutes'     => (int)   ( $row[1] ?? 0 ),
			'avg_view_duration' => (float) ( $row[2] ?? 0 ),
			'avg_view_pct'      => round( (float) ( $row[3] ?? 0 ), 1 ),
			'likes'             => (int)   ( $row[4] ?? 0 ),
			'comments'          => (int)   ( $row[5] ?? 0 ),
			'shares'            => (int)   ( $row[6] ?? 0 ),
			'subs_gained'       => (int)   ( $row[7] ?? 0 ),
			'subs_lost'         => (int)   ( $row[8] ?? 0 ),
			'impressions'       => 0,
			'ctr'               => 0.0,
		];

		set_transient( $cache_key, $result, self::CACHE_TTL );
		return $result;
	}

	// ── Previous-period stats for delta indicators ───────────────────────

	public function get_prev_period_stats( int $days ): array {
		if ( $days >= 9999 ) return []; // All Time has no comparable prior period.

		$cache_key = 'pv_yta_prev_' . $days;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) return $cached;

		$end_date   = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
		$start_date = gmdate( 'Y-m-d', strtotime( '-' . ( $days * 2 ) . ' days' ) );

		$response = $this->api_get( [
			'ids'       => 'channel==MINE',
			'startDate' => $start_date,
			'endDate'   => $end_date,
			'metrics'   => 'views,estimatedMinutesWatched,averageViewPercentage,subscribersGained,subscribersLost',
		] );

		if ( empty( $response['rows'][0] ) ) return [];

		$row    = $response['rows'][0];
		$result = [
			'views'         => (int)   ( $row[0] ?? 0 ),
			'watch_minutes' => (int)   ( $row[1] ?? 0 ),
			'avg_view_pct'  => round( (float) ( $row[2] ?? 0 ), 1 ),
			'subs_gained'   => (int)   ( $row[3] ?? 0 ),
			'subs_lost'     => (int)   ( $row[4] ?? 0 ),
			'impressions'   => 0,
			'ctr'           => 0.0,
		];

		set_transient( $cache_key, $result, self::CACHE_TTL );
		return $result;
	}

	// ── Traffic source breakdown ─────────────────────────────────────────

	public function get_traffic_sources( int $days ): array {
		$cache_key = 'pv_yta_sources_' . $days;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) return $cached;

		$end_date   = gmdate( 'Y-m-d' );
		$start_date = $days >= 9999 ? '2005-04-23' : gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		$response = $this->api_get( [
			'ids'        => 'channel==MINE',
			'startDate'  => $start_date,
			'endDate'    => $end_date,
			'metrics'    => 'views',
			'dimensions' => 'insightTrafficSourceType',
			'sort'       => '-views',
			'maxResults' => 10,
		] );

		$label_map = [
			'YT_SEARCH'         => 'YouTube Search',
			'RELATED_VIDEO'     => 'Suggested Videos',
			'EXT_URL'           => 'External Sources',
			'NO_LINK_EMBEDDED'  => 'Embedded Player',
			'YT_CHANNEL'        => 'Channel Page',
			'SUBSCRIBER'        => 'Browse / Subscriptions',
			'NOTIFICATION'      => 'Notifications',
			'PLAYLIST'          => 'Playlists',
			'ADVERTISING'       => 'Advertising',
			'YT_OTHER_PAGE'     => 'Other YouTube',
			'DIRECT_OR_UNKNOWN' => 'Direct',
			'NO_LINK_OTHER'     => 'Other',
			'SHORTS'            => 'YouTube Shorts',
			'END_SCREEN'        => 'End Screens',
			'CAMPAIGN_CARD'     => 'Campaign Cards',
		];

		$sources = [];
		$total   = 0;
		foreach ( $response['rows'] ?? [] as $row ) {
			$type    = sanitize_text_field( $row[0] ?? '' );
			$views   = (int) ( $row[1] ?? 0 );
			$label   = $label_map[ $type ] ?? ucwords( strtolower( str_replace( '_', ' ', $type ) ) );
			$sources[] = [ 'type' => $type, 'label' => $label, 'views' => $views ];
			$total  += $views;
		}

		$result = [ 'sources' => $sources, 'total' => $total ];
		set_transient( $cache_key, $result, self::CACHE_TTL );
		return $result;
	}

	// ── Daily view trend ─────────────────────────────────────────────────

	public function get_view_trend( int $days = 30 ): array {
		$cache_key = 'pv_yta_trend_' . $days;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) return $cached;

		$end_date   = gmdate( 'Y-m-d' );
		// Cap trend API query to 365 days — chart renders at most 365 points regardless of $days,
		// and querying from 2005 returns ~7300 rows that can hit API row limits or timeout.
		$chart_days  = min( 365, $days );
		$start_date  = gmdate( 'Y-m-d', strtotime( "-{$chart_days} days" ) );

		$response = $this->api_get( [
			'ids'        => 'channel==MINE',
			'startDate'  => $start_date,
			'endDate'    => $end_date,
			'metrics'    => 'views',
			'dimensions' => 'day',
			'sort'       => 'day',
		] );
		$trend = [];
		for ( $i = $chart_days - 1; $i >= 0; $i-- ) {
			$trend[ gmdate( 'Y-m-d', strtotime( "-{$i} days" ) ) ] = 0;
		}
		foreach ( $response['rows'] ?? [] as $row ) {
			if ( isset( $trend[ $row[0] ] ) ) {
				$trend[ $row[0] ] = (int) $row[1];
			}
		}

		$result = [
			'labels' => array_keys( $trend ),
			'values' => array_values( $trend ),
		];

		set_transient( $cache_key, $result, self::CACHE_TTL );
		return $result;
	}

	// ── Top videos by views on YouTube ───────────────────────────────────

	public function get_top_videos( int $days = 30, int $limit = 10 ): array {
		$cache_key = 'pv_yta_top_' . $days . '_' . $limit;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) return $cached;

		$end_date   = gmdate( 'Y-m-d' );
		$start_date = $days >= 9999 ? '2005-04-23' : gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		$response = $this->api_get( [
			'ids'        => 'channel==MINE',
			'startDate'  => $start_date,
			'endDate'    => $end_date,
			'metrics'    => 'views,estimatedMinutesWatched,averageViewPercentage,likes',
			'dimensions' => 'video',
			'sort'       => '-views',
			'maxResults' => $limit,
		] );

		$videos = [];
		foreach ( $response['rows'] ?? [] as $row ) {
			$yt_id = sanitize_text_field( $row[0] ?? '' );
			if ( ! $yt_id ) continue;

			$posts = get_posts( [
				'post_type'      => 'pv_youtube',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_key'       => '_pv_youtube_id',
				'meta_value'     => $yt_id,
				'fields'         => 'ids',
			] );

			$post_id = ! empty( $posts ) ? (int) $posts[0] : 0;
			$post    = $post_id ? get_post( $post_id ) : null;

			$videos[] = [
				'yt_id'     => $yt_id,
				'post_id'   => $post_id,
				'title'     => $post ? $post->post_title : ( 'Video ' . $yt_id ),
				'thumb'     => $post_id ? ( get_the_post_thumbnail_url( $post_id, 'thumbnail' ) ?: '' ) : '',
				'edit'      => $post_id ? ( get_edit_post_link( $post_id, 'raw' ) ?: '' ) : '',
				'views'     => (int)   ( $row[1] ?? 0 ),
				'watch_min' => (int)   ( $row[2] ?? 0 ),
				'avg_pct'   => round( (float) ( $row[3] ?? 0 ), 1 ),
				'likes'     => (int)   ( $row[4] ?? 0 ),
			];
		}

		set_transient( $cache_key, $videos, self::CACHE_TTL );
		return $videos;
	}

	// ── Per-video stats for a specific YouTube ID ────────────────────────

	public function get_video_stats( string $yt_id, int $days = 30 ): array {
		$cache_key = 'pv_yta_vid_' . md5( $yt_id ) . '_' . $days;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) return $cached;

		$end_date   = gmdate( 'Y-m-d' );
		$start_date = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		$response = $this->api_get( [
			'ids'        => 'channel==MINE',
			'startDate'  => $start_date,
			'endDate'    => $end_date,
			'metrics'    => 'views,estimatedMinutesWatched,averageViewDuration,averageViewPercentage,likes,comments,shares,subscribersGained',
			'dimensions' => 'video',
			'filters'    => 'video==' . rawurlencode( $yt_id ),
		] );

		if ( empty( $response['rows'][0] ) ) return [];

		$row    = $response['rows'][0];
		$result = [
			'yt_id'             => $yt_id,
			'views'             => (int)   ( $row[1] ?? 0 ),
			'watch_minutes'     => (int)   ( $row[2] ?? 0 ),
			'avg_view_duration' => (float) ( $row[3] ?? 0 ),
			'avg_view_pct'      => round( (float) ( $row[4] ?? 0 ), 1 ),
			'likes'             => (int)   ( $row[5] ?? 0 ),
			'comments'          => (int)   ( $row[6] ?? 0 ),
			'shares'            => (int)   ( $row[7] ?? 0 ),
			'subs_gained'       => (int)   ( $row[8] ?? 0 ),
		];

		set_transient( $cache_key, $result, self::CACHE_TTL );
		return $result;
	}

	// ── Build the full dashboard payload ─────────────────────────────────

	public function get_dashboard_data( int $days = 30 ): array {
		$channel    = $this->get_channel_stats( $days );
		$trend      = $this->get_view_trend( $days );
		$top_videos = $this->get_top_videos( $days );
		$prev       = $this->get_prev_period_stats( $days );
		$sources    = $this->get_traffic_sources( $days );
		return [
			'channel'    => $channel,
			'trend'      => $trend,
			'top_videos' => $top_videos,
			'prev'       => $prev,
			'sources'    => $sources,
			'api_error'  => $this->last_error,
		];
	}

	// ── Clear all cached YouTube analytics transients ────────────────────

	public static function flush_cache(): void {
		global $wpdb;
		$wpdb->query( // phpcs:ignore
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pv_yta_%' OR option_name LIKE '_transient_timeout_pv_yta_%'"
		);
	}

	// ── Internal HTTP helper ─────────────────────────────────────────────

	private function api_get( array $params ): array {
		$url = add_query_arg( $params, self::API_BASE );

		$response = wp_remote_get( $url, [
			'timeout' => 15,
			'headers' => [
				'Authorization' => 'Bearer ' . $this->access_token,
			],
		] );

		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			return [];
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			$this->last_error = "HTTP {$code}: invalid JSON response";
			return [];
		}

		if ( isset( $body['error'] ) ) {
			$this->last_error = $body['error']['message'] ?? "HTTP {$code} error";
			return [];
		}

		return $body;
	}
}
