<?php
/**
 * Orchestrates fetching YouTube videos and creating pv_video posts.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Channel_Importer {

	private PV_YouTube_API $api;

	public function __construct() {
		$settings      = get_option( 'pv_settings', [] );
		$this->api     = new PV_YouTube_API( $settings['api_key'] ?? '' );
	}

	/**
	 * Import videos from a channel.
	 *
	 * @return array { imported: int, skipped: int, limit_reached: bool, errors: string[] }
	 */
	public function import_channel( string $channel_id ): array {
		$result = [ 'imported' => 0, 'skipped' => 0, 'limit_reached' => false, 'errors' => [] ];

		$videos = $this->api->get_channel_videos( $channel_id, 50 );

		if ( is_wp_error( $videos ) ) {
			$result['errors'][] = $videos->get_error_message();
			return $result;
		}

		$limit          = PV_Tier::get_video_limit();
		$existing_count = (int) ( wp_count_posts( 'pv_youtube' )->publish ?? 0 );

		foreach ( $videos as $video_data ) {
			if ( $existing_count >= $limit ) {
				$result['limit_reached'] = true;
				$result['skipped']      += count( $videos ) - $result['imported'] - $result['skipped'];
				break;
			}

			if ( $this->video_exists( $video_data['youtube_id'] ) ) {
				$result['skipped']++;
				continue;
			}

			$post_id = $this->create_video_post( $video_data, $channel_id );
			if ( is_wp_error( $post_id ) ) {
				$result['errors'][] = $post_id->get_error_message();
				continue;
			}

			$result['imported']++;
			$existing_count++;
		}

		// Persist result for dashboard stats display.
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
	 *
	 * @return int|WP_Error  Post ID on success, WP_Error on failure.
	 */
	private function create_video_post( array $video_data, string $channel_id ): int|WP_Error {
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

		$embed_url = 'https://www.youtube.com/watch?v=' . $youtube_id;
		update_post_meta( $post_id, '_pv_youtube_id',   $youtube_id );
		update_post_meta( $post_id, '_pv_youtube_url',  $embed_url );
		update_post_meta( $post_id, '_pv_channel_id',   sanitize_text_field( $channel_id ) );
		update_post_meta( $post_id, '_pv_imported_at',  time() );

		// Fetch duration, view count, tags, and YouTube category.
		$details = $this->api->get_video_details( $youtube_id );
		if ( ! is_wp_error( $details ) ) {
			update_post_meta( $post_id, '_pv_duration',   $details['duration'] );
			update_post_meta( $post_id, '_pv_view_count', $details['view_count'] );

			// Auto-assign YouTube tags → pv_tag terms.
			if ( ! empty( $details['tags'] ) ) {
				wp_set_object_terms( $post_id, $details['tags'], 'pv_tag' );
			}

			// Auto-assign YouTube category → pv_category term.
			if ( ! empty( $details['category_name'] ) ) {
				wp_set_object_terms( $post_id, [ $details['category_name'] ], 'pv_category' );
			}
		}

		// Sideload thumbnail as featured image.
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
