<?php
/**
 * Template tag functions for use in theme templates.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Output a single video trigger button.
 *
 * @param int    $post_id  pv_video post ID.
 * @param string $label    Button label text.
 */
function pv_video_player( int $post_id, string $label = '' ): void {
	$sc = new PV_Shortcodes();
	echo $sc->render_single_video( [ // phpcs:ignore WordPress.Security.EscapeOutput
		'id'    => $post_id,
		'label' => $label ?: __( 'Watch Video', 'pv-youtube-importer' ),
	] );
}

/**
 * Output a video grid.
 *
 * @param array $args  Shortcode-compatible args array.
 */
function pv_video_grid( array $args = [] ): void {
	$sc = new PV_Shortcodes();
	echo $sc->render_grid( $args ); // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * Render a single broadcast card and return its HTML.
 * Shared between the archive template and the AJAX lazy-load handlers.
 */
function pv_bc_card_html( WP_Post $post, string $display = 'offcanvas', bool $show_views = true ): string {
	$yt     = get_post_meta( $post->ID, '_pv_youtube_id', true );
	$accent = pv_resolve_accent_color( $post->ID );
	$embed  = $yt ? 'https://www.youtube.com/embed/' . $yt . '?rel=0&modestbranding=1' : '';
	$thumb  = get_the_post_thumbnail_url( $post->ID, 'medium' ) ?: '';
	$dur    = get_post_meta( $post->ID, '_pv_duration', true );
	$cats   = get_the_terms( $post->ID, 'pv_category' );
	$cat    = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0]->name : '';
	$cslug  = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0]->slug : '';
	$date   = get_the_date( 'M j, Y', $post->ID );
	$ts     = strtotime( $post->post_date );
	$views    = (int) get_post_meta( $post->ID, '_pv_view_count', true );
	$is_music = pv_is_music_video( $post->ID ) ? '1' : '0';
	$artist   = get_post_meta( $post->ID, '_pv_artist', true ) ?: '';
	$album    = get_post_meta( $post->ID, '_pv_album', true ) ?: '';
	$title    = esc_html( $post->post_title );
	$link     = esc_url( get_permalink( $post->ID ) );
	$play_svg = '<svg width="36" height="36" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>';

	ob_start();
	?>
	<div class="pv-bc-card" data-category="<?php echo esc_attr( $cslug ); ?>" data-date="<?php echo esc_attr( $ts ); ?>" data-views="<?php echo esc_attr( $views ); ?>" data-yt-id="<?php echo esc_attr( $yt ); ?>" data-is-music="<?php echo esc_attr( $is_music ); ?>" style="--pv-accent:<?php echo esc_attr( $accent ); ?>;">
		<div class="pv-bc-card__thumb">
			<?php if ( $thumb ) : ?>
				<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $post->post_title ); ?>" loading="lazy">
			<?php else : ?>
				<div class="pv-bc-card__thumb-placeholder"><?php echo $play_svg; // phpcs:ignore ?></div>
			<?php endif; ?>
			<?php if ( $dur ) : ?><span class="pv-bc-card__dur"><?php echo esc_html( $dur ); ?></span><?php endif; ?>
			<?php if ( $is_music === '1' ) : ?><span class="pv-music-badge"><svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg> Music</span><?php endif; ?>
			<?php if ( $yt && in_array( $display, [ 'offcanvas', 'modal' ], true ) ) : ?>
				<button class="pv-trigger pv-bc-card__play"
				        data-youtube-id="<?php echo esc_attr( $yt ); ?>"
				        data-embed-url="<?php echo esc_attr( $embed ); ?>"
				        data-title="<?php echo esc_attr( $post->post_title ); ?>"
				        data-description=""
				        data-accent="<?php echo esc_attr( $accent ); ?>"
				        data-thumb="<?php echo esc_attr( $thumb ); ?>"
				        data-permalink="<?php echo esc_attr( get_permalink( $post->ID ) ); ?>"
				        data-is-music="<?php echo esc_attr( $is_music ); ?>"
				        data-artist="<?php echo esc_attr( $artist ); ?>"
				        data-album="<?php echo esc_attr( $album ); ?>"
				        aria-label="<?php echo esc_attr( sprintf( __( 'Watch %s', 'pv-youtube-importer' ), $post->post_title ) ); ?>">
					<?php echo $play_svg; // phpcs:ignore ?>
				</button>
			<?php else : ?>
				<a href="<?php echo $link; // phpcs:ignore ?>" class="pv-bc-card__play"
				   aria-label="<?php echo esc_attr( sprintf( __( 'Watch %s', 'pv-youtube-importer' ), $post->post_title ) ); ?>">
					<?php echo $play_svg; // phpcs:ignore ?>
				</a>
			<?php endif; ?>
		</div>
		<div class="pv-bc-card__info">
			<div class="pv-bc-card__title"><a href="<?php echo $link; // phpcs:ignore ?>"><?php echo $title; // phpcs:ignore ?></a></div>
			<div class="pv-bc-card__meta">
				<?php if ( $cat ) : ?><span class="pv-bc-card__cat"><?php echo esc_html( $cat ); ?></span><span aria-hidden="true">&middot;</span><?php endif; ?>
				<span><?php echo esc_html( $date ); ?></span>
				<?php if ( $show_views && $views > 0 ) : ?>
					<span aria-hidden="true">&middot;</span><span><?php echo esc_html( number_format_i18n( $views ) ); ?> <?php esc_html_e( 'views', 'pv-youtube-importer' ); ?></span>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
