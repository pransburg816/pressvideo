<?php
/**
 * Core plugin loader. Instantiates all feature classes and hooks them in.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Plugin {

	public function run(): void {
		$this->load_dependencies();
		$this->register_hooks();
	}

	private function load_dependencies(): void {
		require_once PV_PLUGIN_DIR . 'includes/class-tier.php';

		// CPT
		require_once PV_PLUGIN_DIR . 'includes/cpt/class-videos-cpt.php';
		require_once PV_PLUGIN_DIR . 'includes/cpt/class-video-taxonomies.php';
		require_once PV_PLUGIN_DIR . 'includes/cpt/class-video-meta.php';

		// Display — always needed (frontend shortcodes + offcanvas + templates).
		require_once PV_PLUGIN_DIR . 'includes/display/class-template-loader.php';
		require_once PV_PLUGIN_DIR . 'includes/display/class-renderer-interface.php';
		require_once PV_PLUGIN_DIR . 'includes/display/class-renderer-factory.php';
		require_once PV_PLUGIN_DIR . 'includes/display/renderers/class-renderer-offcanvas.php';
		require_once PV_PLUGIN_DIR . 'includes/display/class-offcanvas.php';
		require_once PV_PLUGIN_DIR . 'includes/display/class-video-grid.php';
		require_once PV_PLUGIN_DIR . 'includes/display/class-shortcodes.php';
		require_once PV_PLUGIN_DIR . 'includes/display/class-template-tags.php';

		// YouTube API class also needed on front end (broadcast layout playlist fetch).
		require_once PV_PLUGIN_DIR . 'includes/import/class-youtube-api.php';

		// Notifications — sitewide live banner + new video toast.
		require_once PV_PLUGIN_DIR . 'includes/display/class-notifications.php';

		// Import — channel importer only needed in admin or WP-Cron.
		if ( is_admin() || wp_doing_cron() ) {
			require_once PV_PLUGIN_DIR . 'includes/import/class-channel-importer.php';
		}

		// Admin UI
		if ( is_admin() ) {
			require_once PV_PLUGIN_DIR . 'includes/admin/class-settings-page.php';
			require_once PV_PLUGIN_DIR . 'includes/admin/class-import-ui.php';
			require_once PV_PLUGIN_DIR . 'includes/admin/class-dashboard-page.php';
			require_once PV_PLUGIN_DIR . 'includes/admin/class-customizer-page.php';
		}
	}

	private function register_hooks(): void {
		// Frontend display first.
		( new PV_Shortcodes() )->register();
		( new PV_Offcanvas() )->register();

		// CPT & taxonomies
		( new PV_Videos_CPT() )->register();
		( new PV_Video_Taxonomies() )->register();
		( new PV_Video_Meta() )->register();

		// Template loader (single & archive)
		( new PV_Template_Loader() )->register();

		// Sitewide notifications (live banner + new video toast)
		( new PV_Notifications() )->register();

		// Track latest published video timestamp for new-video notification
		add_action( 'save_post_pv_youtube', [ $this, 'update_latest_video_ts' ], 10, 2 );

		// Admin pages
		if ( is_admin() ) {
			( new PV_Settings_Page() )->register();
			( new PV_Import_UI() )->register();
			( new PV_Dashboard_Page() )->register();
			( new PV_Customizer_Page() )->register();
		}

		// Broadcast lazy-load AJAX (public — no login required)
		add_action( 'wp_ajax_pv_bc_videos',        [ $this, 'ajax_bc_videos' ] );
		add_action( 'wp_ajax_nopriv_pv_bc_videos',  [ $this, 'ajax_bc_videos' ] );
		add_action( 'wp_ajax_pv_bc_playlists',      [ $this, 'ajax_bc_playlists' ] );
		add_action( 'wp_ajax_nopriv_pv_bc_playlists', [ $this, 'ajax_bc_playlists' ] );

		add_action( 'pre_get_posts',         [ $this, 'archive_per_page' ], 20 );
		add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_frontend_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	public function archive_per_page( WP_Query $q ): void {
		if ( is_admin() || ! $q->is_main_query() || ! $q->is_post_type_archive( 'pv_youtube' ) ) return;
		$raw = sanitize_key( $_GET['per_page'] ?? '' ); // phpcs:ignore
		if ( 'all' === $raw ) {
			$q->set( 'posts_per_page', -1 );
			return;
		}
		$n = (int) $raw;
		if ( in_array( $n, [ 5, 10, 20 ], true ) ) {
			$q->set( 'posts_per_page', $n );
		}
	}

	public function ajax_bc_videos(): void {
		check_ajax_referer( 'pv_bc_load', 'nonce' );
		$settings = get_option( 'pv_settings', [] );
		$display  = $settings['display_mode'] ?? 'offcanvas';
		$page     = max( 1, (int) ( $_POST['page'] ?? 1 ) ); // phpcs:ignore

		$q = new WP_Query( [
			'post_type'      => 'pv_youtube',
			'posts_per_page' => 40,
			'paged'          => $page,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		$html = '';
		foreach ( $q->posts as $_p ) {
			$html .= pv_bc_card_html( $_p, $display );
		}
		wp_reset_postdata();

		wp_send_json_success( [
			'html'  => $html,
			'total' => $q->found_posts,
			'pages' => $q->max_num_pages,
			'page'  => $page,
		] );
	}

	public function ajax_bc_playlists(): void {
		check_ajax_referer( 'pv_bc_load', 'nonce' );

		$settings   = get_option( 'pv_settings', [] );
		$channel_id = $settings['channel_id'] ?? '';

		// Selected playlist entries from settings
		$bc_pl_raw   = $settings['bc_playlists'] ?? '[]';
		$bc_pl_items = json_decode( is_string( $bc_pl_raw ) ? $bc_pl_raw : '[]', true );
		$bc_pl_items = is_array( $bc_pl_items ) ? $bc_pl_items : [];

		$yt_pl_ids    = [];
		$series_slugs = [];
		foreach ( $bc_pl_items as $_item ) {
			if ( strncmp( (string) $_item, 'yt:', 3 ) === 0 ) {
				$yt_pl_ids[] = substr( (string) $_item, 3 );
			} else {
				$series_slugs[] = (string) $_item;
			}
		}

		// Channel playlists — use transient if warm, otherwise fetch fresh from YouTube API
		$_transient_key = 'pv_yt_ch_playlists_' . md5( $channel_id );
		$ch_pls         = get_transient( $_transient_key );

		if ( ! is_array( $ch_pls ) && ! empty( $yt_pl_ids ) ) {
			$api_key = $settings['api_key'] ?? '';
			if ( $api_key && $channel_id ) {
				$_url = add_query_arg( [
					'part'       => 'snippet,contentDetails',
					'channelId'  => $channel_id,
					'maxResults' => 50,
					'key'        => $api_key,
				], 'https://www.googleapis.com/youtube/v3/playlists' );
				$_resp = wp_remote_get( $_url, [ 'timeout' => 12 ] );
				if ( ! is_wp_error( $_resp ) ) {
					$_body = json_decode( wp_remote_retrieve_body( $_resp ), true );
					if ( ! isset( $_body['error'] ) ) {
						$ch_pls = [];
						foreach ( $_body['items'] ?? [] as $_item ) {
							$_cnt = (int) ( $_item['contentDetails']['itemCount'] ?? 0 );
							if ( $_cnt === 0 ) continue;
							$ch_pls[] = [
								'id'    => $_item['id'],
								'title' => $_item['snippet']['title'] ?? '',
								'thumb' => $_item['snippet']['thumbnails']['medium']['url'] ?? $_item['snippet']['thumbnails']['default']['url'] ?? '',
								'count' => $_cnt,
							];
						}
						set_transient( $_transient_key, $ch_pls, HOUR_IN_SECONDS );
					}
				}
			}
		}

		$pl_svg_sm = '<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8 12.5v-9l6 4.5-6 4.5z"/></svg>';
		$pl_svg_lg = '<svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8 12.5v-9l6 4.5-6 4.5z"/></svg>';

		$render_pl_card = function( string $title, int $count, string $thumb, string $link, bool $external = false ) use ( $pl_svg_sm, $pl_svg_lg ): string {
			$target = $external ? ' target="_blank" rel="noopener noreferrer"' : '';
			$no_thumb = '<div class="pv-bc-pl-list-card__no-thumb">' . $pl_svg_lg . '</div>';
			$thumb_html = $thumb
				? '<img src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $title ) . '" loading="lazy">'
				: $no_thumb;
			$count_label = sprintf( _n( '%d video', '%d videos', $count, 'pv-youtube-importer' ), $count );
			$view_all = $link
				? '<a href="' . esc_url( $link ) . '" class="pv-bc-pl-list-card__view-all"' . $target . '>' . esc_html__( 'View All', 'pv-youtube-importer' ) . ' &rarr;</a>'
				: '';
			return '<div class="pv-bc-pl-list-card">'
				. '<div class="pv-bc-pl-list-card__thumb">' . $thumb_html
				. '<div class="pv-bc-pl-list-card__count">' . $pl_svg_sm . esc_html( $count_label ) . '</div>'
				. '</div>'
				. '<div class="pv-bc-pl-list-card__info">'
				. '<div class="pv-bc-pl-list-card__title">' . esc_html( $title ) . '</div>'
				. $view_all
				. '</div></div>';
		};

		$cards = '';

		// ── All YouTube playlists from the channel ────────────────────
		if ( is_array( $ch_pls ) ) {
			foreach ( $ch_pls as $_cp ) {
				$_count = (int) ( $_cp['count'] ?? 0 );
				if ( $_count === 0 ) continue;
				$_yt_link = 'https://www.youtube.com/playlist?list=' . rawurlencode( $_cp['id'] );
				$cards   .= $render_pl_card( $_cp['title'], $_count, $_cp['thumb'] ?? '', $_yt_link, true );
			}
		}

		// ── Series playlists (all non-empty series terms) ─────────────
		$all_series = get_terms( [ 'taxonomy' => 'pv_series', 'hide_empty' => true, 'orderby' => 'name' ] );
		if ( ! is_wp_error( $all_series ) ) {
			foreach ( $all_series as $_pls ) {
				$_pls_q = new WP_Query( [
					'post_type'      => 'pv_youtube',
					'posts_per_page' => 1,
					'post_status'    => 'publish',
					'no_found_rows'  => false,
					'tax_query'      => [ [ 'taxonomy' => 'pv_series', 'field' => 'term_id', 'terms' => $_pls->term_id ] ], // phpcs:ignore
				] );
				$_pls_count = (int) $_pls_q->found_posts;
				if ( $_pls_count === 0 ) { wp_reset_postdata(); continue; }
				$_pls_thumb = ! empty( $_pls_q->posts ) ? get_the_post_thumbnail_url( $_pls_q->posts[0]->ID, 'medium' ) : '';
				$_pls_link  = get_term_link( $_pls );
				wp_reset_postdata();
				$cards .= $render_pl_card( $_pls->name, $_pls_count, $_pls_thumb, is_wp_error( $_pls_link ) ? '' : $_pls_link, false );
			}
		}

		if ( ! $cards ) {
			wp_send_json_success( [ 'html' => '<p class="pv-no-videos">' . esc_html__( 'No playlists found.', 'pv-youtube-importer' ) . '</p>' ] );
			return;
		}

		wp_send_json_success( [ 'html' => '<div class="pv-bc-playlist-list">' . $cards . '</div>' ] );
	}

	public function enqueue_frontend_assets(): void {
		// Always load on PressVideo CPT pages (single, archive, taxonomy).
		$is_pv_template = is_singular( 'pv_youtube' )
			|| is_post_type_archive( 'pv_youtube' )
			|| is_tax( [ 'pv_category', 'pv_tag', 'pv_series', 'pv_type' ] );

		if ( ! $is_pv_template ) {
			// Load on regular pages/posts that contain a PV shortcode.
			$post = get_queried_object();
			if ( ! $post instanceof WP_Post ) return;

			// Decode HTML-entity-encoded brackets that TinyMCE/Classic Editor
			// produces — has_shortcode() needs literal [ ] chars.
			$content = str_replace( [ '&#91;', '&#93;' ], [ '[', ']' ], $post->post_content );

			$has_pv = false;
			foreach ( [ 'pv_video_grid', 'pv_video_latest', 'pv_launcher', 'pv_video' ] as $tag ) {
				if ( has_shortcode( $content, $tag ) ) {
					$has_pv = true;
					break;
				}
			}
			if ( ! $has_pv ) return;
		}

		wp_enqueue_style(
			'pv-offcanvas',
			PV_PLUGIN_URL . 'assets/dist/css/offcanvas.min.css',
			[],
			PV_VERSION
		);
		wp_enqueue_style(
			'pv-grid',
			PV_PLUGIN_URL . 'assets/dist/css/grid.min.css',
			[],
			PV_VERSION
		);
		wp_enqueue_style(
			'pv-watch',
			PV_PLUGIN_URL . 'assets/dist/css/watch-page.min.css',
			[],
			PV_VERSION
		);
		wp_enqueue_script(
			'pv-offcanvas',
			PV_PLUGIN_URL . 'assets/dist/js/offcanvas.min.js',
			[],
			PV_VERSION,
			true
		);
		wp_localize_script( 'pv-offcanvas', 'pvOffcanvas', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		] );
		wp_enqueue_script(
			'pv-lazy-video',
			PV_PLUGIN_URL . 'assets/dist/js/lazy-video.min.js',
			[],
			PV_VERSION,
			true
		);

		if ( is_post_type_archive( 'pv_youtube' ) ) {
			wp_enqueue_script(
				'pv-archive-filter',
				PV_PLUGIN_URL . 'assets/dist/js/archive-filter.min.js',
				[],
				PV_VERSION,
				true
			);
			wp_localize_script( 'pv-archive-filter', 'pvBroadcast', [
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'pv_bc_load' ),
				] );
		}
	}

	public function update_latest_video_ts( int $post_id, WP_Post $post ): void {
		if ( 'publish' === $post->post_status ) {
			update_option( 'pv_latest_video_ts', time(), false );
		}
	}

	public function enqueue_admin_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen ) return;

		$is_pv_screen = in_array( $screen->post_type ?? '', [ 'pv_youtube' ], true )
			|| in_array( $screen->taxonomy ?? '', [ 'pv_tag', 'pv_category', 'pv_series', 'pv_type' ], true )
			|| str_contains( $hook, 'pv-youtube-importer' );

		if ( ! $is_pv_screen ) return;

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script(
			'pv-admin',
			PV_PLUGIN_URL . 'assets/dist/js/admin-color-picker.min.js',
			[ 'wp-color-picker' ],
			PV_VERSION,
			true
		);
		wp_enqueue_style(
			'pv-admin',
			PV_PLUGIN_URL . 'assets/dist/css/admin.min.css',
			[],
			PV_VERSION
		);
	}
}
