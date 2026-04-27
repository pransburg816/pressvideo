<?php
/**
 * PressVideo — Live Customizer admin page.
 * Split-panel: settings sidebar left, live iframe preview right.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Customizer_Page {

	public function register(): void {
		add_action( 'admin_menu',       [ $this, 'add_menu' ] );
		add_action( 'admin_body_class', [ $this, 'body_class' ] );
		add_action( 'wp_ajax_pv_save_preview',       [ $this, 'ajax_save_preview' ] );
		add_action( 'wp_ajax_pv_publish_settings',   [ $this, 'ajax_publish_settings' ] );
		add_action( 'wp_ajax_pv_fetch_yt_playlists',  [ $this, 'ajax_fetch_yt_playlists' ] );
		add_action( 'wp_ajax_pv_check_live_status',    [ $this, 'ajax_check_live_status' ] );
		add_action( 'wp_ajax_pv_detect_theme_colors',  [ $this, 'ajax_detect_theme_colors' ] );
	}

	public function add_menu(): void {
		add_submenu_page(
			'edit.php?post_type=pv_youtube',
			__( 'Live Preview', 'pv-youtube-importer' ),
			__( 'Live Preview', 'pv-youtube-importer' ),
			'manage_options',
			'pv-customizer',
			[ $this, 'render_page' ]
		);
	}

	public function body_class( string $classes ): string {
		$screen = get_current_screen();
		if ( $screen && ( false !== strpos( $screen->id, 'pv-customizer' ) || 'pv_youtube_page_pv-customizer' === $screen->id ) ) {
			$classes .= ' pv-customizer-fullscreen';
		}
		return $classes;
	}

	public function ajax_save_preview(): void {
		check_ajax_referer( 'pv_customizer', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$raw = json_decode( wp_unslash( $_POST['settings'] ?? '{}' ), true );
		if ( ! is_array( $raw ) ) wp_die( -1 );

		set_transient( 'pv_preview_settings', $this->sanitize( $raw ), HOUR_IN_SECONDS );
		wp_send_json_success();
	}

	public function ajax_publish_settings(): void {
		check_ajax_referer( 'pv_customizer', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$raw = json_decode( wp_unslash( $_POST['settings'] ?? '{}' ), true );
		if ( ! is_array( $raw ) ) wp_die( -1 );

		$existing = get_option( 'pv_settings', [] );
		update_option( 'pv_settings', array_merge( $existing, $this->sanitize( $raw ) ) );
		delete_transient( 'pv_preview_settings' );
		wp_send_json_success( [ 'message' => __( 'Settings published!', 'pv-youtube-importer' ) ] );
	}

	public function ajax_fetch_yt_playlists(): void {
		check_ajax_referer( 'pv_customizer', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

		$settings   = get_option( 'pv_settings', [] );
		$api_key    = $settings['api_key']    ?? '';
		$channel_id = $settings['channel_id'] ?? '';

		if ( ! $api_key || ! $channel_id ) {
			wp_send_json_error( 'API key or Channel ID not configured in Settings.' );
		}

		$transient_key = 'pv_yt_ch_playlists_' . md5( $channel_id );
		$cached = get_transient( $transient_key );
		if ( is_array( $cached ) ) {
			wp_send_json_success( $cached );
		}

		$url = add_query_arg( [
			'part'       => 'snippet,contentDetails',
			'channelId'  => $channel_id,
			'maxResults' => 50,
			'key'        => $api_key,
		], 'https://www.googleapis.com/youtube/v3/playlists' );

		$response = wp_remote_get( $url, [ 'timeout' => 15 ] );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $body['error'] ) ) {
			wp_send_json_error( $body['error']['message'] ?? 'YouTube API error' );
		}

		$playlists = [];
		foreach ( $body['items'] ?? [] as $item ) {
			$count = (int) ( $item['contentDetails']['itemCount'] ?? 0 );
			if ( $count === 0 ) continue; // skip empty playlists
			$playlists[] = [
				'id'    => $item['id'],
				'title' => $item['snippet']['title']        ?? '',
				'thumb' => $item['snippet']['thumbnails']['default']['url'] ?? '',
				'count' => $count,
			];
		}

		set_transient( $transient_key, $playlists, HOUR_IN_SECONDS );
		wp_send_json_success( $playlists );
	}

	public function ajax_check_live_status(): void {
		check_ajax_referer( 'pv_customizer', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

		$settings   = get_option( 'pv_settings', [] );
		$api_key    = $settings['api_key']    ?? '';
		$channel_id = $settings['channel_id'] ?? '';

		if ( ! $api_key || ! $channel_id ) {
			wp_send_json_error( [ 'message' => 'API key or Channel ID not configured in Settings.' ] );
		}

		// Delete cached transient so this is a fresh check
		delete_transient( 'pv_live_check_' . md5( $channel_id ) );

		$api    = new PV_YouTube_API( $api_key );
		$stream = $api->get_live_stream( $channel_id );

		if ( $stream && ! empty( $stream['video_id'] ) ) {
			wp_send_json_success( [ 'live' => true, 'title' => $stream['title'] ] );
		} else {
			wp_send_json_success( [ 'live' => false ] );
		}
	}

	public function ajax_detect_theme_colors(): void {
		check_ajax_referer( 'pv_customizer', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );

		$colors   = [];
		$seen_hex = [];

		// ── 1: Block themes — theme.json global settings palette ─────────────
		// WP 6.0+ groups by origin; WP 5.8 returns a flat array.
		if ( function_exists( 'wp_get_global_settings' ) ) {
			$global  = wp_get_global_settings();
			$palette = $global['color']['palette'] ?? [];

			if ( is_array( $palette ) && ( isset( $palette['theme'] ) || isset( $palette['custom'] ) ) ) {
				$src = ! empty( $palette['theme'] ) ? $palette['theme'] : ( $palette['custom'] ?? [] );
			} elseif ( is_array( $palette ) && ! isset( $palette['default'] ) ) {
				$src = $palette;
			} else {
				$src = [];
			}

			foreach ( (array) $src as $item ) {
				$color = sanitize_hex_color( $item['color'] ?? '' );
				if ( $color && ! isset( $seen_hex[ $color ] ) ) {
					$seen_hex[ $color ] = true;
					$colors[] = [
						'name'  => sanitize_text_field( $item['name'] ?? $item['slug'] ?? '' ),
						'color' => $color,
					];
				}
			}
		}

		// ── 2: Classic themes — theme_mods + CSS custom property scan ────────
		if ( empty( $colors ) ) {

			// 2a. Scan ALL theme_mods (parent + child) for any hex-like value.
			//     Catches any theme's Customizer color controls regardless of key name.
			$all_mods = array_merge(
				get_option( 'theme_mods_' . get_template(),   [] ),
				get_option( 'theme_mods_' . get_stylesheet(), [] )
			);
			foreach ( $all_mods as $key => $val ) {
				if ( ! is_string( $val ) || strlen( $val ) > 20 ) continue;
				$val = trim( $val );
				if ( '' === $val ) continue;
				if ( '#' !== substr( $val, 0, 1 ) ) $val = '#' . $val;
				$color = sanitize_hex_color( $val );
				if ( ! $color || isset( $seen_hex[ $color ] ) ) continue;
				$seen_hex[ $color ] = true;
				$label    = ucwords( str_replace( [ '_', '-' ], ' ', (string) $key ) );
				$colors[] = [ 'name' => $label, 'color' => $color ];
			}

			// 2b. Parse CSS files for --custom-property: #hex declarations.
			//     Covers child themes (and parents) that define brand colors as CSS
			//     variables in their stylesheet rather than via the Customizer.
			$css_dirs = array_unique( [
				get_template_directory(),
				get_stylesheet_directory(),
			] );
			$css_text = '';
			foreach ( $css_dirs as $dir ) {
				foreach ( array_merge(
					glob( $dir . '/*.css' )    ?: [],
					glob( $dir . '/*/*.css' )  ?: [],
					glob( $dir . '/*/*/*.css' ) ?: []
				) as $file ) {
					if ( is_readable( $file ) ) {
						$css_text .= ' ' . file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
					}
				}
			}

			// Also include additional CSS saved via the WP Customizer.
			$custom_css = wp_get_custom_css();
			if ( $custom_css ) {
				$css_text .= ' ' . $custom_css;
			}

			if ( $css_text ) {
				preg_match_all(
					'/(--[\w-]+)\s*:\s*(#(?:[0-9a-fA-F]{6}|[0-9a-fA-F]{3}))\b/',
					$css_text,
					$matches,
					PREG_SET_ORDER
				);
				foreach ( $matches as $m ) {
					$color = sanitize_hex_color( $m[2] );
					if ( ! $color || isset( $seen_hex[ $color ] ) ) continue;
					$seen_hex[ $color ] = true;
					$raw      = str_replace( [ '--', '-', '_' ], [ '', ' ', ' ' ], $m[1] );
					$label    = ucwords( trim( $raw ) );
					$colors[] = [ 'name' => $label, 'color' => $color ];
				}
			}
		}

		if ( empty( $colors ) ) {
			wp_send_json_error( __( 'No theme colors found. Colors must be defined in theme.json, the WordPress Customizer, or as CSS custom properties (--var-name: #hex) in the theme stylesheet.', 'pv-youtube-importer' ) );
			return;
		}

		wp_send_json_success( array_slice( $colors, 0, 16 ) );
	}

	private function sanitize( array $raw ): array {
		$clean = [];

		$map_enum = [
			'archive_layout'    => [ 'grid', 'list', 'featured', 'compact', 'wall', 'spotlight', 'broadcast' ],
			'content_width'     => [ 'wide', 'medium', 'narrow', '' ],
			'display_mode'      => [ 'offcanvas', 'page', 'modal' ],
			'watch_page_layout' => [ 'hero-top', 'hero-split', 'theater' ],
			'hero_title_size'   => [ '', 'lg', 'xl' ],
			'hero_overlay'      => [ 'light', 'medium', 'dark' ],
			'hero_text_align'   => [ 'left', 'center', 'right' ],
			'hero_inner_width'  => [ 'full', 'contained' ],
			'search_bar_align'  => [ 'left', 'center', 'right' ],
			'button_shape'      => [ 'pill', 'radius', 'square', '' ],
		];
		foreach ( $map_enum as $key => $allowed ) {
			if ( isset( $raw[ $key ] ) && in_array( $raw[ $key ], $allowed, true ) ) {
				$clean[ $key ] = $raw[ $key ];
			}
		}

		foreach ( [ 'default_accent', 'hero_title_color', 'hero_subtitle_color', 'page_bg_color', 'sidebar_bg_color' ] as $k ) {
			if ( array_key_exists( $k, $raw ) ) {
				$val = sanitize_hex_color( $raw[ $k ] ?? '' );
				if ( $val ) {
					$clean[ $k ] = $val;
				} elseif ( 'default_accent' === $k ) {
					$clean[ $k ] = '#4f46e5';
				} else {
					$clean[ $k ] = '';
				}
			}
		}

		foreach ( [ 'hero_title', 'hero_subtitle', 'aside_new_releases_label', 'aside_topics_label', 'aside_tags_label', 'aside_cat_label', 'aside_tag_label', 'new_video_notify_msg', 'grid_label_text' ] as $k ) {
			if ( array_key_exists( $k, $raw ) ) {
				$clean[ $k ] = sanitize_text_field( $raw[ $k ] );
			}
		}

		foreach ( [ 'aside_cat_term', 'aside_tag_term' ] as $k ) {
			if ( array_key_exists( $k, $raw ) ) {
				$clean[ $k ] = sanitize_text_field( $raw[ $k ] );
			}
		}

		if ( array_key_exists( 'hero_bg_image', $raw ) ) {
			$clean['hero_bg_image'] = esc_url_raw( $raw['hero_bg_image'] );
		}

		foreach ( [ 'aside_new_releases', 'aside_topics', 'aside_tags', 'cards_show_excerpt', 'cards_show_category', 'cards_show_views', 'aside_cat_on', 'aside_tag_on', 'hero_show', 'live_feed_enabled', 'live_chat_enabled', 'live_hide_content', 'live_banner_enabled', 'new_video_notify', 'grid_label_show' ] as $k ) {
			if ( array_key_exists( $k, $raw ) ) {
				$clean[ $k ] = (bool) $raw[ $k ];
			}
		}

		if ( array_key_exists( 'bc_playlists', $raw ) ) {
			$_pl = json_decode( $raw['bc_playlists'], true );
			$clean['bc_playlists'] = wp_json_encode(
				is_array( $_pl ) ? array_map( 'sanitize_text_field', $_pl ) : []
			);
		}

		if ( array_key_exists( 'bc_playlist_titles', $raw ) ) {
			$_titles = json_decode( $raw['bc_playlist_titles'], true );
			if ( is_array( $_titles ) ) {
				$_clean_titles = [];
				foreach ( $_titles as $_id => $_title ) {
					$_clean_titles[ sanitize_text_field( $_id ) ] = sanitize_text_field( $_title );
				}
				$clean['bc_playlist_titles'] = wp_json_encode( $_clean_titles );
			} else {
				$clean['bc_playlist_titles'] = '{}';
			}
		}

		foreach ( [
			'aside_new_releases_count' => [ 3,  10  ],
			'aside_tags_count'         => [ 6,  24  ],
			'aside_cat_count'          => [ 3,  10  ],
			'aside_tag_count'          => [ 3,  10  ],
			'hero_height_desktop'      => [ 100, 800 ],
			'hero_height_mobile'       => [ 80,  500 ],
		] as $k => $range ) {
			if ( array_key_exists( $k, $raw ) ) {
				$clean[ $k ] = max( $range[0], min( $range[1], (int) $raw[ $k ] ) );
			}
		}

		return $clean;
	}

	public function render_page(): void {
		$settings    = get_option( 'pv_settings', [] );
		$archive_url = get_post_type_archive_link( 'pv_youtube' ) ?: home_url( '/' );
		$preview_url = add_query_arg( [
			'pv_preview' => '1',
			'pv_nonce'   => wp_create_nonce( 'pv_preview' ),
		], $archive_url );

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_enqueue_style(
			'pv-customizer',
			PV_PLUGIN_URL . 'assets/dist/css/admin-customizer.min.css',
			[ 'wp-color-picker' ],
			PV_VERSION
		);
		wp_enqueue_script(
			'pv-customizer',
			PV_PLUGIN_URL . 'assets/dist/js/admin-customizer.min.js',
			[ 'jquery', 'wp-color-picker' ],
			PV_VERSION,
			true
		);
		wp_localize_script( 'pv-customizer', 'pvCustomizer', [
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'pv_customizer' ),
			'previewUrl' => esc_url( $preview_url ),
			'archiveUrl' => esc_url( $archive_url ),
			'settings'   => $settings,
		] );

		include PV_PLUGIN_DIR . 'templates/admin/customizer.php';
	}
}
