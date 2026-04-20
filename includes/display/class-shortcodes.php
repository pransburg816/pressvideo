<?php
/**
 * Registers all [pv_*] shortcodes.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Shortcodes {

	public function register(): void {
		add_shortcode( 'pv_video',         [ $this, 'render_single_video' ] );
		add_shortcode( 'pv_video_grid',    [ $this, 'render_grid' ] );
		add_shortcode( 'pv_video_latest',  [ $this, 'render_latest' ] );
		add_shortcode( 'pv_launcher',      [ $this, 'render_launcher' ] );
	}

	/** [pv_video id="POST_ID" label="Watch Video"] */
	public function render_single_video( array $atts ): string {
		$atts = shortcode_atts( [
			'id'      => 0,
			'label'   => __( 'Watch Video', 'pv-youtube-importer' ),
			'color'   => '',
			'class'   => '',
		], $atts, 'pv_video' );

		$post_id = absint( $atts['id'] );
		if ( ! $post_id ) return '';

		$post = get_post( $post_id );
		if ( ! $post || 'pv_youtube' !== $post->post_type ) return '';

		do_action( 'pv_player_enqueued' );

		$youtube_id = get_post_meta( $post_id, '_pv_youtube_id', true );
		if ( ! $youtube_id ) return '';

		$accent    = $atts['color'] ? sanitize_hex_color( $atts['color'] ) : pv_resolve_accent_color( $post_id );
		$embed_url = 'https://www.youtube.com/embed/' . $youtube_id;
		$desc      = wp_trim_words( $post->post_content, 20 );

		return sprintf(
			'<button class="pv-trigger pv-btn %s"
			         style="--pv-accent:%s;border-color:%s;"
			         data-video-id="%s"
			         data-youtube-id="%s"
			         data-embed-url="%s"
			         data-title="%s"
			         data-description="%s"
			         data-accent="%s">%s</button>',
			esc_attr( $atts['class'] ),
			esc_attr( $accent ),
			esc_attr( $accent ),
			esc_attr( $post_id ),
			esc_attr( $youtube_id ),
			esc_attr( $embed_url ),
			esc_attr( $post->post_title ),
			esc_attr( $desc ),
			esc_attr( $accent ),
			esc_html( $atts['label'] )
		);
	}

	/** [pv_video_grid count="6" tag="" category="" display="offcanvas" columns="3" orderby="date" order="DESC"] */
	public function render_grid( array $atts ): string {
		$settings        = get_option( 'pv_settings', [] );
		$default_display = $settings['display_mode'] ?? 'offcanvas';

		$atts = shortcode_atts( [
			'count'    => 6,
			'tag'      => '',
			'category' => '',
			'display'  => $default_display,
			'columns'  => 3,
			'color'    => '',
			'orderby'  => 'date',
			'order'    => 'DESC',
		], $atts, 'pv_video_grid' );

		$videos = $this->query_videos( $atts );
		if ( empty( $videos ) ) {
			return '<p class="pv-no-videos">' . esc_html__( 'No videos found.', 'pv-youtube-importer' ) . '</p>';
		}

		do_action( 'pv_player_enqueued' );

		$renderer = PV_Renderer_Factory::make( sanitize_key( $atts['display'] ) );
		return $renderer->render( $videos, $atts );
	}

	/**
	 * [pv_launcher label="Watch Videos" count="50" style="inline|fixed"]
	 *
	 * Standalone button that opens the offcanvas drawer with the full video
	 * playlist. Place it anywhere — hero sections, headers, CTAs, widgets.
	 * Use style="fixed" for a floating bottom-right launcher button.
	 */
	public function render_launcher( array $atts ): string {
		$atts = shortcode_atts( [
			'label' => __( 'Watch Videos', 'pv-youtube-importer' ),
			'count' => 50,
			'style' => 'inline',
			'class' => '',
		], $atts, 'pv_launcher' );

		$videos = $this->query_videos( [
			'count'    => absint( $atts['count'] ) ?: 50,
			'orderby'  => 'date',
			'order'    => 'DESC',
			'tag'      => '',
			'category' => '',
		] );

		if ( empty( $videos ) ) return '';

		do_action( 'pv_player_enqueued' );

		// Build playlist JSON — same format as PV_Video_Grid.
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
			];
		}

		if ( empty( $playlist ) ) return '';

		$first         = $playlist[0];
		$accent        = $first['accent'];
		$playlist_json = wp_json_encode( $playlist );
		$extra_class   = ( 'fixed' === $atts['style'] ? ' pv-launcher--fixed' : '' )
			. ( $atts['class'] ? ' ' . $atts['class'] : '' );

		return sprintf(
			'<button class="pv-trigger pv-launcher%s"
			         style="--pv-accent:%s;"
			         data-video-id="%s"
			         data-youtube-id="%s"
			         data-embed-url="%s"
			         data-title="%s"
			         data-description="%s"
			         data-accent="%s"
			         data-playlist="%s"
			         aria-label="%s">&#9654; %s</button>',
			esc_attr( $extra_class ),
			esc_attr( $accent ),
			esc_attr( $first['id'] ),
			esc_attr( $first['youtubeId'] ),
			esc_attr( $first['embedUrl'] ),
			esc_attr( $first['title'] ),
			esc_attr( $first['desc'] ),
			esc_attr( $accent ),
			esc_attr( $playlist_json ),
			esc_attr( $atts['label'] ),
			esc_html( $atts['label'] )
		);
	}

	/** [pv_video_latest count="3"] */
	public function render_latest( array $atts ): string {
		$settings        = get_option( 'pv_settings', [] );
		$default_display = $settings['display_mode'] ?? 'offcanvas';
		$atts            = shortcode_atts( [ 'count' => 3, 'columns' => 3, 'display' => $default_display ], $atts, 'pv_video_latest' );
		$atts['orderby'] = 'date';
		$atts['order']   = 'DESC';
		return $this->render_grid( $atts );
	}

	/** Run WP_Query based on shortcode args. Returns array of WP_Post objects. */
	private function query_videos( array $atts ): array {
		$query_args = [
			'post_type'      => 'pv_youtube',
			'post_status'    => 'publish',
			'posts_per_page' => absint( $atts['count'] ) ?: 6,
			'orderby'        => in_array( $atts['orderby'], [ 'date', 'title', 'meta_value_num' ], true )
				? $atts['orderby'] : 'date',
			'order'          => strtoupper( $atts['order'] ) === 'ASC' ? 'ASC' : 'DESC',
		];

		$tax_query = [];

		if ( ! empty( $atts['tag'] ) ) {
			$tax_query[] = [
				'taxonomy' => 'pv_tag',
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_key', explode( ',', $atts['tag'] ) ),
			];
		}

		if ( ! empty( $atts['category'] ) ) {
			$tax_query[] = [
				'taxonomy' => 'pv_category',
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_key', explode( ',', $atts['category'] ) ),
			];
		}

		if ( ! empty( $tax_query ) ) {
			$query_args['tax_query'] = $tax_query;
		}

		$query = new WP_Query( $query_args );
		return $query->posts;
	}
}
