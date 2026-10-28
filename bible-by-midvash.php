<?php
/**
 * Plugin Name: Bible by Midvash
 * Plugin URI:  https://midvash.com/wordpress-plugin
 * Description: Automatically identifies Bible references in posts and creates links with tooltips via the Midvash service.
 * Version: 0.10.1
 * Author: Neto Gregório
 * Author URI: https://www.netogregorio.com.br
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bible-by-midvash
 * Domain Path: /languages
 *
 * @package Bible_By_Midvash
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BBMV_VERSION', '0.10.1' );
define( 'BBMV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BBMV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BBMV_API_BASE_URL', 'https://api.midvash.com' );
define( 'BBMV_SITE_URL', 'https://midvash.com' );
// Identifies the plugin to the Midvash API, which then serves every version
// of midvash.com, including the ones with reserved rights. Sent only from PHP
// (wp_remote_get), never to the visitor's browser. It is public in this open
// source code: it identifies the plugin, it is not a secret. A site can
// define its own in wp-config.php. Empty = no header, free versions only.
if ( ! defined( 'BBMV_PLUGIN_KEY' ) ) {
	define( 'BBMV_PLUGIN_KEY', '' );
}

// Include classes.
require_once BBMV_PLUGIN_DIR . 'includes/class-bbmv-books.php';
require_once BBMV_PLUGIN_DIR . 'includes/class-bbmv-api.php';
require_once BBMV_PLUGIN_DIR . 'includes/class-bbmv-parser.php';
require_once BBMV_PLUGIN_DIR . 'includes/class-bbmv-admin.php';
require_once BBMV_PLUGIN_DIR . 'includes/class-bbmv-widget.php';
require_once BBMV_PLUGIN_DIR . 'includes/class-bbmv-block.php';

// {{WPORG_STRIP_START}}
// Plugin Update Checker — auto-update for installs distributed outside the
// WordPress.org directory (download from WordPress.midvash.com).
//
// IMPORTANT: this whole block (including the markers) is stripped by
// scripts/build-zip.ts when building the ZIP for submission to wordpress.org,
// because the official Plugin Review Guidelines forbid plugins from bundling
// their own update mechanism when hosted on the official directory.
if ( file_exists( BBMV_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php' ) ) {
	require_once BBMV_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
	if ( class_exists( 'YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
		$bbmv_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://midvash.com/api/wordpress/update-info.json',
			__FILE__,
			'bible-by-midvash'
		);
	}
}
// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Build-script marker, not commented-out code.
// {{WPORG_STRIP_END}}

/**
 * Initialize the plugin
 */
function bbmv_init() {
	// WordPress only loads translations on its own from wp-content/languages
	// (translate.wordpress.org). The .mo files bundled in /languages need this
	// call, or the plugin stays in English on every site.
	// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Bundled translations.
	load_plugin_textdomain( 'bible-by-midvash', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Initialize the parser.
	$parser = new BBMV_Parser();
	$parser->init();

	// Initialize the admin (only in panel).
	if ( is_admin() ) {
		$admin = new BBMV_Admin();
		$admin->init();
	}
}
add_action( 'init', 'bbmv_init' );

/**
 * Register the VOTD widget and shortcode
 */
function bbmv_register_widget() {
	register_widget( 'BBMV_Widget' );
}
add_action( 'widgets_init', 'bbmv_register_widget' );
add_shortcode( 'bbm_votd', 'bbmv_votd_shortcode' );

/**
 * Initialize the Gutenberg block
 */
function bbmv_register_block() {
	$block = new BBMV_Block();
	$block->init();
}
bbmv_register_block();

/**
 * URL of a bundled asset, preferring the minified copy built into the ZIP.
 *
 * The repository keeps only the readable sources; scripts/build-zip.ts adds
 * `name-<version>.min.ext` next to each one. The version lives in the file
 * name, not only in `?ver=`, because caching plugins (WP Rocket's "remove
 * query strings") strip the query and a CDN in front of the site then keeps
 * serving the previous release's file for weeks. SCRIPT_DEBUG serves the
 * source.
 *
 * @param string $path Path under the plugin directory (e.g. 'assets/js/bbm-tooltip.js').
 * @return string
 */
function bbmv_asset_url( $path ) {
	$min = preg_replace( '/\.(js|css)$/', '-' . BBMV_VERSION . '.min.$1', $path );
	if ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) && file_exists( BBMV_PLUGIN_DIR . $min ) ) {
		return BBMV_PLUGIN_URL . $min;
	}
	return BBMV_PLUGIN_URL . $path;
}

/**
 * Register scripts and styles.
 *
 * Nothing is enqueued here: BBMV_Parser enqueues the tooltip only when a post
 * actually has a reference, and the verse block enqueues its stylesheet.
 */
function bbmv_register_assets() {
	$options = get_option( 'bbm_options', array() );
	$locale  = BBMV_Books::normalize_locale( isset( $options['locale'] ) ? $options['locale'] : 'pt-br' );

	wp_register_style( 'bbm-style', bbmv_asset_url( 'assets/css/bbm.css' ), array(), BBMV_VERSION );

	// Custom Link Styling.
	$css_styles = array();

	// Color.
	$use_custom_color = isset( $options['use_custom_color'] ) ? (bool) $options['use_custom_color'] : false;
	if ( $use_custom_color ) {
		$link_color   = isset( $options['link_color'] ) ? $options['link_color'] : '#B17027';
		$css_styles[] = sprintf( 'color: %s;', esc_attr( $link_color ) );
	}

	// Underline.
	$underline_link = isset( $options['underline_link'] ) ? (bool) $options['underline_link'] : false;
	if ( $underline_link ) {
		$underline_color = isset( $options['underline_color'] ) ? $options['underline_color'] : '#B17027';
		$underline_style = isset( $options['underline_style'] ) ? $options['underline_style'] : 'solid';

		$css_styles[] = 'text-decoration-line: underline;';
		$css_styles[] = sprintf( 'text-decoration-color: %s;', esc_attr( $underline_color ) );
		$css_styles[] = sprintf( 'text-decoration-style: %s;', esc_attr( $underline_style ) );
	} else {
		$css_styles[] = 'text-decoration: none;';
	}

	// The tooltip's accent ("Read more", verse numbers) follows the Link Color
	// setting even when links keep the theme colour.
	$accent = sanitize_hex_color( isset( $options['link_color'] ) ? $options['link_color'] : '' );
	$accent = $accent ? $accent : '#B17027';

	wp_add_inline_style( 'bbm-style', '.bbm-link { ' . implode( ' ', $css_styles ) . ' } .bbm-tooltip { --bbm-accent: ' . $accent . '; }' );

	wp_register_script( 'bbm-tooltip', bbmv_asset_url( 'assets/js/bbm-tooltip.js' ), array(), BBMV_VERSION, true );

	// Strings flow through __() so they're resolved against the site's
	// textdomain. The copyright attribution comes from the cached catalogue
	// only: the API is never called while a page renders (a slow or offline
	// API would hold the page for the whole timeout). A cold cache is filled
	// by a background cron event instead.
	$api          = new BBMV_API();
	$version_slug = isset( $options['versao'] ) ? BBMV_Books::resolve_version( $options['versao'] ) : BBMV_Books::get_default_version( $locale );
	$version_meta = $api->get_version_meta( $version_slug, false );
	if ( null === $version_meta && ! $api->has_catalog_cache() && ! wp_next_scheduled( 'bbmv_refresh_catalog' ) ) {
		wp_schedule_single_event( time(), 'bbmv_refresh_catalog' );
	}

	wp_localize_script(
		'bbm-tooltip',
		'bbm_config',
		array(
			'ajax_url'          => admin_url( 'admin-ajax.php' ),
			'version'           => $version_slug,
			'version_label'     => $api->get_version_label( $version_slug ),
			'version_copyright' => ( $version_meta && ! empty( $version_meta['copyright'] ) ) ? $version_meta['copyright'] : '',
			'locale'            => $locale,
			'show_version'      => isset( $options['show_version'] ) ? $options['show_version'] : true,
			'fallback_message'  => __( 'Verse currently unavailable', 'bible-by-midvash' ),
			'read_more'         => __( 'Read more', 'bible-by-midvash' ),
			'site_url'          => BBMV_Books::site_url( $locale ),
			'icon_url'          => BBMV_PLUGIN_URL . 'assets/images/icon-bbm.svg',
			'debug'             => defined( 'WP_DEBUG' ) && WP_DEBUG,
		)
	);
}
add_action( 'wp_enqueue_scripts', 'bbmv_register_assets' );

/**
 * Fills the version catalogue cache outside of a page render.
 */
function bbmv_refresh_catalog() {
	$api = new BBMV_API();
	$api->get_versions();
}
add_action( 'bbmv_refresh_catalog', 'bbmv_refresh_catalog' );

/**
 * Returns a stable identifier for rate-limit bucketing.
 *
 * Uses REMOTE_ADDR hashed with WP's secret salt — never stored raw, so this
 * is not “collecting visitor data”, just a one-way bucket key that expires
 * with the transient (60s window). Falls back to a constant string when the
 * IP is missing (CLI, weird proxies) — worst case all anonymous visitors
 * share the bucket, which only over-throttles, never under-throttles.
 *
 * @param string $action Action slug used to namespace the rate-limit bucket.
 */
function bbmv_rate_limit_key( $action ) {
	$ip = '';
	if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}
	$bucket = '' !== $ip ? $ip : 'anon';
	return 'bbm_rl_' . $action . '_' . md5( $bucket . wp_salt( 'auth' ) );
}

/**
 * Throttles an action to N hits per 60-second window. Returns true if the
 * caller is over budget (and should be rejected).
 *
 * @param string $action Action slug identifying the rate-limit bucket.
 * @param int    $limit  Maximum number of hits allowed per 60-second window.
 */
function bbmv_is_rate_limited( $action, $limit ) {
	$key   = bbmv_rate_limit_key( $action );
	$count = (int) get_transient( $key );
	if ( $count >= $limit ) {
		return true;
	}
	set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
	return false;
}

/**
 * AJAX handler to fetch verses (public — used by the tooltip).
 */
function bbmv_ajax_get_verse() {
	// No nonce: this public, read-only endpoint serves Bible text, so there is
	// nothing to forge, and a nonce baked into cached HTML expires after a day
	// and breaks every tooltip on sites behind a page cache. Abuse is bounded
	// by the rate limit below.

	// Throttle to 120 verse lookups per minute per IP. Generous for normal
	// browsing (hovering many references), tight against bots flooding our
	// upstream API.
	if ( bbmv_is_rate_limited( 'verse', 120 ) ) {
		wp_send_json_error( array( 'message' => __( 'Too many requests', 'bible-by-midvash' ) ), 429 );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public read-only endpoint, see above.
	$reference = isset( $_GET['reference'] ) ? sanitize_text_field( wp_unslash( $_GET['reference'] ) ) : '';
	$version   = isset( $_GET['version'] ) ? sanitize_text_field( wp_unslash( $_GET['version'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( empty( $reference ) ) {
		wp_send_json_error( array( 'message' => __( 'Reference not provided', 'bible-by-midvash' ) ) );
	}

	$api    = new BBMV_API();
	$result = $api->get_verse( $reference, $version );

	if ( $result ) {
		wp_send_json_success( $result );
	} else {
		wp_send_json_error( array( 'message' => __( 'Verse not found', 'bible-by-midvash' ) ) );
	}
}
add_action( 'wp_ajax_bbm_get_verse', 'bbmv_ajax_get_verse' );
add_action( 'wp_ajax_nopriv_bbm_get_verse', 'bbmv_ajax_get_verse' );

/**
 * AJAX handler that prefetches every reference on a page in one round-trip.
 *
 * The tooltip script collects the unique data-midvash-ref values after render
 * and calls this once, instead of one bbm_get_verse request per hover. The
 * upstream fan-out is batched through the API's /v1/passages endpoint.
 */
function bbmv_ajax_get_verses() {
	// No nonce, for the same reason as bbmv_ajax_get_verse().

	// One batch covers a whole page view, so the budget is much smaller than
	// the per-verse endpoint's. 20/min per IP still allows brisk browsing.
	if ( bbmv_is_rate_limited( 'batch', 20 ) ) {
		wp_send_json_error( array( 'message' => __( 'Too many requests', 'bible-by-midvash' ) ), 429 );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Public read-only endpoint, see bbmv_ajax_get_verse().
	$refs_raw = isset( $_POST['refs'] ) ? sanitize_text_field( wp_unslash( $_POST['refs'] ) ) : '';
	$version  = isset( $_POST['version'] ) ? sanitize_text_field( wp_unslash( $_POST['version'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	$references = array_filter( array_map( 'trim', explode( '|', $refs_raw ) ) );
	if ( empty( $references ) ) {
		wp_send_json_error( array( 'message' => __( 'Reference not provided', 'bible-by-midvash' ) ) );
	}
	// Cap the page budget: 60 unique references (3 upstream batch requests).
	$references = array_slice( array_unique( $references ), 0, 60 );

	$api     = new BBMV_API();
	$results = $api->get_passages( $references, $version );

	wp_send_json_success( $results );
}
add_action( 'wp_ajax_bbm_get_verses', 'bbmv_ajax_get_verses' );
add_action( 'wp_ajax_nopriv_bbm_get_verses', 'bbmv_ajax_get_verses' );

/**
 * AJAX handler to fetch Bible versions by locale (admin only).
 *
 * Used by the settings page to refresh the version <select> when the language
 * changes. Restricted to `manage_options` to avoid letting any logged-in user
 * (subscribers, contributors) hammer our upstream API.
 */
function bbmv_ajax_get_versions() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Forbidden', 'bible-by-midvash' ) ), 403 );
	}
	check_ajax_referer( 'bbm_nonce', 'nonce' );

	$locale = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : 'pt-br';
	$locale = BBMV_Books::normalize_locale( $locale );

	$api      = new BBMV_API();
	$versions = $api->get_versions( $locale );

	if ( $versions ) {
		wp_send_json_success( $versions );
	} else {
		wp_send_json_error( array( 'message' => __( 'Failed to fetch versions', 'bible-by-midvash' ) ) );
	}
}
add_action( 'wp_ajax_bbm_get_versions', 'bbmv_ajax_get_versions' );

/**
 * Plugin activation - set default options
 */
function bbmv_activate() {
	// Get default version based on locale.
	$locale          = 'pt-br'; // Default locale.
	$default_version = BBMV_Books::get_default_version( $locale );

	$defaults = array(
		'locale'           => $locale,
		'versao'           => $default_version,
		'use_custom_color' => false,
		'link_color'       => '#B17027',
		'underline_link'   => false,
		'underline_color'  => '#B17027',
		'underline_style'  => 'solid',
		'new_tab'          => true,
		'css_class'        => 'bbm-link',
		'cache_enabled'    => true,
		'cache_ttl'        => 2592000, // 30 days
		'timeout'          => 5,
		'show_version'     => true,
		'link_biblia'      => false,
	);

	if ( ! get_option( 'bbm_options' ) ) {
		add_option( 'bbm_options', $defaults );
	}
}
register_activation_hook( __FILE__, 'bbmv_activate' );

/**
 * Plugin deactivation.
 *
 * Intentionally a no-op: deactivating is reversible — wiping transients
 * here would penalise users who deactivate to debug a theme conflict and
 * then reactivate. Persistent cleanup happens in uninstall.php instead.
 */
function bbmv_deactivate() {
	// No-op.
}
register_deactivation_hook( __FILE__, 'bbmv_deactivate' );
