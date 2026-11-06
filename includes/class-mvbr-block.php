<?php
/**
 * Gutenberg block: midvash-bible-references/verse
 *
 * Lets post authors insert a specific Bible verse inline in the block editor.
 * Renders server-side via PHP so the verse is always fetched fresh (with caching).
 *
 * @package Midvash_Bible_References
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the midvash-bible-references/verse Gutenberg block.
 */
class MVBR_Block {

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
			'midvash-bible-references/verse',
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
			'mvbr-block',
			mvbr_asset_url( 'assets/js/mvbr-block.js' ),
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n' ),
			MVBR_VERSION,
			true
		);

		wp_set_script_translations( 'mvbr-block', 'midvash-bible-references' );
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

		$options  = get_option( 'mvbr_options', array() );
		$locale   = ! empty( $atts['locale'] )
			? MVBR_Books::normalize_locale( sanitize_text_field( $atts['locale'] ) )
			: ( isset( $options['locale'] ) ? $options['locale'] : 'pt-br' );
		$locale   = MVBR_Books::normalize_locale( $locale );
		$version  = MVBR_Books::version_for_output(
			! empty( $atts['version'] ) ? sanitize_text_field( $atts['version'] ) : '',
			$locale,
			$options
		);
		$show_ref = isset( $atts['show_reference'] ) ? (bool) $atts['show_reference'] : true;
		$link     = isset( $atts['link_verse'] ) ? (bool) $atts['link_verse'] : true;

		$api  = new MVBR_API();
		$data = $api->get_verse( $reference, $version );

		if ( ! $data || empty( $data['text'] ) ) {
			return '<p class="mvbr-verse mvbr-verse--error">'
				. esc_html__( 'Verse currently unavailable.', 'midvash-bible-references' )
				. '</p>';
		}

		wp_enqueue_style( 'mvbr-style' );

		$text    = $data['text'];
		$ref_str = isset( $data['reference'] ) ? $data['reference'] : $reference;

		// Build URL.
		$url = '';
		if ( $link ) {
			$url = $this->build_url( $reference, $locale, $version );
		}

		$version_badge = '<span class="mvbr-verse__version">' . esc_html( $api->get_version_label( $version ) ) . '</span>';
		$ref_html      = '';
		if ( $show_ref ) {
			if ( $link && $url ) {
				$ref_html = '<cite class="mvbr-verse__reference">'
							. '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" class="mvbr-verse__link" itemprop="url">'
							. esc_html( $ref_str ) . '</a> ' . $version_badge
							. '</cite>';
			} else {
				$ref_html = '<cite class="mvbr-verse__reference">' . esc_html( $ref_str ) . ' ' . $version_badge . '</cite>';
			}
		}

		// Version attribution as returned by the API alongside the text, falling
		// back to the (cached) catalogue entry for responses that predate it.
		$copyright = ( ! empty( $data['copyright'] ) && is_string( $data['copyright'] ) ) ? $data['copyright'] : '';
		if ( '' === $copyright ) {
			$version_meta = $api->get_version_meta( $version );
			$copyright    = ( $version_meta && ! empty( $version_meta['copyright'] ) ) ? (string) $version_meta['copyright'] : '';
		}
		$copyright_html = '' !== trim( $copyright )
			? '<small class="mvbr-verse__copyright">' . nl2br( esc_html( trim( $copyright ) ) ) . '</small>'
			: '';

		return '<blockquote class="mvbr-verse wp-block-midvash-bible-references-verse" itemscope itemtype="https://schema.org/Quotation">'
			. '<p class="mvbr-verse__text" itemprop="text">' . esc_html( $text ) . '</p>'
			. $ref_html
			. $copyright_html
			. '</blockquote>';
	}

	/**
	 * Builds the Midvash URL for a given reference + locale + version.
	 *
	 * Delegates parsing to MVBR_Books::parse_reference() so book-name lookup,
	 * accent tolerance and chapter validation stay consistent across the
	 * parser, the API client and this block.
	 *
	 * @param string $reference Raw reference text (e.g. "John 3:16").
	 * @param string $locale    Normalized locale code.
	 * @param string $version   Version slug.
	 * @return string URL or empty string if reference cannot be parsed.
	 */
	private function build_url( $reference, $locale, $version ) {
		$parsed = MVBR_Books::parse_reference( $reference );
		if ( ! $parsed ) {
			return '';
		}

		$book_slug = MVBR_Books::get_book_slug( $parsed['book_id'], $locale );
		$url       = MVBR_Books::site_url( $locale ) . '/' . $version . '/' . $book_slug . '/' . $parsed['chapter'];

		if ( $parsed['verse'] ) {
			$url .= '#v=' . $parsed['verse'];
			if ( empty( $parsed['chapter_end'] ) && $parsed['verse_end'] && $parsed['verse_end'] > $parsed['verse'] ) {
				$url .= '-' . $parsed['verse_end'];
			}
		}

		return $url;
	}
}
