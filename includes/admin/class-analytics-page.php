<?php
/**
 * Videos > Analytics admin page.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Analytics_Page {

	public function register(): void {
		add_action( 'admin_menu',            [ $this, 'add_menu'       ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
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
			PV_PLUGIN_URL . 'assets/dist/css/analytics.min.css',
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
		wp_localize_script( 'pv-analytics-admin', 'pvAnalytics', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'pv_analytics_admin' ),
		] );
	}

	public function render_page(): void {
		?>
		<div class="wrap pv-settings-wrap pv-analytics-wrap">

			<!-- ── Header ─────────────────────────────────────── -->
			<div class="pv-page-header">
				<div class="pv-page-header__info">
					<h1 class="pv-page-header__title">
						<span class="pva-header-icon" aria-hidden="true">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
								<path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/>
							</svg>
						</span>
						<?php esc_html_e( 'Analytics', 'pv-youtube-importer' ); ?>
					</h1>
					<p class="pva-header-sub">
						<?php esc_html_e( 'See how your videos are performing.', 'pv-youtube-importer' ); ?>
					</p>
				</div>

				<div class="pva-range-pills" role="group" aria-label="<?php esc_attr_e( 'Date range', 'pv-youtube-importer' ); ?>">
					<button class="pva-pill" data-days="7"><?php esc_html_e( 'Last 7 Days', 'pv-youtube-importer' ); ?></button>
					<button class="pva-pill pva-pill--active" data-days="30"><?php esc_html_e( 'Last 30 Days', 'pv-youtube-importer' ); ?></button>
					<button class="pva-pill" data-days="90"><?php esc_html_e( 'Last 90 Days', 'pv-youtube-importer' ); ?></button>
				</div>
			</div>

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

			</div>

			<!-- ── Charts + Table section (hidden when empty) ─── -->
			<div id="pva-charts-section">

				<!-- Play Trend (line chart, full width) -->
				<div class="pv-card pva-card-trend">
					<div class="pv-card__head">
						<h2 class="pv-card__title"><?php esc_html_e( 'Play Trend', 'pv-youtube-importer' ); ?></h2>
						<span class="pva-chart-sub" id="pva-trend-sub"></span>
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

				<!-- Top Videos + Watch Depth (two-col) -->
				<div class="pva-two-col">

					<div class="pv-card">
						<div class="pv-card__head">
							<h2 class="pv-card__title"><?php esc_html_e( 'Top Videos', 'pv-youtube-importer' ); ?></h2>
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

					<div class="pv-card">
						<div class="pv-card__head">
							<h2 class="pv-card__title"><?php esc_html_e( 'Watch Depth', 'pv-youtube-importer' ); ?></h2>
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

				</div>

				<!-- All Videos Table -->
				<div class="pv-card">
					<div class="pv-card__head">
						<h2 class="pv-card__title"><?php esc_html_e( 'All Videos', 'pv-youtube-importer' ); ?></h2>
					</div>
					<div class="pva-table-wrap" id="pva-table-wrap">
						<div class="pva-loading">
							<div class="pva-skeleton pva-skeleton--row"></div>
							<div class="pva-skeleton pva-skeleton--row"></div>
							<div class="pva-skeleton pva-skeleton--row"></div>
							<div class="pva-skeleton pva-skeleton--row"></div>
						</div>
					</div>
				</div>

			</div><!-- #pva-charts-section -->

			<!-- ── Empty State ────────────────────────────────── -->
			<div class="pva-empty" id="pva-empty" hidden>
				<div class="pva-empty__graphic" aria-hidden="true">
					<svg width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<circle cx="12" cy="12" r="11" fill="#eef2ff"/>
						<path d="M9 8l8 4-8 4V8z" fill="#4f46e5" opacity=".4"/>
						<path d="M9.5 9.2l5.6 2.8-5.6 2.8V9.2z" fill="#4f46e5"/>
					</svg>
				</div>
				<h2 class="pva-empty__title"><?php esc_html_e( 'No plays recorded yet', 'pv-youtube-importer' ); ?></h2>
				<p class="pva-empty__desc">
					<?php esc_html_e( 'Share your videos with your audience and data will start appearing here as people watch.', 'pv-youtube-importer' ); ?>
				</p>
			</div>

		</div><!-- .wrap -->
		<?php
	}
}
