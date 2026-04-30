<?php
/**
 * PressVideo admin branding — full-shell takeover for CPT list and taxonomy pages.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Admin_Branding {

	/** Screen IDs that render their own PV shell — skip ours. */
	private function fullscreen_ids(): array {
		return [
			'pv_youtube_page_pv-youtube-importer-dashboard',
			'pv_youtube_page_pv-youtube-importer-settings',
			'pv_youtube_page_pv-customizer',
			'pv_youtube_page_pv-analytics',
		];
	}

	/** True when we're on the Gutenberg post-edit / add-new screen for pv_youtube. */
	private function is_pv_post_edit(): bool {
		$screen = get_current_screen();
		if ( ! $screen ) return false;
		return ( $screen->post_type ?? '' ) === 'pv_youtube' && $screen->base === 'post';
	}

	/** True when we're on a CPT/taxonomy page that needs our shell. */
	private function is_pv_page(): bool {
		$screen = get_current_screen();
		if ( ! $screen ) return false;
		if ( in_array( $screen->id, $this->fullscreen_ids(), true ) ) return false;
		// List screen only — exclude the Gutenberg editor (base=post) which manages its own fullscreen UI.
		if ( ( $screen->post_type ?? '' ) === 'pv_youtube' && $screen->base === 'edit' ) return true;
		if ( in_array( $screen->taxonomy ?? '', [ 'pv_tag', 'pv_category', 'pv_series', 'pv_type' ], true ) ) return true;
		return false;
	}

	public function register(): void {
		add_filter( 'admin_body_class',      [ $this, 'body_class'         ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets'     ] );
		add_action( 'admin_head',            [ $this, 'render_loader'      ] );
		add_action( 'admin_footer',          [ $this, 'render_shell'       ] );
		add_action( 'admin_head',            [ $this, 'render_editor_bar'  ] );
	}

	public function body_class( string $classes ): string {
		if ( ! $this->is_pv_page() ) return $classes;
		return $classes . ' pv-admin-page pv-fullscreen-ui pv-cpt-page';
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

	// ── Animated loader ──────────────────────────────────────────────

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
			document.documentElement.style.marginTop='0';
			document.documentElement.style.paddingTop='0';
			var l=document.createElement('div');
			l.id='pv-brand-loader';
			l.setAttribute('aria-hidden','true');
			l.innerHTML=<?php echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON-encoded by wp_json_encode ?>;
			document.documentElement.appendChild(l);
			document.addEventListener('DOMContentLoaded',function(){
				document.body.style.marginTop='0';
				document.body.style.paddingTop='0';
				setTimeout(function(){
					l.classList.add('pvl-out');
					setTimeout(function(){if(l.parentNode)l.parentNode.removeChild(l);},480);
				},520);
			});
		}());
		</script>
		<?php
	}

	// ── Contextual active-state helper ───────────────────────────────

	private function active_screen(): string {
		$screen = get_current_screen();
		if ( ! $screen ) return '';
		// Taxonomy pages must be checked first — they also carry post_type=pv_youtube.
		if ( ! empty( $screen->taxonomy ) ) {
			return $screen->id; // e.g. 'edit-pv_category'
		}
		if ( ( $screen->post_type ?? '' ) === 'pv_youtube' ) {
			// Distinguish add-new from list/edit so "Add New Video" gets its own active state.
			if ( $screen->base === 'post' && $screen->action === 'add' ) {
				return 'add-pv_youtube';
			}
			return 'edit-pv_youtube';
		}
		return $screen->id;
	}

	// ── Full PV shell (aside + nav) ───────────────────────────────────

	public function render_shell(): void {
		if ( ! $this->is_pv_page() ) return;

		$active = $this->active_screen();

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

		$manage_nav = [
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
		?>
		<aside id="pv-aside">

			<div class="pv-aside__brand">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7L8 5z"/></svg>
				<span>PressVideo</span>
			</div>

			<nav class="pv-aside__nav" aria-label="<?php esc_attr_e( 'PressVideo', 'pv-youtube-importer' ); ?>">
				<div class="pv-aside__nav-section"><?php esc_html_e( 'Library', 'pv-youtube-importer' ); ?></div>
				<?php foreach ( $library_nav as $item ) : ?>
				<a href="<?php echo esc_url( $item['url'] ); ?>"
				   class="pv-aside__nav-item<?php echo $active === $item['screen'] ? ' is-active' : ''; ?>">
					<?php echo $item['icon']; // phpcs:ignore WordPress.Security.EscapeOutput -- trusted internal SVG ?>
					<span><?php echo esc_html( $item['label'] ); ?></span>
				</a>
				<?php endforeach; ?>

				<div class="pv-aside__nav-section"><?php esc_html_e( 'Manage', 'pv-youtube-importer' ); ?></div>
				<?php foreach ( $manage_nav as $item ) : ?>
				<a href="<?php echo esc_url( $item['url'] ); ?>"
				   class="pv-aside__nav-item<?php echo $active === $item['screen'] ? ' is-active' : ''; ?>">
					<?php echo $item['icon']; // phpcs:ignore WordPress.Security.EscapeOutput -- trusted internal SVG ?>
					<span><?php echo esc_html( $item['label'] ); ?></span>
				</a>
				<?php endforeach; ?>
			</nav>

			<div class="pv-aside__footer">
				<a href="<?php echo esc_url( admin_url( 'index.php' ) ); ?>" class="pv-aside__exit">
					<svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
					<?php esc_html_e( 'Exit to WP Admin', 'pv-youtube-importer' ); ?>
				</a>
			</div>

		</aside>
		<script>
		(function(){
			document.addEventListener('DOMContentLoaded',function(){
				document.querySelectorAll('#pv-aside .pv-aside__nav-item[href]').forEach(function(link){
					link.addEventListener('click',function(e){
						if(link.classList.contains('is-active'))return;
						e.preventDefault();
						var dest=link.href;
						var old=document.getElementById('pv-brand-loader');
						if(old)old.parentNode.removeChild(old);
						var mask=document.createElement('div');
						mask.id='pv-brand-loader';
						mask.setAttribute('aria-hidden','true');
						mask.innerHTML=
							'<div class="pvl-inner">'+
							'<div class="pvl-icon"><svg viewBox="0 0 88 88" fill="none" xmlns="http://www.w3.org/2000/svg">'+
							'<circle cx="44" cy="44" r="40" stroke="rgba(99,102,241,0.12)" stroke-width="3"\/>'+
							'<circle cx="44" cy="44" r="40" stroke="#4f46e5" stroke-width="3" stroke-linecap="round" stroke-dasharray="251" stroke-dashoffset="190" class="pvl-arc"\/>'+
							'<circle cx="44" cy="44" r="30" stroke="rgba(99,102,241,0.07)" stroke-width="18" class="pvl-glow"\/>'+
							'<path d="M37 28 L59 44 L37 60 Z" fill="white" class="pvl-play"\/>'+
							'<\/svg><\/div>'+
							'<div class="pvl-wordmark"><span class="pvl-w1">Press<\/span><span class="pvl-w2">Video<\/span><\/div>'+
							'<div class="pvl-dots"><span><\/span><span><\/span><span><\/span><\/div>'+
							'<\/div>';
						document.body.appendChild(mask);
						requestAnimationFrame(function(){
							requestAnimationFrame(function(){
								window.location.href=dest;
							});
						});
					});
				});
			});
		}());
		</script>
		<?php
	}

	// ── Gutenberg editor bar (bottom fixed, non-intrusive) ────────────

	public function render_editor_bar(): void {
		if ( ! $this->is_pv_post_edit() ) return;

		$nav = [
			[ 'label' => 'All Videos',   'url' => admin_url( 'edit.php?post_type=pv_youtube' ) ],
			[ 'label' => 'Categories',   'url' => admin_url( 'edit-tags.php?taxonomy=pv_category&post_type=pv_youtube' ) ],
			[ 'label' => 'Tags',         'url' => admin_url( 'edit-tags.php?taxonomy=pv_tag&post_type=pv_youtube' ) ],
			[ 'label' => 'Series',       'url' => admin_url( 'edit-tags.php?taxonomy=pv_series&post_type=pv_youtube' ) ],
			[ 'label' => 'Dashboard',    'url' => admin_url( 'edit.php?post_type=pv_youtube&page=pv-youtube-importer-dashboard' ) ],
			[ 'label' => 'Settings',     'url' => admin_url( 'edit.php?post_type=pv_youtube&page=pv-youtube-importer-settings' ) ],
			[ 'label' => 'Live Preview', 'url' => admin_url( 'edit.php?post_type=pv_youtube&page=pv-customizer' ) ],
			[ 'label' => 'Analytics',    'url' => admin_url( 'edit.php?post_type=pv_youtube&page=pv-analytics' ) ],
		];
		?>
		<style>
		#pv-editor-bar{position:fixed;bottom:0;left:0;right:0;height:38px;z-index:999999;background:linear-gradient(90deg,#1e1b4b 0%,#2d2a6e 100%);border-top:1px solid rgba(99,102,241,0.35);display:flex;align-items:center;padding:0 16px;gap:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
		#pv-editor-bar__brand{display:flex;align-items:center;gap:7px;color:#fff;font-size:.8rem;font-weight:800;letter-spacing:-.02em;flex-shrink:0;margin-right:18px;}
		#pv-editor-bar__brand svg{color:#818cf8;}
		#pv-editor-bar__sep{width:1px;height:16px;background:rgba(255,255,255,.15);flex-shrink:0;margin-right:6px;}
		#pv-editor-bar__nav{display:flex;align-items:center;gap:2px;overflow-x:auto;scrollbar-width:none;}
		#pv-editor-bar__nav::-webkit-scrollbar{display:none;}
		.pv-eb-link{display:inline-block;padding:4px 10px;border-radius:4px;font-size:.72rem;font-weight:500;color:rgba(199,210,254,.65);text-decoration:none;white-space:nowrap;transition:background .15s,color .15s;}
		.pv-eb-link:hover{background:rgba(255,255,255,.1);color:#c7d2fe;text-decoration:none;}
		/* Push Gutenberg's own bottom bar up so our bar doesn't cover it */
		.block-editor-editor-skeleton__footer,.edit-post-layout__footer{margin-bottom:38px !important;}
		</style>
		<script>
		document.addEventListener('DOMContentLoaded',function(){
			var bar=document.createElement('div');
			bar.id='pv-editor-bar';
			bar.innerHTML=
				'<div id="pv-editor-bar__brand">'+
				'<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"\/><\/svg>'+
				'PressVideo<\/div>'+
				'<div id="pv-editor-bar__sep"><\/div>'+
				'<nav id="pv-editor-bar__nav"><?php
					foreach ( $nav as $item ) {
						printf(
							'<a class="pv-eb-link" href="%s">%s<\/a>',
							esc_url( $item['url'] ),
							esc_html( $item['label'] )
						);
					}
				?><\/nav>';
			document.body.appendChild(bar);
		});
		</script>
		<?php
	}
}
