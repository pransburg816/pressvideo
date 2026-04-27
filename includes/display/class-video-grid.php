<?php
/**
 * Builds the video card grid HTML.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Video_Grid {

	/**
	 * Render a grid of video cards.
	 *
	 * @param array $videos  Array of WP_Post objects.
	 * @param array $args    Display args: columns, display, color.
	 * @return string HTML
	 */
	public function render( array $videos, array $args ): string {
		if ( empty( $videos ) ) {
			return '<p class="pv-no-videos">' . esc_html__( 'No videos found.', 'pv-youtube-importer' ) . '</p>';
		}

		$columns = absint( $args['columns'] ?? 3 );
		$display = sanitize_key( $args['display'] ?? 'offcanvas' );

		// Build playlist JSON for nav (all videos in this grid).
		$playlist = [];
		foreach ( $videos as $post ) {
			$youtube_id = get_post_meta( $post->ID, '_pv_youtube_id', true );
			if ( ! $youtube_id ) continue;
			$playlist[] = [
				'id'        => $post->ID,
				'youtubeId' => $youtube_id,
				'embedUrl'  => 'https://www.youtube.com/embed/' . $youtube_id,
				'title'     => $post->post_title,
				'desc'      => wp_trim_words( $post->post_content, 20 ),
				'accent'    => pv_resolve_accent_color( $post->ID ),
				'thumb'     => get_the_post_thumbnail_url( $post->ID, 'medium' ) ?: '',
				'duration'  => get_post_meta( $post->ID, '_pv_duration', true ) ?: '',
			];
		}

		$playlist_json = wp_json_encode( $playlist );

		ob_start();
		?>
		<div class="pv-grid" style="--pv-cols:<?php echo esc_attr( $columns ); ?>;"
		     data-playlist="<?php echo esc_attr( $playlist_json ); ?>">
			<?php foreach ( $videos as $post ) : ?>
				<?php echo $this->render_card( $post, $display, $playlist ); ?>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/** Render a single video card (IAC hero-box pattern). */
	private function render_card( WP_Post $post, string $display, array $playlist ): string {
		$youtube_id = get_post_meta( $post->ID, '_pv_youtube_id', true );
		if ( ! $youtube_id ) return '';

		$accent    = pv_resolve_accent_color( $post->ID );
		$thumb_url = get_the_post_thumbnail_url( $post->ID, 'medium' ) ?: '';
		$duration  = get_post_meta( $post->ID, '_pv_duration', true );
		$embed_url = 'https://www.youtube.com/embed/' . $youtube_id;
		$desc      = wp_trim_words( get_the_excerpt( $post ) ?: $post->post_content, 30 );

		$cats     = get_the_terms( $post->ID, 'pv_category' );
		$cat_name = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0]->name : '';

		// Full playlist JSON so modal/offcanvas nav can step through all videos in the grid.
		$pl_json = wp_json_encode( $playlist ?: [
			[ 'youtubeId' => $youtube_id, 'embedUrl' => $embed_url, 'title' => $post->post_title, 'desc' => $desc, 'accent' => $accent ],
		] );

		ob_start();
		?>
		<div class="pv-card <?php echo $thumb_url ? '' : 'pv-card--no-thumb'; ?>"
		     style="--pv-accent:<?php echo esc_attr( $accent ); ?>;<?php if ( $thumb_url ) : ?> background-image: linear-gradient(to top right,rgba(0,0,0,.75),rgba(0,0,0,.05)),url(<?php echo esc_url( $thumb_url ); ?>);<?php endif; ?>">
			<?php if ( $cat_name ) : ?>
				<span class="pv-card__cat"><?php echo esc_html( $cat_name ); ?></span>
			<?php endif; ?>
			<?php if ( $duration ) : ?>
				<span class="pv-card__duration"><?php echo esc_html( $duration ); ?></span>
			<?php endif; ?>
			<div class="pv-card__footer">
				<div class="pv-card__title"><?php echo esc_html( $post->post_title ); ?></div>
			</div>
			<div class="pv-card__hover-content">
				<?php if ( $desc ) : ?>
					<p class="pv-card__hover-excerpt"><?php echo esc_html( $desc ); ?></p>
					<a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" class="pv-card__read-more">Read more &rarr;</a>
				<?php endif; ?>
				<?php if ( in_array( $display, [ 'offcanvas', 'modal' ], true ) ) : ?>
					<button class="pv-trigger pv-card__watch-btn"
					        aria-label="<?php echo esc_attr( sprintf( __( 'Watch %s', 'pv-youtube-importer' ), $post->post_title ) ); ?>"
					        data-video-id="<?php echo esc_attr( $post->ID ); ?>"
					        data-youtube-id="<?php echo esc_attr( $youtube_id ); ?>"
					        data-embed-url="<?php echo esc_attr( $embed_url ); ?>"
					        data-title="<?php echo esc_attr( $post->post_title ); ?>"
					        data-description="<?php echo esc_attr( $desc ); ?>"
					        data-accent="<?php echo esc_attr( $accent ); ?>"
					        data-playlist="<?php echo esc_attr( $pl_json ); ?>">
						<svg class="pv-play-icon" width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg> <?php esc_html_e( 'Watch Now', 'pv-youtube-importer' ); ?>
					</button>
				<?php else : ?>
					<a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>"
					   class="pv-card__watch-btn"
					   aria-label="<?php echo esc_attr( sprintf( __( 'Watch %s', 'pv-youtube-importer' ), $post->post_title ) ); ?>">
						<svg class="pv-play-icon" width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg> <?php esc_html_e( 'Watch Video', 'pv-youtube-importer' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
