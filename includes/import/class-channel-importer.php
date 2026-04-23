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

		// Fetch up to 500 channel uploads (all tiers — video limit gates creation, not fetch).
		$videos = $this->api->get_channel_videos( $channel_id, 500 );
		if ( is_wp_error( $videos ) ) {
			$result['errors'][] = $videos->get_error_message();
			return $result;
		}

		// Separate new videos from already-imported ones.
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
			$new_videos[] = $video_data;
			$existing_count++;
		}

		if ( empty( $new_videos ) ) {
			update_option( 'pv_last_import', [ 'time' => time(), 'imported' => 0, 'skipped' => $result['skipped'] ] );
			return $result;
		}

		// Batch-fetch details for all new videos (50 IDs per API call).
		$new_ids      = array_column( $new_videos, 'youtube_id' );
		$details_map  = $this->api->get_video_details_batch( $new_ids );

		// Create posts.
		foreach ( $new_videos as $video_data ) {
			$details = $details_map[ $video_data['youtube_id'] ] ?? [];
			$post_id = $this->create_video_post( $video_data, $channel_id, $details );
			if ( is_wp_error( $post_id ) ) {
				$result['errors'][] = $post_id->get_error_message();
				continue;
			}
			$result['imported']++;
		}

		// Also import from each configured YouTube broadcast playlist so playlist
		// filtering shows the full set even for videos older than the top 500 uploads.
		if ( ! $result['limit_reached'] ) {
			$settings     = get_option( 'pv_settings', [] );
			$bc_raw_items = json_decode( $settings['bc_playlists'] ?? '[]', true );
			if ( is_array( $bc_raw_items ) ) {
				foreach ( $bc_raw_items as $_item ) {
					if ( strncmp( (string) $_item, 'yt:', 3 ) !== 0 ) continue;
					$pl_id     = substr( (string) $_item, 3 );
					if ( $result['limit_reached'] ) break;

					$pl_videos = $this->api->get_playlist_videos( $pl_id, 200 );
					if ( is_wp_error( $pl_videos ) ) {
						$result['errors'][] = $pl_videos->get_error_message();
						continue;
					}

					$pl_new = [];
					foreach ( $pl_videos as $video_data ) {
						if ( $existing_count >= $limit ) { $result['limit_reached'] = true; break; }
						if ( $this->video_exists( $video_data['youtube_id'] ) ) { $result['skipped']++; continue; }
						$pl_new[] = $video_data;
						$existing_count++;
					}

					if ( ! empty( $pl_new ) ) {
						$pl_new_ids   = array_column( $pl_new, 'youtube_id' );
						$pl_details   = $this->api->get_video_details_batch( $pl_new_ids );
						foreach ( $pl_new as $video_data ) {
							$details = $pl_details[ $video_data['youtube_id'] ] ?? [];
							$post_id = $this->create_video_post( $video_data, $channel_id, $details );
							if ( is_wp_error( $post_id ) ) { $result['errors'][] = $post_id->get_error_message(); continue; }
							$result['imported']++;
						}
					}

					// Bust stale transient so next page load re-caches with the full set.
					delete_transient( 'pv_yt_pl_vids_' . md5( $pl_id ) );
				}
			}
		}

		update_option( 'pv_last_import', [
			'time'     => time(),
			'imported' => $result['imported'],
			'skipped'  => $result['skipped'],
		] );

		return $result;
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
