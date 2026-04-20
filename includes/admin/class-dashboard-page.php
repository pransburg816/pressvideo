<?php
/**
 * Videos > Dashboard — stats, playback mode, archive layout, shortcode reference.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Dashboard_Page {

	public function register(): void {
		add_action( 'admin_menu',                     [ $this, 'add_menu' ] );
		add_action( 'wp_ajax_pv_save_display_mode',    [ $this, 'ajax_save_mode' ] );
		add_action( 'wp_ajax_pv_save_archive_layout',  [ $this, 'ajax_save_layout' ] );
		add_action( 'wp_ajax_pv_save_content_width',   [ $this, 'ajax_save_content_width' ] );
		add_action( 'manage_posts_extra_tablenav',    [ $this, 'list_info_bar' ] );
		add_action( 'admin_footer',                   [ $this, 'print_js' ] );
	}

	public function add_menu(): void {
		add_submenu_page(
			'edit.php?post_type=pv_youtube',
			__( 'PressVideo Dashboard', 'pv-youtube-importer' ),
			__( 'Dashboard', 'pv-youtube-importer' ),
			'manage_options',
			'pv-youtube-importer-dashboard',
			[ $this, 'render_page' ]
		);
	}

	// ── Info bar on the All Videos list screen ──────────────────────

	public function list_info_bar( string $which ): void {
		if ( 'top' !== $which ) return;
		$screen = get_current_screen();
		if ( ! $screen || 'pv_youtube' !== ( $screen->post_type ?? '' ) ) return;

		$last   = get_option( 'pv_last_import', null );
		$count  = (int) ( wp_count_posts( 'pv_youtube' )->publish ?? 0 );
		$limit  = PV_Tier::get_video_limit();
		$dash   = admin_url( 'edit.php?post_type=pv_youtube&page=pv-youtube-importer-dashboard' );
		?>
		<div class="pv-list-infobar">
			<span class="pv-list-stat">
				<strong><?php echo esc_html( $count ); ?></strong>
				<?php if ( $limit !== PHP_INT_MAX ) : ?>
					/ <?php echo esc_html( $limit ); ?>
				<?php endif; ?>
				<?php esc_html_e( 'videos', 'pv-youtube-importer' ); ?>
			</span>
			<span class="pv-list-sep">&middot;</span>
			<?php if ( $last ) : ?>
				<span class="pv-list-stat">
					<?php esc_html_e( 'Last import:', 'pv-youtube-importer' ); ?>
					<strong><?php echo esc_html( human_time_diff( $last['time'], time() ) . ' ' . __( 'ago', 'pv-youtube-importer' ) ); ?></strong>
					&mdash; <?php echo esc_html( sprintf(
						/* translators: 1: imported count, 2: skipped count */
						__( '%1$d imported, %2$d skipped', 'pv-youtube-importer' ),
						$last['imported'],
						$last['skipped']
					) ); ?>
				</span>
			<?php else : ?>
				<span class="pv-list-stat pv-list-stat--muted">
					<?php esc_html_e( 'No imports yet', 'pv-youtube-importer' ); ?>
				</span>
			<?php endif; ?>
			<a href="<?php echo esc_url( $dash ); ?>" class="button button-small pv-list-dash-link">
				<?php esc_html_e( 'Dashboard', 'pv-youtube-importer' ); ?>
			</a>
		</div>
		<?php
	}

	// ── AJAX: save display_mode (offcanvas / page) ───────────────────

	public function ajax_save_mode(): void {
		check_ajax_referer( 'pv_save_mode_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		$mode = sanitize_key( $_POST['mode'] ?? '' );
		if ( ! in_array( $mode, [ 'offcanvas', 'page' ], true ) ) {
			wp_send_json_error( 'Invalid mode' );
		}
		$settings                 = get_option( 'pv_settings', [] );
		$settings['display_mode'] = $mode;
		update_option( 'pv_settings', $settings );
		wp_send_json_success( [ 'mode' => $mode ] );
	}

	// ── AJAX: save archive_layout (grid / list / featured / compact) ──

	public function ajax_save_layout(): void {
		check_ajax_referer( 'pv_save_mode_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		$layout = sanitize_key( $_POST['layout'] ?? '' );
		if ( ! in_array( $layout, [ 'grid', 'list', 'featured', 'compact' ], true ) ) {
			wp_send_json_error( 'Invalid layout' );
		}
		$settings                    = get_option( 'pv_settings', [] );
		$settings['archive_layout']  = $layout;
		update_option( 'pv_settings', $settings );
		wp_send_json_success( [ 'layout' => $layout ] );
	}

	// ── AJAX: save content_width (full / wide / medium / narrow) ─────

	public function ajax_save_content_width(): void {
		check_ajax_referer( 'pv_save_mode_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		$width = sanitize_key( $_POST['width'] ?? '' );
		if ( ! in_array( $width, [ 'full', 'wide', 'medium', 'narrow' ], true ) ) {
			wp_send_json_error( 'Invalid width' );
		}
		$settings                    = get_option( 'pv_settings', [] );
		$settings['content_width']   = $width;
		update_option( 'pv_settings', $settings );
		wp_send_json_success( [ 'width' => $width ] );
	}

	// ── Page render ──────────────────────────────────────────────────

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		// Temporary diagnostic — remove after debugging shortcode issue
		$diag_settings = get_option( 'pv_settings', [] );
		$diag_cpt      = post_type_exists( 'pv_youtube' );
		$diag_sc       = shortcode_exists( 'pv_video_grid' );
		$diag_query    = new WP_Query( [ 'post_type' => 'pv_youtube', 'posts_per_page' => 1, 'fields' => 'ids' ] );
		$diag_count    = $diag_query->found_posts;
		echo '<div class="notice notice-warning" style="padding:10px;margin:10px 0;font-family:monospace;">';
		echo '<strong>PV Debug:</strong> ';
		echo 'CPT registered: ' . ( $diag_cpt ? '✓ YES' : '✗ NO' ) . ' | ';
		echo 'Shortcodes: ' . ( $diag_sc ? '✓ YES' : '✗ NO' ) . ' | ';
		echo 'Videos found: ' . $diag_count . ' | ';
		echo 'display_mode: ' . esc_html( $diag_settings['display_mode'] ?? 'NOT SET' ) . ' | ';
		echo 'pv_settings type: ' . gettype( $diag_settings ) . ' | ';
		echo 'PHP: ' . PHP_VERSION . ' | ';
		echo 'PV_VERSION: ' . ( defined( 'PV_VERSION' ) ? PV_VERSION : 'UNDEFINED' );
		echo '</div>';

		$settings      = get_option( 'pv_settings', [] );
		$tier          = PV_Tier::current();
		$limit         = PV_Tier::get_video_limit();
		$video_count   = (int) ( wp_count_posts( 'pv_youtube' )->publish ?? 0 );
		$last          = get_option( 'pv_last_import', null );
		$mode          = $settings['display_mode']   ?? 'offcanvas';
		$layout        = $settings['archive_layout'] ?? 'grid';
		$cwidth        = $settings['content_width']  ?? 'full';
		?>
		<div class="wrap pv-settings-wrap">

			<!-- Header -->
			<div class="pv-page-header">
				<h1>
					<span class="dashicons dashicons-video-alt3"></span>
					<?php esc_html_e( 'PressVideo', 'pv-youtube-importer' ); ?>
				</h1>
				<span class="pv-tier-badge">
					<?php echo esc_html( ucfirst( $tier ) ); ?> <?php esc_html_e( 'Plan', 'pv-youtube-importer' ); ?>
					<?php if ( ! PV_Tier::is_gold() ) : ?>
						&nbsp;&middot;&nbsp;
						<a href="https://pressvideo.com" target="_blank" rel="noopener">
							<?php esc_html_e( 'Upgrade →', 'pv-youtube-importer' ); ?>
						</a>
					<?php endif; ?>
				</span>
			</div>

			<!-- Stats row -->
			<div class="pv-stats-row">

				<div class="pv-stat-card">
					<span class="pv-stat-card__value"><?php echo esc_html( $video_count ); ?></span>
					<span class="pv-stat-card__label">
						<?php echo $limit === PHP_INT_MAX
							? esc_html__( 'Total Videos', 'pv-youtube-importer' )
							: esc_html( sprintf( __( 'of %d Video Limit', 'pv-youtube-importer' ), $limit ) ); ?>
					</span>
				</div>

				<div class="pv-stat-card">
					<?php if ( $last ) : ?>
						<span class="pv-stat-card__value pv-stat-card__value--sm">
							<?php echo esc_html( human_time_diff( $last['time'], time() ) ); ?> <?php esc_html_e( 'ago', 'pv-youtube-importer' ); ?>
						</span>
						<span class="pv-stat-card__label">
							<?php echo esc_html( sprintf(
								/* translators: count of imported videos */
								__( 'Last Import · %d added', 'pv-youtube-importer' ),
								$last['imported']
							) ); ?>
						</span>
					<?php else : ?>
						<span class="pv-stat-card__value pv-stat-card__value--dash">&mdash;</span>
						<span class="pv-stat-card__label"><?php esc_html_e( 'No Imports Yet', 'pv-youtube-importer' ); ?></span>
					<?php endif; ?>
				</div>

				<div class="pv-stat-card">
					<span class="pv-stat-card__value pv-stat-card__value--sm" id="pv-active-mode-label">
						<?php echo esc_html( $mode === 'offcanvas' ? __( 'Offcanvas', 'pv-youtube-importer' ) : __( 'Watch Page', 'pv-youtube-importer' ) ); ?>
					</span>
					<span class="pv-stat-card__label"><?php esc_html_e( 'Active Playback Mode', 'pv-youtube-importer' ); ?></span>
				</div>

			</div>

			<!-- Two-column: Import + Playback Mode -->
			<div class="pv-dash-cols">

				<!-- Import -->
				<div class="pv-card pv-card--narrow">
					<div class="pv-card__head">
						<div class="pv-card__icon"><span class="dashicons dashicons-download"></span></div>
						<div class="pv-card__head-text">
							<h2><?php esc_html_e( 'Import', 'pv-youtube-importer' ); ?></h2>
							<p><?php esc_html_e( 'Fetch the latest videos from your channel.', 'pv-youtube-importer' ); ?></p>
						</div>
					</div>
					<div class="pv-card__body">
						<button id="pv-run-import" class="button button-primary" type="button">
							<?php esc_html_e( 'Run Import Now', 'pv-youtube-importer' ); ?>
						</button>
						<span id="pv-import-spinner" class="spinner" style="float:none;margin:0 6px;vertical-align:middle;"></span>
						<?php wp_nonce_field( 'pv_manual_import_nonce', 'pv_import_nonce' ); ?>
						<div id="pv-import-result"></div>
					</div>
				</div>

				<!-- Playback Mode -->
				<div class="pv-card pv-card--grow">
					<div class="pv-card__head">
						<div class="pv-card__icon"><span class="dashicons dashicons-layout"></span></div>
						<div class="pv-card__head-text">
							<h2><?php esc_html_e( 'Playback Mode', 'pv-youtube-importer' ); ?></h2>
							<p><?php esc_html_e( 'How visitors watch videos when clicking a card in the [pv_video_grid] shortcode.', 'pv-youtube-importer' ); ?></p>
						</div>
					</div>
					<div class="pv-card__body">
						<div class="pv-visual-picker" data-controls="display-mode" data-ajax="1">

							<label class="pv-pick-card <?php echo $mode === 'offcanvas' ? 'is-selected' : ''; ?>">
								<input type="radio" name="pv_dash_display_mode" value="offcanvas" <?php checked( $mode, 'offcanvas' ); ?>>
								<span class="pv-pick-card__check"></span>
								<span class="pv-pick-card__preview"><?php echo PV_Settings_Page::svg_offcanvas(); // phpcs:ignore ?></span>
								<span class="pv-pick-card__body">
									<span class="pv-pick-card__label"><?php esc_html_e( 'Offcanvas Drawer', 'pv-youtube-importer' ); ?></span>
									<span class="pv-pick-card__desc"><?php esc_html_e( 'Slides in without leaving the page', 'pv-youtube-importer' ); ?></span>
								</span>
							</label>

							<label class="pv-pick-card <?php echo $mode === 'page' ? 'is-selected' : ''; ?>">
								<input type="radio" name="pv_dash_display_mode" value="page" <?php checked( $mode, 'page' ); ?>>
								<span class="pv-pick-card__check"></span>
								<span class="pv-pick-card__preview"><?php echo PV_Settings_Page::svg_watch_page(); // phpcs:ignore ?></span>
								<span class="pv-pick-card__body">
									<span class="pv-pick-card__label"><?php esc_html_e( 'Watch Page', 'pv-youtube-importer' ); ?></span>
									<span class="pv-pick-card__desc"><?php esc_html_e( 'Navigates to a dedicated page per video', 'pv-youtube-importer' ); ?></span>
								</span>
							</label>

						</div>
						<div class="pv-mode-status" id="pv-mode-status"></div>
						<?php wp_nonce_field( 'pv_save_mode_nonce', 'pv_mode_nonce' ); ?>
					</div>
				</div>

			</div>

			<!-- Archive Layout -->
			<div class="pv-card">
				<div class="pv-card__head">
					<div class="pv-card__icon"><span class="dashicons dashicons-grid-view"></span></div>
					<div class="pv-card__head-text">
						<h2><?php esc_html_e( 'Archive Layout', 'pv-youtube-importer' ); ?></h2>
						<p><?php esc_html_e( 'How your video archive page (/pv-videos/) displays the video collection.', 'pv-youtube-importer' ); ?></p>
					</div>
				</div>
				<div class="pv-card__body">
					<div class="pv-visual-picker pv-visual-picker--text" data-controls="archive-layout" data-ajax="1">

						<label class="pv-pick-card pv-pick-card--text <?php echo $layout === 'grid' ? 'is-selected' : ''; ?>">
							<input type="radio" name="pv_archive_layout" value="grid" <?php checked( $layout, 'grid' ); ?>>
							<span class="pv-pick-card__check"></span>
							<span class="pv-pick-card__body">
								<span class="pv-pick-card__label"><?php esc_html_e( 'Grid', 'pv-youtube-importer' ); ?></span>
								<span class="pv-pick-card__desc"><?php esc_html_e( '3-column responsive card grid', 'pv-youtube-importer' ); ?></span>
							</span>
						</label>

						<label class="pv-pick-card pv-pick-card--text <?php echo $layout === 'list' ? 'is-selected' : ''; ?>">
							<input type="radio" name="pv_archive_layout" value="list" <?php checked( $layout, 'list' ); ?>>
							<span class="pv-pick-card__check"></span>
							<span class="pv-pick-card__body">
								<span class="pv-pick-card__label"><?php esc_html_e( 'List', 'pv-youtube-importer' ); ?></span>
								<span class="pv-pick-card__desc"><?php esc_html_e( 'Horizontal rows with thumbnail and excerpt', 'pv-youtube-importer' ); ?></span>
							</span>
						</label>

						<label class="pv-pick-card pv-pick-card--text <?php echo $layout === 'featured' ? 'is-selected' : ''; ?>">
							<input type="radio" name="pv_archive_layout" value="featured" <?php checked( $layout, 'featured' ); ?>>
							<span class="pv-pick-card__check"></span>
							<span class="pv-pick-card__body">
								<span class="pv-pick-card__label"><?php esc_html_e( 'Featured', 'pv-youtube-importer' ); ?></span>
								<span class="pv-pick-card__desc"><?php esc_html_e( 'First video hero-style, rest in a grid', 'pv-youtube-importer' ); ?></span>
							</span>
						</label>

						<label class="pv-pick-card pv-pick-card--text <?php echo $layout === 'compact' ? 'is-selected' : ''; ?>">
							<input type="radio" name="pv_archive_layout" value="compact" <?php checked( $layout, 'compact' ); ?>>
							<span class="pv-pick-card__check"></span>
							<span class="pv-pick-card__body">
								<span class="pv-pick-card__label"><?php esc_html_e( 'Compact', 'pv-youtube-importer' ); ?></span>
								<span class="pv-pick-card__desc"><?php esc_html_e( '4-column dense grid', 'pv-youtube-importer' ); ?></span>
							</span>
						</label>

					</div>
					<div class="pv-mode-status" id="pv-layout-status"></div>
				</div>
			</div>

			<!-- Content Width -->
			<div class="pv-card">
				<div class="pv-card__head">
					<div class="pv-card__icon"><span class="dashicons dashicons-editor-expand"></span></div>
					<div class="pv-card__head-text">
						<h2><?php esc_html_e( 'Content Width', 'pv-youtube-importer' ); ?></h2>
						<p><?php esc_html_e( 'Max width for the archive page and single video page. "Full Width" fills the theme container.', 'pv-youtube-importer' ); ?></p>
					</div>
				</div>
				<div class="pv-card__body">
					<div class="pv-visual-picker pv-visual-picker--text" data-controls="content-width" data-ajax="1">
						<?php
						$widths = [
							'full'   => [ 'label' => __( 'Full Width', 'pv-youtube-importer' ), 'desc' => __( 'Fills the theme container', 'pv-youtube-importer' ) ],
							'wide'   => [ 'label' => __( 'Wide',       'pv-youtube-importer' ), 'desc' => '1400px' ],
							'medium' => [ 'label' => __( 'Medium',     'pv-youtube-importer' ), 'desc' => '1200px' ],
							'narrow' => [ 'label' => __( 'Narrow',     'pv-youtube-importer' ), 'desc' => '960px' ],
						];
						foreach ( $widths as $val => $info ) : ?>
							<label class="pv-pick-card pv-pick-card--text <?php echo $cwidth === $val ? 'is-selected' : ''; ?>">
								<input type="radio" name="pv_content_width" value="<?php echo esc_attr( $val ); ?>" <?php checked( $cwidth, $val ); ?>>
								<span class="pv-pick-card__check"></span>
								<span class="pv-pick-card__body">
									<span class="pv-pick-card__label"><?php echo esc_html( $info['label'] ); ?></span>
									<span class="pv-pick-card__desc"><?php echo esc_html( $info['desc'] ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
					<div class="pv-mode-status" id="pv-width-status"></div>
				</div>
			</div>

		</div>
		<?php
	}

	// ── Inline JS for dashboard page ─────────────────────────────────

	public function print_js(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! str_contains( $screen->id ?? '', 'pv-youtube-importer-dashboard' ) ) return;

		$i18n = wp_json_encode( [
			'requestFailed' => __( 'Request failed. Please try again.', 'pv-youtube-importer' ),
			'offcanvas'     => __( 'Offcanvas', 'pv-youtube-importer' ),
			'watchPage'     => __( 'Watch Page', 'pv-youtube-importer' ),
			'saved'         => __( '✓ Saved', 'pv-youtube-importer' ),
			'saveFailed'    => __( 'Save failed', 'pv-youtube-importer' ),
		] );
		?>
		<script>
		(function () {
			'use strict';

			var pvDash = <?php echo $i18n; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;

			// Import button
			var btn     = document.getElementById('pv-run-import');
			var spinner = document.getElementById('pv-import-spinner');
			var result  = document.getElementById('pv-import-result');
			var iNonce  = document.getElementById('pv_import_nonce');

			if (btn && iNonce) {
				btn.addEventListener('click', function () {
					btn.disabled = true;
					spinner.classList.add('is-active');
					result.innerHTML = '';
					var data = new FormData();
					data.append('action', 'pv_manual_import');
					data.append('nonce', iNonce.value);
					fetch(ajaxurl, { method: 'POST', body: data })
						.then(function(r){ return r.json(); })
						.then(function(json){
							var cls = json.success ? 'notice-success' : 'notice-error';
							result.innerHTML = '<div class="notice ' + cls + ' inline" style="margin:10px 0 0"><p>' + json.data.message + '</p></div>';
						})
						.catch(function(){
							result.innerHTML = '<div class="notice notice-error inline" style="margin:10px 0 0"><p>' + pvDash.requestFailed + '</p></div>';
						})
						.finally(function(){
							btn.disabled = false;
							spinner.classList.remove('is-active');
						});
				});
			}

			// Shared nonce for mode + layout AJAX
			var mNonce = document.getElementById('pv_mode_nonce');

			// Playback mode AJAX save
			var modePicker = document.querySelector('.pv-visual-picker[data-controls="display-mode"]');
			var mStatus    = document.getElementById('pv-mode-status');
			var modeLabel  = document.getElementById('pv-active-mode-label');
			var modeNames  = { offcanvas: pvDash.offcanvas, page: pvDash.watchPage };

			if (modePicker && mNonce) {
				modePicker.addEventListener('change', function (e) {
					var radio = e.target;
					if (radio.type !== 'radio') return;
					// Update visual selection
					modePicker.querySelectorAll('.pv-pick-card').forEach(function(c) {
						c.classList.toggle('is-selected', c.querySelector('input') === radio);
					});
					var data = new FormData();
					data.append('action', 'pv_save_display_mode');
					data.append('nonce', mNonce.value);
					data.append('mode', radio.value);
					fetch(ajaxurl, { method: 'POST', body: data })
						.then(function(r){ return r.json(); })
						.then(function(json){
							if (json.success) {
								mStatus.textContent = pvDash.saved;
								mStatus.className = 'pv-mode-status pv-mode-status--ok';
								if (modeLabel) modeLabel.textContent = modeNames[radio.value] || radio.value;
							} else {
								mStatus.textContent = pvDash.saveFailed;
								mStatus.className = 'pv-mode-status pv-mode-status--err';
							}
							setTimeout(function(){
								mStatus.textContent = '';
								mStatus.className = 'pv-mode-status';
							}, 2500);
						});
				});
			}

			// Archive layout AJAX save
			var layoutPicker = document.querySelector('.pv-visual-picker[data-controls="archive-layout"]');
			var lStatus      = document.getElementById('pv-layout-status');

			if (layoutPicker && mNonce) {
				layoutPicker.addEventListener('change', function (e) {
					var radio = e.target;
					if (radio.type !== 'radio') return;
					// Update visual selection
					layoutPicker.querySelectorAll('.pv-pick-card').forEach(function(c) {
						c.classList.toggle('is-selected', c.querySelector('input') === radio);
					});
					var data = new FormData();
					data.append('action', 'pv_save_archive_layout');
					data.append('nonce', mNonce.value);
					data.append('layout', radio.value);
					fetch(ajaxurl, { method: 'POST', body: data })
						.then(function(r){ return r.json(); })
						.then(function(json){
							if (json.success) {
								lStatus.textContent = pvDash.saved;
								lStatus.className = 'pv-mode-status pv-mode-status--ok';
							} else {
								lStatus.textContent = pvDash.saveFailed;
								lStatus.className = 'pv-mode-status pv-mode-status--err';
							}
							setTimeout(function(){
								lStatus.textContent = '';
								lStatus.className = 'pv-mode-status';
							}, 2500);
						});
				});
			}

			// Content width AJAX save
			var widthPicker = document.querySelector('.pv-visual-picker[data-controls="content-width"]');
			var wStatus     = document.getElementById('pv-width-status');

			if (widthPicker && mNonce) {
				widthPicker.addEventListener('change', function (e) {
					var radio = e.target;
					if (radio.type !== 'radio') return;
					// Update visual selection
					widthPicker.querySelectorAll('.pv-pick-card').forEach(function(c) {
						c.classList.toggle('is-selected', c.querySelector('input') === radio);
					});
					var data = new FormData();
					data.append('action', 'pv_save_content_width');
					data.append('nonce', mNonce.value);
					data.append('width', radio.value);
					fetch(ajaxurl, { method: 'POST', body: data })
						.then(function(r){ return r.json(); })
						.then(function(json){
							if (json.success) {
								wStatus.textContent = pvDash.saved;
								wStatus.className = 'pv-mode-status pv-mode-status--ok';
							} else {
								wStatus.textContent = pvDash.saveFailed;
								wStatus.className = 'pv-mode-status pv-mode-status--err';
							}
							setTimeout(function(){
								wStatus.textContent = '';
								wStatus.className = 'pv-mode-status';
							}, 2500);
						});
				});
			}

		}());
		</script>
		<?php
	}
}
