<?php
/**
 * List-item partial — used by archive-videos.php and ajax_load_more().
 * Variables expected in scope: $pv_playlist_json
 * Must be called within an active WP post loop (the_post() already called).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$l_yt       = get_post_meta( get_the_ID(), '_pv_youtube_id', true );
$l_accent   = pv_resolve_accent_color( get_the_ID() );
$l_embed    = $l_yt ? 'https://www.youtube.com/embed/' . $l_yt . '?rel=0&modestbranding=1' : '';
$l_dur      = get_post_meta( get_the_ID(), '_pv_duration', true );
$l_cats     = get_the_terms( get_the_ID(), 'pv_category' );
$l_cat_slug = ( $l_cats && ! is_wp_error( $l_cats ) ) ? $l_cats[0]->slug : '';
$l_ts       = (int) get_the_date( 'U' );
$l_views    = (int) get_post_meta( get_the_ID(), '_pv_view_count', true );
?>
<div class="pv-list-item"
     data-category="<?php echo esc_attr( $l_cat_slug ); ?>"
     data-date="<?php echo esc_attr( $l_ts ); ?>"
     data-views="<?php echo esc_attr( $l_views ); ?>"
     style="--pv-accent:<?php echo esc_attr( $l_accent ); ?>;">
	<div class="pv-list-thumb">
		<?php if ( has_post_thumbnail() ) : the_post_thumbnail( 'medium' ); else : ?><div class="pv-card__no-thumb"><svg class="pv-play-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></div><?php endif; ?>
		<?php if ( $l_dur ) : ?><span class="pv-card__duration"><?php echo esc_html( $l_dur ); ?></span><?php endif; ?>
	</div>
	<div class="pv-list-body">
		<span class="pv-badge" style="background:<?php echo esc_attr( $l_accent ); ?>;">Video</span>
		<h2 class="pv-list-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<p class="pv-list-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
		<?php if ( $l_yt ) : ?>
			<div class="pv-list-actions">
				<button class="pv-trigger pv-btn pv-btn--sm"
				        data-youtube-id="<?php echo esc_attr( $l_yt ); ?>"
				        data-embed-url="<?php echo esc_attr( $l_embed ); ?>"
				        data-title="<?php echo esc_attr( get_the_title() ); ?>"
				        data-description="<?php echo esc_attr( wp_trim_words( get_the_excerpt(), 20 ) ); ?>"
				        data-accent="<?php echo esc_attr( $l_accent ); ?>"
				        data-playlist="<?php echo esc_attr( $pv_playlist_json ); ?>">
					<svg class="pv-play-icon" width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg> <?php esc_html_e( 'Watch', 'pv-youtube-importer' ); ?>
				</button>
			</div>
		<?php endif; ?>
	</div>
</div>
