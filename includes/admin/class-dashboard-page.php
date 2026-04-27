<?php
/**
 * Videos > Dashboard — content creator stats + import + playback mode.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Dashboard_Page {

	public function register(): void {
		add_action( 'admin_menu',                  [ $this, 'add_menu' ] );
		add_action( 'manage_posts_extra_tablenav', [ $this, 'list_info_bar' ] );
		add_action( 'admin_footer',                [ $this, 'print_js' ] );
		add_filter( 'admin_body_class',            [ $this, 'body_class' ] );
	}

	public function body_class( string $classes ): string {
		$screen = get_current_screen();
		if ( $screen && str_contains( $screen->id, 'pv-youtube-importer-dashboard' ) ) {
			$classes .= ' pvd-page';
		}
		return $classes;
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

		$last  = get_option( 'pv_last_import', null );
		$count = (int) ( wp_count_posts( 'pv_youtube' )->publish ?? 0 );
		$limit = PV_Tier::get_video_limit();
		$dash  = admin_url( 'edit.php?post_type=pv_youtube&page=pv-youtube-importer-dashboard' );
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
					<strong><?php echo esc_html( human_time_diff( $last['time'], time() ) . ' ' . __( 'ago', 'pv-youtube-importer' ) ); ?></strong>,
					<?php echo esc_html( sprintf(
						__( '%1$d imported, %2$d skipped', 'pv-youtube-importer' ),
						$last['imported'],
						$last['skipped']
					) ); ?>
				</span>
			<?php else : ?>
				<span class="pv-list-stat pv-list-stat--muted"><?php esc_html_e( 'No imports yet', 'pv-youtube-importer' ); ?></span>
			<?php endif; ?>
			<a href="<?php echo esc_url( $dash ); ?>" class="button button-small pv-list-dash-link">
				<?php esc_html_e( 'Dashboard', 'pv-youtube-importer' ); ?>
			</a>
		</div>
		<?php
	}

	// ── Page render ──────────────────────────────────────────────────

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$settings    = get_option( 'pv_settings', [] );
		$tier        = PV_Tier::current();
		$limit       = PV_Tier::get_video_limit();
		$last        = get_option( 'pv_last_import', null );
		$mode        = $settings['display_mode'] ?? 'offcanvas';
		$archive_url = get_post_type_archive_link( 'pv_youtube' ) ?: '';
		$preview_url = admin_url( 'edit.php?post_type=pv_youtube&page=pv-customizer' );

		// ── Stats ──────────────────────────────────────────────────
		$total_published = (int) ( wp_count_posts( 'pv_youtube' )->publish ?? 0 );

		// Published this month
		$month_posts = get_posts( [
			'post_type'      => 'pv_youtube',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'date_query'     => [ [ 'after' => 'first day of this month', 'inclusive' => true ] ],
		] );
		$this_month = count( $month_posts );

		// Published this week
		$week_posts = get_posts( [
			'post_type'      => 'pv_youtube',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'date_query'     => [ [ 'after' => '7 days ago', 'inclusive' => true ] ],
		] );
		$this_week = count( $week_posts );

		// Category + tag counts
		$cat_count = wp_count_terms( [ 'taxonomy' => 'pv_category', 'hide_empty' => true ] );
		$tag_count = wp_count_terms( [ 'taxonomy' => 'pv_tag',      'hide_empty' => true ] );
		$cat_count = is_wp_error( $cat_count ) ? 0 : (int) $cat_count;
		$tag_count = is_wp_error( $tag_count ) ? 0 : (int) $tag_count;

		// Most-used category
		$top_cats = get_terms( [ 'taxonomy' => 'pv_category', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 1 ] );
		$top_cat  = ( $top_cats && ! is_wp_error( $top_cats ) ) ? $top_cats[0] : null;

		// Recent 6 videos for activity feed
		$recent_videos = get_posts( [
			'post_type'      => 'pv_youtube',
			'post_status'    => 'publish',
			'posts_per_page' => 6,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );
		?>
		<div class="wrap pvd-wrap">
		<div class="pvd-inner">

			<!-- ── Hero ──────────────────────────────────────────── -->
			<div class="pvd-hero">
				<div class="pvd-hero__left">
					<div class="pvd-hero__brand">
						<span class="dashicons dashicons-video-alt3"></span>
						<h1><?php esc_html_e( 'PressVideo', 'pv-youtube-importer' ); ?></h1>
					</div>
					<p class="pvd-hero__tagline"><?php esc_html_e( 'Your YouTube channel, owned.', 'pv-youtube-importer' ); ?></p>
					<div class="pvd-hero__actions">
						<span class="pvd-tier-badge">
							<?php echo esc_html( ucfirst( $tier ) ); ?> <?php esc_html_e( 'Plan', 'pv-youtube-importer' ); ?>
							<?php if ( ! PV_Tier::is_gold() ) : ?>
								&nbsp;&middot;&nbsp;<a href="https://pressvideo.com" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade →', 'pv-youtube-importer' ); ?></a>
							<?php endif; ?>
						</span>
						<?php if ( $archive_url ) : ?>
							<a href="<?php echo esc_url( $archive_url ); ?>" target="_blank" rel="noopener" class="pvd-btn pvd-btn--ghost">
								<span class="dashicons dashicons-external"></span>
								<?php esc_html_e( 'View Archive', 'pv-youtube-importer' ); ?>
							</a>
						<?php endif; ?>
						<a href="<?php echo esc_url( $preview_url ); ?>" class="pvd-btn pvd-btn--primary">
							<span class="dashicons dashicons-visibility"></span>
							<?php esc_html_e( 'Live Preview', 'pv-youtube-importer' ); ?>
						</a>
					</div>
				</div>

				<!-- Decorative video-grid SVG -->
				<div class="pvd-hero__deco" aria-hidden="true">
					<svg viewBox="0 0 220 155" xmlns="http://www.w3.org/2000/svg" fill="none">
						<rect x="0"   y="0"   width="68" height="46" rx="7" fill="white"/>
						<polygon points="25,15 25,31 43,23" fill="#7c3aed"/>
						<rect x="76"  y="0"   width="68" height="46" rx="7" fill="white"/>
						<polygon points="101,15 101,31 119,23" fill="#4f46e5"/>
						<rect x="152" y="0"   width="68" height="46" rx="7" fill="white"/>
						<polygon points="177,15 177,31 195,23" fill="#6d28d9"/>
						<rect x="0"   y="53"  width="68" height="46" rx="7" fill="white"/>
						<polygon points="25,68 25,84 43,76" fill="#4f46e5"/>
						<rect x="76"  y="53"  width="68" height="46" rx="7" fill="white"/>
						<polygon points="101,68 101,84 119,76" fill="#7c3aed"/>
						<rect x="152" y="53"  width="68" height="46" rx="7" fill="white"/>
						<polygon points="177,68 177,84 195,76" fill="#4f46e5"/>
						<rect x="0"   y="106" width="68" height="46" rx="7" fill="white"/>
						<polygon points="25,121 25,137 43,129" fill="#6d28d9"/>
						<rect x="76"  y="106" width="68" height="46" rx="7" fill="white"/>
						<polygon points="101,121 101,137 119,129" fill="#4f46e5"/>
						<rect x="152" y="106" width="68" height="46" rx="7" fill="white"/>
						<polygon points="177,121 177,137 195,129" fill="#7c3aed"/>
					</svg>
				</div>
			</div><!-- /.pvd-hero -->

			<!-- ── Stats row (glass morphism) ───────────────────── -->
			<div class="pvd-stats">

				<div class="pvd-stat">
					<span class="pvd-stat__val"><?php echo esc_html( $total_published ); ?><?php if ( $limit !== PHP_INT_MAX ) : ?><span class="pvd-stat__cap"> / <?php echo esc_html( $limit ); ?></span><?php endif; ?></span>
					<span class="pvd-stat__lbl"><?php esc_html_e( 'Total Videos', 'pv-youtube-importer' ); ?></span>
				</div>

				<div class="pvd-stat">
					<span class="pvd-stat__val"><?php echo esc_html( $this_month ); ?></span>
					<span class="pvd-stat__lbl"><?php esc_html_e( 'Published This Month', 'pv-youtube-importer' ); ?></span>
				</div>

				<div class="pvd-stat">
					<span class="pvd-stat__val"><?php echo esc_html( $this_week ); ?></span>
					<span class="pvd-stat__lbl"><?php esc_html_e( 'Added This Week', 'pv-youtube-importer' ); ?></span>
				</div>

				<div class="pvd-stat">
					<span class="pvd-stat__val"><?php echo esc_html( $cat_count ); ?></span>
					<span class="pvd-stat__lbl"><?php esc_html_e( 'Categories', 'pv-youtube-importer' ); ?></span>
					<?php if ( $top_cat ) : ?>
						<span class="pvd-stat__sub"><?php echo esc_html( sprintf( __( 'Top: %s (%d)', 'pv-youtube-importer' ), $top_cat->name, $top_cat->count ) ); ?></span>
					<?php endif; ?>
				</div>

				<div class="pvd-stat">
					<span class="pvd-stat__val"><?php echo esc_html( $tag_count ); ?></span>
					<span class="pvd-stat__lbl"><?php esc_html_e( 'Tags', 'pv-youtube-importer' ); ?></span>
				</div>

				<div class="pvd-stat">
					<?php if ( $last ) : ?>
						<span class="pvd-stat__val pvd-stat__val--sm"><?php echo esc_html( human_time_diff( $last['time'], time() ) ); ?> <?php esc_html_e( 'ago', 'pv-youtube-importer' ); ?></span>
						<span class="pvd-stat__lbl"><?php echo esc_html( sprintf( __( 'Last Import · %d added', 'pv-youtube-importer' ), $last['imported'] ) ); ?></span>
					<?php else : ?>
						<span class="pvd-stat__val pvd-stat__val--dash">&mdash;</span>
						<span class="pvd-stat__lbl"><?php esc_html_e( 'No Imports Yet', 'pv-youtube-importer' ); ?></span>
					<?php endif; ?>
				</div>

			</div><!-- /.pvd-stats -->

			<!-- ── Main 2-col ────────────────────────────────────── -->
			<div class="pvd-main">

				<!-- Left: Import + Quick Links -->
				<div class="pvd-col pvd-col--left">

					<div class="pvd-card">
						<div class="pvd-card__head">
							<div class="pvd-card__icon"><span class="dashicons dashicons-download"></span></div>
							<div class="pvd-card__head-text">
								<h2><?php esc_html_e( 'Import', 'pv-youtube-importer' ); ?></h2>
								<p><?php esc_html_e( 'Fetch the latest videos from your YouTube channel.', 'pv-youtube-importer' ); ?></p>
							</div>
						</div>
						<div class="pvd-card__body">
							<button id="pv-run-import" class="button button-primary" type="button">
								<?php esc_html_e( 'Run Import Now', 'pv-youtube-importer' ); ?>
							</button>
							<span id="pv-import-spinner" class="spinner" style="float:none;margin:0 6px;vertical-align:middle;"></span>
							<?php wp_nonce_field( 'pv_manual_import_nonce', 'pv_import_nonce' ); ?>
							<div id="pv-import-result"></div>
						</div>
					</div>

					<div class="pvd-card">
						<div class="pvd-card__head">
							<div class="pvd-card__icon"><span class="dashicons dashicons-shortcode"></span></div>
							<div class="pvd-card__head-text">
								<h2><?php esc_html_e( 'Quick Links', 'pv-youtube-importer' ); ?></h2>
								<p><?php esc_html_e( 'Jump to common plugin areas.', 'pv-youtube-importer' ); ?></p>
							</div>
						</div>
						<div class="pvd-card__body">
							<div class="pvd-qlinks">
								<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=pv_youtube' ) ); ?>" class="pvd-qlink">
									<span class="dashicons dashicons-plus-alt"></span><?php esc_html_e( 'Add Video', 'pv-youtube-importer' ); ?>
								</a>
								<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=pv_youtube&page=pv-youtube-importer-settings' ) ); ?>" class="pvd-qlink">
									<span class="dashicons dashicons-admin-settings"></span><?php esc_html_e( 'Settings', 'pv-youtube-importer' ); ?>
								</a>
								<a href="<?php echo esc_url( $preview_url ); ?>" class="pvd-qlink">
									<span class="dashicons dashicons-admin-customizer"></span><?php esc_html_e( 'Live Preview', 'pv-youtube-importer' ); ?>
								</a>
								<?php if ( $archive_url ) : ?>
								<a href="<?php echo esc_url( $archive_url ); ?>" target="_blank" rel="noopener" class="pvd-qlink">
									<span class="dashicons dashicons-video-alt3"></span><?php esc_html_e( 'View Archive', 'pv-youtube-importer' ); ?>
								</a>
								<?php endif; ?>
								<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=pv_category&post_type=pv_youtube' ) ); ?>" class="pvd-qlink">
									<span class="dashicons dashicons-category"></span><?php esc_html_e( 'Categories', 'pv-youtube-importer' ); ?>
								</a>
								<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=pv_youtube&page=pv-youtube-analytics' ) ); ?>" class="pvd-qlink">
									<span class="dashicons dashicons-chart-bar"></span><?php esc_html_e( 'Analytics', 'pv-youtube-importer' ); ?>
								</a>
							</div>
						</div>
					</div>

				</div><!-- /.pvd-col--left -->

				<!-- Right: Recent Videos -->
				<div class="pvd-col pvd-col--right">
					<?php if ( ! empty( $recent_videos ) ) : ?>
					<div class="pvd-card">
						<div class="pvd-card__head">
							<div class="pvd-card__icon"><span class="dashicons dashicons-clock"></span></div>
							<div class="pvd-card__head-text">
								<h2><?php esc_html_e( 'Recently Published', 'pv-youtube-importer' ); ?></h2>
								<p><?php esc_html_e( 'Your latest imported videos at a glance.', 'pv-youtube-importer' ); ?></p>
							</div>
							<div class="pvd-card__head-action">
								<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=pv_youtube' ) ); ?>" class="button button-small"><?php esc_html_e( 'View All', 'pv-youtube-importer' ); ?></a>
							</div>
						</div>
						<div class="pvd-card__body pvd-card__body--flush">
							<div class="pv-recent-videos">
								<?php foreach ( $recent_videos as $rv ) :
									$rv_thumb  = get_the_post_thumbnail_url( $rv->ID, 'thumbnail' ) ?: '';
									$rv_cats   = get_the_terms( $rv->ID, 'pv_category' );
									$rv_cat    = ( $rv_cats && ! is_wp_error( $rv_cats ) ) ? $rv_cats[0]->name : '—';
									$rv_dur    = get_post_meta( $rv->ID, '_pv_duration', true );
									$rv_yt     = get_post_meta( $rv->ID, '_pv_youtube_id', true );
									$rv_accent = pv_resolve_accent_color( $rv->ID );
								?>
								<div class="pv-rv-row">
									<div class="pv-rv-thumb">
										<?php if ( $rv_thumb ) : ?>
											<img src="<?php echo esc_url( $rv_thumb ); ?>" alt="" loading="lazy" width="80" height="45">
										<?php else : ?>
											<div class="pv-rv-thumb__placeholder"><span class="dashicons dashicons-video-alt3"></span></div>
										<?php endif; ?>
										<?php if ( $rv_dur ) : ?>
											<span class="pv-rv-dur"><?php echo esc_html( $rv_dur ); ?></span>
										<?php endif; ?>
									</div>
									<div class="pv-rv-info">
										<a href="<?php echo esc_url( get_edit_post_link( $rv->ID ) ); ?>" class="pv-rv-title"><?php echo esc_html( $rv->post_title ); ?></a>
										<span class="pv-rv-meta">
											<span class="pv-rv-cat" style="--pv-accent:<?php echo esc_attr( $rv_accent ); ?>;"><?php echo esc_html( $rv_cat ); ?></span>
											<span class="pv-rv-date"><?php echo esc_html( get_the_date( 'M j, Y', $rv->ID ) ); ?></span>
										</span>
									</div>
									<div class="pv-rv-actions">
										<?php if ( $rv_yt ) : ?>
											<a href="<?php echo esc_url( 'https://www.youtube.com/watch?v=' . $rv_yt ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'View on YouTube', 'pv-youtube-importer' ); ?>" class="pv-rv-yt-link">
												<span class="dashicons dashicons-external"></span>
											</a>
										<?php endif; ?>
										<a href="<?php echo esc_url( get_permalink( $rv->ID ) ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'View post', 'pv-youtube-importer' ); ?>" class="pv-rv-view-link">
											<span class="dashicons dashicons-visibility"></span>
										</a>
									</div>
								</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
					<?php endif; ?>
				</div><!-- /.pvd-col--right -->

			</div><!-- /.pvd-main -->

		</div><!-- /.pvd-inner -->
		</div><!-- /.pvd-wrap -->
		<?php
	}

	// ── Inline JS ────────────────────────────────────────────────────

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

			// Playback mode AJAX save
			var mNonce    = document.getElementById('pv_mode_nonce');
			var modePicker = document.querySelector('.pv-visual-picker[data-controls="display-mode"]');
			var mStatus    = document.getElementById('pv-mode-status');

			if (modePicker && mNonce) {
				modePicker.addEventListener('change', function (e) {
					var radio = e.target;
					if (radio.type !== 'radio') return;
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
							mStatus.textContent = json.success ? pvDash.saved : pvDash.saveFailed;
							mStatus.className = 'pv-mode-status ' + (json.success ? 'pv-mode-status--ok' : 'pv-mode-status--err');
							setTimeout(function(){ mStatus.textContent = ''; mStatus.className = 'pv-mode-status'; }, 2500);
						});
				});
			}

		}());
		</script>
		<?php
	}
}
