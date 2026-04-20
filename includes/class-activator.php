<?php
/**
 * Runs on plugin activation.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Activator {

	public static function activate(): void {
		// Register CPT and taxonomies so flush_rewrite_rules works.
		require_once PV_PLUGIN_DIR . 'includes/cpt/class-videos-cpt.php';
		require_once PV_PLUGIN_DIR . 'includes/cpt/class-video-taxonomies.php';

		( new PV_Videos_CPT() )->register();
		( new PV_Video_Taxonomies() )->register();

		flush_rewrite_rules();

		// Set default plugin options if not already set.
		$defaults = [
			'api_key'           => '',
			'channel_id'        => '',
			'default_accent'    => '#4f46e5',
			'display_mode'      => 'offcanvas',
			'watch_page_layout' => 'hero-top',
		];

		if ( ! get_option( 'pv_settings' ) ) {
			add_option( 'pv_settings', $defaults );
		}
	}
}
