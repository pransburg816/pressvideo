<?php
/**
 * Single pv_youtube template.
 * Resolves the watch layout and delegates to templates/single/layouts/{layout}.php.
 * Override: theme/pv-youtube-importer/single/single-video.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $post;
if ( ! $post ) $post = get_queried_object();
setup_postdata( $post );

// Resolve layout and width
$pv_layout      = pv_resolve_watch_layout( $post->ID );
$_pv_s          = get_option( 'pv_settings', [] );
$_pv_watch_w    = get_post_meta( $post->ID, '_pv_watch_width', true ) ?: 'full';
$_pv_cw_map     = [ 'wide' => '1400px', 'medium' => '1200px', 'narrow' => '960px' ];
$_pv_max_w      = $_pv_cw_map[ $_pv_watch_w ] ?? '';
$_pv_w_attr     = $_pv_max_w ? ' style="max-width:' . esc_attr( $_pv_max_w ) . ';margin:0 auto;"' : '';

// Variables expected by all layout partials
$pv_youtube_id  = get_post_meta( $post->ID, '_pv_youtube_id', true );
$pv_accent      = pv_resolve_accent_color( $post->ID );
$pv_embed_url   = $pv_youtube_id
	? 'https://www.youtube.com/embed/' . $pv_youtube_id . '?rel=0&modestbranding=1&enablejsapi=1'
	: '';
$pv_watch_url   = $pv_youtube_id
	? 'https://www.youtube.com/watch?v=' . $pv_youtube_id
	: '';
$pv_duration    = get_post_meta( $post->ID, '_pv_duration', true );
$pv_view_count  = get_post_meta( $post->ID, '_pv_view_count', true );
$pv_imported_at = get_post_meta( $post->ID, '_pv_imported_at', true );
$pv_tags        = wp_get_post_terms( $post->ID, 'pv_tag',      [ 'fields' => 'all' ] );
$pv_categories  = wp_get_post_terms( $post->ID, 'pv_category', [ 'fields' => 'all' ] );

do_action( 'pv_player_enqueued' );

add_filter( 'body_class', function( array $classes ) use ( $pv_layout ) {
	$classes[] = 'pv-single-video';
	$classes[] = 'pv-layout-' . $pv_layout;
	return $classes;
} );

// Resolve layout file — fall back to hero-top if file missing
$layout_file = PV_PLUGIN_DIR . 'templates/single/layouts/' . $pv_layout . '.php';
if ( ! file_exists( $layout_file ) ) {
	$layout_file = PV_PLUGIN_DIR . 'templates/single/layouts/hero-top.php';
}

get_header();
?>
<div class="pv-single-wrap" style="--pv-accent:<?php echo esc_attr( $pv_accent ); ?>;">
	<div class="pv-single-inner"<?php echo $_pv_w_attr; // phpcs:ignore ?>>
		<?php include $layout_file; ?>
	</div>
</div>
<?php
get_footer();
wp_reset_postdata();
