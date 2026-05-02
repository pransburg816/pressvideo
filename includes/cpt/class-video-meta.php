<?php
/**
 * Meta boxes for the pv_video CPT.
 * Also defines pv_resolve_accent_color() — used throughout the plugin.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Video_Meta {

	public function register(): void {
		add_action( 'add_meta_boxes',                         [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post_pv_youtube',                   [ $this, 'save_meta' ] );
		add_filter( 'manage_pv_youtube_posts_columns',        [ $this, 'add_music_column' ] );
		add_action( 'manage_pv_youtube_posts_custom_column',  [ $this, 'render_music_column' ], 10, 2 );
	}

	public function add_music_column( array $columns ): array {
		// Insert after the title column.
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['pv_music'] = '<span class="dashicons dashicons-playlist-audio" title="' . esc_attr__( 'Music Mode', 'pv-youtube-importer' ) . '"></span>';
			}
		}
		return $new;
	}

	public function render_music_column( string $column, int $post_id ): void {
		if ( 'pv_music' !== $column ) return;
		if ( pv_is_music_video( $post_id ) ) {
			echo '<span class="dashicons dashicons-yes-alt" style="color:#4f46e5;" title="' . esc_attr__( 'Music mode enabled', 'pv-youtube-importer' ) . '"></span>';
		} else {
			echo '<span class="dashicons dashicons-minus" style="color:#ccc;"></span>';
		}
	}

	public function add_meta_boxes(): void {
		add_meta_box(
			'pv_video_details',
			__( 'Video Details', 'pv-youtube-importer' ),
			[ $this, 'render_meta_box' ],
			'pv_youtube',
			'normal',
			'high'
		);
		add_meta_box(
			'pv_tools_box',
			__( 'PressVideo Tools', 'pv-youtube-importer' ),
			[ $this, 'render_tools_box' ],
			'pv_youtube',
			'normal',
			'default'
		);
	}

	public function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'pv_save_video_meta', 'pv_video_meta_nonce' );

		$youtube_url  = get_post_meta( $post->ID, '_pv_youtube_url',  true );
		$youtube_id   = get_post_meta( $post->ID, '_pv_youtube_id',   true );
		$accent_color = get_post_meta( $post->ID, '_pv_accent_color', true ) ?: '';
		$duration     = get_post_meta( $post->ID, '_pv_duration',     true );
		$view_count   = get_post_meta( $post->ID, '_pv_view_count',   true );
		$channel_id   = get_post_meta( $post->ID, '_pv_channel_id',   true );
		$imported_at  = get_post_meta( $post->ID, '_pv_imported_at',  true );
		$watch_layout = get_post_meta( $post->ID, '_pv_watch_layout', true ) ?: 'inherit';
		$is_music     = pv_is_music_video( $post->ID ) ? '1' : '';
		$artist       = get_post_meta( $post->ID, '_pv_artist',       true );
		$album        = get_post_meta( $post->ID, '_pv_album',        true );
		$track_num    = absint( get_post_meta( $post->ID, '_pv_track_number', true ) );
		?>
		<table class="form-table pv-meta-table">
			<tr>
				<th><label for="pv_youtube_url"><?php esc_html_e( 'YouTube URL', 'pv-youtube-importer' ); ?></label></th>
				<td>
					<input type="url" id="pv_youtube_url" name="pv_youtube_url"
					       value="<?php echo esc_attr( $youtube_url ); ?>"
					       class="widefat" placeholder="https://www.youtube.com/watch?v=..." />
					<?php if ( $youtube_id ) : ?>
						<p class="description"><?php echo esc_html( sprintf( __( 'Video ID: %s', 'pv-youtube-importer' ), $youtube_id ) ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="pv_accent_color"><?php esc_html_e( 'Accent Color', 'pv-youtube-importer' ); ?></label></th>
				<td>
					<input type="text" id="pv_accent_color" name="pv_accent_color"
					       value="<?php echo esc_attr( $accent_color ); ?>"
					       class="pv-color-picker" />
					<p class="description"><?php esc_html_e( 'Overrides tag/category/global color for this video.', 'pv-youtube-importer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="pv_duration"><?php esc_html_e( 'Duration', 'pv-youtube-importer' ); ?></label></th>
				<td>
					<input type="text" id="pv_duration" name="pv_duration"
					       value="<?php echo esc_attr( $duration ); ?>"
					       class="regular-text" placeholder="4:32" />
				</td>
			</tr>
			<tr>
				<th><label for="pv_view_count"><?php esc_html_e( 'View Count', 'pv-youtube-importer' ); ?></label></th>
				<td>
					<input type="number" id="pv_view_count" name="pv_view_count"
					       value="<?php echo esc_attr( $view_count ); ?>"
					       class="regular-text" min="0" />
				</td>
			</tr>
			<tr>
				<!-- Music fields -->
			<tr>
				<th><label for="pv_is_music"><?php esc_html_e( 'Music Content', 'pv-youtube-importer' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" id="pv_is_music" name="pv_is_music" value="1" <?php checked( $is_music, '1' ); ?> />
						<?php esc_html_e( 'This is a music video / audio track', 'pv-youtube-importer' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Enables the music panel skin and persistent mini-player.', 'pv-youtube-importer' ); ?></p>
				</td>
			</tr>
			<tr class="pv-music-field"<?php echo $is_music ? '' : ' style="display:none"'; ?>>
				<th><label for="pv_artist"><?php esc_html_e( 'Artist', 'pv-youtube-importer' ); ?></label></th>
				<td>
					<input type="text" id="pv_artist" name="pv_artist"
					       value="<?php echo esc_attr( $artist ); ?>"
					       class="regular-text" placeholder="<?php esc_attr_e( 'Artist name', 'pv-youtube-importer' ); ?>" />
				</td>
			</tr>
			<tr class="pv-music-field"<?php echo $is_music ? '' : ' style="display:none"'; ?>>
				<th><label for="pv_album"><?php esc_html_e( 'Album / EP', 'pv-youtube-importer' ); ?></label></th>
				<td>
					<input type="text" id="pv_album" name="pv_album"
					       value="<?php echo esc_attr( $album ); ?>"
					       class="regular-text" placeholder="<?php esc_attr_e( 'Album or EP title', 'pv-youtube-importer' ); ?>" />
				</td>
			</tr>
			<tr class="pv-music-field"<?php echo $is_music ? '' : ' style="display:none"'; ?>>
				<th><label for="pv_track_number"><?php esc_html_e( 'Track #', 'pv-youtube-importer' ); ?></label></th>
				<td>
					<input type="number" id="pv_track_number" name="pv_track_number"
					       value="<?php echo esc_attr( $track_num ?: '' ); ?>"
					       class="small-text" min="1" max="999" />
				</td>
			</tr>
			<script>
			(function(){
				var cb = document.getElementById('pv_is_music');
				if (!cb) return;
				cb.addEventListener('change', function(){
					document.querySelectorAll('.pv-music-field').forEach(function(r){
						r.style.display = cb.checked ? '' : 'none';
					});
				});
			}());
			</script>
			<tr>
				<th style="vertical-align:top;padding-top:12px;">
					<?php esc_html_e( 'Watch Page Layout', 'pv-youtube-importer' ); ?>
				</th>
				<td>
					<div class="pv-visual-picker pv-visual-picker--meta">

						<!-- Inherit -->
						<label class="pv-pick-card <?php echo esc_attr( $watch_layout === 'inherit' ? 'is-selected' : '' ); ?>">
							<input type="radio" name="pv_watch_layout" value="inherit" <?php checked( $watch_layout, 'inherit' ); ?>>
							<span class="pv-pick-card__check"></span>
							<span class="pv-pick-card__preview"><?php echo pv_meta_svg_inherit(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span class="pv-pick-card__body">
								<span class="pv-pick-card__label"><?php esc_html_e( 'Inherit', 'pv-youtube-importer' ); ?></span>
								<span class="pv-pick-card__desc"><?php esc_html_e( 'Use global setting', 'pv-youtube-importer' ); ?></span>
							</span>
						</label>

						<!-- Hero Top -->
						<label class="pv-pick-card <?php echo esc_attr( $watch_layout === 'hero-top' ? 'is-selected' : '' ); ?>">
							<input type="radio" name="pv_watch_layout" value="hero-top" <?php checked( $watch_layout, 'hero-top' ); ?>>
							<span class="pv-pick-card__check"></span>
							<span class="pv-pick-card__preview"><?php echo pv_meta_svg_hero_top(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span class="pv-pick-card__body">
								<span class="pv-pick-card__label"><?php esc_html_e( 'Hero Top', 'pv-youtube-importer' ); ?></span>
								<span class="pv-pick-card__desc"><?php esc_html_e( 'Full-width video', 'pv-youtube-importer' ); ?></span>
							</span>
						</label>

						<!-- Hero Split -->
						<label class="pv-pick-card <?php echo esc_attr( $watch_layout === 'hero-split' ? 'is-selected' : '' ); ?>">
							<input type="radio" name="pv_watch_layout" value="hero-split" <?php checked( $watch_layout, 'hero-split' ); ?>>
							<span class="pv-pick-card__check"></span>
							<span class="pv-pick-card__preview"><?php echo pv_meta_svg_hero_split(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span class="pv-pick-card__body">
								<span class="pv-pick-card__label"><?php esc_html_e( 'Hero Split', 'pv-youtube-importer' ); ?></span>
								<span class="pv-pick-card__desc"><?php esc_html_e( 'Video + info side-by-side', 'pv-youtube-importer' ); ?></span>
							</span>
						</label>

						<!-- Theater -->
						<label class="pv-pick-card <?php echo esc_attr( $watch_layout === 'theater' ? 'is-selected' : '' ); ?>">
							<input type="radio" name="pv_watch_layout" value="theater" <?php checked( $watch_layout, 'theater' ); ?>>
							<span class="pv-pick-card__check"></span>
							<span class="pv-pick-card__preview"><?php echo pv_meta_svg_theater(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span class="pv-pick-card__body">
								<span class="pv-pick-card__label"><?php esc_html_e( 'Theater', 'pv-youtube-importer' ); ?></span>
								<span class="pv-pick-card__desc"><?php esc_html_e( 'Dark cinematic stage', 'pv-youtube-importer' ); ?></span>
							</span>
						</label>

					</div>
					<p class="description" style="margin-top:8px;">
						<?php esc_html_e( 'Overrides the global watch page layout for this video only.', 'pv-youtube-importer' ); ?>
					</p>
				</td>
			</tr>
			<?php if ( $channel_id || $imported_at ) : ?>
			<tr>
				<th><?php esc_html_e( 'Import Info', 'pv-youtube-importer' ); ?></th>
				<td>
					<?php if ( $channel_id ) : ?>
						<p><?php echo esc_html( sprintf( __( 'Channel: %s', 'pv-youtube-importer' ), $channel_id ) ); ?></p>
					<?php endif; ?>
					<?php if ( $imported_at ) : ?>
						<p><?php echo esc_html( sprintf( __( 'Imported: %s', 'pv-youtube-importer' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $imported_at ) ) ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	public function render_tools_box( WP_Post $post ): void {
		$watch_width = get_post_meta( $post->ID, '_pv_watch_width', true ) ?: 'full';
		$youtube_id  = get_post_meta( $post->ID, '_pv_youtube_id', true );

		$widths = [
			'full'   => [ 'label' => __( 'Full',   'pv-youtube-importer' ), 'desc' => __( 'Theme width', 'pv-youtube-importer' ) ],
			'wide'   => [ 'label' => __( 'Wide',   'pv-youtube-importer' ), 'desc' => '1400px' ],
			'medium' => [ 'label' => __( 'Medium', 'pv-youtube-importer' ), 'desc' => '1200px' ],
			'narrow' => [ 'label' => __( 'Narrow', 'pv-youtube-importer' ), 'desc' => '960px' ],
		];
		?>

		<div class="pv-metabox-section">
			<p class="pv-metabox-label"><?php esc_html_e( 'Watch Page Width', 'pv-youtube-importer' ); ?></p>
			<div class="pv-visual-picker pv-visual-picker--text">
				<?php foreach ( $widths as $val => $info ) : ?>
					<label class="pv-pick-card pv-pick-card--text <?php echo $watch_width === $val ? 'is-selected' : ''; ?>">
						<input type="radio" name="pv_watch_width" value="<?php echo esc_attr( $val ); ?>" <?php checked( $watch_width, $val ); ?>>
						<span class="pv-pick-card__check"></span>
						<span class="pv-pick-card__body">
							<span class="pv-pick-card__label"><?php echo esc_html( $info['label'] ); ?></span>
							<span class="pv-pick-card__desc"><?php echo esc_html( $info['desc'] ); ?></span>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
			<p class="description" style="margin-top:8px;font-size:0.72rem;">
				<?php esc_html_e( 'Max-width for this video\'s watch page. Saves on post update.', 'pv-youtube-importer' ); ?>
			</p>
		</div>

		<div class="pv-metabox-section">
			<p class="pv-metabox-label"><?php esc_html_e( 'Embed Shortcodes', 'pv-youtube-importer' ); ?></p>

			<?php if ( $youtube_id ) : ?>
				<div class="pv-sc-meta-item">
					<span class="pv-sc-meta-tag"><?php esc_html_e( 'This Video', 'pv-youtube-importer' ); ?></span>
					<div class="pv-sc-meta-row">
						<code class="pv-sc-code">[pv_video id="<?php echo esc_attr( $post->ID ); ?>"]</code>
						<button class="pv-copy-btn" data-copy='[pv_video id="<?php echo esc_attr( $post->ID ); ?>"]' type="button"><?php esc_html_e( 'Copy', 'pv-youtube-importer' ); ?></button>
					</div>
				</div>
			<?php endif; ?>

			<div class="pv-sc-meta-item">
				<span class="pv-sc-meta-tag"><?php esc_html_e( 'Launcher Button', 'pv-youtube-importer' ); ?></span>
				<div class="pv-sc-meta-row">
					<code class="pv-sc-code">[pv_launcher label="Watch Videos"]</code>
					<button class="pv-copy-btn" data-copy='[pv_launcher label="Watch Videos"]' type="button"><?php esc_html_e( 'Copy', 'pv-youtube-importer' ); ?></button>
				</div>
			</div>

			<div class="pv-sc-meta-item">
				<span class="pv-sc-meta-tag"><?php esc_html_e( 'Video Grid', 'pv-youtube-importer' ); ?></span>
				<div class="pv-sc-meta-row">
					<code class="pv-sc-code">[pv_video_grid]</code>
					<button class="pv-copy-btn" data-copy="[pv_video_grid]" type="button"><?php esc_html_e( 'Copy', 'pv-youtube-importer' ); ?></button>
				</div>
			</div>

			<div class="pv-sc-meta-item">
				<span class="pv-sc-meta-tag"><?php esc_html_e( 'Latest Videos', 'pv-youtube-importer' ); ?></span>
				<div class="pv-sc-meta-row">
					<code class="pv-sc-code">[pv_video_latest count="3"]</code>
					<button class="pv-copy-btn" data-copy='[pv_video_latest count="3"]' type="button"><?php esc_html_e( 'Copy', 'pv-youtube-importer' ); ?></button>
				</div>
			</div>

		</div>
		<?php
	}

	public function save_meta( int $post_id ): void {
		if ( ! isset( $_POST['pv_video_meta_nonce'] ) ) return;
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pv_video_meta_nonce'] ) ), 'pv_save_video_meta' ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		if ( isset( $_POST['pv_youtube_url'] ) ) {
			$url = esc_url_raw( wp_unslash( $_POST['pv_youtube_url'] ) );
			update_post_meta( $post_id, '_pv_youtube_url', $url );
			// Auto-extract video ID from URL.
			$vid_id = PV_YouTube_API::extract_video_id( $url );
			if ( $vid_id ) {
				update_post_meta( $post_id, '_pv_youtube_id', sanitize_text_field( $vid_id ) );
			}
		}

		if ( isset( $_POST['pv_accent_color'] ) ) {
			$color = sanitize_hex_color( wp_unslash( $_POST['pv_accent_color'] ) );
			update_post_meta( $post_id, '_pv_accent_color', $color ?? '' );
		}

		if ( isset( $_POST['pv_duration'] ) ) {
			update_post_meta( $post_id, '_pv_duration', sanitize_text_field( wp_unslash( $_POST['pv_duration'] ) ) );
		}

		if ( isset( $_POST['pv_view_count'] ) ) {
			update_post_meta( $post_id, '_pv_view_count', absint( $_POST['pv_view_count'] ) );
		}

		if ( isset( $_POST['pv_watch_layout'] ) ) {
			$layout = sanitize_key( wp_unslash( $_POST['pv_watch_layout'] ) );
			if ( in_array( $layout, [ 'inherit', 'hero-top', 'hero-split', 'theater' ], true ) ) {
				update_post_meta( $post_id, '_pv_watch_layout', $layout );
			}
		}

		if ( isset( $_POST['pv_watch_width'] ) ) {
			$width = sanitize_key( wp_unslash( $_POST['pv_watch_width'] ) );
			if ( in_array( $width, [ 'full', 'wide', 'medium', 'narrow' ], true ) ) {
				update_post_meta( $post_id, '_pv_watch_width', $width );
			}
		}

		// Music fields — checkbox OR Music tag implies music mode.
		$music_via_tag = has_term( 'music', 'pv_tag', $post_id );
		$is_music_val  = ( isset( $_POST['pv_is_music'] ) || $music_via_tag ) ? '1' : '';
		update_post_meta( $post_id, '_pv_is_music', $is_music_val );

		if ( isset( $_POST['pv_artist'] ) ) {
			update_post_meta( $post_id, '_pv_artist', sanitize_text_field( wp_unslash( $_POST['pv_artist'] ) ) );
		}

		if ( isset( $_POST['pv_album'] ) ) {
			update_post_meta( $post_id, '_pv_album', sanitize_text_field( wp_unslash( $_POST['pv_album'] ) ) );
		}

		if ( isset( $_POST['pv_track_number'] ) ) {
			update_post_meta( $post_id, '_pv_track_number', absint( $_POST['pv_track_number'] ) );
		}
	}
}

/**
 * Returns true if a video should use music mode.
 * True when the _pv_is_music meta is '1' OR the post has the "music" pv_tag.
 * Checking the tag means music mode works automatically without needing the
 * meta to be explicitly set — tag-based and cache-proof.
 */
function pv_is_music_video( int $post_id ): bool {
	if ( '1' === get_post_meta( $post_id, '_pv_is_music', true ) ) {
		return true;
	}
	return (bool) has_term( 'music', 'pv_tag', $post_id );
}

/**
 * Resolves the accent color for a given video post using the priority chain.
 *
 * Priority: post meta → first pv_tag term → pv_category term → global setting → hardcoded fallback.
 */
function pv_resolve_accent_color( int $post_id ): string {
	// 1. Per-video meta.
	$color = get_post_meta( $post_id, '_pv_accent_color', true );
	if ( $color ) {
		return sanitize_hex_color( $color ) ?? '#4f46e5';
	}

	// 2. First video tag.
	$tags = wp_get_post_terms( $post_id, 'pv_tag', [ 'fields' => 'ids' ] );
	if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
		$color = get_term_meta( $tags[0], 'pv_color', true );
		if ( $color ) return sanitize_hex_color( $color ) ?? '#4f46e5';
	}

	// 3. Video category.
	$cats = wp_get_post_terms( $post_id, 'pv_category', [ 'fields' => 'ids' ] );
	if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
		$color = get_term_meta( $cats[0], 'pv_color', true );
		if ( $color ) return sanitize_hex_color( $color ) ?? '#4f46e5';
	}

	// 4. Global plugin setting — treat #ffffff as unset (invisible badge on white bg).
	$settings = get_option( 'pv_settings', [] );
	if ( ! empty( $settings['default_accent'] ) ) {
		$_c = sanitize_hex_color( $settings['default_accent'] );
		if ( $_c && '#ffffff' !== strtolower( $_c ) ) {
			return $_c;
		}
	}

	// 5. Hardcoded fallback.
	return '#4f46e5';
}

// ── SVG wireframes for the meta-box visual picker ──────────────────

function pv_meta_svg_inherit(): string {
	return '<svg viewBox="0 0 160 100" fill="none" xmlns="http://www.w3.org/2000/svg">'
		. '<rect width="160" height="100" rx="4" fill="#f8fafc"/>'
		. '<rect x="5" y="5" width="150" height="90" rx="5" stroke="#a5b4fc" stroke-width="1.5" stroke-dasharray="5 4"/>'
		. '<circle cx="56" cy="46" r="14" fill="#e0e7ff"/>'
		. '<circle cx="56" cy="46" r="5.5" fill="#4f46e5"/>'
		. '<circle cx="56" cy="46" r="3" fill="#e0e7ff"/>'
		. '<rect x="53.5" y="30" width="5" height="5" rx="2" fill="#a5b4fc"/>'
		. '<rect x="53.5" y="57" width="5" height="5" rx="2" fill="#a5b4fc"/>'
		. '<rect x="40" y="43.5" width="5" height="5" rx="2" fill="#a5b4fc"/>'
		. '<rect x="67" y="43.5" width="5" height="5" rx="2" fill="#a5b4fc"/>'
		. '<line x1="76" y1="46" x2="96" y2="46" stroke="#4f46e5" stroke-width="2" stroke-linecap="round"/>'
		. '<polygon points="94,42 103,46 94,50" fill="#4f46e5"/>'
		. '<rect x="103" y="32" width="42" height="28" rx="2" fill="#0f172a" opacity=".65"/>'
		. '<polygon points="115,43 115,51 124,47" fill="#4f46e5"/>'
		. '<rect x="48" y="74" width="64" height="5" rx="2.5" fill="#a5b4fc" opacity=".5"/>'
		. '</svg>';
}

function pv_meta_svg_hero_top(): string {
	return '<svg viewBox="0 0 160 100" fill="none" xmlns="http://www.w3.org/2000/svg">'
		. '<rect width="160" height="100" rx="4" fill="#f8fafc"/>'
		. '<rect width="160" height="13" rx="4" fill="#e2e8f0"/>'
		. '<circle cx="9" cy="6.5" r="2.5" fill="#fca5a5"/>'
		. '<circle cx="17" cy="6.5" r="2.5" fill="#fcd34d"/>'
		. '<circle cx="25" cy="6.5" r="2.5" fill="#86efac"/>'
		. '<rect x="0" y="13" width="160" height="44" fill="#111827"/>'
		. '<polygon points="72,28 72,43 85,35.5" fill="#4f46e5"/>'
		. '<rect x="20" y="64" width="120" height="5" rx="2" fill="#334155"/>'
		. '<rect x="30" y="74" width="100" height="3.5" rx="1.5" fill="#e2e8f0"/>'
		. '<rect x="30" y="82" width="82" height="3.5" rx="1.5" fill="#e2e8f0"/>'
		. '<rect x="30" y="90" width="94" height="3.5" rx="1.5" fill="#e2e8f0"/>'
		. '</svg>';
}

function pv_meta_svg_hero_split(): string {
	return '<svg viewBox="0 0 160 100" fill="none" xmlns="http://www.w3.org/2000/svg">'
		. '<rect width="160" height="100" rx="4" fill="#f8fafc"/>'
		. '<rect width="160" height="13" rx="4" fill="#e2e8f0"/>'
		. '<circle cx="9" cy="6.5" r="2.5" fill="#fca5a5"/>'
		. '<circle cx="17" cy="6.5" r="2.5" fill="#fcd34d"/>'
		. '<circle cx="25" cy="6.5" r="2.5" fill="#86efac"/>'
		. '<rect x="8" y="20" width="88" height="56" rx="2" fill="#111827"/>'
		. '<polygon points="40,40 40,55 53,47.5" fill="#4f46e5"/>'
		. '<rect x="104" y="20" width="3.5" height="56" rx="1.75" fill="#4f46e5"/>'
		. '<rect x="113" y="22" width="38" height="6" rx="2" fill="#334155"/>'
		. '<rect x="113" y="33" width="34" height="3.5" rx="1.5" fill="#e2e8f0"/>'
		. '<rect x="113" y="42" width="38" height="3" rx="1.5" fill="#e2e8f0"/>'
		. '<rect x="113" y="50" width="28" height="3" rx="1.5" fill="#e2e8f0"/>'
		. '<rect x="113" y="58" width="36" height="3" rx="1.5" fill="#e2e8f0"/>'
		. '</svg>';
}

function pv_meta_svg_theater(): string {
	return '<svg viewBox="0 0 160 100" fill="none" xmlns="http://www.w3.org/2000/svg">'
		. '<defs>'
		. '<linearGradient id="pvThM" x1="0" y1="0" x2="0" y2="1">'
		. '<stop offset="0" stop-color="#0d0d0d" stop-opacity="0"/>'
		. '<stop offset="1" stop-color="#0d0d0d"/>'
		. '</linearGradient>'
		. '</defs>'
		. '<rect width="160" height="100" rx="4" fill="#f8fafc"/>'
		. '<rect width="160" height="13" rx="4" fill="#e2e8f0"/>'
		. '<circle cx="9" cy="6.5" r="2.5" fill="#fca5a5"/>'
		. '<circle cx="17" cy="6.5" r="2.5" fill="#fcd34d"/>'
		. '<circle cx="25" cy="6.5" r="2.5" fill="#86efac"/>'
		. '<rect x="0" y="13" width="160" height="57" fill="#0d0d0d"/>'
		. '<rect x="14" y="17" width="132" height="38" rx="1" fill="#1a1a2e"/>'
		. '<polygon points="72,29 72,43 84,36" fill="#4f46e5"/>'
		. '<rect x="0" y="44" width="160" height="26" fill="url(#pvThM)"/>'
		. '<rect x="14" y="55" width="68" height="4" rx="2" fill="#fff" opacity=".7"/>'
		. '<rect x="14" y="63" width="46" height="3" rx="1.5" fill="#fff" opacity=".4"/>'
		. '<rect x="20" y="79" width="120" height="3.5" rx="1.5" fill="#e2e8f0"/>'
		. '<rect x="28" y="87" width="104" height="3" rx="1.5" fill="#e2e8f0"/>'
		. '<rect x="28" y="94" width="78" height="3" rx="1.5" fill="#e2e8f0"/>'
		. '</svg>';
}
