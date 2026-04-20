<?php
/**
 * Runs on plugin deletion. Removes all plugin data.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options.
delete_option( 'pv_settings' );

// Delete all pv_video posts and their meta.
$video_ids = get_posts( [
	'post_type'      => 'pv_youtube',
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'post_status'    => 'any',
] );

foreach ( $video_ids as $id ) {
	wp_delete_post( $id, true );
}

// Remove term meta for pv taxonomies.
$taxonomies = [ 'pv_tag', 'pv_category', 'pv_series', 'pv_type' ];
foreach ( $taxonomies as $tax ) {
	$terms = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'ids' ] );
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term_id ) {
			delete_term_meta( $term_id, 'pv_color' );
		}
	}
}

// Flush rewrite rules.
flush_rewrite_rules();
