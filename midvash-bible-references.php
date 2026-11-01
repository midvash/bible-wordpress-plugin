<?php
/**
 * Plugin Name: Midvash Bible References
 * Plugin URI:  https://midvash.com/wordpress-plugin
 * Description: Automatically identifies Bible references in posts and creates links with tooltips via the Midvash service.
 * Version: 1.0.0
 * Author: Midvash
 * Author URI: https://midvash.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: midvash-bible-references
 *
 * @package Midvash_Bible_References
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'MVBR_VERSION', '1.0.0' );
define( 'MVBR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MVBR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MVBR_API_BASE_URL', 'https://api.midvash.com' );
define( 'MVBR_SITE_URL', 'https://midvash.com' );
// Identifies the plugin to the Midvash API, which then serves every version
// of midvash.com, including the ones with reserved rights. Sent only from PHP
// (wp_remote_get), never to the visitor's browser. It is public in this open
// source code: it identifies the plugin, it is not a secret. A site can
// define its own in wp-config.php. Empty = no header, free versions only.
if ( ! defined( 'MVBR_PLUGIN_KEY' ) ) {
	define( 'MVBR_PLUGIN_KEY', '' );
}

// Include classes.
require_once MVBR_PLUGIN_DIR . 'includes/class-mvbr-books.php';
require_once MVBR_PLUGIN_DIR . 'includes/class-mvbr-api.php';
require_once MVBR_PLUGIN_DIR . 'includes/class-mvbr-parser.php';
require_once MVBR_PLUGIN_DIR . 'includes/class-mvbr-admin.php';
require_once MVBR_PLUGIN_DIR . 'includes/class-mvbr-widget.php';
require_once MVBR_PLUGIN_DIR . 'includes/class-mvbr-block.php';

/**
 * One-time move of the settings saved by versions before 1.0.0, when the
 * plugin used the short `bbm` prefix: the options row and the verse-of-the-day
 * widgets. The old rows are deleted once copied.
 */
function mvbr_migrate_legacy_data() {
	$legacy = get_option( 'bbm_options' );
	if ( false !== $legacy ) {
		if ( false === get_option( 'mvbr_options' ) && is_array( $legacy ) ) {
			if ( isset( $legacy['css_class'] ) && 'bbm-link' === $legacy['css_class'] ) {
				$legacy['css_class'] = 'mvbr-link';
			}
			add_option( 'mvbr_options', $legacy );
		}
		delete_option( 'bbm_options' );
	}

	$legacy_widgets = get_option( 'widget_bbm_votd_widget' );
	if ( false !== $legacy_widgets ) {
		// WordPress may already have stored an empty settings row for the new
		// widget (only the `_multiwidget` flag); that one is overwritten.
		$current = get_option( 'widget_mvbr_votd_widget' );
		if ( ! is_array( $current ) || ! array_filter( array_keys( $current ), 'is_int' ) ) {
			update_option( 'widget_mvbr_votd_widget', $legacy_widgets );
			$sidebars = get_option( 'sidebars_widgets' );
			if ( is_array( $sidebars ) ) {
				foreach ( $sidebars as $sidebar => $widgets ) {
					if ( is_array( $widgets ) ) {
						$sidebars[ $sidebar ] = preg_replace( '/^bbm_votd_widget-/', 'mvbr_votd_widget-', $widgets );
					}
				}
				update_option( 'sidebars_widgets', $sidebars );
			}
		}
		delete_option( 'widget_bbm_votd_widget' );
	}
}
add_action( 'plugins_loaded', 'mvbr_migrate_legacy_data' );

/**
 * Initialize the plugin
 */
function mvbr_init() {
	// Initialize the parser.
	$parser = new MVBR_Parser();
	$parser->init();

	// Initialize the admin (only in panel).
	if ( is_admin() ) {
		$admin = new MVBR_Admin();
		$admin->init();
	}
}
add_action( 'init', 'mvbr_init' );

/**
 * Register the VOTD widget and shortcode
 */
function mvbr_register_widget() {
	register_widget( 'MVBR_Widget' );
}
add_action( 'widgets_init', 'mvbr_register_widget' );
add_shortcode( 'mvbr_votd', 'mvbr_votd_shortcode' );

/**
 * Initialize the Gutenberg block
 */
function mvbr_register_block() {
	$block = new MVBR_Block();
	$block->init();
}
mvbr_register_block();

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
 * @param string $path Path under the plugin directory (e.g. 'assets/js/mvbr-tooltip.js').
 * @return string
 */
function mvbr_asset_url( $path ) {
	$min = preg_replace( '/\.(js|css)$/', '-' . MVBR_VERSION . '.min.$1', $path );
	if ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) && file_exists( MVBR_PLUGIN_DIR . $min ) ) {
		return MVBR_PLUGIN_URL . $min;
	}
	return MVBR_PLUGIN_URL . $path;
}

/**
 * A stored colour as a safe CSS value: the hex colour itself, or the default.
 *
 * @param mixed $color Colour from the plugin options.
 * @return string
 */
function mvbr_css_color( $color ) {
	$color = is_string( $color ) ? sanitize_hex_color( $color ) : null;
	return $color ? $color : '#B17027';
}

/**
 * Register scripts and styles.
 *
 * Nothing is enqueued here: MVBR_Parser enqueues the tooltip only when a post
 * actually has a reference, and the verse block enqueues its stylesheet.
 */
function mvbr_register_assets() {
	$options = get_option( 'mvbr_options', array() );
	$locale  = MVBR_Books::normalize_locale( isset( $options['locale'] ) ? $options['locale'] : 'pt-br' );

	wp_register_style( 'mvbr-style', mvbr_asset_url( 'assets/css/mvbr.css' ), array(), MVBR_VERSION );

	// Custom link styling. Every value is validated here, right before it is
	// written into the CSS: colours must be hex, the underline style must be
	// one of the CSS keywords the settings page offers. Anything else falls
	// back to the default, so a malformed stored option cannot inject CSS.
	$css_styles = array();

	$use_custom_color = isset( $options['use_custom_color'] ) ? (bool) $options['use_custom_color'] : false;
	if ( $use_custom_color ) {
		$css_styles[] = 'color: ' . mvbr_css_color( isset( $options['link_color'] ) ? $options['link_color'] : '' ) . ';';
	}

	$underline_link = isset( $options['underline_link'] ) ? (bool) $options['underline_link'] : false;
	if ( $underline_link ) {
		$underline_style = isset( $options['underline_style'] ) ? $options['underline_style'] : 'solid';
		if ( ! in_array( $underline_style, array( 'solid', 'dotted', 'dashed', 'double', 'wavy' ), true ) ) {
			$underline_style = 'solid';
		}

		$css_styles[] = 'text-decoration-line: underline;';
		$css_styles[] = 'text-decoration-color: ' . mvbr_css_color( isset( $options['underline_color'] ) ? $options['underline_color'] : '' ) . ';';
		$css_styles[] = 'text-decoration-style: ' . $underline_style . ';';
	} else {
		$css_styles[] = 'text-decoration: none;';
	}

	// The tooltip's accent ("Read more", verse numbers) follows the Link Color
	// setting even when links keep the theme colour.
	$accent = mvbr_css_color( isset( $options['link_color'] ) ? $options['link_color'] : '' );

	wp_add_inline_style( 'mvbr-style', wp_strip_all_tags( '.mvbr-link { ' . implode( ' ', $css_styles ) . ' } .mvbr-tooltip { --mvbr-accent: ' . $accent . '; }' ) );

	wp_register_script( 'mvbr-tooltip', mvbr_asset_url( 'assets/js/mvbr-tooltip.js' ), array(), MVBR_VERSION, true );

	// Strings flow through __() so they're resolved against the site's
	// textdomain. The copyright attribution comes from the cached catalogue
	// only: the API is never called while a page renders (a slow or offline
	// API would hold the page for the whole timeout). A cold cache is filled
	// by a background cron event instead.
	$api          = new MVBR_API();
	$version_slug = isset( $options['versao'] ) ? MVBR_Books::resolve_version( $options['versao'] ) : MVBR_Books::get_default_version( $locale );
	$version_meta = $api->get_version_meta( $version_slug, false );
	if ( null === $version_meta && ! $api->has_catalog_cache() && ! wp_next_scheduled( 'mvbr_refresh_catalog' ) ) {
		wp_schedule_single_event( time(), 'mvbr_refresh_catalog' );
	}

	wp_localize_script(
		'mvbr-tooltip',
		'mvbr_config',
		array(
			'ajax_url'          => admin_url( 'admin-ajax.php' ),
			'version'           => $version_slug,
			'version_label'     => $api->get_version_label( $version_slug ),
			'version_copyright' => ( $version_meta && ! empty( $version_meta['copyright'] ) ) ? $version_meta['copyright'] : '',
			'locale'            => $locale,
			'show_version'      => isset( $options['show_version'] ) ? $options['show_version'] : true,
			'fallback_message'  => __( 'Verse currently unavailable', 'midvash-bible-references' ),
			'read_more'         => __( 'Read more', 'midvash-bible-references' ),
			'site_url'          => MVBR_Books::site_url( $locale ),
			// Midvash logo and the version badge's link only with the site
			// owner's opt-in (WordPress.org guideline 10).
			'icon_url'          => empty( $options['show_branding'] ) ? '' : MVBR_PLUGIN_URL . 'assets/images/icon-mvbr.svg',
			'link_version'      => ! empty( $options['show_branding'] ),
			'debug'             => defined( 'WP_DEBUG' ) && WP_DEBUG,
		)
	);
}
add_action( 'wp_enqueue_scripts', 'mvbr_register_assets' );

/**
 * Fills the version catalogue cache outside of a page render.
 */
function mvbr_refresh_catalog() {
	$api = new MVBR_API();
	$api->get_versions();
}
add_action( 'mvbr_refresh_catalog', 'mvbr_refresh_catalog' );

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
function mvbr_rate_limit_key( $action ) {
	$ip = '';
	if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}
	$bucket = '' !== $ip ? $ip : 'anon';
	return 'mvbr_rl_' . $action . '_' . md5( $bucket . wp_salt( 'auth' ) );
}

/**
 * Throttles an action to N hits per 60-second window. Returns true if the
 * caller is over budget (and should be rejected).
 *
 * @param string $action Action slug identifying the rate-limit bucket.
 * @param int    $limit  Maximum number of hits allowed per 60-second window.
 */
function mvbr_is_rate_limited( $action, $limit ) {
	$key   = mvbr_rate_limit_key( $action );
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
function mvbr_ajax_get_verse() {
	// No nonce: this public, read-only endpoint serves Bible text, so there is
	// nothing to forge, and a nonce baked into cached HTML expires after a day
	// and breaks every tooltip on sites behind a page cache. Abuse is bounded
	// by the rate limit below.

	// Throttle to 120 verse lookups per minute per IP. Generous for normal
	// browsing (hovering many references), tight against bots flooding our
	// upstream API.
	if ( mvbr_is_rate_limited( 'verse', 120 ) ) {
		wp_send_json_error( array( 'message' => __( 'Too many requests', 'midvash-bible-references' ) ), 429 );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public read-only endpoint, see above.
	$reference = isset( $_GET['reference'] ) ? sanitize_text_field( wp_unslash( $_GET['reference'] ) ) : '';
	$version   = isset( $_GET['version'] ) ? sanitize_text_field( wp_unslash( $_GET['version'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( empty( $reference ) ) {
		wp_send_json_error( array( 'message' => __( 'Reference not provided', 'midvash-bible-references' ) ) );
	}

	$api    = new MVBR_API();
	$result = $api->get_verse( $reference, $version );

	if ( $result ) {
		wp_send_json_success( $result );
	} else {
		wp_send_json_error( array( 'message' => __( 'Verse not found', 'midvash-bible-references' ) ) );
	}
}
add_action( 'wp_ajax_mvbr_get_verse', 'mvbr_ajax_get_verse' );
add_action( 'wp_ajax_nopriv_mvbr_get_verse', 'mvbr_ajax_get_verse' );

/**
 * AJAX handler that prefetches every reference on a page in one round-trip.
 *
 * The tooltip script collects the unique data-midvash-ref values after render
 * and calls this once, instead of one mvbr_get_verse request per hover. The
 * upstream fan-out is batched through the API's /v1/passages endpoint.
 */
function mvbr_ajax_get_verses() {
	// No nonce, for the same reason as mvbr_ajax_get_verse().

	// One batch covers a whole page view, so the budget is much smaller than
	// the per-verse endpoint's. 20/min per IP still allows brisk browsing.
	if ( mvbr_is_rate_limited( 'batch', 20 ) ) {
		wp_send_json_error( array( 'message' => __( 'Too many requests', 'midvash-bible-references' ) ), 429 );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Public read-only endpoint, see mvbr_ajax_get_verse().
	$refs_raw = isset( $_POST['refs'] ) ? sanitize_text_field( wp_unslash( $_POST['refs'] ) ) : '';
	$version  = isset( $_POST['version'] ) ? sanitize_text_field( wp_unslash( $_POST['version'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	$references = array_filter( array_map( 'trim', explode( '|', $refs_raw ) ) );
	if ( empty( $references ) ) {
		wp_send_json_error( array( 'message' => __( 'Reference not provided', 'midvash-bible-references' ) ) );
	}
	// Cap the page budget: 60 unique references (3 upstream batch requests).
	$references = array_slice( array_unique( $references ), 0, 60 );

	$api     = new MVBR_API();
	$results = $api->get_passages( $references, $version );

	wp_send_json_success( $results );
}
add_action( 'wp_ajax_mvbr_get_verses', 'mvbr_ajax_get_verses' );
add_action( 'wp_ajax_nopriv_mvbr_get_verses', 'mvbr_ajax_get_verses' );

/**
 * AJAX handler to fetch Bible versions by locale (admin only).
 *
 * Used by the settings page to refresh the version <select> when the language
 * changes. Restricted to `manage_options` to avoid letting any logged-in user
 * (subscribers, contributors) hammer our upstream API.
 */
function mvbr_ajax_get_versions() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Forbidden', 'midvash-bible-references' ) ), 403 );
	}
	check_ajax_referer( 'mvbr_nonce', 'nonce' );

	$locale = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : 'pt-br';
	$locale = MVBR_Books::normalize_locale( $locale );

	$api      = new MVBR_API();
	$versions = $api->get_versions( $locale );

	if ( $versions ) {
		wp_send_json_success( $versions );
	} else {
		wp_send_json_error( array( 'message' => __( 'Failed to fetch versions', 'midvash-bible-references' ) ) );
	}
}
add_action( 'wp_ajax_mvbr_get_versions', 'mvbr_ajax_get_versions' );

/**
 * Plugin activation - set default options
 */
function mvbr_activate() {
	mvbr_migrate_legacy_data();

	// Get default version based on locale.
	$locale          = 'pt-br'; // Default locale.
	$default_version = MVBR_Books::get_default_version( $locale );

	$defaults = array(
		'locale'           => $locale,
		'versao'           => $default_version,
		'use_custom_color' => false,
		'link_color'       => '#B17027',
		'underline_link'   => false,
		'underline_color'  => '#B17027',
		'underline_style'  => 'solid',
		'new_tab'          => true,
		'css_class'        => 'mvbr-link',
		'cache_enabled'    => true,
		'cache_ttl'        => 2592000, // 30 days
		'timeout'          => 5,
		'show_version'     => true,
		'show_branding'    => false,
		'link_biblia'      => false,
	);

	if ( ! get_option( 'mvbr_options' ) ) {
		add_option( 'mvbr_options', $defaults );
	}
}
register_activation_hook( __FILE__, 'mvbr_activate' );

/**
 * Plugin deactivation.
 *
 * Intentionally a no-op: deactivating is reversible — wiping transients
 * here would penalise users who deactivate to debug a theme conflict and
 * then reactivate. Persistent cleanup happens in uninstall.php instead.
 */
function mvbr_deactivate() {
	// No-op.
}
register_deactivation_hook( __FILE__, 'mvbr_deactivate' );
