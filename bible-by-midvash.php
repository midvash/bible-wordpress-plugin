<?php
/**
 * Plugin Name: Bible by Midvash
 * Plugin URI:  https://wordpress.midvash.com
 * Description: Automatically identifies Bible references in posts and creates links with tooltips via the Midvash service.
 * Version: 0.6.0
 * Author: Neto Gregório
 * Author URI: https://www.netogregorio.com.br
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bible-by-midvash
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('BBM_VERSION', '0.6.0');
define('BBM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BBM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BBM_API_BASE_URL', 'https://api.midvash.com');
define('BBM_SITE_URL', 'https://midvash.com');

// Include classes
require_once BBM_PLUGIN_DIR . 'includes/class-bbm-books.php';
require_once BBM_PLUGIN_DIR . 'includes/class-bbm-api.php';
require_once BBM_PLUGIN_DIR . 'includes/class-bbm-parser.php';
require_once BBM_PLUGIN_DIR . 'includes/class-bbm-admin.php';
require_once BBM_PLUGIN_DIR . 'includes/class-bbm-widget.php';
require_once BBM_PLUGIN_DIR . 'includes/class-bbm-block.php';

// {{WPORG_STRIP_START}}
// Plugin Update Checker — auto-update for installs distributed outside the
// WordPress.org directory (download from wordpress.midvash.com).
//
// IMPORTANT: this whole block (including the markers) is stripped by
// scripts/build-zip.ts when building the ZIP for submission to wordpress.org,
// because the official Plugin Review Guidelines forbid plugins from bundling
// their own update mechanism when hosted on the official directory.
if (file_exists(BBM_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php')) {
    require_once BBM_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
    if (class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
        $bbm_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://wordpress.midvash.com/update-info.json',
            __FILE__,
            'bible-by-midvash'
        );
    }
}
// {{WPORG_STRIP_END}}

/**
 * Load the plugin's text domain.
 *
 * Hooked to `init` (not `plugins_loaded`) — WordPress 6.7+ emits a
 * _doing_it_wrong() notice if textdomains are loaded earlier than `init`,
 * because the new just-in-time loader needs the user locale to be resolved.
 *
 * Note: for plugins hosted on wordpress.org, translations are also loaded
 * automatically from translate.wordpress.org. This call is kept as a fallback
 * for installs distributed outside the directory (via wordpress.midvash.com).
 */
function bbm_load_textdomain()
{
    load_plugin_textdomain('bible-by-midvash', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'bbm_load_textdomain');

/**
 * Initialize the plugin
 */
function bbm_init()
{
    // Initialize the parser
    $parser = new BBM_Parser();
    $parser->init();

    // Initialize the admin (only in panel)
    if (is_admin()) {
        $admin = new BBM_Admin();
        $admin->init();
    }
}
add_action('init', 'bbm_init');

/**
 * Register the VOTD widget and shortcode
 */
function bbm_register_widget()
{
    register_widget('BBM_Widget');
}
add_action('widgets_init', 'bbm_register_widget');
add_shortcode('bbm_votd', 'bbm_votd_shortcode');

/**
 * Initialize the Gutenberg block
 */
function bbm_register_block()
{
    $block = new BBM_Block();
    $block->init();
}
bbm_register_block();

/**
 * Register scripts and styles
 */
function bbm_enqueue_assets()
{
    // Load on single posts and pages
    if (!is_singular()) {
        return;
    }

    $options = get_option('bbm_options', array());
    $locale = isset($options['locale']) ? $options['locale'] : 'pt-br';

    // CSS
    wp_enqueue_style(
        'bbm-style',
        BBM_PLUGIN_URL . 'assets/css/bbm.css',
        array(),
        BBM_VERSION
    );

    // Custom Link Styling
    $css_styles = array();

    // Color
    $use_custom_color = isset($options['use_custom_color']) ? (bool) $options['use_custom_color'] : false;
    if ($use_custom_color) {
        $link_color = isset($options['link_color']) ? $options['link_color'] : '#B17027';
        $css_styles[] = sprintf("color: %s;", esc_attr($link_color));
    }

    // Underline
    $underline_link = isset($options['underline_link']) ? (bool) $options['underline_link'] : false;
    if ($underline_link) {
        $underline_color = isset($options['underline_color']) ? $options['underline_color'] : '#B17027';
        $underline_style = isset($options['underline_style']) ? $options['underline_style'] : 'solid';

        $css_styles[] = "text-decoration-line: underline;";
        $css_styles[] = sprintf("text-decoration-color: %s;", esc_attr($underline_color));
        $css_styles[] = sprintf("text-decoration-style: %s;", esc_attr($underline_style));
    } else {
        $css_styles[] = "text-decoration: none;";
    }

    if (!empty($css_styles)) {
        $custom_css = ".bbm-link { " . implode(' ', $css_styles) . " }";
        wp_add_inline_style('bbm-style', $custom_css);
    }

    // JavaScript
    wp_enqueue_script(
        'bbm-tooltip',
        BBM_PLUGIN_URL . 'assets/js/bbm-tooltip.js',
        array(),
        BBM_VERSION,
        true
    );

    // Pass configuration to JS. Strings flow through __() so they're resolved
    // against the site's active textdomain (.mo files in /languages) instead
    // of a hardcoded 9-locale lookup — translations track WordPress’s normal
    // i18n pipeline and new locales come for free as we add .po files.
    wp_localize_script('bbm-tooltip', 'bbm_config', array(
        'ajax_url'         => admin_url('admin-ajax.php'),
        'nonce'            => wp_create_nonce('bbm_nonce'),
        'version'          => isset($options['versao']) ? $options['versao'] : 'nvt',
        'locale'           => $locale,
        'show_version'     => isset($options['show_version']) ? $options['show_version'] : true,
        'fallback_message' => __('Verse currently unavailable', 'bible-by-midvash'),
        'read_more'        => __('Read more', 'bible-by-midvash'),
        'site_url'         => BBM_SITE_URL,
        'icon_url'         => BBM_PLUGIN_URL . 'assets/images/icon-bbm.svg',
        'debug'            => defined('WP_DEBUG') && WP_DEBUG,
    ));
}
add_action('wp_enqueue_scripts', 'bbm_enqueue_assets');

/**
 * Returns a stable identifier for rate-limit bucketing.
 *
 * Uses REMOTE_ADDR hashed with WP's secret salt — never stored raw, so this
 * is not “collecting visitor data”, just a one-way bucket key that expires
 * with the transient (60s window). Falls back to a constant string when the
 * IP is missing (CLI, weird proxies) — worst case all anonymous visitors
 * share the bucket, which only over-throttles, never under-throttles.
 */
function bbm_rate_limit_key($action)
{
    $ip = '';
    if (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
    }
    $bucket = $ip !== '' ? $ip : 'anon';
    return 'bbm_rl_' . $action . '_' . md5($bucket . wp_salt('auth'));
}

/**
 * Throttles an action to N hits per 60-second window. Returns true if the
 * caller is over budget (and should be rejected).
 */
function bbm_is_rate_limited($action, $limit)
{
    $key   = bbm_rate_limit_key($action);
    $count = (int) get_transient($key);
    if ($count >= $limit) {
        return true;
    }
    set_transient($key, $count + 1, MINUTE_IN_SECONDS);
    return false;
}

/**
 * AJAX handler to fetch verses (public — used by the tooltip).
 */
function bbm_ajax_get_verse()
{
    check_ajax_referer('bbm_nonce', 'nonce');

    // Throttle to 120 verse lookups per minute per IP. Generous for normal
    // browsing (hovering many references), tight against bots flooding our
    // upstream API.
    if (bbm_is_rate_limited('verse', 120)) {
        wp_send_json_error(array('message' => __('Too many requests', 'bible-by-midvash')), 429);
    }

    $reference = isset($_GET['reference']) ? sanitize_text_field(wp_unslash($_GET['reference'])) : '';
    $version   = isset($_GET['version'])   ? sanitize_text_field(wp_unslash($_GET['version']))   : 'nvt';

    if (empty($reference)) {
        wp_send_json_error(array('message' => __('Reference not provided', 'bible-by-midvash')));
    }

    $api    = new BBM_API();
    $result = $api->get_verse($reference, $version);

    if ($result) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error(array('message' => __('Verse not found', 'bible-by-midvash')));
    }
}
add_action('wp_ajax_bbm_get_verse', 'bbm_ajax_get_verse');
add_action('wp_ajax_nopriv_bbm_get_verse', 'bbm_ajax_get_verse');

/**
 * AJAX handler to fetch Bible versions by locale (admin only).
 *
 * Used by the settings page to refresh the version <select> when the language
 * changes. Restricted to `manage_options` to avoid letting any logged-in user
 * (subscribers, contributors) hammer our upstream API.
 */
function bbm_ajax_get_versions()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Forbidden', 'bible-by-midvash')), 403);
    }
    check_ajax_referer('bbm_nonce', 'nonce');

    $locale = isset($_POST['locale']) ? sanitize_text_field(wp_unslash($_POST['locale'])) : 'pt-br';
    $locale = BBM_Books::normalize_locale($locale);

    $api      = new BBM_API();
    $versions = $api->get_versions($locale);

    if ($versions) {
        wp_send_json_success($versions);
    } else {
        wp_send_json_error(array('message' => __('Failed to fetch versions', 'bible-by-midvash')));
    }
}
add_action('wp_ajax_bbm_get_versions', 'bbm_ajax_get_versions');

/**
 * Plugin activation - set default options
 */
function bbm_activate()
{
    // Get default version based on locale
    $locale = 'pt-br'; // Default locale
    $default_version = BBM_Books::get_default_version($locale);
    
    $defaults = array(
        'locale' => $locale,
        'versao' => $default_version,
        'use_custom_color' => false,
        'link_color' => '#B17027',
        'underline_link' => false,
        'underline_color' => '#B17027',
        'underline_style' => 'solid',
        'new_tab' => true,
        'css_class' => 'bbm-link',
        'cache_enabled' => true,
        'cache_ttl' => 2592000, // 30 days
        'timeout' => 5,
        'show_version' => true,
        'link_biblia' => false,
        'link_books' => false,
        'link_versions' => false,
        'link_terms' => false,
        'link_characters' => false,
    );

    if (!get_option('bbm_options')) {
        add_option('bbm_options', $defaults);
    }
}
register_activation_hook(__FILE__, 'bbm_activate');

/**
 * Plugin deactivation.
 *
 * Intentionally a no-op: deactivating is reversible — wiping transients
 * here would penalise users who deactivate to debug a theme conflict and
 * then reactivate. Persistent cleanup happens in uninstall.php instead.
 */
function bbm_deactivate()
{
    // no-op
}
register_deactivation_hook(__FILE__, 'bbm_deactivate');
