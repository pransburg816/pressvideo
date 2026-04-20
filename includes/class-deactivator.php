<?php
/**
 * Runs on plugin deactivation.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Deactivator {

	public static function deactivate(): void {
		// Clear scheduled cron events.
		$timestamp = wp_next_scheduled( 'pv_import_cron' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'pv_import_cron' );
		}

		flush_rewrite_rules();
	}
}
