<?php
/**
 * Class responsible for the plugin settings page.
 *
 * @package Midvash_Bible_References
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings screen: registers the options page, sections, fields and
 * sanitization for the plugin's settings.
 */
class MVBR_Admin {

	/**
	 * Initializes the admin
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'settings_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin CSS/JS only on the plugin's settings page
	 *
	 * @param string $hook Hook suffix of the current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_mvbr' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'mvbr-admin-style',
			mvbr_asset_url( 'assets/css/mvbr-admin.css' ),
			array(),
			MVBR_VERSION
		);

		wp_enqueue_script(
			'mvbr-admin',
			mvbr_asset_url( 'assets/js/mvbr-admin.js' ),
			array(),
			MVBR_VERSION,
			true
		);

		wp_localize_script(
			'mvbr-admin',
			'mvbrAdmin',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'mvbr_nonce' ),
				// Kept in sync with MVBR_Books::DEFAULT_VERSIONS — passed to JS so
				// the version select snaps to the preferred default per locale.
				'defaults' => MVBR_Books::DEFAULT_VERSIONS,
				'i18n'     => array(
					'loading' => __( 'Loading…', 'midvash-bible-references' ),
					'empty'   => __( 'No versions available', 'midvash-bible-references' ),
					'error'   => __( 'Error loading versions', 'midvash-bible-references' ),
				),
			)
		);
	}

	/**
	 * Adds the settings menu
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'Midvash Bible References', 'midvash-bible-references' ),
			__( 'Midvash Bible References', 'midvash-bible-references' ),
			'manage_options',
			'mvbr',
			array( $this, 'options_page' )
		);
	}

	/**
	 * Registers settings
	 */
	public function settings_init() {
		register_setting( 'mvbr', 'mvbr_options', array( $this, 'sanitize_options' ) );

		// Main section.
		add_settings_section(
			'mvbr_section_main',
			esc_html__( 'General Settings', 'midvash-bible-references' ),
			array( $this, 'section_main_callback' ),
			'mvbr_general'
		);

		// Cache section.
		add_settings_section(
			'mvbr_section_cache',
			esc_html__( 'Cache and Performance', 'midvash-bible-references' ),
			array( $this, 'section_cache_callback' ),
			'mvbr_cache'
		);

		// Field: Language/Locale.
		add_settings_field(
			'locale',
			esc_html__( 'Language', 'midvash-bible-references' ),
			array( $this, 'locale_render' ),
			'mvbr_general',
			'mvbr_section_main'
		);

		// Field: Bible Version.
		add_settings_field(
			'versao',
			esc_html__( 'Bible Version', 'midvash-bible-references' ),
			array( $this, 'versao_render' ),
			'mvbr_general',
			'mvbr_section_main'
		);

		// Field: Link Color (sempre visível, aplicada quando use_custom_color estiver marcado).
		add_settings_field(
			'link_color',
			esc_html__( 'Link Color', 'midvash-bible-references' ),
			array( $this, 'link_color_render' ),
			'mvbr_general',
			'mvbr_section_main'
		);

		// Field: Use Custom Color.
		add_settings_field(
			'use_custom_color',
			esc_html__( 'Enable custom color', 'midvash-bible-references' ),
			array( $this, 'use_custom_color_render' ),
			'mvbr_general',
			'mvbr_section_main'
		);

		// Field: Underline Link.
		add_settings_field(
			'underline_link',
			esc_html__( 'Underline links', 'midvash-bible-references' ),
			array( $this, 'underline_link_render' ),
			'mvbr_general',
			'mvbr_section_main'
		);

		// Field: Underline Color.
		add_settings_field(
			'underline_color',
			esc_html__( 'Underline Color', 'midvash-bible-references' ),
			array( $this, 'underline_color_render' ),
			'mvbr_general',
			'mvbr_section_main'
		);

		// Field: Underline Style.
		add_settings_field(
			'underline_style',
			esc_html__( 'Underline Style', 'midvash-bible-references' ),
			array( $this, 'underline_style_render' ),
			'mvbr_general',
			'mvbr_section_main'
		);

		// Field: Open in new tab.
		add_settings_field(
			'new_tab',
			esc_html__( 'Open in new tab', 'midvash-bible-references' ),
			array( $this, 'new_tab_render' ),
			'mvbr_general',
			'mvbr_section_main'
		);

		// Field: Show version in tooltip.
		add_settings_field(
			'show_version',
			esc_html__( 'Show version in tooltip', 'midvash-bible-references' ),
			array( $this, 'show_version_render' ),
			'mvbr_general',
			'mvbr_section_main'
		);

		// Field: Midvash logo in the tooltip (off by default: credits and links
		// on the public site need the site owner's opt-in).
		add_settings_field(
			'show_branding',
			esc_html__( 'Midvash logo in tooltip', 'midvash-bible-references' ),
			array( $this, 'show_branding_render' ),
			'mvbr_general',
			'mvbr_section_main'
		);

		// Field: Cache enabled.
		add_settings_field(
			'cache_enabled',
			esc_html__( 'Enable cache', 'midvash-bible-references' ),
			array( $this, 'cache_enabled_render' ),
			'mvbr_cache',
			'mvbr_section_cache'
		);

		// Field: Cache TTL.
		add_settings_field(
			'cache_ttl',
			esc_html__( 'Cache duration (seconds)', 'midvash-bible-references' ),
			array( $this, 'cache_ttl_render' ),
			'mvbr_cache',
			'mvbr_section_cache'
		);

		// Field: Timeout.
		add_settings_field(
			'timeout',
			esc_html__( 'API Timeout (seconds)', 'midvash-bible-references' ),
			array( $this, 'timeout_render' ),
			'mvbr_cache',
			'mvbr_section_cache'
		);

		// Auto-linking section.
		add_settings_section(
			'mvbr_section_linking',
			esc_html__( 'Auto-linking Settings', 'midvash-bible-references' ),
			array( $this, 'section_linking_callback' ),
			'mvbr_general'
		);

		// Field: Link "Bíblia".
		add_settings_field(
			'link_biblia',
			esc_html__( 'Link the word "Bíblia"', 'midvash-bible-references' ),
			array( $this, 'link_biblia_render' ),
			'mvbr_general',
			'mvbr_section_linking'
		);
	}

	/**
	 * Whitelist of admin tabs. Used by both navigation and the per-tab
	 * checkbox reset logic in sanitize_options().
	 */
	const TABS = array( 'general', 'cache' );

	/**
	 * Reads the active tab from `$_GET['tab']`, sanitized and validated.
	 * Defaults to 'general'.
	 */
	private function get_active_tab() {
		// Reading $_GET only to identify which tab is being saved — there's no
		// form action attached to this value, so no nonce is needed beyond the
		// settings_fields() nonce that wraps the form.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		return in_array( $raw, self::TABS, true ) ? $raw : 'general';
	}

	/**
	 * True when the current POST is the Settings API submit for our option page.
	 * Settings API already verified the nonce via `option_page_capability_*` /
	 * `check_admin_referer` by the time `register_setting`’s sanitize callback
	 * runs, so reading $_POST here is safe; we still sanitize+unslash for WPCS.
	 */
	private function is_our_options_post() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['option_page'] ) ) {
			return false;
		}
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return sanitize_key( wp_unslash( $_POST['option_page'] ) ) === 'mvbr';
	}

	/**
	 * Reads a checkbox value from $input. If the key is missing AND we're
	 * submitting from the right tab (checkboxes don't POST when unchecked),
	 * returns false. Otherwise leaves the existing value untouched.
	 *
	 * @param array  $input       Raw $_POST input.
	 * @param string $key         Option key.
	 * @param string $tab         Tab where this checkbox lives.
	 * @param array  $sanitized   Working sanitized array (passed by ref).
	 */
	private function read_checkbox( $input, $key, $tab, &$sanitized ) {
		if ( isset( $input[ $key ] ) ) {
			$sanitized[ $key ] = (bool) $input[ $key ];
			return;
		}
		if ( $this->is_our_options_post() && $this->get_active_tab() === $tab ) {
			$sanitized[ $key ] = false;
		}
	}

	/**
	 * Sanitizes options
	 *
	 * @param array $input Raw options array submitted via the Settings API.
	 */
	public function sanitize_options( $input ) {
		$existing  = get_option( 'mvbr_options', array() );
		$sanitized = is_array( $existing ) ? $existing : array();

		if ( ! is_array( $input ) ) {
			return $sanitized;
		}

		// ----- General tab checkboxes -----
		$this->read_checkbox( $input, 'use_custom_color', 'general', $sanitized );
		$this->read_checkbox( $input, 'underline_link', 'general', $sanitized );
		$this->read_checkbox( $input, 'new_tab', 'general', $sanitized );
		$this->read_checkbox( $input, 'show_version', 'general', $sanitized );
		$this->read_checkbox( $input, 'show_branding', 'general', $sanitized );
		$this->read_checkbox( $input, 'link_biblia', 'general', $sanitized );

		// ----- Cache tab checkboxes -----
		$this->read_checkbox( $input, 'cache_enabled', 'cache', $sanitized );

		// ----- Colors -----
		if ( isset( $input['link_color'] ) ) {
			$sanitized['link_color'] = sanitize_hex_color( $input['link_color'] );
		}
		if ( isset( $input['underline_color'] ) ) {
			$sanitized['underline_color'] = sanitize_hex_color( $input['underline_color'] );
		}

		// ----- Locale -----
		if ( isset( $input['locale'] ) ) {
			$valid_locales = MVBR_Books::LOCALES;
			$new_locale    = sanitize_text_field( $input['locale'] );
			$new_locale    = in_array( $new_locale, $valid_locales, true ) ? $new_locale : 'pt-br';
			$new_locale    = MVBR_Books::normalize_locale( $new_locale );

			$old_locale          = isset( $sanitized['locale'] ) ? $sanitized['locale'] : 'pt-br';
			$sanitized['locale'] = $new_locale;

			// If locale changed, snap version to that locale's default (unless caller picked one).
			if ( $new_locale !== $old_locale && ! isset( $input['versao'] ) ) {
				$sanitized['versao'] = MVBR_Books::get_default_version( $new_locale );
			}
		}

		// ----- Version -----
		if ( isset( $input['versao'] ) ) {
			$sanitized['versao'] = MVBR_Books::resolve_version( sanitize_text_field( $input['versao'] ) );
		}

		// ----- Underline style (constrained list) -----
		if ( isset( $input['underline_style'] ) ) {
			$style                        = sanitize_text_field( $input['underline_style'] );
			$allowed                      = array( 'solid', 'double', 'dotted', 'dashed', 'wavy' );
			$sanitized['underline_style'] = in_array( $style, $allowed, true ) ? $style : 'solid';
		}

		// CSS class is fixed for now (kept here so consumers can rely on it).
		$sanitized['css_class'] = 'mvbr-link';

		// ----- Cache numbers -----
		if ( isset( $input['cache_ttl'] ) ) {
			// Clamp to [60 s, 1 year] to avoid degenerate values.
			$sanitized['cache_ttl'] = max( 60, min( YEAR_IN_SECONDS, absint( $input['cache_ttl'] ) ) );
		}
		if ( isset( $input['timeout'] ) ) {
			// Clamp to [1, 30] seconds; matches the admin field's `min`/`max`.
			$sanitized['timeout'] = max( 1, min( 30, absint( $input['timeout'] ) ) );
		}

		return $sanitized;
	}

	/**
	 * Main section callback
	 */
	public function section_main_callback() {
		echo '<p>' . esc_html__( 'Configure how Bible references will be displayed.', 'midvash-bible-references' ) . '</p>';
	}

	/**
	 * Cache section callback
	 */
	public function section_cache_callback() {
		echo '<p>' . esc_html__( 'Configure cache settings to improve tooltip performance.', 'midvash-bible-references' ) . '</p>';
	}

	/**
	 * Render: Language/Locale
	 */
	public function locale_render() {
		$options = get_option( 'mvbr_options' );
		$locale  = isset( $options['locale'] ) ? $options['locale'] : 'pt-br';

		$locales = array(
			'pt-br' => array(
				'name'            => 'Português (Brasil)',
				'flag'            => '🇧🇷',
				'default_version' => 'onbv',
			),
			'en'    => array(
				'name'            => 'English',
				'flag'            => '🇺🇸',
				'default_version' => 'bsb',
			),
			'es'    => array(
				'name'            => 'Español',
				'flag'            => '🇪🇸',
				'default_version' => 'rvr1909',
			),
			'fr'    => array(
				'name'            => 'Français',
				'flag'            => '🇫🇷',
				'default_version' => 'lsg',
			),
			'de'    => array(
				'name'            => 'Deutsch',
				'flag'            => '🇩🇪',
				'default_version' => 'luth1912',
			),
			'it'    => array(
				'name'            => 'Italiano',
				'flag'            => '🇮🇹',
				'default_version' => 'nri',
			),
			'ru'    => array(
				'name'            => 'Русский',
				'flag'            => '🇷🇺',
				'default_version' => 'synodal',
			),
			'ko'    => array(
				'name'            => '한국어',
				'flag'            => '🇰🇷',
				'default_version' => 'kor',
			),
			'zh'    => array(
				'name'            => '中文',
				'flag'            => '🇨🇳',
				'default_version' => 'cuv',
			),
		);
		?>
		<select name="mvbr_options[locale]" id="mvbr_locale">
			<?php foreach ( $locales as $code => $data ) : ?>
				<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $locale, $code ); ?>>
					<?php echo esc_html( $data['flag'] . ' ' . $data['name'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Select the language for links and book names. This determines the URL structure on Midvash.', 'midvash-bible-references' ); ?>
		</p>
		<?php
	}

	/**
	 * Render: Bible Version
	 */
	public function versao_render() {
		$options = get_option( 'mvbr_options' );
		$locale  = isset( $options['locale'] ) ? $options['locale'] : 'pt-br';
		$locale  = MVBR_Books::normalize_locale( $locale );

		// Get default version for current locale.
		$default_version = MVBR_Books::get_default_version( $locale );
		$versao          = isset( $options['versao'] ) ? MVBR_Books::resolve_version( $options['versao'] ) : $default_version;

		$api = new MVBR_API();
		// Get versions filtered by current locale from API.
		$api_versions = $api->get_versions( $locale );

		// If API fails, show empty state (will be loaded via AJAX).
		$versions_to_show = $api_versions ? $api_versions : array();
		?>
		<select name="mvbr_options[versao]" id="mvbr_versao">
			<?php if ( $versions_to_show ) : ?>
				<?php foreach ( $versions_to_show as $v ) : ?>
					<?php
					$slug       = isset( $v['slug'] ) ? $v['slug'] : ( isset( $v['code'] ) ? strtolower( $v['code'] ) : '' );
					$name       = isset( $v['name'] ) ? $v['name'] : '';
					$short_name = isset( $v['shortName'] ) ? $v['shortName'] : ( isset( $v['code'] ) ? $v['code'] : '' );
					// Prefer the version name localized to the site's display locale
					// (e.g. "Bíblia em Inglês Básico" on a pt-BR site), falling back
					// to the native name.
					if ( isset( $v['localizedNames'][ $locale ] ) && '' !== $v['localizedNames'][ $locale ] ) {
						$name = $v['localizedNames'][ $locale ];
					}
					$display_name = $name ? ( $name . ' (' . $short_name . ')' ) : $short_name;
					// Versions with reserved rights carry a discreet mark.
					if ( ! empty( $v['restricted'] ) ) {
						$display_name .= ' ©';
					}
					$copyright = isset( $v['copyright'] ) ? $v['copyright'] : '';
					?>
					<option value="<?php echo esc_attr( strtolower( $slug ) ); ?>" title="<?php echo esc_attr( $copyright ); ?>" <?php selected( strtolower( $versao ), strtolower( $slug ) ); ?>>
						<?php echo esc_html( $display_name ); ?>
					</option>
				<?php endforeach; ?>
			<?php endif; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Select the Bible version for links and tooltips. Versions are fetched dynamically from Midvash.', 'midvash-bible-references' ); ?>
		</p>
		<?php
	}

	/**
	 * Render: Use Custom Color
	 */
	public function use_custom_color_render() {
		$options = get_option( 'mvbr_options' );
		$val     = isset( $options['use_custom_color'] ) ? $options['use_custom_color'] : false;
		?>
		<label>
			<input type="checkbox" name="mvbr_options[use_custom_color]" value="1" <?php checked( $val, true ); ?>>
			<?php esc_html_e( 'Enable custom color for links', 'midvash-bible-references' ); ?>
		</label>
		<?php
	}

	/**
	 * Render: Link Color
	 */
	public function link_color_render() {
		$options = get_option( 'mvbr_options' );
		$color   = isset( $options['link_color'] ) ? $options['link_color'] : '#B17027';
		?>
		<input type="color" name="mvbr_options[link_color]" value="<?php echo esc_attr( $color ); ?>">
		<code><?php echo esc_html( $color ); ?></code>
		<p class="description"><?php esc_html_e( 'Main color of the generated links.', 'midvash-bible-references' ); ?></p>
		<?php
	}

	/**
	 * Render: Underline Link
	 */
	public function underline_link_render() {
		$options = get_option( 'mvbr_options' );
		$val     = isset( $options['underline_link'] ) ? $options['underline_link'] : false;
		?>
		<label>
			<input type="checkbox" name="mvbr_options[underline_link]" value="1" <?php checked( $val, true ); ?>>
			<?php esc_html_e( 'Enable underline for links', 'midvash-bible-references' ); ?>
		</label>
		<?php
	}

	/**
	 * Render: Underline Color
	 */
	public function underline_color_render() {
		$options = get_option( 'mvbr_options' );
		$color   = isset( $options['underline_color'] ) ? $options['underline_color'] : '#B17027';
		?>
		<input type="color" name="mvbr_options[underline_color]" value="<?php echo esc_attr( $color ); ?>">
		<code><?php echo esc_html( $color ); ?></code>
		<p class="description"><?php esc_html_e( 'Color of the link underline.', 'midvash-bible-references' ); ?></p>
		<?php
	}

	/**
	 * Render: Underline Style
	 */
	public function underline_style_render() {
		$options = get_option( 'mvbr_options' );
		$style   = isset( $options['underline_style'] ) ? $options['underline_style'] : 'solid';
		$styles  = array(
			'solid'  => esc_html__( 'Solid', 'midvash-bible-references' ),
			'double' => esc_html__( 'Double', 'midvash-bible-references' ),
			'dotted' => esc_html__( 'Dotted', 'midvash-bible-references' ),
			'dashed' => esc_html__( 'Dashed', 'midvash-bible-references' ),
			'wavy'   => esc_html__( 'Wavy', 'midvash-bible-references' ),
		);
		?>
		<select name="mvbr_options[underline_style]">
			<?php foreach ( $styles as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $style, $value ); ?>><?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e( 'Style of the link underline.', 'midvash-bible-references' ); ?></p>
		<?php
	}

	/**
	 * Render: Open in new tab
	 */
	public function new_tab_render() {
		$options = get_option( 'mvbr_options' );
		$new_tab = isset( $options['new_tab'] ) ? $options['new_tab'] : true;
		?>
		<label>
			<input type="checkbox" name="mvbr_options[new_tab]" value="1" <?php checked( $new_tab, true ); ?>>
			<?php esc_html_e( 'Open links in a new browser tab', 'midvash-bible-references' ); ?>
		</label>
		<?php
	}

	/**
	 * Render: Show version in tooltip
	 */
	public function show_version_render() {
		$options      = get_option( 'mvbr_options' );
		$show_version = isset( $options['show_version'] ) ? $options['show_version'] : true;
		?>
		<label>
			<input type="checkbox" name="mvbr_options[show_version]" value="1" <?php checked( $show_version, true ); ?>>
			<?php esc_html_e( 'Display Bible version in tooltip', 'midvash-bible-references' ); ?>
		</label>
		<?php
	}

	/**
	 * Render: Midvash logo in the tooltip
	 */
	public function show_branding_render() {
		$options       = get_option( 'mvbr_options' );
		$show_branding = ! empty( $options['show_branding'] );
		?>
		<label>
			<input type="checkbox" name="mvbr_options[show_branding]" value="1" <?php checked( $show_branding, true ); ?>>
			<?php esc_html_e( 'Show the Midvash logo in the tooltip, link the version badge to midvash.com and add a Midvash credit under the Verse of the Day', 'midvash-bible-references' ); ?>
		</label>
		<?php
	}

	/**
	 * Render: Cache enabled
	 */
	public function cache_enabled_render() {
		$options       = get_option( 'mvbr_options' );
		$cache_enabled = isset( $options['cache_enabled'] ) ? $options['cache_enabled'] : true;
		?>
		<label>
			<input type="checkbox" name="mvbr_options[cache_enabled]" value="1" <?php checked( $cache_enabled, true ); ?>>
			<?php esc_html_e( 'Store verses in cache to improve performance', 'midvash-bible-references' ); ?>
		</label>
		<?php
	}

	/**
	 * Render: Cache TTL
	 */
	public function cache_ttl_render() {
		$options   = get_option( 'mvbr_options' );
		$cache_ttl = isset( $options['cache_ttl'] ) ? $options['cache_ttl'] : 2592000;
		?>
		<input type="number" name="mvbr_options[cache_ttl]" value="<?php echo esc_attr( $cache_ttl ); ?>" min="60" max="2592000"
			step="60">
		<p class="description"><?php esc_html_e( 'Time in seconds (default: 2592000 = 30 days).', 'midvash-bible-references' ); ?>
		</p>
		<?php
	}

	/**
	 * Render: Timeout
	 */
	public function timeout_render() {
		$options = get_option( 'mvbr_options' );
		$timeout = isset( $options['timeout'] ) ? $options['timeout'] : 5;
		?>
		<input type="number" name="mvbr_options[timeout]" value="<?php echo esc_attr( $timeout ); ?>" min="1" max="30">
		<p class="description"><?php esc_html_e( 'Maximum wait time for the API.', 'midvash-bible-references' ); ?></p>
		<?php
	}

	/**
	 * Linking section callback
	 */
	public function section_linking_callback() {
		echo '<p>' . esc_html__( 'Configure auto-linking for various elements in your content.', 'midvash-bible-references' ) . '</p>';
	}

	/**
	 * Render: Link Biblia
	 */
	public function link_biblia_render() {
		$options = get_option( 'mvbr_options' );
		$val     = isset( $options['link_biblia'] ) ? $options['link_biblia'] : false;
		?>
		<label>
			<input type="checkbox" name="mvbr_options[link_biblia]" value="1" <?php checked( $val, true ); ?>>
			<?php esc_html_e( 'Link the word "Bíblia"', 'midvash-bible-references' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Links the first mention of the word "Bíblia" in each post to Midvash', 'midvash-bible-references' ); ?></p>
		<?php
	}

	/**
	 * Options page
	 */
	public function options_page() {
		$active_tab = $this->get_active_tab();
		// Cache-buster com MVBR_VERSION pra que o browser não sirva ícone antigo
		// após upgrade (img tag não tem o equivalente de wp_enqueue_style/_script).
		$icon_url = MVBR_PLUGIN_URL . 'assets/images/icon-mvbr.svg?v=' . MVBR_VERSION;
		?>
		<div class="wrap mvbr-wrap">

			<div class="mvbr-hero">
				<div class="mvbr-hero__logo">
					<img src="<?php echo esc_url( $icon_url ); ?>" alt="Midvash">
				</div>
				<div class="mvbr-hero__content">
					<h1 class="mvbr-hero__title"><?php esc_html_e( 'Midvash Bible References', 'midvash-bible-references' ); ?></h1>
					<p class="mvbr-hero__subtitle">
						<?php esc_html_e( 'Auto-detect Bible references in your posts and turn them into beautiful links with verse tooltips.', 'midvash-bible-references' ); ?>
					</p>
					<div class="mvbr-hero__meta">
						<span class="mvbr-badge">v<?php echo esc_html( MVBR_VERSION ); ?></span>
						<span class="mvbr-badge"><?php esc_html_e( 'Powered by Midvash API', 'midvash-bible-references' ); ?></span>
					</div>
				</div>
				<div class="mvbr-hero__actions">
					<a href="https://midvash.com/wordpress-plugin" target="_blank" rel="noopener noreferrer" class="mvbr-btn">
						<?php esc_html_e( 'Plugin site', 'midvash-bible-references' ); ?> ↗
					</a>
					<a href="https://midvash.com" target="_blank" rel="noopener noreferrer" class="mvbr-btn mvbr-btn--primary">
						<?php esc_html_e( 'Open Midvash', 'midvash-bible-references' ); ?> ↗
					</a>
				</div>
			</div>

			<h2 class="nav-tab-wrapper">
				<a href="?page=mvbr&tab=general"
					class="nav-tab <?php echo esc_attr( 'general' === $active_tab ? 'nav-tab-active' : '' ); ?>">
					<?php esc_html_e( 'General', 'midvash-bible-references' ); ?>
				</a>
				<a href="?page=mvbr&tab=cache"
					class="nav-tab <?php echo esc_attr( 'cache' === $active_tab ? 'nav-tab-active' : '' ); ?>">
					<?php esc_html_e( 'Cache & Performance', 'midvash-bible-references' ); ?>
				</a>
			</h2>

			<div class="mvbr-panel">
				<form action="options.php" method="post" id="mvbr-settings-form">
					<?php
					settings_fields( 'mvbr' );

					if ( 'general' === $active_tab ) {
						do_settings_sections( 'mvbr_general' );
					} else {
						do_settings_sections( 'mvbr_cache' );
					}

					submit_button( esc_html__( 'Save Settings', 'midvash-bible-references' ) );
					?>
				</form>
			</div>
			<?php if ( 'general' === $active_tab ) : ?>
				<div class="mvbr-help">
					<h2><?php esc_html_e( 'How to use', 'midvash-bible-references' ); ?></h2>
					<p><?php esc_html_e( 'The plugin automatically detects Bible references in your posts. When hovering over a reference, a tooltip displays the verse text fetched from the Midvash API.', 'midvash-bible-references' ); ?></p>
					<h3><?php esc_html_e( 'Supported formats', 'midvash-bible-references' ); ?></h3>
					<ul class="mvbr-formats">
						<li><code>John 3:16</code><span class="mvbr-formats__label"><?php esc_html_e( 'Single verse', 'midvash-bible-references' ); ?></span></li>
						<li><code>John 3.16</code><span class="mvbr-formats__label"><?php esc_html_e( 'Alternative separator', 'midvash-bible-references' ); ?></span></li>
						<li><code>John 3:16-18</code><span class="mvbr-formats__label"><?php esc_html_e( 'Verse range', 'midvash-bible-references' ); ?></span></li>
						<li><code>Gn 1:1</code><span class="mvbr-formats__label"><?php esc_html_e( 'Abbreviation', 'midvash-bible-references' ); ?></span></li>
						<li><code>Psalms 23</code><span class="mvbr-formats__label"><?php esc_html_e( 'Entire chapter', 'midvash-bible-references' ); ?></span></li>
					</ul>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
