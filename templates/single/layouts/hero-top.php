<?php
/**
 * Watch Page Layout: Hero Top
 * Full-width video at top, title + meta + description below.
 *
 * Available vars (set in single-video.php):
 * $post, $pv_youtube_id, $pv_accent, $pv_embed_url, $pv_watch_url,
 * $pv_duration, $pv_view_count, $pv_tags, $pv_categories, $pv_imported_at
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="pv-watch-page pv-watch-hero-top" style="--pv-accent:<?php echo esc_attr( $pv_accent ); ?>;">

	<?php if ( $pv_embed_url ) : ?>
	<div class="pv-watch-stage">
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
	</div>
	<?php endif; ?>

	<div class="pv-watch-content">

		<div class="pv-watch-header">
			<?php if ( ! empty( $pv_categories ) && ! is_wp_error( $pv_categories ) ) : ?>
				<div class="pv-watch-cats">
					<?php foreach ( $pv_categories as $cat ) : ?>
						<a href="<?php echo esc_url( get_term_link( $cat ) ); ?>"
						   class="pv-badge"
						   style="background:<?php echo esc_attr( get_term_meta( $cat->term_id, 'pv_color', true ) ?: $pv_accent ); ?>;">
							<?php echo esc_html( $cat->name ); ?>
						</a>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<span class="pv-badge" style="background:<?php echo esc_attr( $pv_accent ); ?>;">Video</span>
			<?php endif; ?>

			<h1 class="pv-watch-title"><?php echo esc_html( $post->post_title ); ?></h1>

			<div class="pv-watch-meta-row">
				<?php if ( $pv_duration ) : ?>
					<span class="pv-meta-item">
						<svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
						<?php echo esc_html( $pv_duration ); ?>
					</span>
				<?php endif; ?>
				<?php if ( $pv_view_count ) : ?>
					<span class="pv-meta-item">
						<svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
						<?php echo esc_html( number_format_i18n( $pv_view_count ) ); ?> <?php esc_html_e( 'views', 'pv-youtube-importer' ); ?>
					</span>
				<?php endif; ?>
				<?php if ( $pv_imported_at ) : ?>
					<span class="pv-meta-item">
						<svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
						<?php echo esc_html( date_i18n( get_option( 'date_format' ), $pv_imported_at ) ); ?>
					</span>
				<?php endif; ?>
				<?php if ( $pv_watch_url ) : ?>
					<a href="<?php echo esc_url( $pv_watch_url ); ?>" class="pv-meta-item pv-yt-link" target="_blank" rel="noopener">
						<svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M23.5 6.2s-.3-2-1.2-2.8c-1.1-1.2-2.4-1.2-3-1.3C16.6 2 12 2 12 2s-4.6 0-7.3.1c-.6.1-1.9.1-3 1.3C.8 4.2.5 6.2.5 6.2S.2 8.5.2 10.8v2.1c0 2.3.3 4.6.3 4.6s.3 2 1.2 2.8c1.1 1.2 2.6 1.1 3.3 1.2C7.2 21.7 12 21.7 12 21.7s4.6 0 7.3-.2c.6-.1 1.9-.1 3-1.2.9-.8 1.2-2.8 1.2-2.8s.3-2.3.3-4.6v-2.1c0-2.3-.3-4.6-.3-4.6zm-13.9 9.4V8.4l8.1 3.6-8.1 3.6z"/></svg>
						<?php esc_html_e( 'Watch on YouTube', 'pv-youtube-importer' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

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

</div>
