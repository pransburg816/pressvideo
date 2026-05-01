<?php
/**
 * Registers all taxonomies for the pv_video CPT.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Video_Taxonomies {

	public function register(): void {
		add_action( 'init', [ $this, 'register_taxonomies' ] );
		add_action( 'init', [ $this, 'seed_type_terms' ], 20 );

		// Noindex all PV taxonomy archive pages — they are filtered views of the
		// main archive (thin/duplicate content). The main /pv-videos/ archive
		// stays indexed and captures all SEO value.
		add_filter( 'wp_robots', [ $this, 'noindex_tax_archives' ] );

		// Color picker fields on taxonomy add/edit forms.
		foreach ( [ 'pv_tag', 'pv_category' ] as $tax ) {
			add_action( "{$tax}_add_form_fields",  [ $this, 'add_color_field' ] );
			add_action( "{$tax}_edit_form_fields", [ $this, 'edit_color_field' ], 10, 2 );
			add_action( "created_{$tax}",          [ $this, 'save_color_meta' ] );
			add_action( "edited_{$tax}",           [ $this, 'save_color_meta' ] );

			add_filter( "manage_edit-{$tax}_columns",          [ $this, 'add_color_column' ] );
			add_filter( "manage_{$tax}_custom_column",         [ $this, 'render_color_column' ], 10, 3 );
		}
	}

	public function noindex_tax_archives( array $robots ): array {
		if ( is_tax( [ 'pv_tag', 'pv_category', 'pv_series', 'pv_type' ] ) ) {
			$robots['noindex'] = true;
		}
		return $robots;
	}

	public function register_taxonomies(): void {
		// Video Category — hierarchical.
		register_taxonomy( 'pv_category', 'pv_youtube', [
			'labels'            => $this->labels( 'Video Category', 'Video Categories' ),
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'rewrite'           => [ 'slug' => 'video-category' ],
		] );

		// Video Tag — flat.
		register_taxonomy( 'pv_tag', 'pv_youtube', [
			'labels'            => $this->labels( 'Video Tag', 'Video Tags' ),
			'hierarchical'      => false,
			'public'            => true,
			'show_in_rest'      => true,
			'rewrite'           => [ 'slug' => 'video-tag' ],
		] );

		// Video Series — hierarchical.
		register_taxonomy( 'pv_series', 'pv_youtube', [
			'labels'            => $this->labels( 'Video Series', 'Video Series' ),
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'rewrite'           => [ 'slug' => 'video-series' ],
		] );

		// Video Type — flat.
		register_taxonomy( 'pv_type', 'pv_youtube', [
			'labels'            => $this->labels( 'Video Type', 'Video Types' ),
			'hierarchical'      => false,
			'public'            => true,
			'show_in_rest'      => true,
			'rewrite'           => [ 'slug' => 'video-type' ],
		] );
	}

	private function labels( string $singular, string $plural ): array {
		return [
			'name'          => __( $plural,   'pv-youtube-importer' ),
			'singular_name' => __( $singular, 'pv-youtube-importer' ),
			'search_items'  => sprintf( __( 'Search %s', 'pv-youtube-importer' ), $plural ),
			'all_items'     => sprintf( __( 'All %s', 'pv-youtube-importer' ), $plural ),
			'edit_item'     => sprintf( __( 'Edit %s', 'pv-youtube-importer' ), $singular ),
			'update_item'   => sprintf( __( 'Update %s', 'pv-youtube-importer' ), $singular ),
			'add_new_item'  => sprintf( __( 'Add New %s', 'pv-youtube-importer' ), $singular ),
			'new_item_name' => sprintf( __( 'New %s Name', 'pv-youtube-importer' ), $singular ),
			'menu_name'     => __( $plural, 'pv-youtube-importer' ),
		];
	}

	public function add_color_field( string $taxonomy ): void {
		?>
		<div class="form-field">
			<label for="pv_color"><?php esc_html_e( 'Accent Color', 'pv-youtube-importer' ); ?></label>
			<input type="text" name="pv_color" id="pv_color" value="#4f46e5" class="pv-color-picker" />
			<p class="description"><?php esc_html_e( 'Accent color inherited by all videos in this term.', 'pv-youtube-importer' ); ?></p>
		</div>
		<?php
	}

	public function edit_color_field( WP_Term $term, string $taxonomy ): void {
		$color = get_term_meta( $term->term_id, 'pv_color', true ) ?: '#4f46e5';
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="pv_color"><?php esc_html_e( 'Accent Color', 'pv-youtube-importer' ); ?></label>
			</th>
			<td>
				<input type="text" name="pv_color" id="pv_color" value="<?php echo esc_attr( $color ); ?>" class="pv-color-picker" />
				<p class="description"><?php esc_html_e( 'Accent color inherited by all videos in this term.', 'pv-youtube-importer' ); ?></p>
			</td>
		</tr>
		<?php
	}

	public function save_color_meta( int $term_id ): void {
		if ( ! isset( $_POST['pv_color'] ) ) return;
		$color = sanitize_hex_color( wp_unslash( $_POST['pv_color'] ) );
		if ( $color ) {
			update_term_meta( $term_id, 'pv_color', $color );
		}
	}

	public function add_color_column( array $columns ): array {
		return array_merge( [ 'pv_color' => __( 'Color', 'pv-youtube-importer' ) ], $columns );
	}

	public function seed_type_terms(): void {
		if ( get_option( 'pv_type_terms_seeded' ) ) return;

		$terms = [
			'Music Video',
			'Single',
			'Live Performance',
			'Album Track',
			'Podcast',
			'Tutorial',
			'Short Film',
		];

		foreach ( $terms as $term ) {
			if ( ! term_exists( $term, 'pv_type' ) ) {
				wp_insert_term( $term, 'pv_type' );
			}
		}

		update_option( 'pv_type_terms_seeded', true );
	}

	public function render_color_column( string $out, string $column, int $term_id ): string {
		if ( 'pv_color' !== $column ) return $out;
		$color = get_term_meta( $term_id, 'pv_color', true ) ?: '#4f46e5';
		return sprintf(
			'<span style="display:inline-block;width:20px;height:20px;border-radius:50%%;background:%s;border:2px solid #ccc;vertical-align:middle;" title="%s"></span>',
			esc_attr( $color ),
			esc_attr( $color )
		);
	}
}
