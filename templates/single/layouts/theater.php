<?php
/**
 * Watch Page Layout: Theater
 * Dark full-bleed stage with centered video. Content section below.
 *
 * Available vars: $post, $pv_youtube_id, $pv_accent, $pv_embed_url,
 * $pv_watch_url, $pv_duration, $pv_view_count, $pv_tags, $pv_categories
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="pv-watch-page pv-watch-theater" style="--pv-accent:<?php echo esc_attr( $pv_accent ); ?>;">

	<!-- Dark stage — full-bleed -->
	<div class="pv-theater-stage">
		<div class="pv-theater-inner">
			<?php if ( $pv_embed_url ) : ?>
				<div class="pv-watch-embed-wrap">
					<iframe
						src="<?php echo esc_url( $pv_embed_url ); ?>"
						title="<?php echo esc_attr( $post->post_title ); ?>"
						frameborder="0"
						allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
						allowfullscreen
						loading="lazy">
					</iframe>
				</div>
			<?php else : ?>
				<div class="pv-watch-no-video">
					<p><?php esc_html_e( 'No video available.', 'pv-youtube-importer' ); ?></p>
				</div>
			<?php endif; ?>
		</div>

		<!-- Title overlay at bottom of stage -->
		<div class="pv-theater-overlay">
			<div class="pv-theater-overlay-inner">
				<?php if ( ! empty( $pv_categories ) && ! is_wp_error( $pv_categories ) ) : ?>
					<?php foreach ( $pv_categories as $cat ) : ?>
						<a href="<?php echo esc_url( get_term_link( $cat ) ); ?>"
						   class="pv-badge"
						   style="background:<?php echo esc_attr( get_term_meta( $cat->term_id, 'pv_color', true ) ?: $pv_accent ); ?>;">
							<?php echo esc_html( $cat->name ); ?>
						</a>
					<?php endforeach; ?>
				<?php else : ?>
					<span class="pv-badge" style="background:<?php echo esc_attr( $pv_accent ); ?>;">Video</span>
				<?php endif; ?>
				<h1 class="pv-watch-title"><?php echo esc_html( $post->post_title ); ?></h1>
				<div class="pv-watch-meta-row">
					<?php if ( $pv_duration ) : ?>
						<span class="pv-meta-item">
							<svg aria-hidden="true" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
							<?php echo esc_html( $pv_duration ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $pv_view_count ) : ?>
						<span class="pv-meta-item">
							<svg aria-hidden="true" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
							<?php echo esc_html( number_format_i18n( $pv_view_count ) ); ?> <?php esc_html_e( 'views', 'pv-youtube-importer' ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $pv_watch_url ) : ?>
						<a href="<?php echo esc_url( $pv_watch_url ); ?>" class="pv-meta-item pv-yt-link" target="_blank" rel="noopener">
							<?php esc_html_e( 'Watch on YouTube', 'pv-youtube-importer' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<!-- Light content section below stage -->
	<?php if ( ! empty( $post->post_content ) || ( ! empty( $pv_tags ) && ! is_wp_error( $pv_tags ) ) ) : ?>
	<div class="pv-watch-content">

		<?php if ( ! empty( $post->post_content ) ) : ?>
			<div class="pv-watch-description">
				<?php the_content(); ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $pv_tags ) && ! is_wp_error( $pv_tags ) ) : ?>
			<div class="pv-watch-tags">
				<?php foreach ( $pv_tags as $tag ) : ?>
					<a href="<?php echo esc_url( get_term_link( $tag ) ); ?>" class="pv-tag-pill">
						#<?php echo esc_html( $tag->name ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

	</div>
	<?php endif; ?>

</div>
