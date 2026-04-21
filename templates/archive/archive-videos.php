<?php
/**
 * Archive template for pv_youtube.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$pv_settings = get_option( 'pv_settings', [] );

$pv_is_preview = false;
if (
	isset( $_GET['pv_preview'], $_GET['pv_nonce'] ) // phpcs:ignore
	&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['pv_nonce'] ) ), 'pv_preview' ) // phpcs:ignore
	&& current_user_can( 'manage_options' )
) {
	$pv_is_preview = true;
	$_preview = get_transient( 'pv_preview_settings' );
	if ( is_array( $_preview ) ) {
		$pv_settings = array_merge( $pv_settings, $_preview );
	}
}

$pv_accent    = $pv_settings['default_accent'] ?? '#4f46e5';
$pv_layout    = $pv_settings['archive_layout'] ?? 'grid';
$pv_display   = $pv_settings['display_mode']   ?? 'offcanvas';
$total_videos = (int) ( wp_count_posts( 'pv_youtube' )->publish ?? 0 );

// Hero
$pv_hero_title = $pv_settings['hero_title']    ?? '';
$pv_hero_sub   = $pv_settings['hero_subtitle'] ?? '';

$_pv_hero_style = '';
if ( ! empty( $pv_settings['hero_bg_image'] ) ) {
	$_ov_map        = [ 'light' => '0.45', 'medium' => '0.65', 'dark' => '0.82' ];
	$_ov_op         = $_ov_map[ $pv_settings['hero_overlay'] ?? 'medium' ] ?? '0.65';
	$_pv_hero_style = ' style="background-image:linear-gradient(rgba(0,0,0,' . esc_attr( $_ov_op ) . '),rgba(0,0,0,' . esc_attr( $_ov_op ) . ')),url(' . esc_url( $pv_settings['hero_bg_image'] ) . ');background-size:cover;background-position:center;"';
}

$_pv_title_size  = $pv_settings['hero_title_size']     ?? '';
$_pv_title_color = $pv_settings['hero_title_color']    ?? '';
$_pv_sub_color   = $pv_settings['hero_subtitle_color'] ?? '';
$_pv_title_class = 'pv-archive-hero__title' . ( $_pv_title_size ? ' pv-archive-hero__title--' . esc_attr( $_pv_title_size ) : '' );
$_pv_title_style = $_pv_title_color ? ' style="color:' . esc_attr( $_pv_title_color ) . '"' : '';
$_pv_sub_style   = $_pv_sub_color   ? ' style="color:' . esc_attr( $_pv_sub_color ) . '"'   : '';

// Card options
$pv_cards_excerpt = isset( $pv_settings['cards_show_excerpt'] )  ? (bool) $pv_settings['cards_show_excerpt']  : true;
$pv_cards_cat     = isset( $pv_settings['cards_show_category'] ) ? (bool) $pv_settings['cards_show_category'] : true;

// Content width
$_pv_cw_map     = [ 'wide' => '1400px', 'medium' => '1200px', 'narrow' => '960px' ];
$_pv_max_w      = $_pv_cw_map[ $pv_settings['content_width'] ?? '' ] ?? '';
$_pv_width_attr = $_pv_max_w ? ' style="max-width:' . esc_attr( $_pv_max_w ) . ';margin:0 auto;"' : '';

if ( 'offcanvas' === $pv_display ) {
	do_action( 'pv_player_enqueued' );
}

$pv_filter_terms = [];
$_terms = get_terms( [ 'taxonomy' => 'pv_category', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ] );
if ( $_terms && ! is_wp_error( $_terms ) ) $pv_filter_terms = $_terms;

$pv_playlist_json = '[]';
if ( 'offcanvas' === $pv_display && ! empty( $GLOBALS['wp_query']->posts ) ) {
	$_pv_pl = [];
	foreach ( (array) $GLOBALS['wp_query']->posts as $_p ) {
		$_yt = get_post_meta( $_p->ID, '_pv_youtube_id', true );
		if ( ! $_yt ) continue;
		$_pv_pl[] = [
			'id'        => $_p->ID,
			'youtubeId' => $_yt,
			'embedUrl'  => 'https://www.youtube.com/embed/' . $_yt,
			'title'     => $_p->post_title,
			'desc'      => wp_trim_words( $_p->post_excerpt ?: $_p->post_content, 20 ),
			'accent'    => pv_resolve_accent_color( $_p->ID ),
			'thumb'     => get_the_post_thumbnail_url( $_p->ID, 'medium' ) ?: '',
			'duration'  => get_post_meta( $_p->ID, '_pv_duration', true ) ?: '',
		];
	}
	$pv_playlist_json = wp_json_encode( $_pv_pl );
}

// Aside settings
$pv_aside_nr_on    = isset( $pv_settings['aside_new_releases'] ) ? (bool) $pv_settings['aside_new_releases'] : true;
$pv_aside_nr_label = $pv_settings['aside_new_releases_label']   ?? 'New Releases';
$pv_aside_nr_count = (int) ( $pv_settings['aside_new_releases_count'] ?? 5 );
$pv_aside_tp_on    = isset( $pv_settings['aside_topics'] )       ? (bool) $pv_settings['aside_topics'] : true;
$pv_aside_tp_label = $pv_settings['aside_topics_label']          ?? 'Browse Topics';
$pv_aside_tg_on    = isset( $pv_settings['aside_tags'] )         ? (bool) $pv_settings['aside_tags'] : true;
$pv_aside_tg_label = $pv_settings['aside_tags_label']            ?? 'Explore Tags';
$pv_aside_tg_count = (int) ( $pv_settings['aside_tags_count'] ?? 12 );

$pv_aside_cat_on    = isset( $pv_settings['aside_cat_on'] ) ? (bool) $pv_settings['aside_cat_on'] : false;
$pv_aside_cat_label = $pv_settings['aside_cat_label']       ?? 'From the Collection';
$pv_aside_cat_term  = $pv_settings['aside_cat_term']        ?? '';
$pv_aside_cat_count = (int) ( $pv_settings['aside_cat_count'] ?? 5 );
$pv_aside_tag_on    = isset( $pv_settings['aside_tag_on'] ) ? (bool) $pv_settings['aside_tag_on'] : false;
$pv_aside_tag_label = $pv_settings['aside_tag_label']       ?? 'Staff Picks';
$pv_aside_tag_term  = $pv_settings['aside_tag_term']        ?? '';
$pv_aside_tag_count = (int) ( $pv_settings['aside_tag_count'] ?? 5 );

// Aside queries
$pv_aside_recent = [];
if ( $pv_aside_nr_on ) {
	$pv_aside_recent = get_posts( [ 'post_type' => 'pv_youtube', 'posts_per_page' => $pv_aside_nr_count, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC' ] );
}

$pv_aside_cat_videos = [];
if ( $pv_aside_cat_on && $pv_aside_cat_term ) {
	$pv_aside_cat_videos = get_posts( [
		'post_type'      => 'pv_youtube',
		'posts_per_page' => $pv_aside_cat_count,
		'post_status'    => 'publish',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'tax_query'      => [ [ 'taxonomy' => 'pv_category', 'field' => 'slug', 'terms' => $pv_aside_cat_term ] ], // phpcs:ignore
	] );
}

$pv_aside_tag_videos = [];
if ( $pv_aside_tag_on && $pv_aside_tag_term ) {
	$pv_aside_tag_videos = get_posts( [
		'post_type'      => 'pv_youtube',
		'posts_per_page' => $pv_aside_tag_count,
		'post_status'    => 'publish',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'tax_query'      => [ [ 'taxonomy' => 'pv_tag', 'field' => 'slug', 'terms' => $pv_aside_tag_term ] ], // phpcs:ignore
	] );
}

$pv_aside_tags = [];
if ( $pv_aside_tg_on ) {
	$_tg = get_terms( [ 'taxonomy' => 'pv_tag', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => $pv_aside_tg_count ] );
	$pv_aside_tags = ( $_tg && ! is_wp_error( $_tg ) ) ? $_tg : [];
}

// Helper: render a mini aside video row
$render_aside_item = function( $_r ) {
	$_r_dur   = get_post_meta( $_r->ID, '_pv_duration', true );
	$_r_thumb = get_the_post_thumbnail_url( $_r->ID, 'thumbnail' ) ?: '';
	$_r_cats  = get_the_terms( $_r->ID, 'pv_category' );
	$_r_cat   = ( $_r_cats && ! is_wp_error( $_r_cats ) ) ? $_r_cats[0]->name : '';
	?>
	<a href="<?php echo esc_url( get_permalink( $_r->ID ) ); ?>" class="pv-aside-recent-item">
		<div class="pv-aside-recent-thumb">
			<?php if ( $_r_thumb ) : ?>
				<img src="<?php echo esc_url( $_r_thumb ); ?>" alt="<?php echo esc_attr( $_r->post_title ); ?>" loading="lazy" width="96" height="54">
			<?php else : ?>
				<div class="pv-aside-recent-thumb__placeholder"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></div>
			<?php endif; ?>
			<?php if ( $_r_dur ) : ?><span class="pv-aside-recent-dur"><?php echo esc_html( $_r_dur ); ?></span><?php endif; ?>
		</div>
		<div class="pv-aside-recent-info">
			<span class="pv-aside-recent-title"><?php echo esc_html( $_r->post_title ); ?></span>
			<span class="pv-aside-recent-meta">
				<?php if ( $_r_cat ) : ?><span class="pv-aside-recent-cat"><?php echo esc_html( $_r_cat ); ?></span><?php endif; ?>
				<span class="pv-aside-recent-date"><?php echo esc_html( get_the_date( 'M j, Y', $_r->ID ) ); ?></span>
			</span>
		</div>
	</a>
	<?php
};

$render_card = function() use ( $pv_display, $pv_playlist_json, $pv_cards_excerpt, $pv_cards_cat ) {
	$youtube_id = get_post_meta( get_the_ID(), '_pv_youtube_id', true );
	if ( ! $youtube_id && 'offcanvas' === $pv_display ) return;
	$accent    = pv_resolve_accent_color( get_the_ID() );
	$embed_url = $youtube_id ? 'https://www.youtube.com/embed/' . $youtube_id . '?rel=0&modestbranding=1' : '';
	$duration  = get_post_meta( get_the_ID(), '_pv_duration', true );
	$thumb_url = get_the_post_thumbnail_url( get_the_ID(), 'medium' ) ?: '';
	$excerpt   = wp_trim_words( get_the_excerpt(), 30 );
	$cats      = get_the_terms( get_the_ID(), 'pv_category' );
	$cat       = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0] : null;
	$cat_name  = $cat ? $cat->name : '';
	$cat_slug  = $cat ? $cat->slug : '';
	?>
	<div class="pv-card <?php echo $thumb_url ? '' : 'pv-card--no-thumb'; ?>"
	     data-category="<?php echo esc_attr( $cat_slug ); ?>"
	     style="--pv-accent:<?php echo esc_attr( $accent ); ?>;<?php if ( $thumb_url ) : ?> background-image: linear-gradient(to top right,rgba(0,0,0,.75),rgba(0,0,0,.05)),url(<?php echo esc_url( $thumb_url ); ?>);<?php endif; ?>">
		<?php if ( $pv_cards_cat && $cat_name ) : ?><span class="pv-card__cat"><?php echo esc_html( $cat_name ); ?></span><?php endif; ?>
		<?php if ( $duration ) : ?><span class="pv-card__duration"><?php echo esc_html( $duration ); ?></span><?php endif; ?>
		<div class="pv-card__footer"><div class="pv-card__title"><?php the_title(); ?></div></div>
		<div class="pv-card__hover-content">
			<?php if ( $excerpt && $pv_cards_excerpt ) : ?>
				<p class="pv-card__hover-excerpt"><?php echo esc_html( $excerpt ); ?></p>
				<a href="<?php the_permalink(); ?>" class="pv-card__read-more">Read more &rarr;</a>
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
	<?php
};

get_header();
?>
<div class="pv-archive-wrap" style="--pv-accent:<?php echo esc_attr( $pv_accent ); ?>;">

	<div class="pv-archive-hero"<?php echo $_pv_hero_style; // phpcs:ignore ?>>
		<div class="pv-archive-hero__inner">
			<p class="pv-archive-hero__eyebrow"><svg class="pv-play-icon" width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg> PressVideo</p>
			<h1 class="<?php echo esc_attr( $_pv_title_class ); ?>"<?php echo $_pv_title_style; // phpcs:ignore ?> data-pv-hero-title>
				<?php echo esc_html( $pv_hero_title ?: ( post_type_archive_title( '', false ) ?: __( 'Video Library', 'pv-youtube-importer' ) ) ); ?>
			</h1>
			<?php if ( $pv_hero_sub || $total_videos > 0 ) : ?>
				<p class="pv-archive-hero__sub"<?php echo $_pv_sub_style; // phpcs:ignore ?> data-pv-hero-sub>
					<?php echo esc_html( $pv_hero_sub ?: sprintf( _n( '%d video available', '%d videos available', $total_videos, 'pv-youtube-importer' ), $total_videos ) ); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<div class="pv-archive-content"<?php echo $_pv_width_attr; // phpcs:ignore ?>>
		<div class="pv-archive-layout">

			<div class="pv-archive-main"
			     data-pv-max-pages="<?php echo esc_attr( $GLOBALS['wp_query']->max_num_pages ); ?>"
			     data-pv-base-url="<?php echo esc_url( get_post_type_archive_link( 'pv_youtube' ) ?: home_url( '/' ) ); ?>">

			<?php if ( have_posts() ) : ?>

				<?php if ( 'broadcast' !== $pv_layout ) : ?>
				<div class="pv-search-wrap">
					<svg class="pv-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
					<input type="search" class="pv-search-input"
					       placeholder="<?php esc_attr_e( 'Search videos...', 'pv-youtube-importer' ); ?>"
					       autocomplete="off" spellcheck="false"
					       aria-label="<?php esc_attr_e( 'Search videos', 'pv-youtube-importer' ); ?>">
					<button class="pv-search-clear" aria-label="<?php esc_attr_e( 'Clear search', 'pv-youtube-importer' ); ?>" hidden>&#x2715;</button>
				</div>
				<p class="pv-search-results-msg" hidden></p>
				<div class="pv-search-results" hidden></div>

				<?php if ( count( $pv_filter_terms ) > 1 ) : ?>
				<div class="pv-filter-bar" role="navigation" aria-label="<?php esc_attr_e( 'Filter videos by category', 'pv-youtube-importer' ); ?>">
					<button class="pv-filter-btn pv-filter-btn--active" data-filter="*"><?php esc_html_e( 'All', 'pv-youtube-importer' ); ?> <span class="pv-filter-btn__count"><?php echo esc_html( $total_videos ); ?></span></button>
					<?php foreach ( $pv_filter_terms as $term ) : ?>
						<button class="pv-filter-btn" data-filter="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?> <span class="pv-filter-btn__count"><?php echo esc_html( $term->count ); ?></span></button>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<?php endif; /* end non-broadcast search/filter bar */ ?>

				<?php if ( 'featured' === $pv_layout ) :
					the_post();
					$f_yt       = get_post_meta( get_the_ID(), '_pv_youtube_id', true );
					$f_accent   = pv_resolve_accent_color( get_the_ID() );
					$f_embed    = $f_yt ? 'https://www.youtube.com/embed/' . $f_yt . '?rel=0&modestbranding=1' : '';
					$f_cats     = get_the_terms( get_the_ID(), 'pv_category' );
					$f_cat_slug = ( $f_cats && ! is_wp_error( $f_cats ) ) ? $f_cats[0]->slug : '';
				?>
					<div class="pv-featured-card" data-category="<?php echo esc_attr( $f_cat_slug ); ?>" style="--pv-accent:<?php echo esc_attr( $f_accent ); ?>;">
						<?php if ( has_post_thumbnail() ) : ?><div class="pv-featured-thumb"><?php the_post_thumbnail( 'large' ); ?></div><?php endif; ?>
						<div class="pv-featured-body">
							<span class="pv-badge">Featured</span>
							<h2 class="pv-featured-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<p class="pv-featured-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 150 ) ); ?></p>
							<a href="<?php the_permalink(); ?>" class="pv-featured-read-more">Read more &rarr;</a>
							<?php if ( $f_yt ) : ?>
								<button class="pv-trigger pv-btn" data-youtube-id="<?php echo esc_attr( $f_yt ); ?>" data-embed-url="<?php echo esc_attr( $f_embed ); ?>" data-title="<?php echo esc_attr( get_the_title() ); ?>" data-description="<?php echo esc_attr( wp_trim_words( get_the_excerpt(), 20 ) ); ?>" data-accent="<?php echo esc_attr( $f_accent ); ?>" data-playlist="<?php echo esc_attr( $pv_playlist_json ); ?>">
									<svg class="pv-play-icon" width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg> <?php esc_html_e( 'Watch Now', 'pv-youtube-importer' ); ?>
								</button>
							<?php endif; ?>
						</div>
					</div>
					<?php if ( have_posts() ) : ?><div class="pv-grid" style="--pv-cols:3;"><?php while ( have_posts() ) : the_post(); $render_card(); endwhile; ?></div><?php endif; ?>

				<?php elseif ( 'list' === $pv_layout ) : ?>
					<div class="pv-list">
						<?php while ( have_posts() ) : the_post();
							$l_yt       = get_post_meta( get_the_ID(), '_pv_youtube_id', true );
							$l_accent   = pv_resolve_accent_color( get_the_ID() );
							$l_embed    = $l_yt ? 'https://www.youtube.com/embed/' . $l_yt . '?rel=0&modestbranding=1' : '';
							$l_dur      = get_post_meta( get_the_ID(), '_pv_duration', true );
							$l_cats     = get_the_terms( get_the_ID(), 'pv_category' );
							$l_cat_slug = ( $l_cats && ! is_wp_error( $l_cats ) ) ? $l_cats[0]->slug : '';
						?>
							<div class="pv-list-item" data-category="<?php echo esc_attr( $l_cat_slug ); ?>" style="--pv-accent:<?php echo esc_attr( $l_accent ); ?>;">
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
											<button class="pv-trigger pv-btn pv-btn--sm" data-youtube-id="<?php echo esc_attr( $l_yt ); ?>" data-embed-url="<?php echo esc_attr( $l_embed ); ?>" data-title="<?php echo esc_attr( get_the_title() ); ?>" data-description="<?php echo esc_attr( wp_trim_words( get_the_excerpt(), 20 ) ); ?>" data-accent="<?php echo esc_attr( $l_accent ); ?>" data-playlist="<?php echo esc_attr( $pv_playlist_json ); ?>">
												<svg class="pv-play-icon" width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg> <?php esc_html_e( 'Watch', 'pv-youtube-importer' ); ?>
											</button>
										</div>
									<?php endif; ?>
								</div>
							</div>
						<?php endwhile; ?>
					</div>

				<?php elseif ( 'compact' === $pv_layout ) : ?>
					<div class="pv-grid" style="--pv-cols:4;"><?php while ( have_posts() ) : the_post(); $render_card(); endwhile; ?></div>

				<?php elseif ( 'wall' === $pv_layout ) : ?>
					<div class="pv-wall"><?php while ( have_posts() ) : the_post(); $render_card(); endwhile; ?></div>

				<?php elseif ( 'spotlight' === $pv_layout ) :
					// Hero: first post
					the_post();
					$sp_id     = get_the_ID();
					$sp_yt     = get_post_meta( $sp_id, '_pv_youtube_id', true );
					$sp_accent = pv_resolve_accent_color( $sp_id );
					$sp_embed  = $sp_yt ? 'https://www.youtube.com/embed/' . $sp_yt . '?rel=0&modestbranding=1' : '';
					$sp_thumb  = get_the_post_thumbnail_url( $sp_id, 'full' ) ?: '';
					$sp_cats   = get_the_terms( $sp_id, 'pv_category' );
					$sp_cat    = ( $sp_cats && ! is_wp_error( $sp_cats ) ) ? $sp_cats[0]->name : '';
					$sp_cat_sl = ( $sp_cats && ! is_wp_error( $sp_cats ) ) ? $sp_cats[0]->slug : '';
					$sp_exc    = wp_trim_words( get_the_excerpt(), 25 );
					// Rail: next 4 posts
					$sp_rail = [];
					for ( $i = 0; $i < 4 && have_posts(); $i++ ) {
						the_post();
						$_sr_yt   = get_post_meta( get_the_ID(), '_pv_youtube_id', true );
						$_sr_cats = get_the_terms( get_the_ID(), 'pv_category' );
						$sp_rail[] = [
							'id'       => get_the_ID(),
							'title'    => get_the_title(),
							'thumb'    => get_the_post_thumbnail_url( get_the_ID(), 'medium' ) ?: '',
							'dur'      => get_post_meta( get_the_ID(), '_pv_duration', true ),
							'date'     => get_the_date( 'M j, Y' ),
							'link'     => get_permalink(),
							'yt'       => $_sr_yt,
							'embed'    => $_sr_yt ? 'https://www.youtube.com/embed/' . $_sr_yt . '?rel=0&modestbranding=1' : '',
							'accent'   => pv_resolve_accent_color( get_the_ID() ),
							'cat_slug' => ( $_sr_cats && ! is_wp_error( $_sr_cats ) ) ? $_sr_cats[0]->slug : '',
						];
					}
				?>
					<div class="pv-spotlight">
						<div class="pv-spotlight-top">
							<div class="pv-spotlight-hero" data-category="<?php echo esc_attr( $sp_cat_sl ); ?>" style="--pv-accent:<?php echo esc_attr( $sp_accent ); ?>;">
								<?php if ( $sp_thumb ) : ?><div class="pv-spotlight-hero__bg" style="background-image:url(<?php echo esc_url( $sp_thumb ); ?>);"></div><?php endif; ?>
								<div class="pv-spotlight-hero__overlay"></div>
								<div class="pv-spotlight-hero__body">
									<?php if ( $sp_cat ) : ?><span class="pv-badge" style="background:<?php echo esc_attr( $sp_accent ); ?>;"><?php echo esc_html( $sp_cat ); ?></span><?php endif; ?>
									<h2 class="pv-spotlight-hero__title"><a href="<?php echo esc_url( get_permalink( $sp_id ) ); ?>"><?php echo esc_html( get_the_title( $sp_id ) ); ?></a></h2>
									<?php if ( $sp_exc ) : ?><p class="pv-spotlight-hero__excerpt"><?php echo esc_html( $sp_exc ); ?></p><?php endif; ?>
									<?php if ( $sp_yt && 'offcanvas' === $pv_display ) : ?>
										<button class="pv-trigger pv-btn" data-youtube-id="<?php echo esc_attr( $sp_yt ); ?>" data-embed-url="<?php echo esc_attr( $sp_embed ); ?>" data-title="<?php echo esc_attr( get_the_title( $sp_id ) ); ?>" data-description="<?php echo esc_attr( $sp_exc ); ?>" data-accent="<?php echo esc_attr( $sp_accent ); ?>" data-playlist="<?php echo esc_attr( $pv_playlist_json ); ?>"><svg class="pv-play-icon" width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg> <?php esc_html_e( 'Watch Now', 'pv-youtube-importer' ); ?></button>
									<?php else : ?>
										<a href="<?php echo esc_url( get_permalink( $sp_id ) ); ?>" class="pv-btn"><svg class="pv-play-icon" width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg> <?php esc_html_e( 'Watch Video', 'pv-youtube-importer' ); ?></a>
									<?php endif; ?>
								</div>
							</div>

							<?php if ( ! empty( $sp_rail ) ) : ?>
							<div class="pv-spotlight-rail">
								<?php foreach ( $sp_rail as $_sr ) : ?>
									<a href="<?php echo esc_url( $_sr['link'] ); ?>"
									   class="pv-spotlight-rail-card<?php echo ( 'offcanvas' === $pv_display && $_sr['yt'] ) ? ' pv-trigger' : ''; ?>"
									   data-category="<?php echo esc_attr( $_sr['cat_slug'] ); ?>"
									   <?php if ( 'offcanvas' === $pv_display && $_sr['yt'] ) : ?>
									   data-youtube-id="<?php echo esc_attr( $_sr['yt'] ); ?>"
									   data-embed-url="<?php echo esc_attr( $_sr['embed'] ); ?>"
									   data-title="<?php echo esc_attr( $_sr['title'] ); ?>"
									   data-description=""
									   data-accent="<?php echo esc_attr( $_sr['accent'] ); ?>"
									   data-playlist="<?php echo esc_attr( $pv_playlist_json ); ?>"
									   <?php endif; ?>>
										<div class="pv-spotlight-rail-thumb">
											<?php if ( $_sr['thumb'] ) : ?><img src="<?php echo esc_url( $_sr['thumb'] ); ?>" alt="<?php echo esc_attr( $_sr['title'] ); ?>" loading="lazy"><?php endif; ?>
											<?php if ( $_sr['dur'] ) : ?><span class="pv-spotlight-rail-dur"><?php echo esc_html( $_sr['dur'] ); ?></span><?php endif; ?>
										</div>
										<div class="pv-spotlight-rail-info">
											<span class="pv-spotlight-rail-title"><?php echo esc_html( $_sr['title'] ); ?></span>
											<span class="pv-spotlight-rail-meta"><?php echo esc_html( $_sr['date'] ); ?></span>
										</div>
									</a>
								<?php endforeach; ?>
							</div>
							<?php endif; ?>

						</div>
						<?php if ( have_posts() ) : ?><div class="pv-grid pv-spotlight-grid" style="--pv-cols:3;"><?php while ( have_posts() ) : the_post(); $render_card(); endwhile; ?></div><?php endif; ?>
					</div>

				<?php elseif ( 'broadcast' === $pv_layout ) :
					// Collect all posts from main loop
					$bc_all_posts = [];
					while ( have_posts() ) {
						the_post();
						$bc_all_posts[] = clone $GLOBALS['post'];
					}

					// Decode selected playlist entries — may be series slugs or yt:PLxxxx
					$bc_pl_raw   = $pv_settings['bc_playlists'] ?? '[]';
					$bc_pl_items = json_decode( is_string( $bc_pl_raw ) ? $bc_pl_raw : '[]', true );
					$bc_pl_items = is_array( $bc_pl_items ) ? $bc_pl_items : [];

					$bc_yt_pl_ids    = []; // YouTube playlist IDs (yt: prefix stripped)
					$bc_series_slugs = []; // Series taxonomy slugs
					foreach ( $bc_pl_items as $_item ) {
						if ( strncmp( (string) $_item, 'yt:', 3 ) === 0 ) {
							$bc_yt_pl_ids[] = substr( (string) $_item, 3 );
						} else {
							$bc_series_slugs[] = (string) $_item;
						}
					}

					// Resolve series terms for Home tab
					$bc_series = [];
					if ( ! empty( $bc_series_slugs ) ) {
						foreach ( $bc_series_slugs as $_slug ) {
							$_t = get_term_by( 'slug', $_slug, 'pv_series' );
							if ( $_t && ! is_wp_error( $_t ) ) $bc_series[] = $_t;
						}
					}

					// Resolve YouTube playlist sections for Home tab (transient-cached API calls)
					$bc_yt_sections = [];
					if ( ! empty( $bc_yt_pl_ids ) ) {
						$_yt_api_key = $pv_settings['api_key']    ?? '';
						$_yt_ch_id   = $pv_settings['channel_id'] ?? '';
						if ( $_yt_api_key ) {
							$_yt_api  = new PV_YouTube_API( $_yt_api_key );
							$_ch_pls  = get_transient( 'pv_yt_ch_playlists_' . md5( $_yt_ch_id ) );
							foreach ( $bc_yt_pl_ids as $_yt_pl_id ) {
								// Resolve playlist title from channel playlists transient
								$_yt_pl_title = '';
								if ( is_array( $_ch_pls ) ) {
									foreach ( $_ch_pls as $_cp ) {
										if ( $_cp['id'] === $_yt_pl_id ) { $_yt_pl_title = $_cp['title']; break; }
									}
								}
								if ( ! $_yt_pl_title ) $_yt_pl_title = __( 'YouTube Playlist', 'pv-youtube-importer' );

								// Get video IDs from playlist (1-hour transient)
								$_yt_vids_key = 'pv_yt_pl_vids_' . md5( $_yt_pl_id );
								$_yt_vid_ids  = get_transient( $_yt_vids_key );
								if ( ! is_array( $_yt_vid_ids ) ) {
									$_yt_result  = $_yt_api->get_playlist_videos( $_yt_pl_id, 20 );
									$_yt_vid_ids = ! is_wp_error( $_yt_result )
										? array_column( $_yt_result, 'youtube_id' )
										: [];
									set_transient( $_yt_vids_key, $_yt_vid_ids, HOUR_IN_SECONDS );
								}

								if ( empty( $_yt_vid_ids ) ) continue;

								$_yt_posts = get_posts( [
									'post_type'      => 'pv_youtube',
									'posts_per_page' => 4,
									'post_status'    => 'publish',
									'meta_query'     => [ // phpcs:ignore
										[
											'key'     => '_pv_youtube_id',
											'value'   => $_yt_vid_ids,
											'compare' => 'IN',
										],
									],
								] );

								if ( ! empty( $_yt_posts ) ) {
									$bc_yt_sections[] = [
										'title' => $_yt_pl_title,
										'id'    => $_yt_pl_id,
										'posts' => $_yt_posts,
									];
								}
							}
						}
					}

					// Fallback: top 4 series when nothing selected (auto-fills the Home tab)
					if ( empty( $bc_series ) && empty( $bc_yt_sections ) ) {
						$_all_s = get_terms( [ 'taxonomy' => 'pv_series', 'hide_empty' => true, 'number' => 4, 'orderby' => 'count', 'order' => 'DESC' ] );
						if ( $_all_s && ! is_wp_error( $_all_s ) ) $bc_series = $_all_s;
					}

					// All series for Playlists tab
					$bc_all_series = get_terms( [ 'taxonomy' => 'pv_series', 'hide_empty' => true, 'orderby' => 'name' ] );
					$bc_all_series = is_wp_error( $bc_all_series ) ? [] : $bc_all_series;

					// Broadcast card helper: YouTube-style (thumb + info below)
					$render_bc_card = function( $_bcp ) use ( $pv_display ) {
						$_bc_yt     = get_post_meta( $_bcp->ID, '_pv_youtube_id', true );
						$_bc_accent = pv_resolve_accent_color( $_bcp->ID );
						$_bc_embed  = $_bc_yt ? 'https://www.youtube.com/embed/' . $_bc_yt . '?rel=0&modestbranding=1' : '';
						$_bc_thumb  = get_the_post_thumbnail_url( $_bcp->ID, 'medium' ) ?: '';
						$_bc_dur    = get_post_meta( $_bcp->ID, '_pv_duration', true );
						$_bc_cats   = get_the_terms( $_bcp->ID, 'pv_category' );
						$_bc_cat    = ( $_bc_cats && ! is_wp_error( $_bc_cats ) ) ? $_bc_cats[0]->name : '';
						$_bc_cslug  = ( $_bc_cats && ! is_wp_error( $_bc_cats ) ) ? $_bc_cats[0]->slug : '';
						$_bc_date   = get_the_date( 'M j, Y', $_bcp->ID );
						$_bc_ts     = strtotime( $_bcp->post_date );
						$_bc_views  = (int) $_bcp->comment_count;
						?>
						<div class="pv-bc-card" data-category="<?php echo esc_attr( $_bc_cslug ); ?>" data-date="<?php echo esc_attr( $_bc_ts ); ?>" data-views="<?php echo esc_attr( $_bc_views ); ?>" style="--pv-accent:<?php echo esc_attr( $_bc_accent ); ?>;">
							<div class="pv-bc-card__thumb">
								<?php if ( $_bc_thumb ) : ?>
									<img src="<?php echo esc_url( $_bc_thumb ); ?>" alt="<?php echo esc_attr( $_bcp->post_title ); ?>" loading="lazy">
								<?php else : ?>
									<div class="pv-bc-card__thumb-placeholder"><svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg></div>
								<?php endif; ?>
								<?php if ( $_bc_dur ) : ?><span class="pv-bc-card__dur"><?php echo esc_html( $_bc_dur ); ?></span><?php endif; ?>
								<?php if ( $_bc_yt && 'offcanvas' === $pv_display ) : ?>
									<button class="pv-trigger pv-bc-card__play"
									        data-youtube-id="<?php echo esc_attr( $_bc_yt ); ?>"
									        data-embed-url="<?php echo esc_attr( $_bc_embed ); ?>"
									        data-title="<?php echo esc_attr( $_bcp->post_title ); ?>"
									        data-description=""
									        data-accent="<?php echo esc_attr( $_bc_accent ); ?>"
									        aria-label="<?php echo esc_attr( sprintf( __( 'Watch %s', 'pv-youtube-importer' ), $_bcp->post_title ) ); ?>">
										<svg width="36" height="36" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
									</button>
								<?php else : ?>
									<a href="<?php echo esc_url( get_permalink( $_bcp->ID ) ); ?>" class="pv-bc-card__play"
									   aria-label="<?php echo esc_attr( sprintf( __( 'Watch %s', 'pv-youtube-importer' ), $_bcp->post_title ) ); ?>">
										<svg width="36" height="36" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
									</a>
								<?php endif; ?>
							</div>
							<div class="pv-bc-card__info">
								<div class="pv-bc-card__title"><a href="<?php echo esc_url( get_permalink( $_bcp->ID ) ); ?>"><?php echo esc_html( $_bcp->post_title ); ?></a></div>
								<div class="pv-bc-card__meta">
									<?php if ( $_bc_cat ) : ?><span class="pv-bc-card__cat"><?php echo esc_html( $_bc_cat ); ?></span><span aria-hidden="true">&middot;</span><?php endif; ?>
									<span><?php echo esc_html( $_bc_date ); ?></span>
								</div>
							</div>
						</div>
						<?php
					};
				?>
				<div class="pv-broadcast">

					<!-- Search bar (50% wide, centered, with mic) -->
					<div class="pv-bc-search-bar">
						<div class="pv-bc-search-wrap">
							<svg class="pv-bc-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
							<input type="search" class="pv-bc-search-input"
							       placeholder="<?php esc_attr_e( 'Search videos...', 'pv-youtube-importer' ); ?>"
							       autocomplete="off" spellcheck="false"
							       aria-label="<?php esc_attr_e( 'Search videos', 'pv-youtube-importer' ); ?>">
							<button class="pv-bc-search-clear" aria-label="<?php esc_attr_e( 'Clear search', 'pv-youtube-importer' ); ?>" hidden>&#x2715;</button>
							<button class="pv-bc-mic-btn" aria-label="<?php esc_attr_e( 'Voice search', 'pv-youtube-importer' ); ?>" title="<?php esc_attr_e( 'Search by voice', 'pv-youtube-importer' ); ?>">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm-1-9c0-.55.45-1 1-1s1 .45 1 1v6c0 .55-.45 1-1 1s-1-.45-1-1V5zm6 6c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/></svg>
							</button>
						</div>
					</div>
					<p class="pv-bc-search-msg" hidden></p>
					<div class="pv-bc-search-results" hidden></div>

					<!-- Tab bar -->
					<div class="pv-bc-tabs" role="tablist">
						<button class="pv-bc-tab pv-bc-tab--active" data-bc-tab="home" role="tab" aria-selected="true"><?php esc_html_e( 'Home', 'pv-youtube-importer' ); ?></button>
						<button class="pv-bc-tab" data-bc-tab="videos" role="tab" aria-selected="false"><?php esc_html_e( 'Videos', 'pv-youtube-importer' ); ?></button>
						<button class="pv-bc-tab" data-bc-tab="playlists" role="tab" aria-selected="false"><?php esc_html_e( 'Playlists', 'pv-youtube-importer' ); ?></button>
						<div class="pv-bc-tab-indicator" aria-hidden="true"></div>
					</div>

					<!-- Panels -->
					<div class="pv-bc-panels">

						<!-- HOME panel: YouTube playlist sections + series sections -->
						<div class="pv-bc-panel pv-bc-panel--active" data-bc-panel="home" role="tabpanel">

							<?php // YouTube playlist sections
							foreach ( $bc_yt_sections as $_yts ) : ?>
							<div class="pv-bc-section">
								<div class="pv-bc-section__head">
									<h2 class="pv-bc-section__title"><?php echo esc_html( $_yts['title'] ); ?></h2>
									<button type="button" class="pv-bc-section__view-all pv-bc-tab-switch" data-target-tab="videos"><?php esc_html_e( 'View All', 'pv-youtube-importer' ); ?> &rarr;</button>
								</div>
								<div class="pv-bc-row">
									<?php foreach ( $_yts['posts'] as $_yts_p ) : $render_bc_card( $_yts_p ); endforeach; ?>
								</div>
							</div>
							<?php endforeach; ?>

							<?php // Series sections
							foreach ( $bc_series as $_bcs ) :
								$_bcs_posts = get_posts( [
									'post_type'      => 'pv_youtube',
									'posts_per_page' => 4,
									'post_status'    => 'publish',
									'orderby'        => 'date',
									'order'          => 'DESC',
									'tax_query'      => [ [ 'taxonomy' => 'pv_series', 'field' => 'term_id', 'terms' => $_bcs->term_id ] ], // phpcs:ignore
								] );
								if ( empty( $_bcs_posts ) ) continue;
								$_bcs_link = get_term_link( $_bcs );
							?>
							<div class="pv-bc-section">
								<div class="pv-bc-section__head">
									<h2 class="pv-bc-section__title"><?php echo esc_html( $_bcs->name ); ?></h2>
									<?php if ( ! is_wp_error( $_bcs_link ) ) : ?>
										<a href="<?php echo esc_url( $_bcs_link ); ?>" class="pv-bc-section__view-all"><?php esc_html_e( 'View All', 'pv-youtube-importer' ); ?> &rarr;</a>
									<?php endif; ?>
								</div>
								<div class="pv-bc-row">
									<?php foreach ( $_bcs_posts as $_bcs_p ) : $render_bc_card( $_bcs_p ); endforeach; ?>
								</div>
							</div>
							<?php endforeach; ?>

							<?php // Fallback: show latest videos when no sections configured
							if ( empty( $bc_yt_sections ) && empty( $bc_series ) && ! empty( $bc_all_posts ) ) : ?>
							<div class="pv-bc-section">
								<div class="pv-bc-section__head">
									<h2 class="pv-bc-section__title"><?php esc_html_e( 'Latest Videos', 'pv-youtube-importer' ); ?></h2>
								</div>
								<div class="pv-bc-row">
									<?php foreach ( array_slice( $bc_all_posts, 0, 4 ) as $_bcp ) : $render_bc_card( $_bcp ); endforeach; ?>
								</div>
							</div>
							<?php endif; ?>

						</div><!-- /home panel -->

						<!-- VIDEOS panel: sort bar + chip filter + 4-col grid -->
						<div class="pv-bc-panel" data-bc-panel="videos" role="tabpanel">

							<!-- Sort bar -->
							<div class="pv-bc-sort-bar">
								<button class="pv-bc-sort-btn pv-bc-sort-btn--active" data-sort="latest"><?php esc_html_e( 'Latest', 'pv-youtube-importer' ); ?></button>
								<button class="pv-bc-sort-btn" data-sort="popular"><?php esc_html_e( 'Popular', 'pv-youtube-importer' ); ?></button>
								<button class="pv-bc-sort-btn" data-sort="oldest"><?php esc_html_e( 'Oldest', 'pv-youtube-importer' ); ?></button>
							</div>

							<!-- Category chip filter -->
							<?php if ( count( $pv_filter_terms ) > 1 ) : ?>
							<div class="pv-bc-chips" role="navigation" aria-label="<?php esc_attr_e( 'Filter by category', 'pv-youtube-importer' ); ?>">
								<button class="pv-bc-chip pv-bc-chip--active" data-filter="*"><?php esc_html_e( 'All', 'pv-youtube-importer' ); ?></button>
								<?php foreach ( $pv_filter_terms as $_bc_term ) : ?>
									<button class="pv-bc-chip" data-filter="<?php echo esc_attr( $_bc_term->slug ); ?>"><?php echo esc_html( $_bc_term->name ); ?></button>
								<?php endforeach; ?>
							</div>
							<?php endif; ?>

							<!-- 4-column video grid -->
							<div class="pv-bc-video-grid">
								<?php foreach ( $bc_all_posts as $_bc_v ) : $render_bc_card( $_bc_v ); endforeach; ?>
							</div>

						</div><!-- /videos panel -->

						<!-- PLAYLISTS panel: grid of all series as playlist cards -->
						<div class="pv-bc-panel" data-bc-panel="playlists" role="tabpanel">
							<?php if ( ! empty( $bc_all_series ) ) : ?>
							<div class="pv-bc-playlist-list">
								<?php foreach ( $bc_all_series as $_pls ) :
									// One query gives both the cover image and an accurate published post count
									$_pls_q = new WP_Query( [
										'post_type'      => 'pv_youtube',
										'posts_per_page' => 1,
										'post_status'    => 'publish',
										'orderby'        => 'date',
										'order'          => 'DESC',
										'no_found_rows'  => false,
										'tax_query'      => [ [ 'taxonomy' => 'pv_series', 'field' => 'term_id', 'terms' => $_pls->term_id ] ], // phpcs:ignore
									] );
									$_pls_count = (int) $_pls_q->found_posts;
									if ( $_pls_count === 0 ) continue; // hide empty playlists
									$_pls_thumb = ! empty( $_pls_q->posts ) ? get_the_post_thumbnail_url( $_pls_q->posts[0]->ID, 'medium' ) : '';
									$_pls_link  = get_term_link( $_pls );
								?>
								<div class="pv-bc-pl-list-card">
									<div class="pv-bc-pl-list-card__thumb">
										<?php if ( $_pls_thumb ) : ?>
											<img src="<?php echo esc_url( $_pls_thumb ); ?>" alt="<?php echo esc_attr( $_pls->name ); ?>" loading="lazy">
										<?php else : ?>
											<div class="pv-bc-pl-list-card__no-thumb"><svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8 12.5v-9l6 4.5-6 4.5z"/></svg></div>
										<?php endif; ?>
										<div class="pv-bc-pl-list-card__count">
											<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8 12.5v-9l6 4.5-6 4.5z"/></svg>
											<?php echo esc_html( sprintf( _n( '%d video', '%d videos', $_pls_count, 'pv-youtube-importer' ), $_pls_count ) ); ?>
										</div>
									</div>
									<div class="pv-bc-pl-list-card__info">
										<div class="pv-bc-pl-list-card__title"><?php echo esc_html( $_pls->name ); ?></div>
										<?php if ( ! is_wp_error( $_pls_link ) ) : ?>
											<a href="<?php echo esc_url( $_pls_link ); ?>" class="pv-bc-pl-list-card__view-all"><?php esc_html_e( 'View All', 'pv-youtube-importer' ); ?> &rarr;</a>
										<?php endif; ?>
									</div>
								</div>
								<?php endforeach; ?>
							</div>
							<?php else : ?>
							<p class="pv-no-videos"><?php esc_html_e( 'No playlists found. Assign the Series taxonomy to your videos to create playlists.', 'pv-youtube-importer' ); ?></p>
							<?php endif; ?>
						</div><!-- /playlists panel -->

					</div><!-- /.pv-bc-panels -->

				</div><!-- /.pv-broadcast -->

				<?php else : /* grid (default) */ ?>
					<div class="pv-grid" style="--pv-cols:3;"><?php while ( have_posts() ) : the_post(); $render_card(); endwhile; ?></div>
				<?php endif; ?>

				<div class="pv-pagination"><?php the_posts_pagination( [ 'prev_text' => '&#8592; ' . __( 'Prev', 'pv-youtube-importer' ), 'next_text' => __( 'Next', 'pv-youtube-importer' ) . ' &#8594;' ] ); ?></div>

			<?php else : ?>
				<p class="pv-no-videos"><?php esc_html_e( 'No videos found.', 'pv-youtube-importer' ); ?></p>
			<?php endif; ?>

			</div><!-- /.pv-archive-main -->

			<aside class="pv-archive-aside">

				<?php if ( $pv_aside_nr_on && ! empty( $pv_aside_recent ) ) : ?>
				<div class="pv-aside-section">
					<h3 class="pv-aside-heading"><svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg> <?php echo esc_html( $pv_aside_nr_label ); ?></h3>
					<div class="pv-aside-recent"><?php foreach ( $pv_aside_recent as $_r ) { $render_aside_item( $_r ); } ?></div>
				</div>
				<?php endif; ?>

				<?php if ( $pv_aside_cat_on && ! empty( $pv_aside_cat_videos ) ) : ?>
				<div class="pv-aside-section">
					<h3 class="pv-aside-heading"><svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg> <?php echo esc_html( $pv_aside_cat_label ); ?></h3>
					<div class="pv-aside-recent"><?php foreach ( $pv_aside_cat_videos as $_r ) { $render_aside_item( $_r ); } ?></div>
				</div>
				<?php endif; ?>

				<?php if ( $pv_aside_tag_on && ! empty( $pv_aside_tag_videos ) ) : ?>
				<div class="pv-aside-section">
					<h3 class="pv-aside-heading"><svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H7c-1.1 0-1.99.9-1.99 2L5 21l7-3 7 3V5c0-1.1-.9-2-2-2z"/></svg> <?php echo esc_html( $pv_aside_tag_label ); ?></h3>
					<div class="pv-aside-recent"><?php foreach ( $pv_aside_tag_videos as $_r ) { $render_aside_item( $_r ); } ?></div>
				</div>
				<?php endif; ?>

				<?php if ( $pv_aside_tp_on && count( $pv_filter_terms ) > 0 ) : ?>
				<div class="pv-aside-section">
					<h3 class="pv-aside-heading"><svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg> <?php echo esc_html( $pv_aside_tp_label ); ?></h3>
					<div class="pv-aside-pills">
						<?php foreach ( $pv_filter_terms as $_tc ) : $_tc_link = get_term_link( $_tc ); if ( is_wp_error( $_tc_link ) ) continue; ?>
							<a href="<?php echo esc_url( $_tc_link ); ?>" class="pv-aside-pill"><?php echo esc_html( $_tc->name ); ?> <span class="pv-aside-pill__count"><?php echo esc_html( $_tc->count ); ?></span></a>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>

				<?php if ( $pv_aside_tg_on && ! empty( $pv_aside_tags ) ) : ?>
				<div class="pv-aside-section">
					<h3 class="pv-aside-heading"><svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58s1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41s-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/></svg> <?php echo esc_html( $pv_aside_tg_label ); ?></h3>
					<div class="pv-aside-pills pv-aside-pills--tags">
						<?php foreach ( $pv_aside_tags as $_tag ) : $_tag_link = get_term_link( $_tag ); if ( is_wp_error( $_tag_link ) ) continue; ?>
							<a href="<?php echo esc_url( $_tag_link ); ?>" class="pv-aside-pill pv-aside-pill--tag"><?php echo esc_html( $_tag->name ); ?></a>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>

			</aside>

		</div>
	</div>
</div>

<?php if ( $pv_is_preview ) : ?>
<script>
window.addEventListener('message', function(e) {
	if (!e.data || e.data.type !== 'pv-update') return;
	var d = e.data;
	if (d.accent) {
		document.querySelectorAll('[style*="--pv-accent"]').forEach(function(el) { el.style.setProperty('--pv-accent', d.accent); });
		document.documentElement.style.setProperty('--pv-accent', d.accent);
	}
	if (d.hero_title !== undefined) { var t = document.querySelector('[data-pv-hero-title]'); if (t) t.textContent = d.hero_title || t.textContent; }
	if (d.hero_subtitle !== undefined) { var s = document.querySelector('[data-pv-hero-sub]'); if (s) s.textContent = d.hero_subtitle || s.textContent; }
	if (d.hero_title_color !== undefined) { var tc = document.querySelector('[data-pv-hero-title]'); if (tc) tc.style.color = d.hero_title_color || ''; }
	if (d.hero_sub_color !== undefined) { var sc = document.querySelector('[data-pv-hero-sub]'); if (sc) sc.style.color = d.hero_sub_color || ''; }
});
</script>
<?php endif; ?>

<?php get_footer(); ?>
