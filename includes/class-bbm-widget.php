<?php
/**
 * Verse of the Day widget and shortcode.
 *
 * Registers a classic WordPress widget (WP_Widget) and a [bbm_votd] shortcode
 * that display the daily Bible verse fetched from the Midvash API.
 *
 * @package Bible_by_Midvash
 */

if (!defined('ABSPATH')) {
    exit;
}

class BBM_Widget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct(
            'bbm_votd_widget',
            __('Verse of the Day — Midvash', 'bible-by-midvash'),
            array(
                'description' => __('Displays the daily Bible verse powered by the Midvash API.', 'bible-by-midvash'),
                'classname'   => 'bbm-votd-widget',
            )
        );
    }

    /**
     * Front-end display
     */
    public function widget($args, $instance)
    {
        $title          = !empty($instance['title'])          ? $instance['title']          : '';
        $locale         = !empty($instance['locale'])         ? $instance['locale']         : '';
        $version        = !empty($instance['version'])        ? $instance['version']        : '';
        $show_reference = !empty($instance['show_reference']) ? (bool) $instance['show_reference'] : true;
        $link_verse     = !empty($instance['link_verse'])     ? (bool) $instance['link_verse']     : true;

        echo wp_kses_post($args['before_widget']);

        if ($title) {
            echo wp_kses_post($args['before_title'] . esc_html($title) . $args['after_title']);
        }

        echo bbm_render_votd(array(
            'locale'         => $locale,
            'version'        => $version,
            'show_reference' => $show_reference,
            'link_verse'     => $link_verse,
        ));

        echo wp_kses_post($args['after_widget']);
    }

    /**
     * Admin form
     */
    public function form($instance)
    {
        $title          = !empty($instance['title'])          ? $instance['title']          : __('Verse of the Day', 'bible-by-midvash');
        $locale         = !empty($instance['locale'])         ? $instance['locale']         : '';
        $version        = !empty($instance['version'])        ? $instance['version']        : '';
        $show_reference = isset($instance['show_reference'])  ? (bool) $instance['show_reference'] : true;
        $link_verse     = isset($instance['link_verse'])      ? (bool) $instance['link_verse']     : true;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Title:', 'bible-by-midvash'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                   type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('locale')); ?>"><?php esc_html_e('Language (leave empty to use plugin setting):', 'bible-by-midvash'); ?></label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('locale')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('locale')); ?>">
                <option value="" <?php selected($locale, ''); ?>><?php esc_html_e('— Use plugin setting —', 'bible-by-midvash'); ?></option>
                <?php
                $locales = array(
                    'pt-br' => '🇧🇷 Português (Brasil)',
                    'en'    => '🇺🇸 English',
                    'es'    => '🇪🇸 Español',
                    'fr'    => '🇫🇷 Français',
                    'de'    => '🇩🇪 Deutsch',
                    'it'    => '🇮🇹 Italiano',
                    'ru'    => '🇷🇺 Русский',
                    'ko'    => '🇰🇷 한국어',
                    'zh'    => '🇨🇳 中文',
                );
                foreach ($locales as $code => $label) {
                    echo '<option value="' . esc_attr($code) . '" ' . selected($locale, $code, false) . '>' . esc_html($label) . '</option>';
                }
                ?>
            </select>
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('version')); ?>"><?php esc_html_e('Version (leave empty for locale default):', 'bible-by-midvash'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('version')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('version')); ?>"
                   type="text" value="<?php echo esc_attr($version); ?>"
                   placeholder="<?php esc_attr_e('e.g. nvt, kjv, lsg', 'bible-by-midvash'); ?>">
        </p>
        <p>
            <label>
                <input type="checkbox" name="<?php echo esc_attr($this->get_field_name('show_reference')); ?>" value="1" <?php checked($show_reference); ?>>
                <?php esc_html_e('Show reference (e.g. João 3:16)', 'bible-by-midvash'); ?>
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="<?php echo esc_attr($this->get_field_name('link_verse')); ?>" value="1" <?php checked($link_verse); ?>>
                <?php esc_html_e('Link verse to Midvash', 'bible-by-midvash'); ?>
            </label>
        </p>
        <?php
    }

    /**
     * Save settings
     */
    public function update($new_instance, $old_instance)
    {
        $valid_locales = array('', 'pt-br', 'en', 'es', 'fr', 'de', 'it', 'ru', 'ko', 'zh');
        return array(
            'title'          => sanitize_text_field($new_instance['title']),
            'locale'         => in_array($new_instance['locale'], $valid_locales, true) ? $new_instance['locale'] : '',
            'version'        => sanitize_text_field($new_instance['version']),
            'show_reference' => !empty($new_instance['show_reference']),
            'link_verse'     => !empty($new_instance['link_verse']),
        );
    }
}

/**
 * Renders the VOTD HTML — used by both the widget and the shortcode.
 *
 * @param array $atts {
 *   @type string $locale         Content locale. Empty = use plugin setting.
 *   @type string $version        Bible version slug. Empty = locale default.
 *   @type bool   $show_reference Whether to display the reference string.
 *   @type bool   $link_verse     Whether to wrap reference in a link to Midvash.
 * }
 * @return string HTML output.
 */
function bbm_render_votd($atts = array())
{
    $atts = wp_parse_args($atts, array(
        'locale'         => '',
        'version'        => '',
        'show_reference' => true,
        'link_verse'     => true,
    ));

    $options = get_option('bbm_options', array());
    $locale  = $atts['locale'] ? BBM_Books::normalize_locale($atts['locale'])
                                : (isset($options['locale']) ? $options['locale'] : 'pt-br');
    $version = $atts['version'] ?: (isset($options['versao']) ? $options['versao'] : BBM_Books::get_default_version($locale));

    $api  = new BBM_API();
    $data = $api->get_votd($locale, $version);

    if (!$data) {
        return '<div class="bbm-votd bbm-votd--error">'
             . '<p class="bbm-votd__text">' . esc_html__('Verse currently unavailable.', 'bible-by-midvash') . '</p>'
             . '</div>';
    }

    $text      = isset($data['text'])      ? $data['text']      : '';
    $reference = isset($data['reference']) ? $data['reference'] : '';
    $url       = isset($data['url'])       ? $data['url']       : '';

    $show_ref  = filter_var($atts['show_reference'], FILTER_VALIDATE_BOOLEAN);
    $link      = filter_var($atts['link_verse'],     FILTER_VALIDATE_BOOLEAN);

    $ref_html = '';
    if ($show_ref && $reference) {
        if ($link && $url) {
            $ref_html = sprintf(
                '<cite class="bbm-votd__reference"><a href="%s" target="_blank" rel="noopener noreferrer" class="bbm-votd__link">%s</a></cite>',
                esc_url($url),
                esc_html($reference)
            );
        } else {
            $ref_html = '<cite class="bbm-votd__reference">' . esc_html($reference) . '</cite>';
        }
    }

    return sprintf(
        '<div class="bbm-votd" itemscope itemtype="https://schema.org/Quotation">'
        . '<p class="bbm-votd__text" itemprop="text">%s</p>'
        . '%s'
        . '<p class="bbm-votd__powered"><a href="%s" target="_blank" rel="noopener noreferrer">Midvash</a></p>'
        . '</div>',
        esc_html($text),
        $ref_html,
        esc_url(BBM_SITE_URL . '/' . $locale)
    );
}

/**
 * [bbm_votd] shortcode
 *
 * Attributes (all optional):
 *   locale          — pt-br | en | es | fr | de | it | ru | ko | zh
 *   version         — nvt | kjv | lsg | … (any valid slug)
 *   show_reference  — true | false
 *   link_verse      — true | false
 *   title           — text displayed above the verse
 *
 * @return string HTML
 */
function bbm_votd_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'locale'         => '',
        'version'        => '',
        'show_reference' => 'true',
        'link_verse'     => 'true',
        'title'          => '',
    ), $atts, 'bbm_votd');

    $html = '';

    if (!empty($atts['title'])) {
        $html .= '<h3 class="bbm-votd__title">' . esc_html($atts['title']) . '</h3>';
    }

    $html .= bbm_render_votd(array(
        'locale'         => sanitize_text_field($atts['locale']),
        'version'        => sanitize_text_field($atts['version']),
        'show_reference' => $atts['show_reference'],
        'link_verse'     => $atts['link_verse'],
    ));

    return $html;
}
