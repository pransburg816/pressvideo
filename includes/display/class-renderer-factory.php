<?php
/**
 * Resolves the correct renderer class for a given display mode and enforces tier gating.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Renderer_Factory {

	/** @var array<string, class-string<PV_Renderer_Interface>> */
	private static array $map = [
		'offcanvas'    => 'PV_Renderer_Offcanvas',
		'modal'        => 'PV_Renderer_Modal',
		// Phase 3 renderers — registered here once built:
		// 'lightbox'     => 'PV_Renderer_Lightbox',
		// 'grid-hero'    => 'PV_Renderer_Grid_Hero',
		// 'watch-page'   => 'PV_Renderer_Watch_Page',
		// 'carousel'     => 'PV_Renderer_Carousel',
		// 'masonry'      => 'PV_Renderer_Masonry',
		// 'channel-tabs' => 'PV_Renderer_Channel_Tabs',
	];

	public static function make( string $layout ): PV_Renderer_Interface {
		$class    = self::$map[ $layout ] ?? 'PV_Renderer_Offcanvas';
		$required = $class::get_required_tier();

		if ( ! PV_Tier::meets( $required ) ) {
			return new PV_Renderer_Upgrade_Prompt( $required );
		}

		return new $class();
	}

	/** Return all registered display modes with their metadata. */
	public static function all_modes(): array {
		$modes = [];
		foreach ( self::$map as $key => $class ) {
			$modes[ $key ] = [
				'label'         => $class::get_label(),
				'icon'          => $class::get_icon(),
				'description'   => $class::get_description(),
				'required_tier' => $class::get_required_tier(),
				'available'     => PV_Tier::meets( $class::get_required_tier() ),
			];
		}
		return $modes;
	}
}

/**
 * Fallback renderer shown when a user tries to use a tier-gated display mode.
 */
class PV_Renderer_Upgrade_Prompt implements PV_Renderer_Interface {

	private string $required_tier;

	public function __construct( string $required_tier ) {
		$this->required_tier = $required_tier;
	}

	public function render( array $videos, array $args ): string {
		return sprintf(
			'<div class="pv-upgrade-notice"><p>%s</p></div>',
			esc_html( sprintf(
				/* translators: %s: tier name */
				__( 'This display mode requires the %s plan. Upgrade to unlock it.', 'pv-youtube-importer' ),
				ucfirst( $this->required_tier )
			) )
		);
	}

	public function enqueue_assets(): void {}

	public static function get_label(): string       { return __( 'Upgrade Required', 'pv-youtube-importer' ); }
	public static function get_icon(): string        { return 'dashicons-lock'; }
	public static function get_description(): string { return ''; }
	public static function get_required_tier(): string { return 'silver'; }
}
