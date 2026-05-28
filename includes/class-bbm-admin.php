<?php
/**
 * Class responsible for the plugin settings page.
 */

if (!defined('ABSPATH')) {
    exit;
}

class BBM_Admin
{


    /**
     * Initializes the admin
     */
    public function init()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Enqueue admin CSS/JS only on the plugin's settings page
     */
    public function enqueue_admin_assets($hook)
    {
        if ($hook !== 'settings_page_bbm') {
            return;
        }

        wp_enqueue_style(
            'bbm-admin-style',
            BBM_PLUGIN_URL . 'assets/css/bbm-admin.css',
            array(),
            BBM_VERSION
        );

        wp_enqueue_script(
            'bbm-admin',
            BBM_PLUGIN_URL . 'assets/js/bbm-admin.js',
            array(),
            BBM_VERSION,
            true
        );

        wp_localize_script('bbm-admin', 'bbmAdmin', array(
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('bbm_nonce'),
            // Kept in sync with BBM_Books::DEFAULT_VERSIONS — passed to JS so
            // the version select snaps to the preferred default per locale.
            'defaults' => BBM_Books::DEFAULT_VERSIONS,
            'i18n'     => array(
                'loading' => __('Loading…', 'bible-by-midvash'),
                'empty'   => __('No versions available', 'bible-by-midvash'),
                'error'   => __('Error loading versions', 'bible-by-midvash'),
            ),
        ));
    }

    /**
     * Adds the settings menu
     */
    public function add_admin_menu()
    {
        add_options_page(
            __('Bible by Midvash', 'bible-by-midvash'),
            __('Bible by Midvash', 'bible-by-midvash'),
            'manage_options',
            'bbm',
            array($this, 'options_page')
        );
    }

    /**
     * Registers settings
     */
    public function settings_init()
    {
        register_setting('bbm', 'bbm_options', array($this, 'sanitize_options'));

        // Main section
        add_settings_section(
            'bbm_section_main',
            esc_html__('General Settings', 'bible-by-midvash'),
            array($this, 'section_main_callback'),
            'bbm_general'
        );

        // Cache section
        add_settings_section(
            'bbm_section_cache',
            esc_html__('Cache and Performance', 'bible-by-midvash'),
            array($this, 'section_cache_callback'),
            'bbm_cache'
        );

        // Field: Language/Locale
        add_settings_field(
            'locale',
            esc_html__('Language', 'bible-by-midvash'),
            array($this, 'locale_render'),
            'bbm_general',
            'bbm_section_main'
        );

        // Field: Bible Version
        add_settings_field(
            'versao',
            esc_html__('Bible Version', 'bible-by-midvash'),
            array($this, 'versao_render'),
            'bbm_general',
            'bbm_section_main'
        );

        // Field: Link Color (sempre visível, aplicada quando use_custom_color estiver marcado)
        add_settings_field(
            'link_color',
            esc_html__('Link Color', 'bible-by-midvash'),
            array($this, 'link_color_render'),
            'bbm_general',
            'bbm_section_main'
        );

        // Field: Use Custom Color
        add_settings_field(
            'use_custom_color',
            esc_html__('Enable custom color', 'bible-by-midvash'),
            array($this, 'use_custom_color_render'),
            'bbm_general',
            'bbm_section_main'
        );

        // Field: Underline Link
        add_settings_field(
            'underline_link',
            esc_html__('Underline links', 'bible-by-midvash'),
            array($this, 'underline_link_render'),
            'bbm_general',
            'bbm_section_main'
        );

        // Field: Underline Color
        add_settings_field(
            'underline_color',
            esc_html__('Underline Color', 'bible-by-midvash'),
            array($this, 'underline_color_render'),
            'bbm_general',
            'bbm_section_main'
        );

        // Field: Underline Style
        add_settings_field(
            'underline_style',
            esc_html__('Underline Style', 'bible-by-midvash'),
            array($this, 'underline_style_render'),
            'bbm_general',
            'bbm_section_main'
        );

        // Field: Open in new tab
        add_settings_field(
            'new_tab',
            esc_html__('Open in new tab', 'bible-by-midvash'),
            array($this, 'new_tab_render'),
            'bbm_general',
            'bbm_section_main'
        );

        // Field: Show version in tooltip
        add_settings_field(
            'show_version',
            esc_html__('Show version in tooltip', 'bible-by-midvash'),
            array($this, 'show_version_render'),
            'bbm_general',
            'bbm_section_main'
        );

        // Field: Cache enabled
        add_settings_field(
            'cache_enabled',
            esc_html__('Enable cache', 'bible-by-midvash'),
            array($this, 'cache_enabled_render'),
            'bbm_cache',
            'bbm_section_cache'
        );

        // Field: Cache TTL
        add_settings_field(
            'cache_ttl',
            esc_html__('Cache duration (seconds)', 'bible-by-midvash'),
            array($this, 'cache_ttl_render'),
            'bbm_cache',
            'bbm_section_cache'
        );

        // Field: Timeout
        add_settings_field(
            'timeout',
            esc_html__('API Timeout (seconds)', 'bible-by-midvash'),
            array($this, 'timeout_render'),
            'bbm_cache',
            'bbm_section_cache'
        );

        // Auto-linking section
        add_settings_section(
            'bbm_section_linking',
            esc_html__('Auto-linking Settings', 'bible-by-midvash'),
            array($this, 'section_linking_callback'),
            'bbm_general'
        );

        // Field: Link "Bíblia"
        add_settings_field(
            'link_biblia',
            esc_html__('Link the word "Bíblia"', 'bible-by-midvash'),
            array($this, 'link_biblia_render'),
            'bbm_general',
            'bbm_section_linking'
        );

        // Field: Link Versions
        add_settings_field(
            'link_versions',
            esc_html__('Link Bible version names', 'bible-by-midvash'),
            array($this, 'link_versions_render'),
            'bbm_general',
            'bbm_section_linking'
        );

        // Field: Link Books
        add_settings_field(
            'link_books',
            esc_html__('Link Bible book names', 'bible-by-midvash'),
            array($this, 'link_books_render'),
            'bbm_general',
            'bbm_section_linking'
        );

        // Field: Link Dictionary Terms
        add_settings_field(
            'link_terms',
            esc_html__('Link dictionary terms', 'bible-by-midvash'),
            array($this, 'link_terms_render'),
            'bbm_general',
            'bbm_section_linking'
        );

        // Field: Link Biblical Characters
        add_settings_field(
            'link_characters',
            esc_html__('Link biblical characters', 'bible-by-midvash'),
            array($this, 'link_characters_render'),
            'bbm_general',
            'bbm_section_linking'
        );
    }

    /**
     * Whitelist of admin tabs. Used by both navigation and the per-tab
     * checkbox reset logic in sanitize_options().
     */
    const TABS = array('general', 'cache');

    /**
     * Reads the active tab from `$_GET['tab']`, sanitized and validated.
     * Defaults to 'general'.
     */
    private function get_active_tab()
    {
        // Reading $_GET only to identify which tab is being saved — there's no
        // form action attached to this value, so no nonce is needed beyond the
        // settings_fields() nonce that wraps the form.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $raw = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
        return in_array($raw, self::TABS, true) ? $raw : 'general';
    }

    /**
     * True when the current POST is the Settings API submit for our option page.
     * Settings API already verified the nonce via `option_page_capability_*` /
     * `check_admin_referer` by the time `register_setting`’s sanitize callback
     * runs, so reading $_POST here is safe; we still sanitize+unslash for WPCS.
     */
    private function is_our_options_post()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (empty($_POST['option_page'])) {
            return false;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return sanitize_key(wp_unslash($_POST['option_page'])) === 'bbm';
    }

    /**
     * Reads a checkbox value from $input. If the key is missing AND we're
     * submitting from the right tab (checkboxes don't POST when unchecked),
     * returns false. Otherwise leaves the existing value untouched.
     *
     * @param array       $input       Raw $_POST input.
     * @param string      $key         Option key.
     * @param string      $tab         Tab where this checkbox lives.
     * @param array       $sanitized   Working sanitized array (passed by ref).
     */
    private function read_checkbox($input, $key, $tab, &$sanitized)
    {
        if (isset($input[$key])) {
            $sanitized[$key] = (bool) $input[$key];
            return;
        }
        if ($this->is_our_options_post() && $this->get_active_tab() === $tab) {
            $sanitized[$key] = false;
        }
    }

    /**
     * Sanitizes options
     */
    public function sanitize_options($input)
    {
        $existing  = get_option('bbm_options', array());
        $sanitized = is_array($existing) ? $existing : array();

        if (!is_array($input)) {
            return $sanitized;
        }

        // ----- General tab checkboxes -----
        $this->read_checkbox($input, 'use_custom_color', 'general', $sanitized);
        $this->read_checkbox($input, 'underline_link',   'general', $sanitized);
        $this->read_checkbox($input, 'new_tab',          'general', $sanitized);
        $this->read_checkbox($input, 'show_version',     'general', $sanitized);
        $this->read_checkbox($input, 'link_biblia',      'general', $sanitized);
        $this->read_checkbox($input, 'link_versions',    'general', $sanitized);
        $this->read_checkbox($input, 'link_books',       'general', $sanitized);
        $this->read_checkbox($input, 'link_terms',       'general', $sanitized);
        $this->read_checkbox($input, 'link_characters',  'general', $sanitized);

        // ----- Cache tab checkboxes -----
        $this->read_checkbox($input, 'cache_enabled', 'cache', $sanitized);

        // ----- Colors -----
        if (isset($input['link_color'])) {
            $sanitized['link_color'] = sanitize_hex_color($input['link_color']);
        }
        if (isset($input['underline_color'])) {
            $sanitized['underline_color'] = sanitize_hex_color($input['underline_color']);
        }

        // ----- Locale -----
        if (isset($input['locale'])) {
            $valid_locales = BBM_Books::LOCALES;
            $new_locale    = sanitize_text_field($input['locale']);
            $new_locale    = in_array($new_locale, $valid_locales, true) ? $new_locale : 'pt-br';
            $new_locale    = BBM_Books::normalize_locale($new_locale);

            $old_locale            = isset($sanitized['locale']) ? $sanitized['locale'] : 'pt-br';
            $sanitized['locale']   = $new_locale;

            // If locale changed, snap version to that locale's default (unless caller picked one).
            if ($new_locale !== $old_locale && !isset($input['versao'])) {
                $sanitized['versao'] = BBM_Books::get_default_version($new_locale);
            }
        }

        // ----- Version -----
        if (isset($input['versao'])) {
            $sanitized['versao'] = sanitize_text_field($input['versao']);
        }

        // ----- Underline style (constrained list) -----
        if (isset($input['underline_style'])) {
            $style = sanitize_text_field($input['underline_style']);
            $allowed = array('solid', 'double', 'dotted', 'dashed', 'wavy');
            $sanitized['underline_style'] = in_array($style, $allowed, true) ? $style : 'solid';
        }

        // CSS class is fixed for now (kept here so consumers can rely on it).
        $sanitized['css_class'] = 'bbm-link';

        // ----- Cache numbers -----
        if (isset($input['cache_ttl'])) {
            // Clamp to [60 s, 1 year] to avoid degenerate values.
            $sanitized['cache_ttl'] = max(60, min(YEAR_IN_SECONDS, absint($input['cache_ttl'])));
        }
        if (isset($input['timeout'])) {
            // Clamp to [1, 30] seconds; matches the admin field's `min`/`max`.
            $sanitized['timeout'] = max(1, min(30, absint($input['timeout'])));
        }

        return $sanitized;
    }

    /**
     * Main section callback
     */
    public function section_main_callback()
    {
        echo '<p>' . esc_html__('Configure how Bible references will be displayed.', 'bible-by-midvash') . '</p>';
    }

    /**
     * Cache section callback
     */
    public function section_cache_callback()
    {
        echo '<p>' . esc_html__('Configure cache settings to improve tooltip performance.', 'bible-by-midvash') . '</p>';
    }

    /**
     * Render: Language/Locale
     */
    public function locale_render()
    {
        $options = get_option('bbm_options');
        $locale = isset($options['locale']) ? $options['locale'] : 'pt-br';
        
        $locales = array(
            'pt-br' => array(
                'name' => 'Português (Brasil)',
                'flag' => '🇧🇷',
                'default_version' => 'nvt'
            ),
            'en' => array(
                'name' => 'English',
                'flag' => '🇺🇸',
                'default_version' => 'nlt'
            ),
            'es' => array(
                'name' => 'Español',
                'flag' => '🇪🇸',
                'default_version' => 'ntv'
            ),
            'fr' => array(
                'name' => 'Français',
                'flag' => '🇫🇷',
                'default_version' => 'lsg'
            ),
            'de' => array(
                'name' => 'Deutsch',
                'flag' => '🇩🇪',
                'default_version' => 'luth1912'
            ),
            'it' => array(
                'name' => 'Italiano',
                'flag' => '🇮🇹',
                'default_version' => 'nri'
            ),
            'ru' => array(
                'name' => 'Русский',
                'flag' => '🇷🇺',
                'default_version' => 'synodal'
            ),
            'ko' => array(
                'name' => '한국어',
                'flag' => '🇰🇷',
                'default_version' => 'kor'
            ),
            'zh' => array(
                'name' => '中文',
                'flag' => '🇨🇳',
                'default_version' => 'cuv'
            ),
        );
        ?>
        <select name="bbm_options[locale]" id="bbm_locale">
            <?php foreach ($locales as $code => $data): ?>
                <option value="<?php echo esc_attr($code); ?>" <?php selected($locale, $code); ?>>
                    <?php echo esc_html($data['flag'] . ' ' . $data['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('Select the language for links and book names. This determines the URL structure on Midvash.', 'bible-by-midvash'); ?>
        </p>
        <?php
    }

    /**
     * Render: Bible Version
     */
    public function versao_render()
    {
        $options = get_option('bbm_options');
        $locale = isset($options['locale']) ? $options['locale'] : 'pt-br';
        $locale = BBM_Books::normalize_locale($locale);
        
        // Get default version for current locale
        $default_version = BBM_Books::get_default_version($locale);
        $versao = isset($options['versao']) ? $options['versao'] : $default_version;

        $api = new BBM_API();
        // Get versions filtered by current locale from API
        $api_versions = $api->get_versions($locale);
        
        // If API fails, show empty state (will be loaded via AJAX)
        $versions_to_show = $api_versions ?: array();
        ?>
        <select name="bbm_options[versao]" id="bbm_versao">
            <?php if ($versions_to_show): ?>
                <?php foreach ($versions_to_show as $v): ?>
                    <?php
                    $slug = isset($v['slug']) ? $v['slug'] : (isset($v['code']) ? strtolower($v['code']) : '');
                    $name = isset($v['name']) ? $v['name'] : '';
                    $shortName = isset($v['shortName']) ? $v['shortName'] : (isset($v['code']) ? $v['code'] : '');
                    $display_name = $name ? ($name . ' (' . $shortName . ')') : $shortName;
                    ?>
                    <option value="<?php echo esc_attr(strtolower($slug)); ?>" <?php selected(strtolower($versao), strtolower($slug)); ?>>
                        <?php echo esc_html($display_name); ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
        <p class="description">
            <?php esc_html_e('Select the Bible version for links and tooltips. Versions are fetched dynamically from Midvash.', 'bible-by-midvash'); ?>
        </p>
        <?php
    }

    /**
     * Render: Use Custom Color
     */
    public function use_custom_color_render()
    {
        $options = get_option('bbm_options');
        $val = isset($options['use_custom_color']) ? $options['use_custom_color'] : false;
        ?>
        <label>
            <input type="checkbox" name="bbm_options[use_custom_color]" value="1" <?php checked($val, true); ?>>
            <?php esc_html_e('Enable custom color for links', 'bible-by-midvash'); ?>
        </label>
        <?php
    }

    /**
     * Render: Link Color
     */
    public function link_color_render()
    {
        $options = get_option('bbm_options');
        $color = isset($options['link_color']) ? $options['link_color'] : '#B17027';
        ?>
        <input type="color" name="bbm_options[link_color]" value="<?php echo esc_attr($color); ?>">
        <code><?php echo esc_html($color); ?></code>
        <p class="description"><?php esc_html_e('Main color of the generated links.', 'bible-by-midvash'); ?></p>
        <?php
    }

    /**
     * Render: Underline Link
     */
    public function underline_link_render()
    {
        $options = get_option('bbm_options');
        $val = isset($options['underline_link']) ? $options['underline_link'] : false;
        ?>
        <label>
            <input type="checkbox" name="bbm_options[underline_link]" value="1" <?php checked($val, true); ?>>
            <?php esc_html_e('Enable underline for links', 'bible-by-midvash'); ?>
        </label>
        <?php
    }

    /**
     * Render: Underline Color
     */
    public function underline_color_render()
    {
        $options = get_option('bbm_options');
        $color = isset($options['underline_color']) ? $options['underline_color'] : '#B17027';
        ?>
        <input type="color" name="bbm_options[underline_color]" value="<?php echo esc_attr($color); ?>">
        <code><?php echo esc_html($color); ?></code>
        <p class="description"><?php esc_html_e('Color of the link underline.', 'bible-by-midvash'); ?></p>
        <?php
    }

    /**
     * Render: Underline Style
     */
    public function underline_style_render()
    {
        $options = get_option('bbm_options');
        $style = isset($options['underline_style']) ? $options['underline_style'] : 'solid';
        $styles = array(
            'solid' => esc_html__('Solid', 'bible-by-midvash'),
            'double' => esc_html__('Double', 'bible-by-midvash'),
            'dotted' => esc_html__('Dotted', 'bible-by-midvash'),
            'dashed' => esc_html__('Dashed', 'bible-by-midvash'),
            'wavy' => esc_html__('Wavy', 'bible-by-midvash'),
        );
        ?>
        <select name="bbm_options[underline_style]">
            <?php foreach ($styles as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($style, $value); ?>><?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('Style of the link underline.', 'bible-by-midvash'); ?></p>
        <?php
    }

    /**
     * Render: Open in new tab
     */
    public function new_tab_render()
    {
        $options = get_option('bbm_options');
        $new_tab = isset($options['new_tab']) ? $options['new_tab'] : true;
        ?>
        <label>
            <input type="checkbox" name="bbm_options[new_tab]" value="1" <?php checked($new_tab, true); ?>>
            <?php esc_html_e('Open links in a new browser tab', 'bible-by-midvash'); ?>
        </label>
        <?php
    }

    /**
     * Render: Show version in tooltip
     */
    public function show_version_render()
    {
        $options = get_option('bbm_options');
        $show_version = isset($options['show_version']) ? $options['show_version'] : true;
        ?>
        <label>
            <input type="checkbox" name="bbm_options[show_version]" value="1" <?php checked($show_version, true); ?>>
            <?php esc_html_e('Display Bible version in tooltip', 'bible-by-midvash'); ?>
        </label>
        <?php
    }

    /**
     * Render: Cache enabled
     */
    public function cache_enabled_render()
    {
        $options = get_option('bbm_options');
        $cache_enabled = isset($options['cache_enabled']) ? $options['cache_enabled'] : true;
        ?>
        <label>
            <input type="checkbox" name="bbm_options[cache_enabled]" value="1" <?php checked($cache_enabled, true); ?>>
            <?php esc_html_e('Store verses in cache to improve performance', 'bible-by-midvash'); ?>
        </label>
        <?php
    }

    /**
     * Render: Cache TTL
     */
    public function cache_ttl_render()
    {
        $options = get_option('bbm_options');
        $cache_ttl = isset($options['cache_ttl']) ? $options['cache_ttl'] : 2592000;
        ?>
        <input type="number" name="bbm_options[cache_ttl]" value="<?php echo esc_attr($cache_ttl); ?>" min="60" max="2592000"
            step="60">
        <p class="description"><?php esc_html_e('Time in seconds (default: 2592000 = 30 days).', 'bible-by-midvash'); ?>
        </p>
        <?php
    }

    /**
     * Render: Timeout
     */
    public function timeout_render()
    {
        $options = get_option('bbm_options');
        $timeout = isset($options['timeout']) ? $options['timeout'] : 5;
        ?>
        <input type="number" name="bbm_options[timeout]" value="<?php echo esc_attr($timeout); ?>" min="1" max="30">
        <p class="description"><?php esc_html_e('Maximum wait time for the API.', 'bible-by-midvash'); ?></p>
        <?php
    }

    /**
     * Linking section callback
     */
    public function section_linking_callback()
    {
        echo '<p>' . esc_html__('Configure auto-linking for various elements in your content.', 'bible-by-midvash') . '</p>';
    }

    /**
     * Render: Link Biblia
     */
    public function link_biblia_render()
    {
        $options = get_option('bbm_options');
        $val = isset($options['link_biblia']) ? $options['link_biblia'] : false;
        ?>
        <label>
            <input type="checkbox" name="bbm_options[link_biblia]" value="1" <?php checked($val, true); ?>>
            <?php esc_html_e('Link the word "Bíblia"', 'bible-by-midvash'); ?>
        </label>
        <p class="description"><?php esc_html_e('Auto-link the word "Bíblia" to Midvash', 'bible-by-midvash'); ?></p>
        <?php
    }

    /**
     * Render: Link Versions
     */
    public function link_versions_render()
    {
        $options = get_option('bbm_options');
        $val = isset($options['link_versions']) ? $options['link_versions'] : false;
        ?>
        <label>
            <input type="checkbox" name="bbm_options[link_versions]" value="1" <?php checked($val, true); ?>>
            <?php esc_html_e('Link version names', 'bible-by-midvash'); ?>
        </label>
        <p class="description"><?php esc_html_e('Auto-link Bible version names mentioned in text', 'bible-by-midvash'); ?></p>
        <?php
    }



    /**
     * Render: Link Books
     */
    public function link_books_render()
    {
        $options = get_option('bbm_options');
        $val = isset($options['link_books']) ? $options['link_books'] : false;
        ?>
        <label>
            <input type="checkbox" name="bbm_options[link_books]" value="1" <?php checked($val, true); ?>>
            <?php esc_html_e('Link book names', 'bible-by-midvash'); ?>
        </label>
        <p class="description"><?php esc_html_e('Auto-link Bible book names mentioned in text', 'bible-by-midvash'); ?></p>
        <?php
    }

    /**
     * Render: Link Dictionary Terms
     */
    public function link_terms_render()
    {
        $options = get_option('bbm_options');
        $val = isset($options['link_terms']) ? $options['link_terms'] : false;
        ?>
        <label>
            <input type="checkbox" name="bbm_options[link_terms]" value="1" <?php checked($val, true); ?>>
            <?php esc_html_e('Link dictionary terms', 'bible-by-midvash'); ?>
        </label>
        <p class="description"><?php esc_html_e('Auto-link dictionary terms mentioned in text', 'bible-by-midvash'); ?></p>
        <?php
    }

    /**
     * Render: Link Biblical Characters
     */
    public function link_characters_render()
    {
        $options = get_option('bbm_options');
        $val = isset($options['link_characters']) ? $options['link_characters'] : false;
        ?>
        <label>
            <input type="checkbox" name="bbm_options[link_characters]" value="1" <?php checked($val, true); ?>>
            <?php esc_html_e('Link biblical characters', 'bible-by-midvash'); ?>
        </label>
        <p class="description"><?php esc_html_e('Auto-link biblical characters mentioned in text', 'bible-by-midvash'); ?></p>
        <?php
    }

    /**
     * Options page
     */
    public function options_page()
    {
        $active_tab = $this->get_active_tab();
        // Cache-buster com BBM_VERSION pra que o browser não sirva ícone antigo
        // após upgrade (img tag não tem o equivalente de wp_enqueue_style/_script).
        $icon_url = BBM_PLUGIN_URL . 'assets/images/icon-bbm.svg?v=' . BBM_VERSION;
        ?>
        <div class="wrap bbm-wrap">

            <div class="bbm-hero">
                <div class="bbm-hero__logo">
                    <img src="<?php echo esc_url($icon_url); ?>" alt="Midvash">
                </div>
                <div class="bbm-hero__content">
                    <h1 class="bbm-hero__title"><?php esc_html_e('Bible by Midvash', 'bible-by-midvash'); ?></h1>
                    <p class="bbm-hero__subtitle">
                        <?php esc_html_e('Auto-detect Bible references in your posts and turn them into beautiful links with verse tooltips.', 'bible-by-midvash'); ?>
                    </p>
                    <div class="bbm-hero__meta">
                        <span class="bbm-badge">v<?php echo esc_html(BBM_VERSION); ?></span>
                        <span class="bbm-badge"><?php esc_html_e('Powered by Midvash API', 'bible-by-midvash'); ?></span>
                    </div>
                </div>
                <div class="bbm-hero__actions">
                    <a href="https://wordpress.midvash.com" target="_blank" rel="noopener noreferrer" class="bbm-btn">
                        <?php esc_html_e('Plugin site', 'bible-by-midvash'); ?> ↗
                    </a>
                    <a href="https://midvash.com" target="_blank" rel="noopener noreferrer" class="bbm-btn bbm-btn--primary">
                        <?php esc_html_e('Open Midvash', 'bible-by-midvash'); ?> ↗
                    </a>
                </div>
            </div>

            <h2 class="nav-tab-wrapper">
                <a href="?page=bbm&tab=general"
                    class="nav-tab <?php echo esc_attr($active_tab === 'general' ? 'nav-tab-active' : ''); ?>">
                    <?php esc_html_e('General', 'bible-by-midvash'); ?>
                </a>
                <a href="?page=bbm&tab=cache"
                    class="nav-tab <?php echo esc_attr($active_tab === 'cache' ? 'nav-tab-active' : ''); ?>">
                    <?php esc_html_e('Cache & Performance', 'bible-by-midvash'); ?>
                </a>
            </h2>

            <div class="bbm-panel">
                <form action="options.php" method="post" id="bbm-settings-form">
                    <?php
                    settings_fields('bbm');

                    if ($active_tab === 'general') {
                        do_settings_sections('bbm_general');
                    } else {
                        do_settings_sections('bbm_cache');
                    }

                    submit_button(esc_html__('Save Settings', 'bible-by-midvash'));
                    ?>
                </form>
            </div>
            <?php if ($active_tab === 'general'): ?>
                <div class="bbm-help">
                    <h2><?php esc_html_e('How to use', 'bible-by-midvash'); ?></h2>
                    <p><?php esc_html_e('The plugin automatically detects Bible references in your posts. When hovering over a reference, a tooltip displays the verse text fetched from the Midvash API.', 'bible-by-midvash'); ?></p>
                    <h3><?php esc_html_e('Supported formats', 'bible-by-midvash'); ?></h3>
                    <ul class="bbm-formats">
                        <li><code>John 3:16</code><span class="bbm-formats__label"><?php esc_html_e('Single verse', 'bible-by-midvash'); ?></span></li>
                        <li><code>John 3.16</code><span class="bbm-formats__label"><?php esc_html_e('Alternative separator', 'bible-by-midvash'); ?></span></li>
                        <li><code>John 3:16-18</code><span class="bbm-formats__label"><?php esc_html_e('Verse range', 'bible-by-midvash'); ?></span></li>
                        <li><code>Gn 1:1</code><span class="bbm-formats__label"><?php esc_html_e('Abbreviation', 'bible-by-midvash'); ?></span></li>
                        <li><code>Psalms 23</code><span class="bbm-formats__label"><?php esc_html_e('Entire chapter', 'bible-by-midvash'); ?></span></li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
