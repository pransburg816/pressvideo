<?php
/**
 * Contract all display renderers must implement.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

interface PV_Renderer_Interface {
	/** Render the display mode and return the HTML string. */
	public function render( array $videos, array $args ): string;

	/** Enqueue any CSS/JS required by this mode. Called by the shortcode. */
	public function enqueue_assets(): void;

	/** Human-readable label shown in the Display Mode Picker. */
	public static function get_label(): string;

	/** Dashicon class or inline SVG path. */
	public static function get_icon(): string;

	/** One-line description shown in the Display Mode Picker. */
	public static function get_description(): string;

	/** Minimum tier required: 'silver', 'gold', or 'platinum'. */
	public static function get_required_tier(): string;
}
