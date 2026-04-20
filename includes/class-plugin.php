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

		// Import — only needed in admin or WP-Cron.
		if ( is_admin() || wp_doing_cron() ) {
			require_once PV_PLUGIN_DIR . 'includes/import/class-youtube-api.php';
			require_once PV_PLUGIN_DIR . 'includes/import/class-channel-importer.php';
		}

		// Admin UI
		if ( is_admin() ) {
			require_once PV_PLUGIN_DIR . 'includes/admin/class-settings-page.php';
			require_once PV_PLUGIN_DIR . 'includes/admin/class-import-ui.php';
			require_once PV_PLUGIN_DIR . 'includes/admin/class-dashboard-page.php';
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

		// Admin pages
		if ( is_admin() ) {
			( new PV_Settings_Page() )->register();
			( new PV_Import_UI() )->register();
			( new PV_Dashboard_Page() )->register();
		}

		add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_frontend_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
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
		wp_enqueue_script(
			'pv-lazy-video',
			PV_PLUGIN_URL . 'assets/dist/js/lazy-video.min.js',
			[],
			PV_VERSION,
			true
		);
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
