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
		require_once PV_PLUGIN_DIR . 'includes/display/renderers/class-renderer-modal.php';
		require_once PV_PLUGIN_DIR . 'includes/display/class-offcanvas.php';
		require_once PV_PLUGIN_DIR . 'includes/display/class-modal.php';
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

		// Analytics tracker (AJAX handlers needed on front end too for guest tracking).
		require_once PV_PLUGIN_DIR . 'includes/analytics/class-analytics-tracker.php';
		require_once PV_PLUGIN_DIR . 'includes/analytics/class-youtube-oauth.php';
		require_once PV_PLUGIN_DIR . 'includes/analytics/class-youtube-analytics-api.php';

		// Admin UI
		if ( is_admin() ) {
			require_once PV_PLUGIN_DIR . 'includes/admin/class-settings-page.php';
			require_once PV_PLUGIN_DIR . 'includes/admin/class-import-ui.php';
			require_once PV_PLUGIN_DIR . 'includes/admin/class-dashboard-page.php';
			require_once PV_PLUGIN_DIR . 'includes/admin/class-customizer-page.php';
			require_once PV_PLUGIN_DIR . 'includes/admin/class-analytics-page.php';
			require_once PV_PLUGIN_DIR . 'includes/admin/class-admin-branding.php';
		}
	}

	private function register_hooks(): void {
		// Frontend display first.
		( new PV_Shortcodes() )->register();
		( new PV_Offcanvas() )->register();
		( new PV_Modal() )->register();

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

		// Analytics (AJAX handlers needed for both guests and admins).
		( new PV_Analytics_Tracker() )->register();

		// Admin pages
		if ( is_admin() ) {
			( new PV_Settings_Page() )->register();
			( new PV_Import_UI() )->register();
			( new PV_Dashboard_Page() )->register();
			( new PV_Customizer_Page() )->register();
			( new PV_Analytics_Page() )->register();
			( new PV_Admin_Branding() )->register();
		}

		// Archive page AJAX (pagination + per-page)
		add_action( 'wp_ajax_pv_load_page',         [ $this, 'ajax_load_page' ] );
		add_action( 'wp_ajax_nopriv_pv_load_page',  [ $this, 'ajax_load_page' ] );

		// Broadcast lazy-load AJAX (public — no login required)
		add_action( 'wp_ajax_pv_bc_videos',        [ $this, 'ajax_bc_videos' ] );
		add_action( 'wp_ajax_nopriv_pv_bc_videos',  [ $this, 'ajax_bc_videos' ] );
		add_action( 'wp_ajax_pv_bc_playlists',      [ $this, 'ajax_bc_playlists' ] );
		add_action( 'wp_ajax_nopriv_pv_bc_playlists', [ $this, 'ajax_bc_playlists' ] );

		add_action( 'pre_get_posts',         [ $this, 'archive_per_page' ], 50 );
		add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_frontend_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		if ( is_admin() ) {
			add_filter( 'admin_body_class', [ $this, 'fullscreen_body_class' ] );
			add_action( 'admin_head',       [ $this, 'render_pv_head_loader' ] );
			add_action( 'admin_footer',     [ $this, 'render_pv_shell' ] );
		}
	}

	private function pv_fullscreen_screen_ids(): array {
		return [
			'pv_youtube_page_pv-youtube-importer-dashboard',
			'pv_youtube_page_pv-youtube-importer-settings',
			'pv_youtube_page_pv-customizer',
			'pv_youtube_page_pv-analytics',
		];
	}

	public function fullscreen_body_class( string $classes ): string {
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->id, $this->pv_fullscreen_screen_ids(), true ) ) {
			$classes .= ' pv-fullscreen-ui';
		}
		return $classes;
	}

	public function render_pv_head_loader(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, $this->pv_fullscreen_screen_ids(), true ) ) return;

		$inner = wp_json_encode(
			'<div class="pvl-inner">'
			. '<div class="pvl-icon"><svg viewBox="0 0 88 88" fill="none" xmlns="http://www.w3.org/2000/svg">'
			. '<circle cx="44" cy="44" r="40" stroke="rgba(99,102,241,0.12)" stroke-width="3"/>'
			. '<circle cx="44" cy="44" r="40" stroke="#4f46e5" stroke-width="3" stroke-linecap="round"'
			.   ' stroke-dasharray="251" stroke-dashoffset="190" class="pvl-arc"/>'
			. '<circle cx="44" cy="44" r="30" stroke="rgba(99,102,241,0.07)" stroke-width="18" class="pvl-glow"/>'
			. '<path d="M37 28 L59 44 L37 60 Z" fill="white" class="pvl-play"/>'
			. '</svg></div>'
			. '<div class="pvl-wordmark"><span class="pvl-w1">Press</span><span class="pvl-w2">Video</span></div>'
			. '<div class="pvl-dots"><span></span><span></span><span></span></div>'
			. '</div>'
		);
		?>
		<style>
		/* Applied in <head> — dark bg on <html> prevents the white flash that occurs
		   between pages during navigation, before any body content paints. */
		html{background:#1a1740!important;margin-top:0!important;padding-top:0!important;}
		body{margin-top:0!important;padding-top:0!important;}
		#pv-brand-loader{position:fixed;inset:0;z-index:999999;background:#1a1740;display:flex;align-items:center;justify-content:center;transition:opacity .45s ease,visibility .45s ease;}
		#pv-brand-loader.pvl-out{opacity:0;visibility:hidden;pointer-events:none;}
		.pvl-inner{display:flex;flex-direction:column;align-items:center;gap:0;}
		.pvl-icon{width:88px;height:88px;}
		.pvl-icon svg{width:88px;height:88px;overflow:visible;}
		.pvl-arc{transform-origin:44px 44px;animation:pvl-spin 1.1s linear infinite;}
		.pvl-glow{animation:pvl-glow 2s ease-in-out infinite;}
		.pvl-play{transform-origin:48px 44px;animation:pvl-play 2s ease-in-out infinite;}
		@keyframes pvl-spin{to{transform:rotate(360deg);}}
		@keyframes pvl-glow{0%,100%{stroke-width:14;opacity:.5;}50%{stroke-width:22;opacity:.9;}}
		@keyframes pvl-play{0%,100%{opacity:.75;transform:scale(1);}50%{opacity:1;transform:scale(1.07);}}
		.pvl-wordmark{margin-top:22px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:1.45rem;font-weight:800;letter-spacing:-.03em;line-height:1;animation:pvl-up .5s ease .1s both;}
		.pvl-w1{color:#fff;}.pvl-w2{color:#818cf8;}
		.pvl-dots{margin-top:14px;display:flex;gap:6px;animation:pvl-up .4s ease .25s both;}
		.pvl-dots span{width:5px;height:5px;border-radius:50%;background:rgba(99,102,241,.45);animation:pvl-dot 1.2s ease-in-out infinite;}
		.pvl-dots span:nth-child(2){animation-delay:.2s;}.pvl-dots span:nth-child(3){animation-delay:.4s;}
		@keyframes pvl-dot{0%,80%,100%{transform:scale(.75);opacity:.35;}40%{transform:scale(1.3);opacity:1;}}
		@keyframes pvl-up{from{opacity:0;transform:translateY(7px);}to{opacity:1;transform:translateY(0);}}
		</style>
		<script>
		(function(){
			document.documentElement.style.marginTop = '0';
			document.documentElement.style.paddingTop = '0';
			var l=document.createElement('div');
			l.id='pv-brand-loader';
			l.setAttribute('aria-hidden','true');
			l.innerHTML=<?php echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON-encoded by wp_json_encode ?>;
			document.documentElement.appendChild(l);
			document.addEventListener('DOMContentLoaded',function(){
				document.body.style.marginTop = '0';
				document.body.style.paddingTop = '0';
				setTimeout(function(){
					l.classList.add('pvl-out');
					setTimeout(function(){if(l.parentNode)l.parentNode.removeChild(l);},480);
				},520);
			});
		}());
		</script>
		<?php
	}

	public function render_pv_shell(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, $this->pv_fullscreen_screen_ids(), true ) ) return;

		$is_customizer = 'pv_youtube_page_pv-customizer' === $screen->id;

		// Inline SVG helper — wraps Feather-style path data in a stroke SVG element.
		$svgi = static function( string $p ): string {
			return '<svg class="pv-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
		};

		$library_nav = [
			[
				'label'  => 'All Videos',
				'url'    => admin_url( 'edit.php?post_type=pv_youtube' ),
				'screen' => 'edit-pv_youtube',
				'icon'   => $svgi( '<path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/>' ),
			],
			[
				'label'  => 'Add New Video',
				'url'    => admin_url( 'post-new.php?post_type=pv_youtube' ),
				'screen' => 'add-pv_youtube',
				'icon'   => $svgi( '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>' ),
			],
			[
				'label'  => 'Categories',
				'url'    => admin_url( 'edit-tags.php?taxonomy=pv_category&post_type=pv_youtube' ),
				'screen' => 'edit-pv_category',
				'icon'   => $svgi( '<path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>' ),
			],
			[
				'label'  => 'Tags',
				'url'    => admin_url( 'edit-tags.php?taxonomy=pv_tag&post_type=pv_youtube' ),
				'screen' => 'edit-pv_tag',
				'icon'   => $svgi( '<path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>' ),
			],
			[
				'label'  => 'Series',
				'url'    => admin_url( 'edit-tags.php?taxonomy=pv_series&post_type=pv_youtube' ),
				'screen' => 'edit-pv_series',
				'icon'   => $svgi( '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>' ),
			],
			[
				'label'  => 'Video Types',
				'url'    => admin_url( 'edit-tags.php?taxonomy=pv_type&post_type=pv_youtube' ),
				'screen' => 'edit-pv_type',
				'icon'   => $svgi( '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>' ),
			],
		];

		$main_nav = [
			[
				'label'  => 'Dashboard',
				'url'    => admin_url( 'edit.php?post_type=pv_youtube&page=pv-youtube-importer-dashboard' ),
				'screen' => 'pv_youtube_page_pv-youtube-importer-dashboard',
				'icon'   => $svgi( '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>' ),
			],
			[
				'label'  => 'Settings',
				'url'    => admin_url( 'edit.php?post_type=pv_youtube&page=pv-youtube-importer-settings' ),
				'screen' => 'pv_youtube_page_pv-youtube-importer-settings',
				'icon'   => $svgi( '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>' ),
			],
			[
				'label'  => 'Live Preview',
				'url'    => admin_url( 'edit.php?post_type=pv_youtube&page=pv-customizer' ),
				'screen' => 'pv_youtube_page_pv-customizer',
				'icon'   => $svgi( '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>' ),
			],
			[
				'label'  => 'Analytics',
				'url'    => admin_url( 'edit.php?post_type=pv_youtube&page=pv-analytics' ),
				'screen' => 'pv_youtube_page_pv-analytics',
				'icon'   => $svgi( '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>' ),
			],
		];

		$customizer_panels = [
			[ 'panel' => 'layout',        'label' => 'Layout', 'icon' => $svgi( '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18"/>' ) ],
			[ 'panel' => 'hero',          'label' => 'Hero',   'icon' => $svgi( '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 15l4-4 4 4 4-5 4 5"/>' ) ],
			[ 'panel' => 'sidebar',       'label' => 'Sidebar','icon' => $svgi( '<rect x="2" y="3" width="20" height="18" rx="2"/><path d="M9 3v18"/>' ) ],
			[ 'panel' => 'style',         'label' => 'Style',  'icon' => $svgi( '<path d="M20.24 12.24a6 6 0 00-8.49-8.49L5 10.5V19h8.5z"/><line x1="16" y1="8" x2="2" y2="22"/><line x1="17.5" y1="15" x2="9" y2="15"/>' ) ],
			[ 'panel' => 'notifications', 'label' => 'Alerts', 'icon' => $svgi( '<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>' ) ],
		];
		?>
		<aside id="pv-aside">

			<div class="pv-aside__brand">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7L8 5z"/></svg>
				<span>PressVideo</span>
			</div>

			<a href="<?php echo esc_url( admin_url( 'index.php' ) ); ?>" class="pv-aside__close" aria-label="Exit to WordPress admin">&#215;</a>

			<?php if ( $is_customizer ) : ?>

				<div class="pv-aside__sub-header">
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=pv_youtube&page=pv-youtube-importer-dashboard' ) ); ?>" class="pv-aside__back">
						<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
						<?php esc_html_e( 'Main Menu', 'pv-youtube-importer' ); ?>
					</a>
					<div class="pv-aside__section-label"><?php esc_html_e( 'Live Preview', 'pv-youtube-importer' ); ?></div>
				</div>
				<nav class="pv-aside__nav" aria-label="<?php esc_attr_e( 'Live Preview Sections', 'pv-youtube-importer' ); ?>">
					<?php foreach ( $customizer_panels as $i => $p ) : ?>
					<button class="pv-aside__nav-item pv-aside__panel-btn<?php echo 0 === $i ? ' is-active' : ''; ?>"
					        data-pv-panel="<?php echo esc_attr( $p['panel'] ); ?>">
						<?php echo $p['icon']; // phpcs:ignore WordPress.Security.EscapeOutput -- trusted internal SVG ?>
						<span><?php echo esc_html( $p['label'] ); ?></span>
					</button>
					<?php endforeach; ?>
				</nav>

			<?php else : ?>

				<nav class="pv-aside__nav" aria-label="<?php esc_attr_e( 'PressVideo Admin', 'pv-youtube-importer' ); ?>">
					<div class="pv-aside__nav-section"><?php esc_html_e( 'Library', 'pv-youtube-importer' ); ?></div>
					<?php foreach ( $library_nav as $item ) : ?>
					<a href="<?php echo esc_url( $item['url'] ); ?>"
					   class="pv-aside__nav-item<?php echo $screen->id === $item['screen'] ? ' is-active' : ''; ?>">
						<?php echo $item['icon']; // phpcs:ignore WordPress.Security.EscapeOutput -- trusted internal SVG ?>
						<span><?php echo esc_html( $item['label'] ); ?></span>
					</a>
					<?php endforeach; ?>

					<div class="pv-aside__nav-section"><?php esc_html_e( 'Manage', 'pv-youtube-importer' ); ?></div>
					<?php foreach ( $main_nav as $item ) : ?>
					<a href="<?php echo esc_url( $item['url'] ); ?>"
					   class="pv-aside__nav-item<?php echo $screen->id === $item['screen'] ? ' is-active' : ''; ?>">
						<?php echo $item['icon']; // phpcs:ignore WordPress.Security.EscapeOutput -- trusted internal SVG ?>
						<span><?php echo esc_html( $item['label'] ); ?></span>
					</a>
					<?php endforeach; ?>

					<?php if ( 'pv_youtube_page_pv-analytics' === $screen->id ) :
					$focus_site = [
						[ 'id' => 'coach',        'label' => 'Creator Growth Coach', 'icon' => $svgi( '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>' ) ],
						[ 'id' => 'insights',     'label' => 'Performance Insights', 'icon' => $svgi( '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>' ) ],
						[ 'id' => 'trend',        'label' => 'Play Trend',           'icon' => $svgi( '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>' ) ],
						[ 'id' => 'top-videos',   'label' => 'Top Videos',           'icon' => $svgi( '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>' ) ],
						[ 'id' => 'watch-depth',  'label' => 'Watch Depth',          'icon' => $svgi( '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>' ) ],
						[ 'id' => 'all-videos',   'label' => 'All Videos',           'icon' => $svgi( '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18"/>' ) ],
						[ 'id' => 'least-watched','label' => 'Least Watched',        'icon' => $svgi( '<line x1="6" y1="20" x2="6" y2="14"/><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="16"/>' ) ],
						[ 'id' => 'feature',      'label' => 'What to Feature',      'icon' => $svgi( '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>' ) ],
					];
					$focus_yt = [
						[ 'id' => 'coach',        'label' => 'Creator Growth Coach', 'icon' => $svgi( '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>' ) ],
						[ 'id' => 'trend',        'label' => 'View Trend',           'icon' => $svgi( '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>' ) ],
						[ 'id' => 'top-videos',   'label' => 'Top YouTube Videos',   'icon' => $svgi( '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>' ) ],
						[ 'id' => 'watch-depth',  'label' => 'Engagement',           'icon' => $svgi( '<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>' ) ],
						[ 'id' => 'yt-retention', 'label' => 'Lowest Retention',     'icon' => $svgi( '<polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/>' ) ],
						[ 'id' => 'all-videos',   'label' => 'YouTube Performance',  'icon' => $svgi( '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18"/>' ) ],
					];
					?>
					<div class="pv-aside__focus-divider"></div>
					<div class="pv-aside__focus-header">
						<span class="pv-aside__focus-label"><?php esc_html_e( 'Quick Focus', 'pv-youtube-importer' ); ?></span>
						<span class="pv-aside__focus-source-pill" id="pv-focus-source-pill">Site</span>
					</div>
					<div class="pv-aside__focus-group" id="pv-focus-group-site">
					<?php foreach ( $focus_site as $fi ) : ?>
					<button class="pv-aside__nav-item pv-aside__focus-btn"
					        type="button"
					        data-pv-focus="<?php echo esc_attr( $fi['id'] ); ?>">
						<?php echo $fi['icon']; // phpcs:ignore WordPress.Security.EscapeOutput -- trusted internal SVG ?>
						<span><?php echo esc_html( $fi['label'] ); ?></span>
					</button>
					<?php endforeach; ?>
					</div>
					<div class="pv-aside__focus-group" id="pv-focus-group-yt" style="display:none">
					<?php foreach ( $focus_yt as $fi ) : ?>
					<button class="pv-aside__nav-item pv-aside__focus-btn"
					        type="button"
					        data-pv-focus="<?php echo esc_attr( $fi['id'] ); ?>">
						<?php echo $fi['icon']; // phpcs:ignore WordPress.Security.EscapeOutput -- trusted internal SVG ?>
						<span><?php echo esc_html( $fi['label'] ); ?></span>
					</button>
					<?php endforeach; ?>
					</div>
					<?php endif; ?>
				</nav>

			<?php endif; ?>

			<div class="pv-aside__footer">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=pv_youtube' ) ); ?>" class="pv-aside__exit">
					<svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
					<?php esc_html_e( 'Exit to WP Admin', 'pv-youtube-importer' ); ?>
				</a>
			</div>

		</aside>

		<script>
		(function() {
			document.addEventListener('DOMContentLoaded', function() {

				// ── Navigation bridge ─────────────────────────────────────────
				// On nav click: discard the page-load loader and show a fresh one
				// so the outgoing-page transition is immediate and seamless with
				// the incoming page's head-injected loader.
				document.querySelectorAll('#pv-aside .pv-aside__nav-item[href]').forEach(function(link) {
					link.addEventListener('click', function(e) {
						if (link.classList.contains('is-active')) return;
						e.preventDefault();
						var dest = link.href;
						// Reuse the same brand-loader structure as the head-injected loader
						// so the outgoing transition is visually identical to the incoming one.
						var old = document.getElementById('pv-brand-loader');
						if (old) old.parentNode.removeChild(old);
						var mask = document.createElement('div');
						mask.id = 'pv-brand-loader';
						mask.setAttribute('aria-hidden', 'true');
						mask.innerHTML =
							'<div class="pvl-inner">' +
							'<div class="pvl-icon"><svg viewBox="0 0 88 88" fill="none" xmlns="http://www.w3.org/2000/svg">' +
							'<circle cx="44" cy="44" r="40" stroke="rgba(99,102,241,0.12)" stroke-width="3"\/>' +
							'<circle cx="44" cy="44" r="40" stroke="#4f46e5" stroke-width="3" stroke-linecap="round" stroke-dasharray="251" stroke-dashoffset="190" class="pvl-arc"\/>' +
							'<circle cx="44" cy="44" r="30" stroke="rgba(99,102,241,0.07)" stroke-width="18" class="pvl-glow"\/>' +
							'<path d="M37 28 L59 44 L37 60 Z" fill="white" class="pvl-play"\/>' +
							'<\/svg><\/div>' +
							'<div class="pvl-wordmark"><span class="pvl-w1">Press<\/span><span class="pvl-w2">Video<\/span><\/div>' +
							'<div class="pvl-dots"><span><\/span><span><\/span><span><\/span><\/div>' +
							'<\/div>';
						document.body.appendChild(mask);
						requestAnimationFrame(function() {
							requestAnimationFrame(function() {
								window.location.href = dest;
							});
						});
					});
				});

				// ── Analytics quick-focus shortcuts ──────────────────────────
				(function() {
					function triggerFocus(card) {
						card.scrollIntoView({ behavior: 'smooth', block: 'center' });
						setTimeout(function() {
							var btn = card.querySelector('.pva-expand-btn');
							if (btn) btn.click();
						}, 320);
					}
					function focusCard(cardId) {
						var card = document.querySelector('[data-card-id="' + cardId + '"]');
						if (card) { triggerFocus(card); return; }
						// Card not yet in DOM (async-loaded) — wait for it
						var obs = new MutationObserver(function(_, o) {
							var c = document.querySelector('[data-card-id="' + cardId + '"]');
							if (!c) return;
							o.disconnect();
							triggerFocus(c);
						});
						obs.observe(document.body, { childList: true, subtree: true });
						setTimeout(function() { obs.disconnect(); }, 5000);
						// Scroll toward the likely area immediately
						var hint = document.getElementById('pva-ai-row') || document.getElementById('pva-charts-section');
						if (hint) hint.scrollIntoView({ behavior: 'smooth', block: 'start' });
					}
					document.querySelectorAll('#pv-aside .pv-aside__focus-btn[data-pv-focus]').forEach(function(btn) {
						btn.addEventListener('click', function() { focusCard(btn.getAttribute('data-pv-focus')); });
					});
					// Swap Quick Focus links when analytics source tab changes
					document.addEventListener('pva:sourcechange', function(e) {
						var isYt    = e.detail && e.detail.source === 'youtube';
						var siteGrp = document.getElementById('pv-focus-group-site');
						var ytGrp   = document.getElementById('pv-focus-group-yt');
						var pill    = document.getElementById('pv-focus-source-pill');
						if (siteGrp) siteGrp.style.display = isYt ? 'none' : '';
						if (ytGrp)   ytGrp.style.display   = isYt ? '' : 'none';
						if (pill)    pill.textContent = isYt ? 'YouTube' : 'Site';
					});
				}());

				// ── Customizer panel bridge ───────────────────────────────────
				// Wire aside panel buttons to the existing pvc-nav-btn click logic
				var asideBtns = document.querySelectorAll('.pv-aside__panel-btn[data-pv-panel]');
				if (!asideBtns.length) return;
				asideBtns.forEach(function(btn) {
					btn.addEventListener('click', function() {
						var pvcBtn = document.querySelector('.pvc-nav-btn[data-tab="' + btn.dataset.pvPanel + '"]');
						if (pvcBtn) pvcBtn.click();
						asideBtns.forEach(function(b) { b.classList.remove('is-active'); });
						btn.classList.add('is-active');
					});
				});
				// Keep aside in sync when pvc-nav-btn is clicked directly
				document.querySelectorAll('.pvc-nav-btn[data-tab]').forEach(function(pvcBtn) {
					pvcBtn.addEventListener('click', function() {
						asideBtns.forEach(function(b) {
							b.classList.toggle('is-active', b.dataset.pvPanel === pvcBtn.dataset.tab);
						});
					});
				});

			});
		}());
		</script>
		<?php
	}

	public function archive_per_page( WP_Query $q ): void {
		if ( is_admin() || ! $q->is_main_query() ) return;
		$pt = $q->get( 'post_type' );
		if ( is_array( $pt ) ) $pt = (string) reset( $pt );
		if ( 'pv_youtube' !== $pt ) return;

		$raw = sanitize_key( $_GET['per_page'] ?? '' ); // phpcs:ignore
		if ( 'all' === $raw ) {
			$q->set( 'posts_per_page', -1 );
			$q->set( 'nopaging', true );
			return;
		}
		$n   = (int) $raw;
		$ppp = in_array( $n, [ 10, 20, 50 ], true ) ? $n : 20;
		$q->set( 'posts_per_page', $ppp );
		$q->set( 'nopaging', false );
	}

	public function ajax_load_page(): void {
		check_ajax_referer( 'pv_load_page', 'nonce' );

		$settings = get_option( 'pv_settings', [] );
		$_pv_prev_nonce = sanitize_text_field( wp_unslash( $_POST['pv_preview_nonce'] ?? '' ) ); // phpcs:ignore
		if ( $_pv_prev_nonce && wp_verify_nonce( $_pv_prev_nonce, 'pv_preview' ) && current_user_can( 'manage_options' ) ) {
			$_preview = get_transient( 'pv_preview_settings' );
			if ( is_array( $_preview ) ) {
				$settings = array_merge( $settings, $_preview );
			}
		}
		$layout   = $settings['archive_layout'] ?? 'grid';
		$display  = $settings['display_mode']   ?? 'offcanvas';
		$page      = max( 1, (int) ( $_POST['page'] ?? 1 ) );                                       // phpcs:ignore
		$per_page  = sanitize_key( $_POST['per_page'] ?? '' );                                      // phpcs:ignore
		$yt_pl_id  = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $_POST['pv_yt_pl'] ?? '' ) ); // phpcs:ignore

		if ( 'all' === $per_page ) {
			$ppp      = -1;
			$nopaging = true;
		} else {
			$n        = (int) $per_page;
			$ppp      = in_array( $n, [ 10, 20, 50 ], true ) ? $n : 20;
			$nopaging = false;
		}

		$q_args = [
			'post_type'      => 'pv_youtube',
			'post_status'    => 'publish',
			'posts_per_page' => $ppp,
			'paged'          => $page,
			'nopaging'       => $nopaging,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( $yt_pl_id ) {
			$_pl_transient = 'pv_yt_pl_vids_' . md5( $yt_pl_id );
			$_pl_vid_ids   = get_transient( $_pl_transient );
			if ( ! is_array( $_pl_vid_ids ) ) {
				$_api_key    = $settings['api_key'] ?? '';
				$_pl_vid_ids = [];
				if ( $_api_key ) {
					$_yt_api    = new PV_YouTube_API( $_api_key );
					$_pl_videos = $_yt_api->get_playlist_videos( $yt_pl_id, 200 );
					if ( is_array( $_pl_videos ) ) {
						$_pl_vid_ids = array_column( $_pl_videos, 'youtube_id' );
					}
					set_transient( $_pl_transient, $_pl_vid_ids, HOUR_IN_SECONDS );
				}
			}
			if ( $_pl_vid_ids ) {
				$q_args['meta_query'] = [ // phpcs:ignore
					[ 'key' => '_pv_youtube_id', 'value' => $_pl_vid_ids, 'compare' => 'IN' ],
				];
			} else {
				wp_send_json_success( [
					'html'       => '<p class="pv-no-videos">' . esc_html__( 'No imported videos found for this playlist.', 'pv-youtube-importer' ) . '</p>',
					'pagination' => '',
					'max_pages'  => 0,
					'page'       => 1,
				] );
				return;
			}
		}

		$q = new WP_Query( $q_args );

		// Build playlist JSON for offcanvas/modal card buttons (full dataset, capped at 200).
		$pv_playlist_json = '[]';
		if ( in_array( $display, [ 'offcanvas', 'modal' ], true ) && ! empty( $q->posts ) ) {
			$_pl_posts = $q->posts;
			$_found    = (int) $q->found_posts;

			if ( ! $nopaging && $_found > count( $_pl_posts ) && $_found <= 200 ) {
				$_full_args = [
					'post_type'      => 'pv_youtube',
					'post_status'    => 'publish',
					'posts_per_page' => 200,
					'nopaging'       => true,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'no_found_rows'  => true,
				];
				if ( ! empty( $q_args['meta_query'] ) ) {
					$_full_args['meta_query'] = $q_args['meta_query']; // phpcs:ignore
				}
				$_full_q   = new WP_Query( $_full_args );
				$_pl_posts = $_full_q->posts;
				wp_reset_postdata();
			}

			$_pl = [];
			foreach ( $_pl_posts as $_p ) {
				$_yt = get_post_meta( $_p->ID, '_pv_youtube_id', true );
				if ( ! $_yt ) continue;
				$_pl[] = [
					'id'        => $_p->ID,
					'youtubeId' => $_yt,
					'embedUrl'  => 'https://www.youtube.com/embed/' . $_yt,
					'title'     => $_p->post_title,
					'desc'      => wp_trim_words( $_p->post_excerpt ?: $_p->post_content, 20 ),
					'accent'    => pv_resolve_accent_color( $_p->ID ),
					'thumb'     => get_the_post_thumbnail_url( $_p->ID, 'medium' ) ?: '',
					'duration'  => get_post_meta( $_p->ID, '_pv_duration', true ) ?: '',
					'isMusic'   => (bool) get_post_meta( $_p->ID, '_pv_is_music', true ),
					'artist'    => get_post_meta( $_p->ID, '_pv_artist', true ) ?: '',
					'album'     => get_post_meta( $_p->ID, '_pv_album', true ) ?: '',
				];
			}
			$pv_playlist_json = wp_json_encode( $_pl );
		}

		$pv_display       = $display;
		$pv_cards_excerpt = isset( $settings['cards_show_excerpt'] )  ? (bool) $settings['cards_show_excerpt']  : true;
		$pv_cards_cat     = isset( $settings['cards_show_category'] ) ? (bool) $settings['cards_show_category'] : true;
		$pv_cards_views   = isset( $settings['cards_show_views'] )    ? (bool) $settings['cards_show_views']    : true;

		ob_start();

		if ( $q->have_posts() ) {
			if ( 'list' === $layout ) {
				echo '<div class="pv-list">';
				while ( $q->have_posts() ) {
					$q->the_post();
					include PV_PLUGIN_DIR . 'templates/archive/partials/list-item.php';
				}
				echo '</div>';
			} elseif ( 'compact' === $layout ) {
				echo '<div class="pv-grid" style="--pv-cols:4;">';
				while ( $q->have_posts() ) { $q->the_post(); include PV_PLUGIN_DIR . 'templates/archive/partials/card.php'; }
				echo '</div>';
			} elseif ( 'wall' === $layout ) {
				echo '<div class="pv-wall">';
				while ( $q->have_posts() ) { $q->the_post(); include PV_PLUGIN_DIR . 'templates/archive/partials/card.php'; }
				echo '</div>';
			} else {
				// grid, featured, spotlight — render as 3-col grid
				echo '<div class="pv-grid" style="--pv-cols:3;">';
				while ( $q->have_posts() ) { $q->the_post(); include PV_PLUGIN_DIR . 'templates/archive/partials/card.php'; }
				echo '</div>';
			}
		} else {
			echo '<p class="pv-no-videos">' . esc_html__( 'No videos found.', 'pv-youtube-importer' ) . '</p>';
		}

		$html = ob_get_clean();
		wp_reset_postdata();

		// Build pagination HTML
		$max_pages = $q->max_num_pages;
		$base_url  = add_query_arg(
			'per_page',
			( 'all' === $per_page ? 'all' : $ppp ),
			get_post_type_archive_link( 'pv_youtube' )
		);

		$pagination = '';
		if ( ! $nopaging && $max_pages > 1 ) {
			$links = paginate_links( [
				'base'      => add_query_arg( 'paged', '%#%', $base_url ),
				'format'    => '',
				'current'   => $page,
				'total'     => $max_pages,
				'prev_text' => '&#8592; ' . __( 'Prev', 'pv-youtube-importer' ),
				'next_text' => __( 'Next', 'pv-youtube-importer' ) . ' &#8594;',
				'type'      => 'plain',
			] );
			$pagination = '<nav class="navigation pagination" aria-label="'
				. esc_attr__( 'Posts navigation', 'pv-youtube-importer' )
				. '"><div class="nav-links">' . $links . '</div></nav>';
		}

		wp_send_json_success( [
			'html'       => $html,
			'pagination' => $pagination,
			'found'      => $q->found_posts,
			'max_pages'  => $max_pages,
			'page'       => $page,
		] );
	}

	public function ajax_bc_videos(): void {
		check_ajax_referer( 'pv_bc_load', 'nonce' );
		$settings = get_option( 'pv_settings', [] );
		$_pv_prev_nonce = sanitize_text_field( wp_unslash( $_POST['pv_preview_nonce'] ?? '' ) ); // phpcs:ignore
		if ( $_pv_prev_nonce && wp_verify_nonce( $_pv_prev_nonce, 'pv_preview' ) && current_user_can( 'manage_options' ) ) {
			$_preview = get_transient( 'pv_preview_settings' );
			if ( is_array( $_preview ) ) {
				$settings = array_merge( $settings, $_preview );
			}
		}
		$display      = $settings['display_mode'] ?? 'offcanvas';
		$page         = max( 1, (int) ( $_POST['page'] ?? 1 ) );                                    // phpcs:ignore
		$category     = sanitize_key( $_POST['category'] ?? '' );                                   // phpcs:ignore
		$yt_pl_id     = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $_POST['pv_yt_pl'] ?? '' ) ); // phpcs:ignore
		$per_page_raw = sanitize_key( $_POST['per_page'] ?? '' );                                   // phpcs:ignore

		if ( 'all' === $per_page_raw ) {
			$ppp      = -1;
			$nopaging = true;
		} else {
			$n        = (int) $per_page_raw;
			$ppp      = in_array( $n, [ 4, 10, 12, 20, 24, 48, 50 ], true ) ? $n : 24;
			$nopaging = false;
		}

		$query_args = [
			'post_type'      => 'pv_youtube',
			'posts_per_page' => $ppp,
			'paged'          => $page,
			'nopaging'       => $nopaging,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( $category ) {
			$query_args['tax_query'] = [ // phpcs:ignore
				[
					'taxonomy' => 'pv_category',
					'field'    => 'slug',
					'terms'    => $category,
				],
			];
		}

		if ( $yt_pl_id ) {
			$_pl_transient = 'pv_yt_pl_vids_' . md5( $yt_pl_id );
			$_pl_vid_ids   = get_transient( $_pl_transient );
			if ( ! is_array( $_pl_vid_ids ) ) {
				$_api_key   = $settings['api_key'] ?? '';
				$_pl_vid_ids = [];
				if ( $_api_key ) {
					$_yt_api    = new PV_YouTube_API( $_api_key );
					$_pl_videos = $_yt_api->get_playlist_videos( $yt_pl_id, 200 );
					if ( is_array( $_pl_videos ) ) {
						$_pl_vid_ids = array_column( $_pl_videos, 'youtube_id' );
					}
					set_transient( $_pl_transient, $_pl_vid_ids, HOUR_IN_SECONDS );
				}
			}
			if ( $_pl_vid_ids ) {
				$query_args['meta_query'] = [ // phpcs:ignore
					[
						'key'     => '_pv_youtube_id',
						'value'   => $_pl_vid_ids,
						'compare' => 'IN',
					],
				];
			} else {
				wp_send_json_success( [ 'html' => '<p class="pv-no-videos">' . esc_html__( 'No imported videos found for this playlist.', 'pv-youtube-importer' ) . '</p>', 'total' => 0, 'pages' => 0, 'page' => 1 ] );
				return;
			}
		}

		$q = new WP_Query( $query_args );

		$show_views = isset( $settings['cards_show_views'] ) ? (bool) $settings['cards_show_views'] : true;
		$html = '';
		foreach ( $q->posts as $_p ) {
			$html .= pv_bc_card_html( $_p, $display, $show_views );
		}
		wp_reset_postdata();

		// Build full playlist (all pages, capped at 200) for modal/offcanvas navigation.
		$_bc_playlist_json = '[]';
		if ( in_array( $display, [ 'offcanvas', 'modal' ], true ) && ! empty( $q->posts ) ) {
			$_bc_pl_posts = $q->posts;
			$_bc_found    = (int) $q->found_posts;

			if ( ! $nopaging && $_bc_found > count( $_bc_pl_posts ) && $_bc_found <= 200 ) {
				$_bc_full_args = [
					'post_type'      => 'pv_youtube',
					'post_status'    => 'publish',
					'posts_per_page' => 200,
					'nopaging'       => true,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'no_found_rows'  => true,
				];
				if ( ! empty( $query_args['meta_query'] ) ) {
					$_bc_full_args['meta_query'] = $query_args['meta_query']; // phpcs:ignore
				}
				if ( ! empty( $query_args['tax_query'] ) ) {
					$_bc_full_args['tax_query'] = $query_args['tax_query']; // phpcs:ignore
				}
				$_bc_full_q   = new WP_Query( $_bc_full_args );
				$_bc_pl_posts = $_bc_full_q->posts;
				wp_reset_postdata();
			}

			$_bc_pl = [];
			foreach ( $_bc_pl_posts as $_bc_p ) {
				$_bc_yt = get_post_meta( $_bc_p->ID, '_pv_youtube_id', true );
				if ( ! $_bc_yt ) continue;
				$_bc_pl[] = [
					'id'        => $_bc_p->ID,
					'youtubeId' => $_bc_yt,
					'embedUrl'  => 'https://www.youtube.com/embed/' . $_bc_yt,
					'title'     => $_bc_p->post_title,
					'desc'      => '',
					'accent'    => pv_resolve_accent_color( $_bc_p->ID ),
					'thumb'     => get_the_post_thumbnail_url( $_bc_p->ID, 'medium' ) ?: '',
					'duration'  => get_post_meta( $_bc_p->ID, '_pv_duration', true ) ?: '',
					'isMusic'   => (bool) get_post_meta( $_bc_p->ID, '_pv_is_music', true ),
					'artist'    => get_post_meta( $_bc_p->ID, '_pv_artist', true ) ?: '',
					'album'     => get_post_meta( $_bc_p->ID, '_pv_album', true ) ?: '',
				];
			}
			$_bc_playlist_json = wp_json_encode( $_bc_pl );
		}

		$max_pages  = $q->max_num_pages;
		$pagination = '';
		if ( ! $nopaging && $max_pages > 1 ) {
			$_pag_links = paginate_links( [
				'base'      => add_query_arg( 'paged', '%#%', get_post_type_archive_link( 'pv_youtube' ) ),
				'format'    => '',
				'current'   => $page,
				'total'     => $max_pages,
				'prev_text' => '&#8592; ' . __( 'Prev', 'pv-youtube-importer' ),
				'next_text' => __( 'Next', 'pv-youtube-importer' ) . ' &#8594;',
				'type'      => 'plain',
			] );
			$pagination = '<nav class="navigation pagination" aria-label="' . esc_attr__( 'Posts navigation', 'pv-youtube-importer' ) . '"><div class="nav-links">' . $_pag_links . '</div></nav>';
		}

		wp_send_json_success( [
			'html'       => $html,
			'pagination' => $pagination,
			'total'      => $q->found_posts,
			'pages'      => $max_pages,
			'page'       => $page,
			'playlist'   => $_bc_playlist_json,
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

		$render_pl_card = function( string $title, int $count, string $thumb, string $link, string $yt_pl_id = '' ) use ( $pl_svg_sm, $pl_svg_lg ): string {
			$extra_attr = $yt_pl_id ? ' data-pv-yt-pl="' . esc_attr( $yt_pl_id ) . '" data-pv-yt-pl-title="' . esc_attr( $title ) . '"' : '';
			$no_thumb = '<div class="pv-bc-pl-list-card__no-thumb">' . $pl_svg_lg . '</div>';
			$thumb_html = $thumb
				? '<img src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $title ) . '" loading="lazy">'
				: $no_thumb;
			$count_label = sprintf( _n( '%d video', '%d videos', $count, 'pv-youtube-importer' ), $count );
			$view_all = $link
				? '<a href="' . esc_url( $link ) . '" class="pv-bc-pl-list-card__view-all"' . $extra_attr . '>' . esc_html__( 'View All', 'pv-youtube-importer' ) . ' &rarr;</a>'
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
		$_archive_url = (string) get_post_type_archive_link( 'pv_youtube' );
		if ( is_array( $ch_pls ) ) {
			foreach ( $ch_pls as $_cp ) {
				$_count = (int) ( $_cp['count'] ?? 0 );
				if ( $_count === 0 ) continue;
				$cards .= $render_pl_card( $_cp['title'], $_count, $_cp['thumb'] ?? '', $_archive_url, $_cp['id'] );
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
				$cards .= $render_pl_card( $_pls->name, $_pls_count, $_pls_thumb, is_wp_error( $_pls_link ) ? '' : (string) $_pls_link );
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
			'pv-modal',
			PV_PLUGIN_URL . 'assets/dist/css/modal.min.css',
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
			'pv-modal',
			PV_PLUGIN_URL . 'assets/dist/js/modal.min.js',
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
		wp_enqueue_script(
			'pv-tracker',
			PV_PLUGIN_URL . 'assets/dist/js/pv-tracker.min.js',
			[],
			PV_VERSION,
			true
		);
		wp_enqueue_style(
			'pv-music-mode',
			PV_PLUGIN_URL . 'assets/dist/css/music-mode.min.css',
			[ 'pv-offcanvas' ],
			PV_VERSION
		);
		wp_enqueue_script(
			'pv-music-mode',
			PV_PLUGIN_URL . 'assets/dist/js/music-mode.min.js',
			[ 'pv-offcanvas', 'pv-lazy-video' ],
			PV_VERSION,
			true
		);

		$settings = get_option( 'pv_settings', [] );
		$ga_id    = sanitize_text_field( $settings['ga_measurement_id'] ?? '' );

		// Inject Google Analytics 4 if a Measurement ID is configured.
		if ( $ga_id && preg_match( '/^G-[A-Z0-9]{4,15}$/i', $ga_id ) ) {
			wp_enqueue_script(
				'pv-gtag',
				'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $ga_id ),
				[],
				null,
				false // load in <head> per GA best practice
			);
			wp_add_inline_script(
				'pv-gtag',
				"window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config'," . wp_json_encode( $ga_id ) . ',{"send_page_view":true});',
				'after'
			);
		}

		wp_localize_script( 'pv-tracker', 'pvTracker', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'pv_track' ),
			'gaId'    => $ga_id,
		] );

		// Prevent body background flash during navigation between archive and tax archive pages.
		if ( is_post_type_archive( 'pv_youtube' ) || is_tax( [ 'pv_tag', 'pv_category', 'pv_series', 'pv_type' ] ) ) {
			$_pv_arc_bg = ! empty( $settings['page_bg_color'] )
				? ( sanitize_hex_color( $settings['page_bg_color'] ) ?: '#0c0c18' )
				: '#0c0c18';
			wp_add_inline_style( 'pv-grid', "html,body{background-color:{$_pv_arc_bg}}" );
		}

		if ( is_post_type_archive( 'pv_youtube' ) || is_tax( [ 'pv_tag', 'pv_category', 'pv_series', 'pv_type' ] ) ) {
			wp_enqueue_script(
				'pv-archive-filter',
				PV_PLUGIN_URL . 'assets/dist/js/archive-filter.min.js',
				[],
				PV_VERSION,
				true
			);
			$_pv_is_preview_req = (
				isset( $_GET['pv_preview'], $_GET['pv_nonce'] ) // phpcs:ignore
				&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['pv_nonce'] ) ), 'pv_preview' ) // phpcs:ignore
				&& current_user_can( 'manage_options' )
			);
			wp_localize_script( 'pv-archive-filter', 'pvBroadcast', [
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'pv_bc_load' ),
				'loadPageNonce' => wp_create_nonce( 'pv_load_page' ),
				'previewNonce'  => $_pv_is_preview_req ? sanitize_text_field( wp_unslash( $_GET['pv_nonce'] ) ) : '', // phpcs:ignore
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
