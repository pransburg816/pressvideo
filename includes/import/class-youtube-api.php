<?php
/**
 * YouTube Data API v3 wrapper.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_YouTube_API {

	private string $api_key;
	private string $base_url = 'https://www.googleapis.com/youtube/v3/';

	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Fetch videos from a YouTube channel.
	 *
	 * @return array|WP_Error  Array of video data arrays on success, WP_Error on failure.
	 */
	public function get_channel_videos( string $channel_id, int $max_results = 50 ): array|WP_Error {
		// First get the uploads playlist ID from the channel.
		$channel_response = $this->request( 'channels', [
			'part' => 'contentDetails',
			'id'   => $channel_id,
		] );

		if ( is_wp_error( $channel_response ) ) return $channel_response;

		$items = $channel_response['items'] ?? [];
		if ( empty( $items ) ) {
			return new WP_Error( 'pv_channel_not_found', __( 'Channel not found. Check the Channel ID.', 'pv-youtube-importer' ) );
		}

		$uploads_playlist = $items[0]['contentDetails']['relatedPlaylists']['uploads'] ?? '';
		if ( ! $uploads_playlist ) {
			return new WP_Error( 'pv_no_uploads', __( 'Could not find uploads playlist for this channel.', 'pv-youtube-importer' ) );
		}

		return $this->get_playlist_videos( $uploads_playlist, $max_results );
	}

	/**
	 * Fetch videos from a playlist.
	 *
	 * @return array|WP_Error
	 */
	public function get_playlist_videos( string $playlist_id, int $max_results = 50 ): array|WP_Error {
		$videos     = [];
		$page_token = '';
		$fetched    = 0;

		do {
			$params = [
				'part'       => 'snippet,contentDetails',
				'playlistId' => $playlist_id,
				'maxResults' => 50, // always request full pages; caller's $max_results gates the count
			];
			if ( $page_token ) $params['pageToken'] = $page_token;

			$response = $this->request( 'playlistItems', $params );
			if ( is_wp_error( $response ) ) return $response;

			foreach ( $response['items'] ?? [] as $item ) {
				$snippet  = $item['snippet'] ?? [];
				$video_id = $snippet['resourceId']['videoId'] ?? '';
				if ( ! $video_id ) continue;

				$videos[] = [
					'youtube_id'    => $video_id,
					'title'         => $snippet['title'] ?? '',
					'description'   => $snippet['description'] ?? '',
					'published_at'  => $snippet['publishedAt'] ?? '',
					'thumbnail'     => $this->best_thumbnail( $snippet['thumbnails'] ?? [] ),
					'channel_id'    => $snippet['channelId'] ?? '',
					'channel_title' => $snippet['channelTitle'] ?? '',
				];
				$fetched++;

				if ( $fetched >= $max_results ) break; // honour caller's limit
			}

			$page_token = $response['nextPageToken'] ?? '';
		} while ( $page_token && $fetched < $max_results );

		return $videos;
	}

	/**
	 * Check if the channel is currently live. Returns stream data or null.
	 * Cached for 2 minutes to avoid hammering the API on every page load.
	 *
	 * @return array{video_id:string,title:string}|null
	 */
	public function get_live_stream( string $channel_id ): ?array {
		if ( ! $channel_id ) return null;

		$transient_key = 'pv_live_check_' . md5( $channel_id );
		$cached = get_transient( $transient_key );
		if ( $cached !== false ) {
			return is_array( $cached ) ? $cached : null;
		}

		$response = $this->request( 'search', [
			'part'       => 'snippet',
			'channelId'  => $channel_id,
			'eventType'  => 'live',
			'type'       => 'video',
			'maxResults' => 1,
		] );

		if ( is_wp_error( $response ) || empty( $response['items'] ) ) {
			set_transient( $transient_key, 0, 2 * MINUTE_IN_SECONDS );
			return null;
		}

		$item   = $response['items'][0];
		$result = [
			'video_id' => $item['id']['videoId']       ?? '',
			'title'    => $item['snippet']['title']     ?? '',
		];

		set_transient( $transient_key, $result, 2 * MINUTE_IN_SECONDS );
		return $result;
	}

	/**
	 * Get detailed info for a single video (duration, view count, tags, category).
	 *
	 * @return array|WP_Error
	 */
	/**
	 * Fetch all non-empty playlists for a channel.
	 *
	 * @return array[]|WP_Error  Each item: { id, title, count }
	 */
	public function get_channel_playlists( string $channel_id ): array|WP_Error {
		$playlists  = [];
		$page_token = '';

		do {
			$params = [
				'part'       => 'snippet,contentDetails',
				'channelId'  => $channel_id,
				'maxResults' => 50,
			];
			if ( $page_token ) $params['pageToken'] = $page_token;

			$response = $this->request( 'playlists', $params );
			if ( is_wp_error( $response ) ) return $response;

			foreach ( $response['items'] ?? [] as $item ) {
				$count = (int) ( $item['contentDetails']['itemCount'] ?? 0 );
				if ( $count === 0 ) continue;
				$playlists[] = [
					'id'    => $item['id'],
					'title' => $item['snippet']['title'] ?? '',
					'count' => $count,
				];
			}

			$page_token = $response['nextPageToken'] ?? '';
		} while ( $page_token );

		return $playlists;
	}

	/**
	 * Fetch details for multiple videos in batches of 50 (one API call per batch).
	 * Returns an array keyed by YouTube video ID.
	 *
	 * @param  string[] $video_ids
	 * @return array<string, array>
	 */
	public function get_video_details_batch( array $video_ids ): array {
		$results = [];
		foreach ( array_chunk( $video_ids, 50 ) as $chunk ) {
			$response = $this->request( 'videos', [
				'part' => 'snippet,contentDetails,statistics',
				'id'   => implode( ',', $chunk ),
			] );
			if ( is_wp_error( $response ) ) continue;
			foreach ( $response['items'] ?? [] as $item ) {
				$id = $item['id'] ?? '';
				if ( ! $id ) continue;
				$snippet     = $item['snippet'] ?? [];
				$category_id = (string) ( $snippet['categoryId'] ?? '' );
				$results[ $id ] = [
					'duration'      => $this->parse_duration( $item['contentDetails']['duration'] ?? '' ),
					'view_count'    => absint( $item['statistics']['viewCount'] ?? 0 ),
					'tags'          => array_values( array_filter( array_map(
						'sanitize_text_field',
						(array) ( $snippet['tags'] ?? [] )
					) ) ),
					'category_name' => self::youtube_category_name( $category_id ),
				];
			}
		}
		return $results;
	}

	public function get_video_details( string $video_id ): array|WP_Error {
		$response = $this->request( 'videos', [
			'part' => 'snippet,contentDetails,statistics',
			'id'   => $video_id,
		] );

		if ( is_wp_error( $response ) ) return $response;

		$item = $response['items'][0] ?? null;
		if ( ! $item ) {
			return new WP_Error( 'pv_video_not_found', __( 'Video not found.', 'pv-youtube-importer' ) );
		}

		$snippet     = $item['snippet'] ?? [];
		$category_id = (string) ( $snippet['categoryId'] ?? '' );

		return [
			'duration'      => $this->parse_duration( $item['contentDetails']['duration'] ?? '' ),
			'view_count'    => absint( $item['statistics']['viewCount'] ?? 0 ),
			'tags'          => array_values( array_filter( array_map(
				'sanitize_text_field',
				(array) ( $snippet['tags'] ?? [] )
			) ) ),
			'category_name' => self::youtube_category_name( $category_id ),
		];
	}

	/** Map a YouTube categoryId to a human-readable category name. */
	public static function youtube_category_name( string $id ): string {
		$map = [
			'1'  => 'Film & Animation',
			'2'  => 'Autos & Vehicles',
			'10' => 'Music',
			'15' => 'Pets & Animals',
			'17' => 'Sports',
			'19' => 'Travel & Events',
			'20' => 'Gaming',
			'22' => 'People & Blogs',
			'23' => 'Comedy',
			'24' => 'Entertainment',
			'25' => 'News & Politics',
			'26' => 'Howto & Style',
			'27' => 'Education',
			'28' => 'Science & Technology',
			'29' => 'Nonprofits & Activism',
		];
		return $map[ $id ] ?? '';
	}

	/**
	 * Extract a YouTube video ID from any common URL format.
	 */
	public static function extract_video_id( string $url ): string {
		if ( ! $url ) return '';

		// youtu.be/VIDEO_ID
		if ( preg_match( '#youtu\.be/([A-Za-z0-9_\-]{11})#', $url, $m ) ) return $m[1];

		// youtube.com/watch?v=VIDEO_ID
		if ( preg_match( '#[?&]v=([A-Za-z0-9_\-]{11})#', $url, $m ) ) return $m[1];

		// youtube.com/embed/VIDEO_ID or youtube.com/shorts/VIDEO_ID
		if ( preg_match( '#/(?:embed|shorts|v)/([A-Za-z0-9_\-]{11})#', $url, $m ) ) return $m[1];

		// Bare 11-character ID.
		if ( preg_match( '#^([A-Za-z0-9_\-]{11})$#', trim( $url ), $m ) ) return $m[1];

		return '';
	}

	/**
	 * Make an authenticated request to the YouTube API.
	 *
	 * @return array|WP_Error
	 */
	private function request( string $endpoint, array $params = [] ): array|WP_Error {
		if ( ! $this->api_key ) {
			return new WP_Error( 'pv_no_api_key', __( 'YouTube API key is not set. Please configure it in Videos > Settings.', 'pv-youtube-importer' ) );
		}

		$params['key'] = $this->api_key;
		$url = add_query_arg( $params, $this->base_url . $endpoint );

		$response = wp_remote_get( $url, [ 'timeout' => 15 ] );

		if ( is_wp_error( $response ) ) return $response;

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			$message = $body['error']['message'] ?? __( 'Unknown YouTube API error.', 'pv-youtube-importer' );
			return new WP_Error( 'pv_api_error', $message );
		}

		return $body;
	}

	/** Pick the highest-quality available thumbnail URL. */
	private function best_thumbnail( array $thumbnails ): string {
		foreach ( [ 'maxres', 'standard', 'high', 'medium', 'default' ] as $size ) {
			if ( ! empty( $thumbnails[ $size ]['url'] ) ) {
				return $thumbnails[ $size ]['url'];
			}
		}
		return '';
	}

	/** Convert ISO 8601 duration (PT4M32S) to human-readable string (4:32). */
	private function parse_duration( string $iso ): string {
		if ( ! $iso ) return '';
		preg_match( '/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $iso, $m );
		$h = isset( $m[1] ) ? (int) $m[1] : 0;
		$i = isset( $m[2] ) ? (int) $m[2] : 0;
		$s = isset( $m[3] ) ? (int) $m[3] : 0;

		if ( $h ) return sprintf( '%d:%02d:%02d', $h, $i, $s );
		return sprintf( '%d:%02d', $i, $s );
	}
}
