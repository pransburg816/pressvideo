<?php
/**
 * Archive template for pv_youtube.
 * Dark cinematic hero + layout driven by Dashboard archive_layout setting.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$pv_settings   = get_option( 'pv_settings', [] );
$pv_accent     = $pv_settings['default_accent'] ?? '#4f46e5';
$pv_layout     = $pv_settings['archive_layout']  ?? 'grid';
$pv_display    = $pv_settings['display_mode']    ?? 'offcanvas';
$total_videos  = (int) ( wp_count_posts( 'pv_youtube' )->publish ?? 0 );

// Content width
$_pv_cw_map   = [ 'wide' => '1400px', 'medium' => '1200px', 'narrow' => '960px' ];
$_pv_max_w    = $_pv_cw_map[ $pv_settings['content_width'] ?? '' ] ?? '';
$_pv_width_attr = $_pv_max_w ? ' style="max-width:' . esc_attr( $_pv_max_w ) . ';margin:0 auto;"' : '';

if ( 'offcanvas' === $pv_display ) {
	do_action( 'pv_player_enqueued' );
}

// Filter terms (pv_category pills — grid/compact only)
$pv_filter_terms = [];
if ( in_array( $pv_layout, [ 'grid', 'compact', 'featured' ], true ) ) {
	$_terms = get_terms( [
		'taxonomy'   => 'pv_category',
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
	] );
	if ( $_terms && ! is_wp_error( $_terms ) ) {
		$pv_filter_terms = $_terms;
	}
}

// Build playlist JSON for offcanvas rail from the current page's posts.
$pv_playlist_json = '[]';
if ( 'offcanvas' === $pv_display && ! empty( $GLOBALS['wp_query']->posts ) ) {
	$_pv_pl = [];
	foreach ( (array) $GLOBALS['wp_query']->posts as $_p ) {
		$_yt = get_post_meta( $_p->ID, '_pv_youtube_id', true );
		if ( ! $_yt ) continue;
		$_pv_pl[] = [
			'id'       => $_p->ID,
			'youtubeId'=> $_yt,
			'embedUrl' => 'https://www.youtube.com/embed/' . $_yt,
			'title'    => $_p->post_title,
			'desc'     => wp_trim_words( $_p->post_excerpt ?: $_p->post_content, 20 ),
			'accent'   => pv_resolve_accent_color( $_p->ID ),
			'thumb'    => get_the_post_thumbnail_url( $_p->ID, 'medium' ) ?: '',
			'duration' => get_post_meta( $_p->ID, '_pv_duration', true ) ?: '',
		];
	}
	$pv_playlist_json = wp_json_encode( $_pv_pl );
}

// Renders a grid card for the current post in The Loop.
$render_card = function() use ( $pv_display, $pv_playlist_json ) {
	$youtube_id = get_post_meta( get_the_ID(), '_pv_youtube_id', true );
	if ( ! $youtube_id && 'offcanvas' === $pv_display ) return;
	$accent    = pv_resolve_accent_color( get_the_ID() );
	$embed_url = $youtube_id
		? 'https://www.youtube.com/embed/' . $youtube_id . '?rel=0&modestbranding=1'
		: '';
	$duration  = get_post_meta( get_the_ID(), '_pv_duration', true );
	$thumb_url = get_the_post_thumbnail_url( get_the_ID(), 'medium' ) ?: '';
	$excerpt   = wp_trim_words( get_the_excerpt(), 18 );
	$cats      = get_the_terms( get_the_ID(), 'pv_category' );
	$cat       = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0] : null;
	$cat_name  = $cat ? $cat->name : '';
	$cat_slug  = $cat ? $cat->slug : '';
	?>
	<div class="pv-card <?php echo $thumb_url ? '' : 'pv-card--no-thumb'; ?>"
	     data-category="<?php echo esc_attr( $cat_slug ); ?>"
	     style="--pv-accent:<?php echo esc_attr( $accent ); ?>;<?php if ( $thumb_url ) : ?> background-image: linear-gradient(to top right,rgba(0,0,0,.75),rgba(0,0,0,.05)),url(<?php echo esc_url( $thumb_url ); ?>);<?php endif; ?>">
		<?php if ( $cat_name ) : ?>
			<span class="pv-card__cat"><?php echo esc_html( $cat_name ); ?></span>
		<?php endif; ?>
		<span class="pv-card__circle" aria-hidden="true">
			<span class="pv-card__circle-icon">&#9654;</span>
		</span>
		<?php if ( $duration ) : ?>
			<span class="pv-card__duration"><?php echo esc_html( $duration ); ?></span>
		<?php endif; ?>
		<div class="pv-card__footer">
			<div class="pv-card__title"><?php the_title(); ?></div>
		</div>
		<div class="pv-card__hover-content">
			<?php if ( $excerpt ) : ?>
				<p class="pv-card__hover-excerpt"><?php echo esc_html( $excerpt ); ?></p>
			<?php endif; ?>
			<?php if ( 'offcanvas' === $pv_display && $youtube_id ) : ?>
				<button class="pv-trigger pv-card__watch-btn"
				        data-youtube-id="<?php echo esc_attr( $youtube_id ); ?>"
				        data-embed-url="<?php echo esc_attr( $embed_url ); ?>"
				        data-title="<?php echo esc_attr( get_the_title() ); ?>"
				        data-description="<?php echo esc_attr( $excerpt ); ?>"
				        data-accent="<?php echo esc_attr( $accent ); ?>"
				        data-playlist="<?php echo esc_attr( $pv_playlist_json ); ?>"
				        aria-label="<?php echo esc_attr( sprintf( __( 'Watch %s', 'pv-youtube-importer' ), get_the_title() ) ); ?>">
					&#9654; <?php esc_html_e( 'Watch Now', 'pv-youtube-importer' ); ?>
				</button>
			<?php else : ?>
				<a href="<?php the_permalink(); ?>" class="pv-card__watch-btn"
				   aria-label="<?php echo esc_attr( sprintf( __( 'Watch %s', 'pv-youtube-importer' ), get_the_title() ) ); ?>">
					&#9654; <?php esc_html_e( 'Watch Video', 'pv-youtube-importer' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
	<?php
};

get_header();
?>
<div class="pv-archive-wrap" style="--pv-accent:<?php echo esc_attr( $pv_accent ); ?>;">

	<!-- Dark cinematic hero -->
	<div class="pv-archive-hero">
		<div class="pv-archive-hero__inner">
			<p class="pv-archive-hero__eyebrow">&#9654; PressVideo</p>
			<h1 class="pv-archive-hero__title">
				<?php echo esc_html( post_type_archive_title( '', false ) ?: __( 'Video Library', 'pv-youtube-importer' ) ); ?>
			</h1>
			<?php if ( $total_videos > 0 ) : ?>
				<p class="pv-archive-hero__sub">
					<?php echo esc_html( sprintf(
						_n( '%d video available', '%d videos available', $total_videos, 'pv-youtube-importer' ),
						$total_videos
					) ); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<!-- Video content -->
	<div class="pv-archive-content"<?php echo $_pv_width_attr; // phpcs:ignore ?>>

		<?php if ( have_posts() ) : ?>

			<?php if ( count( $pv_filter_terms ) > 1 ) : ?>
			<div class="pv-filter-bar" role="navigation" aria-label="<?php esc_attr_e( 'Filter videos by category', 'pv-youtube-importer' ); ?>">
				<button class="pv-filter-btn pv-filter-btn--active" data-filter="*">
					<?php esc_html_e( 'All', 'pv-youtube-importer' ); ?>
					<span class="pv-filter-btn__count"><?php echo esc_html( $total_videos ); ?></span>
				</button>
				<?php foreach ( $pv_filter_terms as $term ) : ?>
					<button class="pv-filter-btn" data-filter="<?php echo esc_attr( $term->slug ); ?>">
						<?php echo esc_html( $term->name ); ?>
						<span class="pv-filter-btn__count"><?php echo esc_html( $term->count ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<?php if ( 'featured' === $pv_layout ) :
				the_post();
				$f_yt     = get_post_meta( get_the_ID(), '_pv_youtube_id', true );
				$f_accent = pv_resolve_accent_color( get_the_ID() );
				$f_embed  = $f_yt ? 'https://www.youtube.com/embed/' . $f_yt . '?rel=0&modestbranding=1' : '';
			?>
				<div class="pv-featured-card" style="--pv-accent:<?php echo esc_attr( $f_accent ); ?>;">
					<?php if ( has_post_thumbnail() ) : ?>
						<div class="pv-featured-thumb">
							<?php the_post_thumbnail( 'large' ); ?>
						</div>
					<?php endif; ?>
					<div class="pv-featured-body">
						<span class="pv-badge">Featured</span>
						<h2 class="pv-featured-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<p class="pv-featured-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 30 ) ); ?></p>
						<?php if ( $f_yt ) : ?>
							<button class="pv-trigger pv-btn"
							        data-youtube-id="<?php echo esc_attr( $f_yt ); ?>"
							        data-embed-url="<?php echo esc_attr( $f_embed ); ?>"
							        data-title="<?php echo esc_attr( get_the_title() ); ?>"
							        data-description="<?php echo esc_attr( wp_trim_words( get_the_excerpt(), 20 ) ); ?>"
							        data-accent="<?php echo esc_attr( $f_accent ); ?>"
							        data-playlist="<?php echo esc_attr( $pv_playlist_json ); ?>">
								&#9654; <?php esc_html_e( 'Watch Now', 'pv-youtube-importer' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( have_posts() ) : ?>
					<div class="pv-grid" style="--pv-cols:3;">
						<?php while ( have_posts() ) : the_post(); ?>
							<?php $render_card(); ?>
						<?php endwhile; ?>
					</div>
				<?php endif; ?>

			<?php elseif ( 'list' === $pv_layout ) : ?>
				<div class="pv-list">
					<?php while ( have_posts() ) : the_post();
						$l_yt     = get_post_meta( get_the_ID(), '_pv_youtube_id', true );
						$l_accent = pv_resolve_accent_color( get_the_ID() );
						$l_embed  = $l_yt ? 'https://www.youtube.com/embed/' . $l_yt . '?rel=0&modestbranding=1' : '';
						$l_dur    = get_post_meta( get_the_ID(), '_pv_duration', true );
					?>
						<div class="pv-list-item" style="--pv-accent:<?php echo esc_attr( $l_accent ); ?>;">
							<div class="pv-list-thumb">
								<?php if ( has_post_thumbnail() ) : ?>
									<?php the_post_thumbnail( 'medium' ); ?>
								<?php else : ?>
									<div class="pv-card__no-thumb">&#9654;</div>
								<?php endif; ?>
								<?php if ( $l_dur ) : ?>
									<span class="pv-card__duration"><?php echo esc_html( $l_dur ); ?></span>
								<?php endif; ?>
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
											&#9654; <?php esc_html_e( 'Watch', 'pv-youtube-importer' ); ?>
										</button>
									</div>
								<?php endif; ?>
							</div>
						</div>
					<?php endwhile; ?>
				</div>

			<?php elseif ( 'compact' === $pv_layout ) : ?>
				<div class="pv-grid" style="--pv-cols:4;">
					<?php while ( have_posts() ) : the_post(); ?>
						<?php $render_card(); ?>
					<?php endwhile; ?>
				</div>

			<?php else : /* grid (default) */ ?>
				<div class="pv-grid" style="--pv-cols:3;">
					<?php while ( have_posts() ) : the_post(); ?>
						<?php $render_card(); ?>
					<?php endwhile; ?>
				</div>

			<?php endif; ?>

			<div class="pv-pagination">
				<?php the_posts_pagination( [
					'prev_text' => '&#8592; ' . __( 'Prev', 'pv-youtube-importer' ),
					'next_text' => __( 'Next', 'pv-youtube-importer' ) . ' &#8594;',
				] ); ?>
			</div>

		<?php else : ?>
			<p class="pv-no-videos"><?php esc_html_e( 'No videos found.', 'pv-youtube-importer' ); ?></p>
		<?php endif; ?>

	</div>
</div>
<?php get_footer(); ?>
