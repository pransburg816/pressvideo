<?php
/**
 * Settings page: Videos > Settings.
 * Intentionally lean — API credentials + appearance only.
 * Display mode and import controls live on the Dashboard page.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Settings_Page {

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function add_menu(): void {
		add_submenu_page(
			'edit.php?post_type=pv_youtube',
			__( 'PressVideo Settings', 'pv-youtube-importer' ),
			__( 'Settings', 'pv-youtube-importer' ),
			'manage_options',
			'pv-youtube-importer-settings',
			[ $this, 'render_page' ]
		);
	}

	public function register_settings(): void {
		register_setting( 'pv_settings_group', 'pv_settings', [
			'sanitize_callback' => [ $this, 'sanitize_settings' ],
		] );
	}

	public function sanitize_settings( array $input ): array {
		$existing = get_option( 'pv_settings', [] );
		// Start from existing so customizer-managed keys (hero, aside, etc.) are never wiped by this form.
		$clean = is_array( $existing ) ? $existing : [];

		$clean['api_key']    = sanitize_text_field( $input['api_key']    ?? '' );
		$clean['channel_id'] = sanitize_text_field( $input['channel_id'] ?? '' );

		// Overwrite only the keys that this settings page owns (sent via hidden inputs).
		$clean['default_accent']    = sanitize_hex_color( $input['default_accent'] ?? '' ) ?: ( $existing['default_accent'] ?? '#4f46e5' );
		$clean['display_mode']      = in_array( $input['display_mode'] ?? '', [ 'offcanvas', 'page' ], true )
			? $input['display_mode'] : ( $existing['display_mode'] ?? 'offcanvas' );
		$clean['watch_page_layout'] = in_array( $input['watch_page_layout'] ?? '', [ 'hero-top', 'hero-split', 'theater' ], true )
			? $input['watch_page_layout'] : ( $existing['watch_page_layout'] ?? 'hero-top' );
		$clean['archive_layout']    = in_array( $input['archive_layout'] ?? '', [ 'grid', 'list', 'featured', 'compact', 'wall', 'spotlight', 'broadcast' ], true )
			? $input['archive_layout'] : ( $existing['archive_layout'] ?? 'grid' );
		$clean['content_width']     = in_array( $input['content_width'] ?? '', [ 'full', 'wide', 'medium', 'narrow' ], true )
			? $input['content_width'] : ( $existing['content_width'] ?? 'full' );

		return $clean;
	}

	// ── Page render ──────────────────────────────────────────────────

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$settings = get_option( 'pv_settings', [] );
		$tier     = PV_Tier::current();
		?>
		<div class="wrap pv-settings-wrap">

			<div class="pv-page-header">
				<h1>
					<span class="dashicons dashicons-video-alt3"></span>
					<?php esc_html_e( 'PressVideo Settings', 'pv-youtube-importer' ); ?>
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

			<form method="post" action="options.php">
				<?php settings_fields( 'pv_settings_group' ); ?>
				<!-- Preserve values managed by Dashboard / Live Preview so a settings save doesn't wipe them -->
				<input type="hidden" name="pv_settings[display_mode]"      value="<?php echo esc_attr( $settings['display_mode']      ?? 'offcanvas' ); ?>">
				<input type="hidden" name="pv_settings[watch_page_layout]" value="<?php echo esc_attr( $settings['watch_page_layout'] ?? 'hero-top' ); ?>">
				<input type="hidden" name="pv_settings[default_accent]"    value="<?php echo esc_attr( $settings['default_accent']    ?? '#4f46e5' ); ?>">
				<input type="hidden" name="pv_settings[archive_layout]"    value="<?php echo esc_attr( $settings['archive_layout']    ?? 'grid' ); ?>">
				<input type="hidden" name="pv_settings[content_width]"     value="<?php echo esc_attr( $settings['content_width']     ?? '' ); ?>">

				<!-- YouTube Connection -->
				<div class="pv-card">
					<div class="pv-card__head">
						<div class="pv-card__icon"><span class="dashicons dashicons-admin-network"></span></div>
						<div class="pv-card__head-text">
							<h2><?php esc_html_e( 'YouTube Connection', 'pv-youtube-importer' ); ?></h2>
							<p><?php esc_html_e( 'API credentials used for importing and fetching video data.', 'pv-youtube-importer' ); ?></p>
						</div>
					</div>
					<div class="pv-card__body">

						<!-- API Key instructions -->
						<div class="pv-api-instructions">
							<div class="pv-api-instructions__head">
								<span class="dashicons dashicons-info-outline"></span>
								<strong><?php esc_html_e( 'How to get your YouTube API Key', 'pv-youtube-importer' ); ?></strong>
							</div>
							<ol class="pv-api-instructions__steps">
								<li><?php printf(
									/* translators: link to Google Cloud Console */
									wp_kses( __( 'Go to the <a href="%s" target="_blank" rel="noopener">Google Cloud Console</a> and sign in with your Google account.', 'pv-youtube-importer' ), [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ] ),
									'https://console.cloud.google.com/'
								); ?></li>
								<li><?php esc_html_e( 'Create a new project (or select an existing one) from the top navigation bar.', 'pv-youtube-importer' ); ?></li>
								<li><?php printf(
									wp_kses( __( 'Open the <a href="%s" target="_blank" rel="noopener">API Library</a>, search for <strong>YouTube Data API v3</strong>, and click <strong>Enable</strong>.', 'pv-youtube-importer' ), [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ], 'strong' => [] ] ),
									'https://console.cloud.google.com/apis/library'
								); ?></li>
								<li><?php printf(
									wp_kses( __( 'Go to <a href="%s" target="_blank" rel="noopener">Credentials</a>, click <strong>Create Credentials → API key</strong>, and copy the generated key.', 'pv-youtube-importer' ), [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ], 'strong' => [] ] ),
									'https://console.cloud.google.com/apis/credentials'
								); ?></li>
								<li><?php esc_html_e( 'Paste the key into the field below and save. It\'s recommended to restrict the key to the YouTube Data API v3 only.', 'pv-youtube-importer' ); ?></li>
							</ol>
						</div>

						<div class="pv-field-rows">

							<div class="pv-field-row">
								<div class="pv-field-row__label">
									<label for="pv_api_key"><?php esc_html_e( 'API Key', 'pv-youtube-importer' ); ?></label>
								</div>
								<div class="pv-field-row__control">
									<input type="password" name="pv_settings[api_key]"
									       id="pv_api_key"
									       value="<?php echo esc_attr( $settings['api_key'] ?? '' ); ?>"
									       class="regular-text" autocomplete="off" />
									<p class="pv-field-row__desc">
										<?php esc_html_e( 'Your YouTube Data API v3 key. Never shared publicly.', 'pv-youtube-importer' ); ?>
									</p>
								</div>
							</div>

							<div class="pv-field-row">
								<div class="pv-field-row__label">
									<label for="pv_channel_id"><?php esc_html_e( 'Channel ID', 'pv-youtube-importer' ); ?></label>
								</div>
								<div class="pv-field-row__control">
									<input type="text" name="pv_settings[channel_id]"
									       id="pv_channel_id"
									       value="<?php echo esc_attr( $settings['channel_id'] ?? '' ); ?>"
									       class="regular-text"
									       placeholder="UCxxxxxxxxxxxxxxxxxxxxxxxxx" />
									<p class="pv-field-row__desc">
										<?php printf(
											wp_kses( __( 'Your YouTube Channel ID (starts with <code>UC</code>). Find it at <a href="%s" target="_blank" rel="noopener">youtube.com/account_advanced</a>.', 'pv-youtube-importer' ), [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ], 'code' => [] ] ),
											'https://www.youtube.com/account_advanced'
										); ?>
									</p>
								</div>
							</div>

						</div>
					</div>
				</div>

				<?php submit_button( __( 'Save Settings', 'pv-youtube-importer' ) ); ?>
			</form>

		</div>
		<?php
	}

	// ── SVG wireframes — public static so Dashboard can reuse them ───

	public static function svg_offcanvas(): string {
		return '<svg viewBox="0 0 180 112" fill="none" xmlns="http://www.w3.org/2000/svg">'
			. '<rect width="180" height="112" rx="5" fill="#f1f5f9"/>'
			. '<rect width="180" height="20" rx="5" fill="#e2e8f0"/>'
			. '<circle cx="12" cy="10" r="3" fill="#fca5a5"/>'
			. '<circle cx="22" cy="10" r="3" fill="#fcd34d"/>'
			. '<circle cx="32" cy="10" r="3" fill="#86efac"/>'
			. '<rect x="52" y="7" width="76" height="7" rx="3.5" fill="#fff" opacity=".7"/>'
			. '<rect x="0" y="20" width="112" height="92" fill="rgba(0,0,0,.08)"/>'
			. '<rect x="8" y="29" width="68" height="5" rx="2" fill="#c7d2e0" opacity=".5"/>'
			. '<rect x="8" y="40" width="52" height="4" rx="2" fill="#dde3ec" opacity=".5"/>'
			. '<rect x="8" y="50" width="60" height="4" rx="2" fill="#dde3ec" opacity=".5"/>'
			. '<rect x="8" y="60" width="44" height="4" rx="2" fill="#dde3ec" opacity=".5"/>'
			. '<rect x="8" y="70" width="56" height="4" rx="2" fill="#dde3ec" opacity=".5"/>'
			. '<rect x="112" y="20" width="68" height="92" fill="#fff"/>'
			. '<rect x="112" y="20" width="68" height="2.5" fill="#4f46e5"/>'
			. '<rect x="119" y="27" width="54" height="33" rx="2" fill="#0f172a"/>'
			. '<polygon points="134,37 134,51 147,44" fill="#4f46e5"/>'
			. '<rect x="119" y="66" width="54" height="5" rx="2" fill="#e2e8f0"/>'
			. '<rect x="119" y="76" width="40" height="4" rx="2" fill="#f1f5f9"/>'
			. '<rect x="119" y="86" width="48" height="4" rx="2" fill="#f1f5f9"/>'
			. '<line x1="167" y1="24" x2="173" y2="30" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round"/>'
			. '<line x1="173" y1="24" x2="167" y2="30" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round"/>'
			. '<line x1="112" y1="20" x2="112" y2="112" stroke="#e2e8f0" stroke-width="1"/>'
			. '</svg>';
	}

	public static function svg_watch_page(): string {
		return '<svg viewBox="0 0 180 112" fill="none" xmlns="http://www.w3.org/2000/svg">'
			. '<rect width="180" height="112" rx="5" fill="#f1f5f9"/>'
			. '<rect width="180" height="20" rx="5" fill="#e2e8f0"/>'
			. '<circle cx="12" cy="10" r="3" fill="#fca5a5"/>'
			. '<circle cx="22" cy="10" r="3" fill="#fcd34d"/>'
			. '<circle cx="32" cy="10" r="3" fill="#86efac"/>'
			. '<rect x="52" y="7" width="76" height="7" rx="3.5" fill="#fff" opacity=".7"/>'
			. '<rect x="0" y="20" width="180" height="56" fill="#111827"/>'
			. '<polygon points="82,43 82,59 97,51" fill="#4f46e5"/>'
			. '<rect x="28" y="84" width="124" height="6" rx="2" fill="#334155"/>'
			. '<rect x="40" y="96" width="100" height="4" rx="2" fill="#e2e8f0"/>'
			. '<rect x="52" y="105" width="76" height="4" rx="2" fill="#e2e8f0"/>'
			. '</svg>';
	}

	public static function svg_hero_top(): string {
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

	public static function svg_hero_split(): string {
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

	public static function svg_theater(): string {
		return '<svg viewBox="0 0 160 100" fill="none" xmlns="http://www.w3.org/2000/svg">'
			. '<defs><linearGradient id="pvTh" x1="0" y1="0" x2="0" y2="1">'
			. '<stop offset="0" stop-color="#0d0d0d" stop-opacity="0"/>'
			. '<stop offset="1" stop-color="#0d0d0d"/>'
			. '</linearGradient></defs>'
			. '<rect width="160" height="100" rx="4" fill="#f8fafc"/>'
			. '<rect width="160" height="13" rx="4" fill="#e2e8f0"/>'
			. '<circle cx="9" cy="6.5" r="2.5" fill="#fca5a5"/>'
			. '<circle cx="17" cy="6.5" r="2.5" fill="#fcd34d"/>'
			. '<circle cx="25" cy="6.5" r="2.5" fill="#86efac"/>'
			. '<rect x="0" y="13" width="160" height="57" fill="#0d0d0d"/>'
			. '<rect x="14" y="17" width="132" height="38" rx="1" fill="#1a1a2e"/>'
			. '<polygon points="72,29 72,43 84,36" fill="#4f46e5"/>'
			. '<rect x="0" y="44" width="160" height="26" fill="url(#pvTh)"/>'
			. '<rect x="14" y="55" width="68" height="4" rx="2" fill="#fff" opacity=".7"/>'
			. '<rect x="14" y="63" width="46" height="3" rx="1.5" fill="#fff" opacity=".4"/>'
			. '<rect x="20" y="79" width="120" height="3.5" rx="1.5" fill="#e2e8f0"/>'
			. '<rect x="28" y="87" width="104" height="3" rx="1.5" fill="#e2e8f0"/>'
			. '<rect x="28" y="94" width="78" height="3" rx="1.5" fill="#e2e8f0"/>'
			. '</svg>';
	}

	// ── Kept for backward compat in render_display_picker (settings page) ──

	private function render_display_picker(): void {
		$settings = get_option( 'pv_settings', [] );
		$mode     = $settings['display_mode']      ?? 'offcanvas';
		$layout   = $settings['watch_page_layout'] ?? 'hero-top';
		?>
		<div class="pv-visual-picker" data-controls="display-mode">

			<label class="pv-pick-card <?php echo esc_attr( $mode === 'offcanvas' ? 'is-selected' : '' ); ?>">
				<input type="radio" name="pv_settings[display_mode]" value="offcanvas" <?php checked( $mode, 'offcanvas' ); ?>>
				<span class="pv-pick-card__check"></span>
				<span class="pv-pick-card__preview"><?php echo self::svg_offcanvas(); // phpcs:ignore ?></span>
				<span class="pv-pick-card__body">
					<span class="pv-pick-card__label"><?php esc_html_e( 'Offcanvas Drawer', 'pv-youtube-importer' ); ?></span>
					<span class="pv-pick-card__desc"><?php esc_html_e( 'Slides in without leaving the page', 'pv-youtube-importer' ); ?></span>
				</span>
			</label>

			<label class="pv-pick-card <?php echo esc_attr( $mode === 'page' ? 'is-selected' : '' ); ?>">
				<input type="radio" name="pv_settings[display_mode]" value="page" <?php checked( $mode, 'page' ); ?>>
				<span class="pv-pick-card__check"></span>
				<span class="pv-pick-card__preview"><?php echo self::svg_watch_page(); // phpcs:ignore ?></span>
				<span class="pv-pick-card__body">
					<span class="pv-pick-card__label"><?php esc_html_e( 'Watch Page', 'pv-youtube-importer' ); ?></span>
					<span class="pv-pick-card__desc"><?php esc_html_e( 'Dedicated page per video', 'pv-youtube-importer' ); ?></span>
				</span>
			</label>

		</div>

		<div class="pv-sublayout <?php echo esc_attr( $mode !== 'page' ? 'is-hidden' : '' ); ?>" data-for="display-mode">
			<p class="pv-sublayout__label"><?php esc_html_e( 'Watch Page Layout', 'pv-youtube-importer' ); ?></p>
			<div class="pv-visual-picker pv-visual-picker--sm">

				<label class="pv-pick-card <?php echo esc_attr( $layout === 'hero-top' ? 'is-selected' : '' ); ?>">
					<input type="radio" name="pv_settings[watch_page_layout]" value="hero-top" <?php checked( $layout, 'hero-top' ); ?>>
					<span class="pv-pick-card__check"></span>
					<span class="pv-pick-card__preview"><?php echo self::svg_hero_top(); // phpcs:ignore ?></span>
					<span class="pv-pick-card__body">
						<span class="pv-pick-card__label"><?php esc_html_e( 'Hero Top', 'pv-youtube-importer' ); ?></span>
						<span class="pv-pick-card__desc"><?php esc_html_e( 'Full-width video, content below', 'pv-youtube-importer' ); ?></span>
					</span>
				</label>

				<label class="pv-pick-card <?php echo esc_attr( $layout === 'hero-split' ? 'is-selected' : '' ); ?>">
					<input type="radio" name="pv_settings[watch_page_layout]" value="hero-split" <?php checked( $layout, 'hero-split' ); ?>>
					<span class="pv-pick-card__check"></span>
					<span class="pv-pick-card__preview"><?php echo self::svg_hero_split(); // phpcs:ignore ?></span>
					<span class="pv-pick-card__body">
						<span class="pv-pick-card__label"><?php esc_html_e( 'Hero Split', 'pv-youtube-importer' ); ?></span>
						<span class="pv-pick-card__desc"><?php esc_html_e( 'Video left, info right', 'pv-youtube-importer' ); ?></span>
					</span>
				</label>

				<label class="pv-pick-card <?php echo esc_attr( $layout === 'theater' ? 'is-selected' : '' ); ?>">
					<input type="radio" name="pv_settings[watch_page_layout]" value="theater" <?php checked( $layout, 'theater' ); ?>>
					<span class="pv-pick-card__check"></span>
					<span class="pv-pick-card__preview"><?php echo self::svg_theater(); // phpcs:ignore ?></span>
					<span class="pv-pick-card__body">
						<span class="pv-pick-card__label"><?php esc_html_e( 'Theater', 'pv-youtube-importer' ); ?></span>
						<span class="pv-pick-card__desc"><?php esc_html_e( 'Dark cinematic stage', 'pv-youtube-importer' ); ?></span>
					</span>
				</label>

			</div>
		</div>
		<?php
	}
}
