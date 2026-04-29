<?php
/**
 * Videos > Analytics admin page.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Analytics_Page {

	public function register(): void {
		add_action( 'admin_menu',            [ $this, 'add_menu'       ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_filter( 'admin_body_class',      [ $this, 'body_class'     ] );
		add_action( 'admin_init',            [ $this, 'handle_oauth_callback' ] );
	}

	public function body_class( string $classes ): string {
		$screen = get_current_screen();
		if ( $screen && str_contains( $screen->id, 'pv-analytics' ) ) {
			$classes .= ' pva-page';
		}
		return $classes;
	}

	public function handle_oauth_callback(): void {
		if ( ! isset( $_GET['pv_yta_callback'], $_GET['code'], $_GET['state'] ) ) { // phpcs:ignore
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) return;

		$code  = sanitize_text_field( wp_unslash( $_GET['code']  ) ); // phpcs:ignore
		$state = sanitize_text_field( wp_unslash( $_GET['state'] ) ); // phpcs:ignore

		if ( PV_YouTube_OAuth::handle_callback( $code, $state ) ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=pv_youtube&page=pv-analytics&pv_yta_status=connected' ) );
		} else {
			wp_safe_redirect( admin_url( 'edit.php?post_type=pv_youtube&page=pv-analytics&pv_yta_status=error' ) );
		}
		exit;
	}

	public function add_menu(): void {
		add_submenu_page(
			'edit.php?post_type=pv_youtube',
			__( 'Analytics', 'pv-youtube-importer' ),
			__( 'Analytics', 'pv-youtube-importer' ),
			'manage_options',
			'pv-analytics',
			[ $this, 'render_page' ]
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! str_contains( $hook, 'pv-analytics' ) ) return;

		wp_enqueue_style(
			'pv-admin',
			PV_PLUGIN_URL . 'assets/dist/css/admin.min.css',
			[],
			PV_VERSION
		);
		wp_enqueue_style(
			'pv-analytics',
			PV_PLUGIN_URL . 'assets/dist/css/analytics-v2.min.css',
			[ 'pv-admin' ],
			PV_VERSION
		);
		wp_enqueue_script(
			'chart-js',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js',
			[],
			'4.4.4',
			true
		);
		wp_enqueue_script(
			'pv-analytics-admin',
			PV_PLUGIN_URL . 'assets/dist/js/analytics-admin.min.js',
			[ 'chart-js' ],
			PV_VERSION,
			true
		);
		// ── AI Coach: resolve key (Platinum = master key, Gold = own key) ──
		$api_key     = PV_Analytics_Tracker::resolve_ai_key();
		$has_ai_key  = ! empty( $api_key );
		$is_platinum = PV_Tier::meets( 'platinum' );
		$ai_moves    = [];

		$ai_source = 'none';

		$ai_summary = null;

		if ( $has_ai_key ) {
			$transient      = 'pv_ai_insights_' . get_current_user_id() . '_30';
			$cached         = get_transient( $transient );
			$cache_empty    = is_array( $cached ) && empty( $cached );
			$cache_old_fmt  = is_array( $cached ) && ! empty( $cached ) && ! isset( $cached['moves'] );

			$ai_cached_at = null;
			if ( false === $cached || $cache_empty || $cache_old_fmt ) {
				delete_transient( $transient );
				$tracker  = new PV_Analytics_Tracker();
				$data     = PV_Analytics_Tracker::get_dashboard_data( 30 );
				$result   = $tracker->get_ai_insights( $data, $api_key, 30 );
				$ai_moves = $result['moves']   ?? [];
				$ai_summary = $result['summary'] ?? null;
				if ( ! empty( $ai_moves ) ) {
					$result['cached_at'] = time();
					set_transient( $transient, $result, DAY_IN_SECONDS );
					$ai_cached_at = $result['cached_at'];
					$ai_source = 'fresh';
				} else {
					$ai_source = 'api_failed';
				}
			} else {
				$ai_moves   = $cached['moves']   ?? [];
				$ai_summary = $cached['summary'] ?? null;
				$ai_cached_at = $cached['cached_at'] ?? null;
				$ai_source  = 'cached';
			}
		}

		// ── YouTube Analytics state ───────────────────────────────────────
		$yt_connected    = $is_platinum && PV_YouTube_OAuth::is_connected();
		$yt_has_creds    = $is_platinum && PV_YouTube_OAuth::has_credentials();
		$yt_auth_url     = ( $is_platinum && $yt_has_creds && ! $yt_connected )
			? PV_YouTube_OAuth::get_auth_url()
			: '';
		$yt_disconnect_nonce = $yt_connected ? wp_create_nonce( 'pv_yt_disconnect' ) : '';

		wp_localize_script( 'pv-analytics-admin', 'pvAnalytics', [
			'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
			'nonce'              => wp_create_nonce( 'pv_analytics_admin' ),
			'hasData'            => $this->has_any_data(),
			'siteName'           => get_bloginfo( 'name' ),
			'siteUrl'            => home_url(),
			'aiMoves'            => $ai_moves,
			'aiSummary'          => $ai_summary,
			'aiCachedAt'         => $ai_cached_at,
			'hasAiKey'           => $has_ai_key,
			'isPlatinum'         => $is_platinum,
			'settingsUrl'        => admin_url( 'edit.php?post_type=pv_youtube&page=pv-youtube-importer-settings' ),
			'ytConnected'        => $yt_connected,
			'ytHasCreds'         => $yt_has_creds,
			'ytAuthUrl'          => $yt_auth_url,
			'ytDisconnectNonce'  => $yt_disconnect_nonce,
			'aiDebug'            => [
				'source'          => $ai_source,
				'constantDefined' => defined( 'PV_ANTHROPIC_KEY' ),
				'keyResolved'     => $has_ai_key,
				'isPlatinum'      => $is_platinum,
				'moveCount'       => count( $ai_moves ),
			],
		] );
	}

	private function has_any_data(): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'pv_analytics';
		$from  = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE event = 'play' AND created_at >= %s",
			$from
		) );
		return $count > 0;
	}

	public function render_page(): void {
		$settings     = get_option( 'pv_settings', [] );
		$ga_id        = sanitize_text_field( $settings['ga_measurement_id'] ?? '' );
		$settings_url = admin_url( 'edit.php?post_type=pv_youtube&page=pv-youtube-importer-settings' );

		$is_platinum         = PV_Tier::meets( 'platinum' );
		$yt_connected        = $is_platinum && PV_YouTube_OAuth::is_connected();
		$yt_has_creds        = $is_platinum && PV_YouTube_OAuth::has_credentials();
		$yt_auth_url         = ( $is_platinum && $yt_has_creds && ! $yt_connected )
			? PV_YouTube_OAuth::get_auth_url()
			: '';
		$yt_disconnect_nonce = $yt_connected ? wp_create_nonce( 'pv_yt_disconnect' ) : '';

		$_cbtn = '<button class="pva-expand-btn" type="button" aria-label="' . esc_attr__( 'Focus this block', 'pv-youtube-importer' ) . '"><svg class="pva-expand-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg></button>';
		?>
		<div class="wrap pva-wrap">
		<div class="pva-inner">

			<!-- ── Branded Hero ───────────────────────────────── -->
			<div class="pva-hero">
				<div class="pva-hero__orb pva-hero__orb--1"></div>
				<div class="pva-hero__orb pva-hero__orb--2"></div>

				<div class="pva-hero__content">
					<p class="pva-hero__eyebrow">PressVideo</p>
					<h1 class="pva-hero__title"><?php esc_html_e( 'Analytics', 'pv-youtube-importer' ); ?></h1>
					<p class="pva-hero__sub">
						<?php esc_html_e( 'Track plays, watch depth, and engagement across all your videos.', 'pv-youtube-importer' ); ?>
					</p>
					<button class="pva-demo-btn" id="pva-demo-toggle" type="button">
						<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
						<?php esc_html_e( 'Preview sample data', 'pv-youtube-importer' ); ?>
					</button>
				</div>

			</div><!-- .pva-hero -->

			<!-- ── Data source toggle (Site / YouTube) ──────────── -->
			<?php if ( $is_platinum ) : ?>
			<div class="pva-source-toggle" id="pva-source-toggle" role="group" aria-label="<?php esc_attr_e( 'Data source', 'pv-youtube-importer' ); ?>">
				<button class="pva-source-btn pva-source-btn--active" data-source="site" type="button">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>
					<?php esc_html_e( 'Site Stats', 'pv-youtube-importer' ); ?>
				</button>
				<button class="pva-source-btn" data-source="youtube" type="button">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.582 6.186a2.506 2.506 0 00-1.768-1.768C18.254 4 12 4 12 4s-6.254 0-7.814.418a2.506 2.506 0 00-1.768 1.768C2 7.746 2 12 2 12s0 4.254.418 5.814a2.506 2.506 0 001.768 1.768C5.746 20 12 20 12 20s6.254 0 7.814-.418a2.506 2.506 0 001.768-1.768C22 16.254 22 12 22 12s0-4.254-.418-5.814zM10 15.464V8.536L16 12l-6 3.464z"/></svg>
					<?php esc_html_e( 'YouTube Stats', 'pv-youtube-importer' ); ?>
				</button>
			</div>
			<?php endif; ?>

			<!-- ── Export bar ────────────────────────────────────── -->
			<div class="pva-export-bar">
				<span class="pva-export-bar__label">
					<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
					<?php esc_html_e( 'Export', 'pv-youtube-importer' ); ?>
				</span>
				<div class="pva-export-bar__actions">
					<button class="pva-export-btn" id="pva-export-csv" type="button" disabled>
						<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 7V3.5L18.5 9H13zm-5 4h8v1H8v-1zm0 3h8v1H8v-1zm0-6h3v1H8v-1z"/></svg>
						CSV
					</button>
					<button class="pva-export-btn" id="pva-export-json" type="button" disabled>
						<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 3h2v2H5v5a2 2 0 01-2 2 2 2 0 012 2v5h2v2H5c-1.07-.27-2-.9-2-2v-4a2 2 0 00-2-2H0v-2h1a2 2 0 002-2V5a2 2 0 012-2zm14 0c1.07.27 2 .9 2 2v4a2 2 0 002 2h1v2h-1a2 2 0 00-2 2v4a2 2 0 01-2 2h-2v-2h2v-5a2 2 0 012-2 2 2 0 01-2-2V5h-2V3h2z"/></svg>
						JSON
					</button>
					<div class="pva-export-sep" aria-hidden="true"></div>
					<button class="pva-export-btn pva-export-btn--report" id="pva-report-btn" type="button" disabled>
						<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 5h-3v5.5a2.5 2.5 0 01-5 0 2.5 2.5 0 012.5-2.5c.46 0 .89.13 1.25.34V5h4v2zM4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6z"/></svg>
						<?php esc_html_e( 'Download Report', 'pv-youtube-importer' ); ?>
					</button>
				</div>
				<div class="pva-range-pills" role="group" aria-label="<?php esc_attr_e( 'Date range', 'pv-youtube-importer' ); ?>">
					<button class="pva-pill" data-days="7"><?php esc_html_e( 'Last 7 Days', 'pv-youtube-importer' ); ?></button>
					<button class="pva-pill pva-pill--active" data-days="30"><?php esc_html_e( 'Last 30 Days', 'pv-youtube-importer' ); ?></button>
					<button class="pva-pill" data-days="90"><?php esc_html_e( 'Last 90 Days', 'pv-youtube-importer' ); ?></button>
				</div>
			</div><!-- .pva-export-bar -->

			<!-- ── Stat Cards ─────────────────────────────────── -->
			<div class="pva-stats-row">

				<div class="pva-stat-card">
					<div class="pva-stat-icon pva-icon--plays" aria-hidden="true">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
					</div>
					<div class="pva-stat-body">
						<div class="pva-stat-value" id="pva-total-plays">—</div>
						<div class="pva-stat-label"><?php esc_html_e( 'Total Plays', 'pv-youtube-importer' ); ?></div>
					</div>
				</div>

				<div class="pva-stat-card">
					<div class="pva-stat-icon pva-icon--videos" aria-hidden="true">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>
					</div>
					<div class="pva-stat-body">
						<div class="pva-stat-value" id="pva-unique-videos">—</div>
						<div class="pva-stat-label"><?php esc_html_e( 'Videos Played', 'pv-youtube-importer' ); ?></div>
					</div>
				</div>

				<div class="pva-stat-card">
					<div class="pva-stat-icon pva-icon--completion" aria-hidden="true">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>
					</div>
					<div class="pva-stat-body">
						<div class="pva-stat-value" id="pva-avg-completion">—</div>
						<div class="pva-stat-label"><?php esc_html_e( 'Avg Completion', 'pv-youtube-importer' ); ?></div>
					</div>
				</div>

			</div><!-- .pva-stats-row -->

			<!-- ── Integration Cards — GA4 + YouTube Analytics ─── -->
			<?php
			$yt_status_msg = '';
			if ( isset( $_GET['pv_yta_status'] ) ) { // phpcs:ignore
				$yt_status_msg = sanitize_key( $_GET['pv_yta_status'] ); // phpcs:ignore
			}
			?>
			<div class="pva-integrations-row">

				<!-- Google Analytics 4 -->
				<div class="pva-ga-card <?php echo $ga_id ? 'pva-ga-card--connected' : 'pva-ga-card--disconnected'; ?>">
					<div class="pva-ga-card__icon" aria-hidden="true">
						<svg width="44" height="44" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
							<rect width="44" height="44" rx="10" fill="#fff5e6"/>
							<rect x="9"  y="26" width="8" height="11" rx="2" fill="#F9AB00"/>
							<rect x="21" y="18" width="8" height="19" rx="2" fill="#E37400"/>
							<rect x="27" y="18" width="8" height="19" rx="2" fill="#E37400" opacity="0.4"/>
							<rect x="9"  y="26" width="8" height="11" rx="2" fill="#F9AB00"/>
							<polyline points="10,24 18,18 26,20 35,11" fill="none" stroke="#E37400" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0.6"/>
							<circle cx="35" cy="11" r="2.5" fill="#E37400"/>
						</svg>
					</div>
					<div class="pva-ga-card__body">
						<div class="pva-ga-card__head-row">
							<h3 class="pva-ga-card__title">Google Analytics 4</h3>
							<?php if ( $ga_id ) : ?>
								<span class="pva-ga-badge pva-ga-badge--on"><span class="pva-ga-badge__dot"></span><?php esc_html_e( 'Active', 'pv-youtube-importer' ); ?></span>
							<?php else : ?>
								<span class="pva-ga-badge pva-ga-badge--off"><?php esc_html_e( 'Not connected', 'pv-youtube-importer' ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( $ga_id ) : ?>
							<p class="pva-ga-card__id"><?php esc_html_e( 'Measurement ID:', 'pv-youtube-importer' ); ?> <code><?php echo esc_html( $ga_id ); ?></code></p>
							<div class="pva-ga-events">
								<span class="pva-ga-event"><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>pv_video_play</span>
								<span class="pva-ga-event"><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>pv_watch_depth (25 / 50 / 75 / 100%)</span>
							</div>
						<?php else : ?>
							<p class="pva-ga-card__desc"><?php esc_html_e( 'Add your GA4 Measurement ID to automatically send video play events and watch depth milestones to Google Analytics.', 'pv-youtube-importer' ); ?></p>
						<?php endif; ?>
					</div>
					<div class="pva-ga-card__action">
						<?php if ( $ga_id ) : ?>
							<a href="https://analytics.google.com/analytics/web/" target="_blank" rel="noopener noreferrer" class="pva-ga-btn pva-ga-btn--primary">
								<?php esc_html_e( 'Open Google Analytics', 'pv-youtube-importer' ); ?>
								<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19 19H5V5h7V3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>
							</a>
						<?php else : ?>
							<a href="<?php echo esc_url( $settings_url ); ?>" class="pva-ga-btn pva-ga-btn--secondary">
								<?php esc_html_e( 'Connect in Settings', 'pv-youtube-importer' ); ?>
								<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
							</a>
						<?php endif; ?>
					</div>
				</div><!-- .pva-ga-card -->

				<!-- YouTube Analytics -->
				<div class="pva-yta-card <?php echo $yt_connected ? 'pva-yta-card--connected' : ( $is_platinum ? 'pva-yta-card--disconnected' : 'pva-yta-card--locked' ); ?>" id="pva-yta-card">
					<div class="pva-yta-card__icon" aria-hidden="true">
						<svg width="44" height="44" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
							<rect width="44" height="44" rx="10" fill="#fef2f2"/>
							<path d="M34.582 14.186a2.506 2.506 0 00-1.768-1.768C31.254 12 22 12 22 12s-9.254 0-10.814.418a2.506 2.506 0 00-1.768 1.768C9 15.746 9 22 9 22s0 6.254.418 7.814a2.506 2.506 0 001.768 1.768C12.746 32 22 32 22 32s9.254 0 10.814-.418a2.506 2.506 0 001.768-1.768C35 28.254 35 22 35 22s0-6.254-.418-7.814zM19.5 26.464v-8.928L26.5 22l-7 4.464z" fill="#ef4444"/>
						</svg>
					</div>
					<div class="pva-yta-card__body">
						<div class="pva-yta-card__head-row">
							<h3 class="pva-yta-card__title">
								<?php esc_html_e( 'YouTube Analytics', 'pv-youtube-importer' ); ?>
								<?php if ( ! $is_platinum ) : ?><span class="pv-tier-lock-badge" style="font-size:.75rem;vertical-align:middle">Platinum</span><?php endif; ?>
							</h3>
							<?php if ( $yt_connected ) : ?>
								<span class="pva-ga-badge pva-ga-badge--on"><span class="pva-ga-badge__dot"></span><?php esc_html_e( 'Connected', 'pv-youtube-importer' ); ?></span>
							<?php elseif ( $is_platinum ) : ?>
								<span class="pva-ga-badge pva-ga-badge--off"><?php esc_html_e( 'Not connected', 'pv-youtube-importer' ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( $yt_status_msg === 'connected' ) : ?>
							<p class="pva-yta-card__notice pva-yta-card__notice--success">
								<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
								<?php esc_html_e( 'Successfully connected! Your YouTube stats are now available.', 'pv-youtube-importer' ); ?>
							</p>
						<?php elseif ( $yt_status_msg === 'error' ) : ?>
							<p class="pva-yta-card__notice pva-yta-card__notice--error"><?php esc_html_e( 'Authorization failed. Please try connecting again.', 'pv-youtube-importer' ); ?></p>
						<?php endif; ?>
						<?php if ( $yt_connected ) : ?>
							<p class="pva-ga-card__desc"><?php esc_html_e( 'Your YouTube channel is authorized. Use the "YouTube Stats" toggle above to view channel-level views, watch time, and engagement data.', 'pv-youtube-importer' ); ?></p>
						<?php elseif ( $is_platinum && $yt_has_creds ) : ?>
							<p class="pva-ga-card__desc"><?php esc_html_e( 'OAuth credentials saved. Click Connect to authorize PressVideo to read your YouTube Analytics data.', 'pv-youtube-importer' ); ?></p>
						<?php elseif ( $is_platinum ) : ?>
							<p class="pva-ga-card__desc"><?php esc_html_e( 'Pull YouTube channel views, watch time, and subscriber growth directly into this dashboard.', 'pv-youtube-importer' ); ?></p>
						<?php else : ?>
							<p class="pva-ga-card__desc"><?php esc_html_e( 'Platinum feature: compare YouTube channel stats against your site plays to find your biggest growth opportunities.', 'pv-youtube-importer' ); ?></p>
						<?php endif; ?>
					</div>
					<div class="pva-ga-card__action">
						<?php if ( $yt_connected ) : ?>
							<button class="pva-ga-btn pva-ga-btn--danger" id="pva-yta-disconnect" type="button" data-nonce="<?php echo esc_attr( $yt_disconnect_nonce ); ?>"><?php esc_html_e( 'Disconnect', 'pv-youtube-importer' ); ?></button>
						<?php elseif ( $is_platinum && $yt_has_creds ) : ?>
							<a href="<?php echo esc_url( $yt_auth_url ); ?>" class="pva-ga-btn pva-ga-btn--yt">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.582 6.186a2.506 2.506 0 00-1.768-1.768C18.254 4 12 4 12 4s-6.254 0-7.814.418a2.506 2.506 0 00-1.768 1.768C2 7.746 2 12 2 12s0 4.254.418 5.814a2.506 2.506 0 001.768 1.768C5.746 20 12 20 12 20s6.254 0 7.814-.418a2.506 2.506 0 001.768-1.768C22 16.254 22 12 22 12s0-4.254-.418-5.814zM10 15.464V8.536L16 12l-6 3.464z"/></svg>
								<?php esc_html_e( 'Connect YouTube Analytics', 'pv-youtube-importer' ); ?>
							</a>
						<?php elseif ( $is_platinum ) : ?>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=pv_youtube&page=pv-youtube-importer-settings' ) ); ?>" class="pva-ga-btn pva-ga-btn--secondary">
								<?php esc_html_e( 'Add Credentials in Settings', 'pv-youtube-importer' ); ?>
								<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
							</a>
						<?php else : ?>
							<a href="https://pressvideo.com" target="_blank" rel="noopener" class="pva-ga-btn pva-ga-btn--secondary">
								<?php esc_html_e( 'Upgrade to Platinum', 'pv-youtube-importer' ); ?>
								<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
							</a>
						<?php endif; ?>
					</div>
				</div><!-- .pva-yta-card -->

			</div><!-- .pva-integrations-row -->

			<!-- ── Dynamic Analytics Summary (JS-rendered) ──────── -->
			<div id="pva-summary-section" hidden></div>

			<!-- ── Row 1: AI Blocks — Coach + Performance Insights ── -->
			<div class="pva-ai-row" id="pva-ai-row" hidden>
				<div class="pva-ai-row__coach" id="pva-coach-col"></div>
				<div class="pva-ai-row__insights" id="pva-insights-col"></div>
			</div>

			<!-- ── Row 2: Charts — Play Trend + Top Videos + Watch Depth ── -->
			<div class="pva-charts-row" id="pva-charts-section">

				<!-- Play Trend -->
				<div class="pv-card" data-card-id="trend">
					<div class="pv-card__head">
						<h2 class="pv-card__title"><?php esc_html_e( 'Play Trend', 'pv-youtube-importer' ); ?></h2>
						<span class="pva-chart-sub" id="pva-trend-sub"></span>
						<button class="pva-tip-btn" type="button" data-tip="trend" aria-label="<?php esc_attr_e( 'About Play Trend', 'pv-youtube-importer' ); ?>">?</button>
						<?php echo $_cbtn; // phpcs:ignore ?>
					</div>
					<div class="pv-card__body">
						<div class="pva-chart-wrap pva-chart-wrap--trend">
							<canvas id="pva-trend-chart" aria-label="<?php esc_attr_e( 'Play trend over time', 'pv-youtube-importer' ); ?>" role="img"></canvas>
						</div>
						<div class="pva-loading" id="pva-trend-loading" aria-live="polite">
							<div class="pva-skeleton pva-skeleton--chart"></div>
						</div>
					</div>
				</div>

				<!-- Top Videos -->
				<div class="pv-card" data-card-id="top-videos">
					<div class="pv-card__head">
						<h2 class="pv-card__title"><?php esc_html_e( 'Top Videos', 'pv-youtube-importer' ); ?></h2>
						<button class="pva-tip-btn" type="button" data-tip="top-videos" aria-label="<?php esc_attr_e( 'About Top Videos', 'pv-youtube-importer' ); ?>">?</button>
						<?php echo $_cbtn; // phpcs:ignore ?>
					</div>
					<div class="pv-card__body">
						<div class="pva-chart-wrap pva-chart-wrap--top">
							<canvas id="pva-top-chart" aria-label="<?php esc_attr_e( 'Top videos by play count', 'pv-youtube-importer' ); ?>" role="img"></canvas>
						</div>
						<div class="pva-loading" id="pva-top-loading">
							<div class="pva-skeleton pva-skeleton--bars"></div>
						</div>
					</div>
				</div>

				<!-- Watch Depth -->
				<div class="pv-card" data-card-id="watch-depth">
					<div class="pv-card__head">
						<h2 class="pv-card__title"><?php esc_html_e( 'Watch Depth', 'pv-youtube-importer' ); ?></h2>
						<button class="pva-tip-btn" type="button" data-tip="watch-depth" aria-label="<?php esc_attr_e( 'About Watch Depth', 'pv-youtube-importer' ); ?>">?</button>
						<?php echo $_cbtn; // phpcs:ignore ?>
					</div>
					<div class="pv-card__body">
						<div class="pva-chart-wrap pva-chart-wrap--donut">
							<canvas id="pva-depth-chart" aria-label="<?php esc_attr_e( 'Watch depth distribution', 'pv-youtube-importer' ); ?>" role="img"></canvas>
						</div>
						<div class="pva-loading" id="pva-depth-loading">
							<div class="pva-skeleton pva-skeleton--donut"></div>
						</div>
					</div>
				</div>

			</div><!-- .pva-charts-row -->

			<!-- ── Row 3: Tables — All Videos + Least Watched ────── -->
			<div class="pva-table-row" id="pva-table-row" hidden>

				<div class="pv-card pva-table-row__all" data-card-id="all-videos">
					<div class="pv-card__head">
						<h2 class="pv-card__title"><?php esc_html_e( 'All Videos', 'pv-youtube-importer' ); ?></h2>
						<button class="pva-tip-btn" type="button" data-tip="all-videos" aria-label="<?php esc_attr_e( 'About All Videos', 'pv-youtube-importer' ); ?>">?</button>
						<?php echo $_cbtn; // phpcs:ignore ?>
					</div>
					<div class="pva-table-wrap" id="pva-table-wrap">
						<div class="pva-loading" style="padding:16px">
							<div class="pva-skeleton pva-skeleton--row"></div>
							<div class="pva-skeleton pva-skeleton--row"></div>
							<div class="pva-skeleton pva-skeleton--row"></div>
							<div class="pva-skeleton pva-skeleton--row"></div>
						</div>
					</div>
				</div>

				<div class="pva-table-row__least" id="pva-least-col"></div>

			</div><!-- .pva-table-row -->

			<!-- ── Row 4: What to Feature (JS-rendered) ──────────── -->
			<div id="pva-feature-section" hidden></div>

			<!-- ── Shared tooltip popover ────────────────────────── -->
			<div id="pva-tip-pop" role="tooltip" hidden>
				<div class="pva-tip-pop__arrow"></div>
				<p class="pva-tip-pop__text"></p>
			</div>

			<!-- ── Empty State ────────────────────────────────── -->
			<div class="pva-empty" id="pva-empty" hidden>
				<div class="pva-empty__graphic" aria-hidden="true">
					<svg width="88" height="88" viewBox="0 0 88 88" fill="none" xmlns="http://www.w3.org/2000/svg">
						<circle cx="44" cy="44" r="44" fill="#eef2ff"/>
						<rect x="24" y="54" width="10" height="20" rx="3" fill="#c7d2fe"/>
						<rect x="39" y="40" width="10" height="34" rx="3" fill="#818cf8"/>
						<rect x="54" y="28" width="10" height="46" rx="3" fill="#4f46e5"/>
						<circle cx="56" cy="24" r="6" fill="#4f46e5" opacity=".2"/>
						<path d="M22 50 L44 34 L66 22" stroke="#4f46e5" stroke-width="2" stroke-linecap="round" opacity=".4"/>
					</svg>
				</div>
				<h2 class="pva-empty__title"><?php esc_html_e( 'No plays recorded yet', 'pv-youtube-importer' ); ?></h2>
				<p class="pva-empty__desc">
					<?php esc_html_e( 'Share your videos with your audience and data will appear here as people watch.', 'pv-youtube-importer' ); ?>
				</p>
			</div>

		</div><!-- .pva-inner -->
		</div><!-- .pva-wrap -->
		<?php
	}
}
