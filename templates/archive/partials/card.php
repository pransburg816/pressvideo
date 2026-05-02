<?php
/**
 * Card partial — used by archive-videos.php and ajax_load_more().
 * Variables expected in scope: $pv_display, $pv_playlist_json, $pv_cards_excerpt, $pv_cards_cat, $pv_cards_views
 * Must be called within an active WP post loop (the_post() already called).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$youtube_id = get_post_meta( get_the_ID(), '_pv_youtube_id', true );
if ( ! $youtube_id && in_array( $pv_display, [ 'offcanvas', 'modal' ], true ) ) return;
$accent    = pv_resolve_accent_color( get_the_ID() );
$views     = (int) get_post_meta( get_the_ID(), '_pv_view_count', true );
$embed_url = $youtube_id ? 'https://www.youtube.com/embed/' . $youtube_id . '?rel=0&modestbranding=1' : '';
$duration  = get_post_meta( get_the_ID(), '_pv_duration', true );
$thumb_url = get_the_post_thumbnail_url( get_the_ID(), 'medium' ) ?: '';
$excerpt   = wp_trim_words( get_the_excerpt(), 30 );
$cats      = get_the_terms( get_the_ID(), 'pv_category' );
$cat       = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0] : null;
$cat_name  = $cat ? $cat->name : '';
$cat_slug  = $cat ? $cat->slug : '';
$is_music  = pv_is_music_video( get_the_ID() ) ? '1' : '0';
$artist    = get_post_meta( get_the_ID(), '_pv_artist', true ) ?: '';
$album     = get_post_meta( get_the_ID(), '_pv_album', true ) ?: '';
?>
<div class="pv-card <?php echo $thumb_url ? '' : 'pv-card--no-thumb'; ?>"
     data-category="<?php echo esc_attr( $cat_slug ); ?>"
     data-date="<?php echo esc_attr( get_the_date( 'U' ) ); ?>"
     data-views="<?php echo esc_attr( (int) get_post_meta( get_the_ID(), '_pv_view_count', true ) ); ?>"
     data-is-music="<?php echo esc_attr( $is_music ); ?>"
     style="--pv-accent:<?php echo esc_attr( $accent ); ?>;<?php if ( $thumb_url ) : ?> background-image: linear-gradient(to top right,rgba(0,0,0,.75),rgba(0,0,0,.05)),url(<?php echo esc_url( $thumb_url ); ?>);<?php endif; ?>">
	<?php if ( $pv_cards_cat && $cat_name ) : ?><span class="pv-card__cat"><?php echo esc_html( $cat_name ); ?></span><?php endif; ?>
	<?php if ( $duration ) : ?><span class="pv-card__duration"><?php echo esc_html( $duration ); ?></span><?php endif; ?>
	<?php if ( $is_music ) : ?><span class="pv-music-badge"><svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg> Music</span><?php endif; ?>
	<div class="pv-card__footer">
		<div class="pv-card__title"><?php the_title(); ?></div>
		<?php if ( ! empty( $pv_cards_views ) && $views > 0 ) : ?>
			<div class="pv-card__meta"><?php echo esc_html( get_the_date( 'M j, Y' ) ); ?> &middot; <?php echo esc_html( number_format_i18n( $views ) ); ?> <?php esc_html_e( 'views', 'pv-youtube-importer' ); ?></div>
		<?php endif; ?>
	</div>
	<div class="pv-card__hover-content">
		<?php if ( $excerpt && $pv_cards_excerpt ) : ?>
			<p class="pv-card__hover-excerpt"><?php echo esc_html( $excerpt ); ?></p>
			<a href="<?php the_permalink(); ?>" class="pv-card__read-more">Read more &rarr;</a>
		<?php endif; ?>
		<?php if ( in_array( $pv_display, [ 'offcanvas', 'modal' ], true ) && $youtube_id ) : ?>
			<button class="pv-trigger pv-card__watch-btn"
			        data-youtube-id="<?php echo esc_attr( $youtube_id ); ?>"
			        data-embed-url="<?php echo esc_attr( $embed_url ); ?>"
			        data-title="<?php echo esc_attr( get_the_title() ); ?>"
			        data-description="<?php echo esc_attr( $excerpt ); ?>"
			        data-accent="<?php echo esc_attr( $accent ); ?>"
			        data-permalink="<?php echo esc_attr( get_permalink() ); ?>"
			        data-is-music="<?php echo esc_attr( $is_music ); ?>"
			        data-artist="<?php echo esc_attr( $artist ); ?>"
			        data-album="<?php echo esc_attr( $album ); ?>"
			        data-playlist="<?php echo esc_attr( $pv_playlist_json ); ?>"
			        aria-label="<?php echo esc_attr( sprintf( __( 'Watch %s', 'pv-youtube-importer' ), get_the_title() ) ); ?>">
				<svg class="pv-play-icon" width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg> <?php esc_html_e( 'Watch Now', 'pv-youtube-importer' ); ?>
			</button>
		<?php else : ?>
			<a href="<?php the_permalink(); ?>" class="pv-card__watch-btn"
			   aria-label="<?php echo esc_attr( sprintf( __( 'Watch %s', 'pv-youtube-importer' ), get_the_title() ) ); ?>">
				<svg class="pv-play-icon" width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg> <?php esc_html_e( 'Watch Video', 'pv-youtube-importer' ); ?>
			</a>
		<?php endif; ?>
	</div>
</div>
