<?php
/**
 * Analytics — DB schema, event recorder, and data queries.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Analytics_Tracker {

	const DB_VERSION = '1.0';

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'pv_analytics';
	}

	public static function create_table(): void {
		global $wpdb;
		$table      = self::table();
		$charset_db = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id         bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			video_id   bigint(20) UNSIGNED NOT NULL,
			event      varchar(20)         NOT NULL,
			session_id varchar(40)         NOT NULL DEFAULT '',
			created_at datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY video_id   (video_id),
			KEY created_at (created_at),
			KEY event      (event)
		) {$charset_db};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( 'pv_analytics_db_version', self::DB_VERSION );
	}

	public function register(): void {
		add_action( 'wp_ajax_pv_track_event',           [ $this, 'ajax_track'           ] );
		add_action( 'wp_ajax_nopriv_pv_track_event',    [ $this, 'ajax_track'           ] );
		add_action( 'wp_ajax_pv_analytics_data',        [ $this, 'ajax_data'            ] );
		add_action( 'wp_ajax_pv_refresh_ai_insights',   [ $this, 'ajax_refresh_ai'      ] );
	}

	public function ajax_track(): void {
		check_ajax_referer( 'pv_track', 'nonce' );

		$youtube_id = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $_POST['youtube_id'] ?? '' ) ); // phpcs:ignore
		$event      = sanitize_key( $_POST['event'] ?? '' );                                             // phpcs:ignore
		$session_id = sanitize_text_field( substr( (string) ( $_POST['session_id'] ?? '' ), 0, 40 ) );  // phpcs:ignore

		if ( ! $youtube_id || ! in_array( $event, [ 'play', 'd25', 'd50', 'd75', 'd100' ], true ) ) {
			wp_send_json_error( 'invalid' );
			return;
		}

		// Resolve YouTube ID → WP post ID.
		$posts = get_posts( [
			'post_type'      => 'pv_youtube',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => '_pv_youtube_id',
			'meta_value'     => $youtube_id,
			'fields'         => 'ids',
		] );

		if ( empty( $posts ) ) {
			wp_send_json_error( 'not_found' );
			return;
		}

		$video_id = (int) $posts[0];

		global $wpdb;
		$wpdb->insert(  // phpcs:ignore
			self::table(),
			[
				'video_id'   => $video_id,
				'event'      => $event,
				'session_id' => $session_id,
				'created_at' => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%s' ]
		);

		wp_send_json_success();
	}

	public function ajax_data(): void {
		check_ajax_referer( 'pv_analytics_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'forbidden' );
			return;
		}

		$days = min( 90, max( 7, (int) ( $_POST['days'] ?? 30 ) ) ); // phpcs:ignore
		wp_send_json_success( self::get_dashboard_data( $days ) );
	}

	public static function get_dashboard_data( int $days = 30 ): array {
		global $wpdb;
		$table = self::table();
		$from  = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// ── Stat: Total plays ─────────────────────────────────────────
		$total_plays = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE event = 'play' AND created_at >= %s",
			$from
		) );

		// ── Stat: Unique videos played ────────────────────────────────
		$unique_videos = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT video_id) FROM {$table} WHERE event = 'play' AND created_at >= %s",
			$from
		) );

		// ── Stat: Avg completion ──────────────────────────────────────
		$depth_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT MAX(CASE WHEN event='d100' THEN 100
			               WHEN event='d75'  THEN 75
			               WHEN event='d50'  THEN 50
			               WHEN event='d25'  THEN 25
			               ELSE 0 END) AS max_depth
			 FROM {$table}
			 WHERE created_at >= %s
			 GROUP BY video_id, session_id",
			$from
		), ARRAY_A );

		$avg_completion = 0;
		if ( ! empty( $depth_rows ) ) {
			$avg_completion = (int) round( array_sum( array_column( $depth_rows, 'max_depth' ) ) / count( $depth_rows ) );
		}

		// ── Daily trend ───────────────────────────────────────────────
		$trend_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE(created_at) AS day, COUNT(*) AS plays
			 FROM {$table}
			 WHERE event = 'play' AND created_at >= %s
			 GROUP BY day ORDER BY day ASC",
			$from
		), ARRAY_A );

		$trend = [];
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$trend[ gmdate( 'Y-m-d', strtotime( "-{$i} days" ) ) ] = 0;
		}
		foreach ( $trend_rows as $row ) {
			if ( isset( $trend[ $row['day'] ] ) ) {
				$trend[ $row['day'] ] = (int) $row['plays'];
			}
		}

		// ── Top 10 videos ─────────────────────────────────────────────
		$top_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT video_id, COUNT(*) AS plays
			 FROM {$table} WHERE event = 'play' AND created_at >= %s
			 GROUP BY video_id ORDER BY plays DESC LIMIT 10",
			$from
		), ARRAY_A );

		$top_videos = [];
		foreach ( $top_rows as $row ) {
			$post = get_post( (int) $row['video_id'] );
			if ( ! $post ) continue;
			$yt_id = get_post_meta( $post->ID, '_pv_youtube_id', true );
			$top_videos[] = [
				'id'        => (int) $row['video_id'],
				'title'     => $post->post_title,
				'plays'     => (int) $row['plays'],
				'thumb'     => get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ?: '',
				'edit'      => get_edit_post_link( $post->ID, 'raw' ) ?: '',
				'permalink' => get_permalink( $post->ID ) ?: '',
				'yt_id'     => $yt_id ?: '',
			];
		}

		// ── Watch depth distribution ──────────────────────────────────
		$depth = [];
		foreach ( [ 'd25', 'd50', 'd75', 'd100' ] as $evt ) {
			$depth[ $evt ] = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT CONCAT(video_id,'_',session_id))
				 FROM {$table} WHERE event = %s AND created_at >= %s",
				$evt,
				$from
			) );
		}

		// ── All videos table ──────────────────────────────────────────
		$all_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT video_id,
			        SUM(event='play') AS plays,
			        MAX(created_at)   AS last_played
			 FROM {$table} WHERE created_at >= %s
			 GROUP BY video_id ORDER BY plays DESC",
			$from
		), ARRAY_A );

		$all_videos = [];
		foreach ( $all_rows as $row ) {
			$post = get_post( (int) $row['video_id'] );
			if ( ! $post ) continue;
			$yt_id = get_post_meta( $post->ID, '_pv_youtube_id', true );
			$all_videos[] = [
				'id'          => (int) $row['video_id'],
				'title'       => $post->post_title,
				'plays'       => (int) $row['plays'],
				'last_played' => human_time_diff( strtotime( $row['last_played'] ), time() ) . ' ago',
				'thumb'       => get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ?: '',
				'edit'        => get_edit_post_link( $post->ID, 'raw' ) ?: '',
				'permalink'   => get_permalink( $post->ID ) ?: '',
				'yt_id'       => $yt_id ?: '',
			];
		}

		return [
			'stats'      => [
				'total_plays'    => $total_plays,
				'unique_videos'  => $unique_videos,
				'avg_completion' => $avg_completion,
			],
			'trend'      => [
				'labels' => array_keys( $trend ),
				'values' => array_values( $trend ),
			],
			'top_videos' => $top_videos,
			'depth'      => $depth,
			'all_videos' => $all_videos,
		];
	}

	// ── Resolve the API key for the current user/tier ───────────────────
	// Platinum: use master key from wp-config.php constant PV_ANTHROPIC_KEY.
	// Gold+: fall back to the user's own key from pv_settings.

	public static function resolve_ai_key(): string {
		if ( PV_Tier::meets( 'platinum' ) && defined( 'PV_ANTHROPIC_KEY' ) && PV_ANTHROPIC_KEY ) {
			return PV_ANTHROPIC_KEY;
		}
		if ( PV_Tier::meets( 'gold' ) ) {
			$settings = get_option( 'pv_settings', [] );
			return sanitize_text_field( $settings['anthropic_api_key'] ?? '' );
		}
		return '';
	}

	// ── AJAX: force-refresh AI insights ──────────────────────────────────

	public function ajax_refresh_ai(): void {
		check_ajax_referer( 'pv_analytics_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'forbidden' );
			return;
		}

		$api_key = self::resolve_ai_key();

		if ( empty( $api_key ) ) {
			wp_send_json_error( 'no_api_key' );
			return;
		}

		$days   = min( 90, max( 7, (int) ( $_POST['days'] ?? 30 ) ) ); // phpcs:ignore
		$data   = self::get_dashboard_data( $days );
		$result = $this->get_ai_insights( $data, $api_key, $days );

		$transient = 'pv_ai_insights_' . get_current_user_id() . '_' . $days;
		if ( ! empty( $result['moves'] ) ) {
			$result['cached_at'] = time();
			set_transient( $transient, $result, DAY_IN_SECONDS );
		}

		wp_send_json_success( $result );
	}

	// ── AI coaching via Claude API ────────────────────────────────────────

	public function get_ai_insights( array $data, string $api_key, int $days = 30 ): array {
		$stats     = $data['stats']      ?? [];
		$depth     = $data['depth']      ?? [];
		$top_vids  = $data['top_videos'] ?? [];
		$trend     = $data['trend']      ?? [ 'values' => [] ];

		$top_title  = sanitize_text_field( $top_vids[0]['title'] ?? 'your top video' );
		$top_plays  = (int) ( $top_vids[0]['plays'] ?? 0 );
		$trend_vals = implode( ',', array_map( 'intval', $trend['values'] ?? [] ) );

		$prompt = "You are a video content growth coach for a WordPress creator who embeds their YouTube videos on their own site.\n\n"
			. "Analyze these {$days}-day analytics. Respond ONLY with valid JSON — no markdown, no backticks, no extra text.\n\n"
			. "Data:\n"
			. '- Total plays: ' . (int) ( $stats['total_plays'] ?? 0 )
				. ' | Unique videos: ' . (int) ( $stats['unique_videos'] ?? 0 )
				. ' | Avg completion: ' . (int) ( $stats['avg_completion'] ?? 0 ) . "%\n"
			. '- Watch depth: 25%=' . (int) ( $depth['d25'] ?? 0 )
				. ' sessions, 50%=' . (int) ( $depth['d50'] ?? 0 )
				. ', 75%=' . (int) ( $depth['d75'] ?? 0 )
				. ', 100%=' . (int) ( $depth['d100'] ?? 0 ) . "\n"
			. '- Top video: "' . $top_title . '" (' . $top_plays . " plays)\n"
			. '- Daily trend (' . $days . ' days): [' . $trend_vals . "]\n\n"
			. "Return this exact JSON structure:\n"
			. '{"summary":{"grade":"...","title":"...","body":"...","tips":[{"title":"...","desc":"..."},{"title":"...","desc":"..."},{"title":"...","desc":"..."}]},"moves":[{"title":"...","edge":"...","script":"...","impact":"..."},{"title":"...","edge":"...","script":"...","impact":"..."},{"title":"...","edge":"...","script":"...","impact":"..."}]}' . "\n\n"
			. "summary.grade: exactly one of: Getting Started, Growing, Holding Steady, Needs Attention, Strong Momentum\n"
			. "summary.title: 8 words max — direct headline about their current situation\n"
			. "summary.body: 2 sentences — what is happening and what to prioritize, using their real numbers\n"
			. "summary.tips[].title: 4 words max action label\n"
			. "summary.tips[].desc: 1-2 sentences using their specific data — no generic advice\n\n"
			. "moves[].title: 5 words max, action-oriented verb phrase\n"
			. "moves[].edge: one sentence — the specific insight from their data\n"
			. "moves[].script: 2-3 sentences — exactly what to do or say\n"
			. "moves[].impact: one sentence — expected measurable outcome";

		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
			'timeout' => 15,
			'headers' => [
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
				'content-type'      => 'application/json',
			],
			'body' => wp_json_encode( [
				'model'      => 'claude-haiku-4-5-20251001',
				'max_tokens' => 900,
				'messages'   => [
					[ 'role' => 'user', 'content' => $prompt ],
				],
			] ),
		] );

		if ( is_wp_error( $response ) ) return [];

		$body   = wp_remote_retrieve_body( $response );
		$json   = json_decode( $body, true );

		if ( ! $json || ! isset( $json['content'][0]['text'] ) ) return [];

		$text = trim( $json['content'][0]['text'] );
		// Strip markdown code fences if the model wraps the JSON despite instructions
		$text = preg_replace( '/^```(?:json)?\s*/i', '', $text );
		$text = preg_replace( '/\s*```$/', '', $text );

		$parsed = json_decode( trim( $text ), true );

		if ( ! $parsed || ! isset( $parsed['moves'] ) || ! is_array( $parsed['moves'] ) ) return [];

		$moves = array_map( function ( $move ) {
			return [
				'title'  => sanitize_text_field( $move['title']  ?? '' ),
				'edge'   => sanitize_textarea_field( $move['edge']   ?? '' ),
				'script' => sanitize_textarea_field( $move['script'] ?? '' ),
				'impact' => sanitize_textarea_field( $move['impact'] ?? '' ),
			];
		}, array_slice( $parsed['moves'], 0, 3 ) );

		$raw_summary = $parsed['summary'] ?? [];
		$summary = [
			'grade' => sanitize_text_field( $raw_summary['grade'] ?? '' ),
			'title' => sanitize_text_field( $raw_summary['title'] ?? '' ),
			'body'  => sanitize_textarea_field( $raw_summary['body']  ?? '' ),
			'tips'  => array_map( function ( $tip ) {
				return [
					'title' => sanitize_text_field( $tip['title'] ?? '' ),
					'desc'  => sanitize_textarea_field( $tip['desc']  ?? '' ),
				];
			}, array_slice( (array) ( $raw_summary['tips'] ?? [] ), 0, 3 ) ),
		];

		return [ 'moves' => $moves, 'summary' => $summary ];
	}
}
