<?php
/**
 * Temporary MU-plugin: invalidate specific PV plugin files in OPcache,
 * add a visible admin notice to confirm execution, then self-delete.
 */

// Invalidate each key file so PHP recompiles from disk on next load.
if ( function_exists( 'opcache_invalidate' ) ) {
	$dir = WP_CONTENT_DIR . '/plugins/pv-youtube-importer/';
	$files = [
		'pv-youtube-importer.php',
		'includes/class-plugin.php',
		'includes/class-tier.php',
		'includes/admin/class-analytics-page.php',
		'includes/analytics/class-analytics-tracker.php',
		'includes/analytics/class-youtube-oauth.php',
		'includes/analytics/class-youtube-analytics-api.php',
	];
	foreach ( $files as $f ) {
		opcache_invalidate( $dir . $f, true );
	}
}

// Show a visible admin notice so we know this ran.
add_action( 'admin_notices', function() {
	echo '<div class="notice notice-success"><p><strong>PV OPcache flush ran.</strong> You can now reload the Analytics page.</p></div>';
} );

// Self-delete after this request.
add_action( 'shutdown', function() {
	@unlink( __FILE__ );
} );
