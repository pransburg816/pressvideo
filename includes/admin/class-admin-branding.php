<?php
/**
 * PressVideo admin branding — loader, brand bar, and WP element overrides
 * for CPT list/edit and taxonomy pages.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Admin_Branding {

	/** Screen IDs that already have their own full PV shell — skip ours. */
	private function fullscreen_ids(): array {
		return [
			'pv_youtube_page_pv-youtube-importer-dashboard',
			'pv_youtube_page_pv-youtube-importer-settings',
			'pv_youtube_page_pv-customizer',
			'pv_youtube_page_pv-analytics',
		];
	}

	/** True when we're on a PV page that needs our branding layer. */
	private function is_pv_page(): bool {
		$screen = get_current_screen();
		if ( ! $screen ) return false;
		if ( in_array( $screen->id, $this->fullscreen_ids(), true ) ) return false;
		if ( ( $screen->post_type ?? '' ) === 'pv_youtube' ) return true;
		if ( in_array( $screen->taxonomy ?? '', [ 'pv_tag', 'pv_category', 'pv_series', 'pv_type' ], true ) ) return true;
		return false;
	}

	public function register(): void {
		add_filter( 'admin_body_class',      [ $this, 'body_class'     ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_head',            [ $this, 'render_loader'  ] );
		add_action( 'admin_notices',         [ $this, 'render_brand_bar' ] );
	}

	public function body_class( string $classes ): string {
		if ( ! $this->is_pv_page() ) return $classes;
		return $classes . ' pv-admin-page';
	}

	public function enqueue_assets(): void {
		if ( ! $this->is_pv_page() ) return;
		wp_enqueue_style(
			'pv-admin-brand',
			PV_PLUGIN_URL . 'assets/dist/css/pv-admin-brand.css',
			[ 'pv-admin' ],
			PV_VERSION
		);
	}

	// ── Contextual label + icon for the current page ─────────────────

	private function page_context(): array {
		$screen = get_current_screen();

		$tax_map = [
			'pv_tag'      => [ 'Video Tags',       'M17.63 5.84C17.27 5.33 16.67 5 16 5L5 5.01C3.9 5.01 3 5.9 3 7v10c0 1.1.9 1.99 2 1.99L16 19c.67 0 1.27-.33 1.63-.84L22 12l-4.37-6.16z' ],
			'pv_category' => [ 'Video Categories', 'M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z' ],
			'pv_series'   => [ 'Video Series',     'M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8 12.5v-9l6 4.5-6 4.5z' ],
			'pv_type'     => [ 'Video Types',      'M12 2l-5.5 9h11L12 2zm0 3.84L14.6 10h-5.2L12 5.84zM17.5 13c-2.49 0-4.5 2.01-4.5 4.5S15.01 22 17.5 22 22 19.99 22 17.5 19.99 13 17.5 13zm0 7c-1.38 0-2.5-1.12-2.5-2.5S16.12 15 17.5 15s2.5 1.12 2.5 2.5S18.88 20 17.5 20zM3 21.5h8v-8H3v8zm2-6h4v4H5v-4z' ],
		];

		if ( $screen ) {
			if ( ! empty( $screen->taxonomy ) && isset( $tax_map[ $screen->taxonomy ] ) ) {
				return $tax_map[ $screen->taxonomy ];
			}
			if ( ( $screen->post_type ?? '' ) === 'pv_youtube' ) {
				if ( $screen->base === 'post' ) {
					return [ 'Edit Video', 'M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z' ];
				}
				return [ 'All Videos', 'M8 5v14l11-7z' ];
			}
		}

		return [ 'PressVideo', 'M8 5v14l11-7z' ];
	}

	// ── Brand bar (injected via admin_notices, JS moves it to top) ────

	public function render_brand_bar(): void {
		if ( ! $this->is_pv_page() ) return;
		$screen = get_current_screen();

		[ $page_label, $page_icon ] = $this->page_context();

		$nav = [
			[
				'label'  => 'All Videos',
				'url'    => admin_url( 'edit.php?post_type=pv_youtube' ),
				'active' => $screen && $screen->base === 'edit' && ( $screen->post_type ?? '' ) === 'pv_youtube',
			],
			[
				'label'  => 'Tags',
				'url'    => admin_url( 'edit-tags.php?taxonomy=pv_tag&post_type=pv_youtube' ),
				'active' => $screen && ( $screen->taxonomy ?? '' ) === 'pv_tag',
			],
			[
				'label'  => 'Categories',
				'url'    => admin_url( 'edit-tags.php?taxonomy=pv_category&post_type=pv_youtube' ),
				'active' => $screen && ( $screen->taxonomy ?? '' ) === 'pv_category',
			],
			[
				'label'  => 'Series',
				'url'    => admin_url( 'edit-tags.php?taxonomy=pv_series&post_type=pv_youtube' ),
				'active' => $screen && ( $screen->taxonomy ?? '' ) === 'pv_series',
			],
			[
				'label'  => 'Dashboard',
				'url'    => admin_url( 'edit.php?post_type=pv_youtube&page=pv-youtube-importer-dashboard' ),
				'active' => false,
			],
			[
				'label'  => 'Analytics',
				'url'    => admin_url( 'edit.php?post_type=pv_youtube&page=pv-analytics' ),
				'active' => false,
			],
		];
		?>
		<div class="pv-admin-bar notice" id="pv-admin-bar">
			<div class="pv-admin-bar__inner">

				<div class="pv-admin-bar__brand">
					<span class="pv-admin-bar__logo" aria-hidden="true">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
					</span>
					<span class="pv-admin-bar__name">PressVideo</span>
					<span class="pv-admin-bar__sep" aria-hidden="true">/</span>
					<span class="pv-admin-bar__page">
						<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="<?php echo esc_attr( $page_icon ); ?>"/></svg>
						<?php echo esc_html( $page_label ); ?>
					</span>
				</div>

				<nav class="pv-admin-bar__nav" aria-label="<?php esc_attr_e( 'PressVideo navigation', 'pv-youtube-importer' ); ?>">
					<?php foreach ( $nav as $item ) : ?>
						<a href="<?php echo esc_url( $item['url'] ); ?>"
						   class="pv-admin-bar__link<?php echo $item['active'] ? ' pv-admin-bar__link--active' : ''; ?>">
							<?php echo esc_html( $item['label'] ); ?>
						</a>
					<?php endforeach; ?>
				</nav>

			</div>
		</div>
		<?php
	}

	// ── Animated loader (critical CSS + JS inline in <head>) ──────────

	public function render_loader(): void {
		if ( ! $this->is_pv_page() ) return;

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
		<style id="pv-brand-loader-css">
		#pv-brand-loader{position:fixed;inset:0;z-index:999998;background:#1a1740;display:flex;align-items:center;justify-content:center;transition:opacity .45s ease,visibility .45s ease;}
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
			var l=document.createElement('div');
			l.id='pv-brand-loader';
			l.setAttribute('aria-hidden','true');
			l.innerHTML=<?php echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON-encoded by wp_json_encode ?>;
			document.documentElement.appendChild(l);
			document.addEventListener('DOMContentLoaded',function(){
				setTimeout(function(){
					l.classList.add('pvl-out');
					setTimeout(function(){if(l.parentNode)l.parentNode.removeChild(l);},480);
				},520);
			});
		}());
		</script>
		<script>
		/* Move brand bar above the WP page title once DOM is ready */
		document.addEventListener('DOMContentLoaded',function(){
			var bar=document.getElementById('pv-admin-bar');
			if(!bar)return;
			var wrap=document.querySelector('#wpbody-content .wrap');
			if(wrap){wrap.insertBefore(bar,wrap.firstChild);}
			else{var wbc=document.getElementById('wpbody-content');if(wbc)wbc.insertBefore(bar,wbc.firstChild);}
		});
		</script>
		<?php
	}
}
