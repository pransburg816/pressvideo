<?php
/**
 * Temporary OPcache flush utility — DELETE after use.
 * Visit this URL once while logged into WP admin, then delete the file.
 */

define( 'ABSPATH', '' );
$wp_load = __DIR__ . '/../../../wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	exit( 'wp-load.php not found' );
}
require $wp_load;

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Access denied.' );
}

$result = false;
if ( function_exists( 'opcache_reset' ) ) {
	$result = opcache_reset();
}

echo '<h2>OPcache flush: ' . ( $result ? '✓ Success' : '✗ opcache_reset() unavailable or failed' ) . '</h2>';
echo '<p>You can close this tab and reload the Analytics page now.</p>';
echo '<p><a href="' . admin_url( 'edit.php?post_type=pv_youtube&page=pv-analytics' ) . '">Go to Analytics &rarr;</a></p>';
