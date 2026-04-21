<?php
/**
 * Injects the offcanvas drawer HTML into wp_footer (once per page, only when needed).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Offcanvas {

	public function register(): void {
		// Priority 1: drawer HTML must be in the DOM before wp_print_footer_scripts (priority 20) prints the JS.
		add_action( 'wp_footer', [ $this, 'maybe_render' ], 1 );
		add_action( 'wp_ajax_pv_playlist_page',        [ $this, 'ajax_playlist_page' ] );
		add_action( 'wp_ajax_nopriv_pv_playlist_page', [ $this, 'ajax_playlist_page' ] );
		add_action( 'wp_ajax_pv_search_videos',        [ $this, 'ajax_search_videos' ] );
		add_action( 'wp_ajax_nopriv_pv_search_videos', [ $this, 'ajax_search_videos' ] );
	}

	public function ajax_playlist_page(): void {
		$page     = max( 1, (int) ( $_GET['page'] ?? 1 ) ); // phpcs:ignore
		$per_page = 24;

		$posts = get_posts( [
			'post_type'      => 'pv_youtube',
			'posts_per_page' => $per_page,
			'offset'         => ( $page - 1 ) * $per_page,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		$total     = (int) ( wp_count_posts( 'pv_youtube' )->publish ?? 0 );
		$max_pages = max( 1, (int) ceil( $total / $per_page ) );

		$items = [];
		foreach ( $posts as $p ) {
			$yt = get_post_meta( $p->ID, '_pv_youtube_id', true );
			if ( ! $yt ) continue;
			$items[] = [
				'id'        => $p->ID,
				'youtubeId' => $yt,
				'embedUrl'  => 'https://www.youtube.com/embed/' . $yt . '?rel=0&modestbranding=1',
				'title'     => $p->post_title,
				'desc'      => wp_trim_words( $p->post_excerpt ?: $p->post_content, 20 ),
				'accent'    => pv_resolve_accent_color( $p->ID ),
				'thumb'     => get_the_post_thumbnail_url( $p->ID, 'medium' ) ?: '',
				'duration'  => get_post_meta( $p->ID, '_pv_duration', true ) ?: '',
			];
		}

		wp_send_json_success( [
			'items'    => $items,
			'page'     => $page,
			'maxPages' => $max_pages,
			'total'    => $total,
		] );
	}

	public function ajax_search_videos(): void {
		$q_str = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ); // phpcs:ignore
		if ( strlen( $q_str ) < 2 ) {
			wp_send_json_success( [ 'html' => '', 'count' => 0, 'query' => $q_str ] );
			return;
		}

		$settings     = get_option( 'pv_settings', [] );
		$display_mode = $settings['display_mode']       ?? 'offcanvas';
		$show_excerpt = isset( $settings['cards_show_excerpt'] )  ? (bool) $settings['cards_show_excerpt']  : true;
		$show_cat     = isset( $settings['cards_show_category'] ) ? (bool) $settings['cards_show_category'] : true;

		$wp_q = new WP_Query( [
			'post_type'      => 'pv_youtube',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			's'              => $q_str,
		] );

		$html = '';

		if ( $wp_q->have_posts() ) {
			$cards = '';
			while ( $wp_q->have_posts() ) {
				$wp_q->the_post();
				$post_id    = get_the_ID();
				$youtube_id = get_post_meta( $post_id, '_pv_youtube_id', true );
				if ( ! $youtube_id && 'offcanvas' === $display_mode ) continue;
				$accent    = pv_resolve_accent_color( $post_id );
				$embed_url = $youtube_id ? 'https://www.youtube.com/embed/' . $youtube_id . '?rel=0&modestbranding=1' : '';
				$duration  = get_post_meta( $post_id, '_pv_duration', true );
				$thumb_url = get_the_post_thumbnail_url( $post_id, 'medium' ) ?: '';
				$excerpt   = wp_trim_words( get_the_excerpt(), 30 );
				$cats      = get_the_terms( $post_id, 'pv_category' );
				$cat       = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0] : null;
				$cat_name  = $cat ? $cat->name : '';
				$cat_slug  = $cat ? $cat->slug : '';

				$bg_style = $thumb_url
					? 'background-image:linear-gradient(to top right,rgba(0,0,0,.75),rgba(0,0,0,.05)),url(' . esc_url( $thumb_url ) . ');'
					: '';

				$cards .= '<div class="pv-card ' . ( $thumb_url ? '' : 'pv-card--no-thumb' ) . '"'
					. ' data-category="' . esc_attr( $cat_slug ) . '"'
					. ' style="--pv-accent:' . esc_attr( $accent ) . ';' . $bg_style . '">';
				if ( $show_cat && $cat_name ) {
					$cards .= '<span class="pv-card__cat">' . esc_html( $cat_name ) . '</span>';
				}
				if ( $duration ) {
					$cards .= '<span class="pv-card__duration">' . esc_html( $duration ) . '</span>';
				}
				$cards .= '<div class="pv-card__footer"><div class="pv-card__title">' . esc_html( get_the_title() ) . '</div></div>';
				$cards .= '<div class="pv-card__hover-content">';
				if ( $excerpt && $show_excerpt ) {
					$cards .= '<p class="pv-card__hover-excerpt">' . esc_html( $excerpt ) . '</p>';
					$cards .= '<a href="' . esc_url( get_permalink() ) . '" class="pv-card__read-more">Read more &rarr;</a>';
				}
				if ( 'offcanvas' === $display_mode && $youtube_id ) {
					$cards .= '<button class="pv-trigger pv-card__watch-btn"'
						. ' data-youtube-id="' . esc_attr( $youtube_id ) . '"'
						. ' data-embed-url="' . esc_attr( $embed_url ) . '"'
						. ' data-title="' . esc_attr( get_the_title() ) . '"'
						. ' data-description="' . esc_attr( $excerpt ) . '"'
						. ' data-accent="' . esc_attr( $accent ) . '"'
						. ' aria-label="' . esc_attr( sprintf( __( 'Watch %s', 'pv-youtube-importer' ), get_the_title() ) ) . '">'
						. '<svg class="pv-play-icon" width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg> '
						. esc_html__( 'Watch Now', 'pv-youtube-importer' )
						. '</button>';
				} else {
					$cards .= '<a href="' . esc_url( get_permalink() ) . '" class="pv-card__watch-btn">'
						. '<svg class="pv-play-icon" width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg> '
						. esc_html__( 'Watch Video', 'pv-youtube-importer' )
						. '</a>';
				}
				$cards .= '</div></div>';
			}
			wp_reset_postdata();

			if ( $cards ) {
				$html = '<div class="pv-grid" style="--pv-cols:3;">' . $cards . '</div>';
			}
		}

		wp_send_json_success( [
			'html'  => $html,
			'count' => $wp_q->found_posts,
			'query' => $q_str,
		] );
	}

	public function maybe_render(): void {
		if ( ! did_action( 'pv_player_enqueued' ) ) return;

		$template = $this->locate_template( 'offcanvas/video-offcanvas.php' );
		if ( $template ) {
			include $template;
		}
	}

	public static function locate_template( string $name ): string {
		$theme_file  = get_stylesheet_directory() . '/pv-youtube-importer/' . $name;
		$plugin_file = PV_PLUGIN_DIR . 'templates/' . $name;
		return file_exists( $theme_file ) ? $theme_file : $plugin_file;
	}
}
