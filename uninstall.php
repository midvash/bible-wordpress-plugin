<?php
/**
 * Uninstall script for Bible by Midvash.
 *
 * Runs when the user clicks “Delete” on the plugins screen (NOT on deactivate).
 * Removes everything we own: the bbm_options row and every transient we cached
 * (verse bodies, version catalogs, votd lookups, AJAX rate-limit counters).
 *
 * Hardcoded prefixes (bbm_, bbm_votd_, bbm_versions_, bbm_rl_) are intentionally
 * spelled out so future renames stay caught here.
 *
 * @package Bible_By_Midvash
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Single-site.
if ( ! is_multisite() ) {
	bbmv_uninstall_cleanup();
	return;
}

// Multisite: clean every blog. WordPress core itself reads $wpdb->blogs via
// $wpdb->get_col for this exact pattern (cf. wp_clean_plugins_cache uninstall
// paths) — there is no higher-level "for each blog" helper at uninstall time.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$bbmv_blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
if ( ! is_array( $bbmv_blog_ids ) ) {
	return;
}
foreach ( $bbmv_blog_ids as $bbmv_blog_id ) {
	switch_to_blog( (int) $bbmv_blog_id );
	bbmv_uninstall_cleanup();
	restore_current_blog();
}

/**
 * Wipes plugin state on the current blog.
 */
function bbmv_uninstall_cleanup() {
	global $wpdb;

	delete_option( 'bbm_options' );

	// Direct DELETE is the only reasonable way to nuke transients by prefix —
	// there is no bulk WordPress API for "delete every transient matching
	// bbm_*". Caching the result is meaningless on uninstall (we are wiping
	// state, not reading it), so the NoCaching warning is also intentional.
	//
	// Note: $wpdb->esc_like() escapes `_` and `%` so the underscores in our
	// prefix are treated as literals, not LIKE wildcards. An earlier version
	// used raw `\_transient\_bbm\_` with `ESCAPE '\\'` which MySQL parsed as
	// `_transient_bbm_` (underscores are 1-char wildcards by default) — that
	// "worked" only because the literal `transient` between them made
	// accidental matches impossible. The prepare()+esc_like() form is the
	// canonical pattern.
	$prefix_value   = $wpdb->esc_like( '_transient_bbm_' ) . '%';
	$prefix_timeout = $wpdb->esc_like( '_transient_timeout_bbm_' ) . '%';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $prefix_value ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $prefix_timeout ) );
}
