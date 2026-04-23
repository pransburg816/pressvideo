<?php
/**
 * Orchestrates fetching YouTube videos and creating pv_video posts.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Channel_Importer {

	private PV_YouTube_API $api;

	public function __construct() {
		$settings  = get_option( 'pv_settings', [] );
		$this->api = new PV_YouTube_API( $settings['api_key'] ?? '' );
	}

	/**
	 * Import videos from a channel.
	 *
	 * @return array { imported: int, skipped: int, limit_reached: bool, errors: string[] }
	 */
	public function import_channel( string $channel_id ): array {
		// Give the import process plenty of time — image sideloading is slow.
		@set_time_limit( 300 );

		$result         = [ 'imported' => 0, 'skipped' => 0, 'limit_reached' => false, 'errors' => [] ];
		$limit          = PV_Tier::get_video_limit();
		$existing_count = (int) ( wp_count_posts( 'pv_youtube' )->publish ?? 0 );

		// Fetch channel uploads — tier gates creation, not fetch count.
		// Platinum: no fetch cap (paginate until exhausted).
		// Gold/Silver: fetch enough to fill the tier limit with a buffer.
		$fetch_max = PHP_INT_MAX === $limit ? PHP_INT_MAX : min( $limit + 100, 500 );
		$videos    = $this->api->get_channel_videos( $channel_id, $fetch_max );
		if ( is_wp_error( $videos ) ) {
			$result['errors'][] = $videos->get_error_message();
			return $result;
		}

		// Process channel uploads — skip already-imported, batch-create new ones.
		[ $result, $existing_count ] = $this->process_video_list( $videos, $channel_id, $result, $limit, $existing_count );

		// ── Playlist pass ────────────────────────────────────────────────────
		// Platinum: automatically import from ALL channel playlists so unlisted
		// or playlist-only videos (not in the public uploads feed) are captured.
		// Any tier: also run configured bc_playlists as a supplement.
		$playlist_ids = [];

		if ( PV_Tier::is_platinum() && ! $result['limit_reached'] ) {
			$ch_playlists = $this->api->get_channel_playlists( $channel_id );
			if ( ! is_wp_error( $ch_playlists ) ) {
				foreach ( $ch_playlists as $pl ) {
					$playlist_ids[ $pl['id'] ] = true;
				}
			}
		}

		// Merge in manually-configured bc_playlists (any tier).
		if ( ! $result['limit_reached'] ) {
			$settings     = get_option( 'pv_settings', [] );
			$bc_raw_items = json_decode( $settings['bc_playlists'] ?? '[]', true );
			foreach ( (array) $bc_raw_items as $_item ) {
				if ( strncmp( (string) $_item, 'yt:', 3 ) === 0 ) {
					$playlist_ids[ substr( (string) $_item, 3 ) ] = true;
				}
			}

			// Merge in manually-entered import_playlists (any tier — enables private/unlisted).
			foreach ( preg_split( '/[\r\n]+/', $settings['import_playlists'] ?? '' ) as $_pl_id ) {
				$_pl_id = trim( $_pl_id );
				if ( $_pl_id ) $playlist_ids[ $_pl_id ] = true;
			}
		}

		foreach ( array_keys( $playlist_ids ) as $pl_id ) {
			if ( $result['limit_reached'] ) break;

			$pl_videos = $this->api->get_playlist_videos( $pl_id, PHP_INT_MAX );
			if ( is_wp_error( $pl_videos ) ) {
				$result['errors'][] = $pl_videos->get_error_message();
				continue;
			}

			[ $result, $existing_count ] = $this->process_video_list( $pl_videos, $channel_id, $result, $limit, $existing_count );

			// Bust stale video-ID transient so archive page rebuilds it with full set.
			delete_transient( 'pv_yt_pl_vids_' . md5( $pl_id ) );
		}

		update_option( 'pv_last_import', [
			'time'     => time(),
			'imported' => $result['imported'],
			'skipped'  => $result['skipped'],
		] );

		return $result;
	}

	/**
	 * Filter $videos to new-only, batch-fetch details, create posts.
	 *
	 * @return array{ 0: array, 1: int } Updated [$result, $existing_count].
	 */
	private function process_video_list( array $videos, string $channel_id, array $result, int $limit, int $existing_count ): array {
		$new_videos = [];
		foreach ( $videos as $video_data ) {
			if ( $existing_count >= $limit ) {
				$result['limit_reached'] = true;
				break;
			}
			if ( $this->video_exists( $video_data['youtube_id'] ) ) {
				$result['skipped']++;
				continue;
			}
			$new_videos[]   = $video_data;
			$existing_count++;
		}

		if ( empty( $new_videos ) ) {
			return [ $result, $existing_count ];
		}

		$details_map = $this->api->get_video_details_batch( array_column( $new_videos, 'youtube_id' ) );

		foreach ( $new_videos as $video_data ) {
			$details = $details_map[ $video_data['youtube_id'] ] ?? [];
			$post_id = $this->create_video_post( $video_data, $channel_id, $details );
			if ( is_wp_error( $post_id ) ) {
				$result['errors'][] = $post_id->get_error_message();
				continue;
			}
			$result['imported']++;
		}

		return [ $result, $existing_count ];
	}

	/** Check if a video with the given YouTube ID already exists. */
	public function video_exists( string $youtube_id ): bool {
		$query = new WP_Query( [
			'post_type'      => 'pv_youtube',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => [ [
				'key'   => '_pv_youtube_id',
				'value' => sanitize_text_field( $youtube_id ),
			] ],
			'no_found_rows'  => true,
		] );
		return $query->have_posts();
	}

	/**
	 * Create a pv_video post from YouTube video data.
	 * Accepts pre-fetched $details to avoid redundant API calls.
	 *
	 * @return int|WP_Error Post ID on success, WP_Error on failure.
	 */
	private function create_video_post( array $video_data, string $channel_id, array $details = [] ): int|WP_Error {
		$youtube_id = sanitize_text_field( $video_data['youtube_id'] );

		$post_id = wp_insert_post( [
			'post_type'    => 'pv_youtube',
			'post_status'  => 'publish',
			'post_title'   => sanitize_text_field( $video_data['title'] ),
			'post_content' => wp_kses_post( $video_data['description'] ),
			'post_date'    => ! empty( $video_data['published_at'] )
				? date( 'Y-m-d H:i:s', strtotime( $video_data['published_at'] ) )
				: current_time( 'mysql' ),
		], true );

		if ( is_wp_error( $post_id ) ) return $post_id;

		update_post_meta( $post_id, '_pv_youtube_id',  $youtube_id );
		update_post_meta( $post_id, '_pv_youtube_url', 'https://www.youtube.com/watch?v=' . $youtube_id );
		update_post_meta( $post_id, '_pv_channel_id',  sanitize_text_field( $channel_id ) );
		update_post_meta( $post_id, '_pv_imported_at', time() );

		// Use pre-fetched details if available; fall back to individual API call.
		if ( empty( $details ) ) {
			$fetched = $this->api->get_video_details( $youtube_id );
			$details = is_wp_error( $fetched ) ? [] : $fetched;
		}

		if ( ! empty( $details ) ) {
			update_post_meta( $post_id, '_pv_duration',   $details['duration'] ?? '' );
			update_post_meta( $post_id, '_pv_view_count', $details['view_count'] ?? 0 );

			if ( ! empty( $details['tags'] ) ) {
				wp_set_object_terms( $post_id, $details['tags'], 'pv_tag' );
			}
			if ( ! empty( $details['category_name'] ) ) {
				wp_set_object_terms( $post_id, [ $details['category_name'] ], 'pv_category' );
			}
		}

		if ( ! empty( $video_data['thumbnail'] ) ) {
			$this->sideload_thumbnail( $video_data['thumbnail'], $post_id, $video_data['title'] );
		}

		return $post_id;
	}

	/** Sideload a remote image URL and set it as the post's featured image. */
	private function sideload_thumbnail( string $url, int $post_id, string $desc ): void {
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$attachment_id = media_sideload_image( esc_url_raw( $url ), $post_id, sanitize_text_field( $desc ), 'id' );

		if ( ! is_wp_error( $attachment_id ) ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}
}
