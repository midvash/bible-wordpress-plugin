<?php
/**
 * Gutenberg block: bible-by-midvash/verse
 *
 * Lets post authors insert a specific Bible verse inline in the block editor.
 * Renders server-side via PHP so the verse is always fetched fresh (with caching).
 *
 * @package Bible_By_Midvash
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the bible-by-midvash/verse Gutenberg block.
 */
class BBMV_Block {

	/**
	 * Hook block registration and editor assets into WordPress.
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Register the block type with its attributes and render callback.
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			'bible-by-midvash/verse',
			array(
				'render_callback' => array( $this, 'render' ),
				'attributes'      => array(
					'reference'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'version'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'locale'         => array(
						'type'    => 'string',
						'default' => '',
					),
					'show_reference' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'link_verse'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			)
		);
	}

	/**
	 * Enqueue the block JS (editor only).
	 */
	public function enqueue_editor_assets() {
		wp_enqueue_script(
			'bbm-block',
			BBMV_PLUGIN_URL . 'assets/js/bbm-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n' ),
			BBMV_VERSION,
			true
		);

		wp_set_script_translations( 'bbm-block', 'bible-by-midvash' );
	}

	/**
	 * Server-side render callback.
	 *
	 * @param array $atts Block attributes.
	 * @return string HTML output.
	 */
	public function render( $atts ) {
		$reference = isset( $atts['reference'] ) ? sanitize_text_field( $atts['reference'] ) : '';
		if ( ! $reference ) {
			return '';
		}

		$options  = get_option( 'bbm_options', array() );
		$locale   = ! empty( $atts['locale'] )
			? BBMV_Books::normalize_locale( sanitize_text_field( $atts['locale'] ) )
			: ( isset( $options['locale'] ) ? $options['locale'] : 'pt-br' );
		$version  = ! empty( $atts['version'] )
			? strtolower( sanitize_text_field( $atts['version'] ) )
			: ( isset( $options['versao'] ) ? $options['versao'] : BBMV_Books::get_default_version( $locale ) );
		$show_ref = isset( $atts['show_reference'] ) ? (bool) $atts['show_reference'] : true;
		$link     = isset( $atts['link_verse'] ) ? (bool) $atts['link_verse'] : true;

		$api  = new BBMV_API();
		$data = $api->get_verse( $reference, $version );

		if ( ! $data || empty( $data['text'] ) ) {
			return '<p class="bbm-verse bbm-verse--error">'
				. esc_html__( 'Verse currently unavailable.', 'bible-by-midvash' )
				. '</p>';
		}

		$text    = $data['text'];
		$ref_str = isset( $data['reference'] ) ? $data['reference'] : $reference;

		// Build URL.
		$url = '';
		if ( $link ) {
			$url = $this->build_url( $reference, $locale, $version );
		}

		$version_badge = '<span class="bbm-verse__version">' . esc_html( strtoupper( $version ) ) . '</span>';
		$ref_html      = '';
		if ( $show_ref ) {
			if ( $link && $url ) {
				$ref_html = '<cite class="bbm-verse__reference">'
							. '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" class="bbm-verse__link" itemprop="url">'
							. esc_html( $ref_str ) . '</a> ' . $version_badge
							. '</cite>';
			} else {
				$ref_html = '<cite class="bbm-verse__reference">' . esc_html( $ref_str ) . ' ' . $version_badge . '</cite>';
			}
		}

		return '<blockquote class="bbm-verse wp-block-bible-by-midvash-verse" itemscope itemtype="https://schema.org/Quotation">'
			. '<p class="bbm-verse__text" itemprop="text">' . esc_html( $text ) . '</p>'
			. $ref_html
			. '</blockquote>';
	}

	/**
	 * Builds the Midvash URL for a given reference + locale + version.
	 *
	 * Delegates parsing to BBMV_Books::parse_reference() so book-name lookup,
	 * accent tolerance and chapter validation stay consistent across the
	 * parser, the API client and this block.
	 *
	 * @param string $reference Raw reference text (e.g. "John 3:16").
	 * @param string $locale    Normalized locale code.
	 * @param string $version   Version slug.
	 * @return string URL or empty string if reference cannot be parsed.
	 */
	private function build_url( $reference, $locale, $version ) {
		$parsed = BBMV_Books::parse_reference( $reference );
		if ( ! $parsed ) {
			return '';
		}

		$book_slug = BBMV_Books::get_book_slug( $parsed['book_id'], $locale );
		$url       = BBMV_SITE_URL . '/' . $locale . '/' . $version . '/' . $book_slug . '/' . $parsed['chapter'];

		if ( $parsed['verse'] ) {
			$url .= '/' . $parsed['verse'];
			if ( $parsed['verse_end'] && $parsed['verse_end'] !== $parsed['verse'] ) {
				$url .= '-' . $parsed['verse_end'];
			}
		}

		return $url;
	}
}
