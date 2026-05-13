<?php
/**
 * Plugin Name: Bible by Midvash
 * Plugin URI:  https://wordpress.midvash.com
 * Description: Automatically identifies Bible references in posts and creates links with tooltips via the Midvash service.
 * Version: 0.4.0
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
define('BBM_VERSION', '0.4.0');
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

// Plugin Update Checker — auto-update sem depender do wordpress.org.
// Consulta wordpress.midvash.com/update-info.json a cada ~12h e mostra
// "Atualização disponível" no painel WP como plugins do diretório oficial.
require_once BBM_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
$bbm_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://wordpress.midvash.com/update-info.json',
    __FILE__,
    'bible-by-midvash'
);

/**
 * Initialize the plugin
 */
function bbm_init()
{
    // Load translations
    load_plugin_textdomain('bible-by-midvash', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Initialize the parser
    $parser = new BBM_Parser();
    $parser->init();

    // Initialize the admin (only in panel)
    if (is_admin()) {
        $admin = new BBM_Admin();
        $admin->init();
    }
}
add_action('plugins_loaded', 'bbm_init');

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

    // Translations for tooltip (frontend strings — passed to JS, not loaded from .mo)
    $translations = array(
        'pt-br' => array(
            'read_more' => 'Ler mais',
            'verse_unavailable' => 'Versículo indisponível no momento',
        ),
        'es' => array(
            'read_more' => 'Leer más',
            'verse_unavailable' => 'Versículo no disponible en este momento',
        ),
        'en' => array(
            'read_more' => 'Read more',
            'verse_unavailable' => 'Verse currently unavailable',
        ),
        'fr' => array(
            'read_more' => 'Lire plus',
            'verse_unavailable' => 'Verset actuellement indisponible',
        ),
        'de' => array(
            'read_more' => 'Mehr lesen',
            'verse_unavailable' => 'Vers derzeit nicht verfügbar',
        ),
        'it' => array(
            'read_more' => 'Leggi di più',
            'verse_unavailable' => 'Versetto attualmente non disponibile',
        ),
        'ru' => array(
            'read_more' => 'Читать далее',
            'verse_unavailable' => 'Стих временно недоступен',
        ),
        'ko' => array(
            'read_more' => '더 읽기',
            'verse_unavailable' => '구절을 현재 사용할 수 없습니다',
        ),
        'zh' => array(
            'read_more' => '阅读更多',
            'verse_unavailable' => '经文暂时无法显示',
        ),
    );
    
    $current_translations = isset($translations[$locale]) ? $translations[$locale] : $translations['en'];

    // Pass configuration to JS
    wp_localize_script('bbm-tooltip', 'bbm_config', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bbm_nonce'),
        'version' => isset($options['versao']) ? $options['versao'] : 'nvt',
        'locale' => $locale,
        'show_version' => isset($options['show_version']) ? $options['show_version'] : true,
        'fallback_message' => $current_translations['verse_unavailable'],
        'read_more' => $current_translations['read_more'],
        'site_url' => BBM_SITE_URL,
        'icon_url' => BBM_PLUGIN_URL . 'assets/images/icon-bbm.svg',
    ));
}
add_action('wp_enqueue_scripts', 'bbm_enqueue_assets');

/**
 * AJAX handler to fetch verses
 */
function bbm_ajax_get_verse()
{
    check_ajax_referer('bbm_nonce', 'nonce');

    $reference = isset($_GET['reference']) ? sanitize_text_field(wp_unslash($_GET['reference'])) : '';
    $version = isset($_GET['version']) ? sanitize_text_field(wp_unslash($_GET['version'])) : 'nvt';

    if (empty($reference)) {
        wp_send_json_error(array('message' => 'Reference not provided'));
    }

    $api = new BBM_API();
    $result = $api->get_verse($reference, $version);

    if ($result) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error(array('message' => 'Verse not found'));
    }
}
add_action('wp_ajax_bbm_get_verse', 'bbm_ajax_get_verse');
add_action('wp_ajax_nopriv_bbm_get_verse', 'bbm_ajax_get_verse');

/**
 * AJAX handler to fetch Bible versions by locale
 */
function bbm_ajax_get_versions()
{
    check_ajax_referer('bbm_nonce', 'nonce');

    $locale = isset($_POST['locale']) ? sanitize_text_field(wp_unslash($_POST['locale'])) : 'pt-br';
    $locale = BBM_Books::normalize_locale($locale);

    $api = new BBM_API();
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
 * Plugin deactivation
 */
function bbm_deactivate()
{
    // Clear cache transients
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Needed for deleting transients with wildcards.
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bbm_%'");
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Needed for deleting transients with wildcards.
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bbm_%'");
}
register_deactivation_hook(__FILE__, 'bbm_deactivate');
