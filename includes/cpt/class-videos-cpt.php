<?php
/**
 * Registers the pv_video custom post type.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Videos_CPT {

	public function register(): void {
		add_action( 'init', [ $this, 'register_cpt' ] );
		add_filter( 'manage_pv_youtube_posts_columns',       [ $this, 'add_color_column' ] );
		add_action( 'manage_pv_youtube_posts_custom_column', [ $this, 'render_color_column' ], 10, 2 );
		add_action( 'pre_get_posts', [ $this, 'fix_archive_pagination' ], 99 );
	}

	public function register_cpt(): void {
		$labels = [
			'name'                  => __( 'PressVideo Videos', 'pv-youtube-importer' ),
			'singular_name'         => __( 'PressVideo Video', 'pv-youtube-importer' ),
			'add_new'               => __( 'Add New', 'pv-youtube-importer' ),
			'add_new_item'          => __( 'Add New Video', 'pv-youtube-importer' ),
			'edit_item'             => __( 'Edit Video', 'pv-youtube-importer' ),
			'new_item'              => __( 'New Video', 'pv-youtube-importer' ),
			'view_item'             => __( 'View Video', 'pv-youtube-importer' ),
			'search_items'          => __( 'Search Videos', 'pv-youtube-importer' ),
			'not_found'             => __( 'No videos found', 'pv-youtube-importer' ),
			'not_found_in_trash'    => __( 'No videos in trash', 'pv-youtube-importer' ),
			'all_items'             => __( 'All Videos', 'pv-youtube-importer' ),
			'menu_name'             => __( 'PressVideo', 'pv-youtube-importer' ),
			'name_admin_bar'        => __( 'PressVideo Video', 'pv-youtube-importer' ),
		];

		register_post_type( 'pv_youtube', [
			'labels'              => $labels,
			'public'              => true,
			'has_archive'         => true,
			'supports'            => [ 'title', 'editor', 'thumbnail' ],
			'menu_icon'           => 'dashicons-video-alt3',
			'rewrite'             => [ 'slug' => 'pv-videos' ],
			'show_in_rest'        => true,
			'menu_position'       => 20,
			'capability_type'     => 'post',
		] );
	}

	public function fix_archive_pagination( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) return;
		if ( ! $query->is_post_type_archive( 'pv_youtube' ) ) return;
		// IAC archive-pagination-filtering.php sets offset=0 on all CPT archives because
		// WP_Query::get() returns '' for unset vars and null !== '' is true.
		// An explicit offset=0 causes WP to ignore the paged calculation (always LIMIT 0,N).
		unset( $query->query_vars['offset'] );
	}

	public function add_color_column( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['pv_accent'] = __( 'Color', 'pv-youtube-importer' );
			}
		}
		return $new;
	}

	public function render_color_column( string $column, int $post_id ): void {
		if ( 'pv_accent' !== $column ) return;
		$color = pv_resolve_accent_color( $post_id );
		printf(
			'<span style="display:inline-block;width:22px;height:22px;border-radius:50%%;background:%s;border:2px solid #ccc;" title="%s"></span>',
			esc_attr( $color ),
			esc_attr( $color )
		);
	}
}
