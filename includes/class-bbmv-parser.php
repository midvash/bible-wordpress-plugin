<?php
/**
 * Class responsible for identifying and linking Bible references.
 *
 * Uses BBMV_Books for centralized book data with multilingual support.
 *
 * @package Bible_By_Midvash
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses post content for Bible references and turns them into Midvash links.
 */
class BBMV_Parser {

	/**
	 * Plugin options
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Current locale
	 *
	 * @var string
	 */
	private $locale;

	/**
	 * Books referenced in the current post
	 *
	 * @var array
	 */
	private $referenced_books = array();

	/**
	 * Every reference string linked during this request, in order of
	 * appearance. Static because the footer hydration hook runs outside the
	 * parser instance created in bbmv_init(). Read via get_collected_refs().
	 *
	 * @var array
	 */
	private static $collected_refs = array();

	/**
	 * Initializes the parser
	 */
	public function init() {
		$this->options = get_option(
			'bbm_options',
			array(
				'locale'           => 'pt-br',
				'versao'           => 'nvt',
				'use_custom_color' => false,
				'link_color'       => '#B17027',
				'underline_link'   => false,
				'underline_color'  => '#B17027',
				'underline_style'  => 'solid',
				'new_tab'          => true,
				'css_class'        => 'bbm-link',
				'show_version'     => true,
				'link_biblia'      => false,
				'link_versions'    => false,
				'link_books'       => false,
				'link_terms'       => false,
				'link_characters'  => false,
			)
		);

		$this->locale = isset( $this->options['locale'] ) ? $this->options['locale'] : 'pt-br';
		$this->locale = BBMV_Books::normalize_locale( $this->locale );

		// Add filter to content.
		add_filter( 'the_content', array( $this, 'parse_content' ), 20 );
	}

	/**
	 * Processes content and adds links
	 *
	 * @param string $content Post content filtered via the_content.
	 */
	public function parse_content( $content ) {
		// Only on single posts/pages, not in admin.
		if ( ! is_singular() || is_admin() ) {
			return $content;
		}

		$this->referenced_books = array();

		// Build the regex pattern using book names and abbreviations.
		$pattern = BBMV_Books::get_matching_pattern();

		// Regex that skips content inside <a> tags and headers h1-h6.
		$skip_tags = '<a\b[^>]*>.*?<\/a>|<h[1-6]\b[^>]*>.*?<\/h[1-6]>(*SKIP)(*F)';

		// Pattern: BookName Chapter:Verse(-VerseEnd)?
		// Supports both : and . as separators.
		$pattern_verses = '/' . $skip_tags . '|\b(' . $pattern . ')\s+(\d{1,3})(?:[:\.](\d{1,3}))?(?:\s*[-–]\s*(\d{1,3}))?\b/iu';

		$content = preg_replace_callback(
			$pattern_verses,
			function ( $matches ) {
				if ( empty( $matches[1] ) ) {
					return $matches[0];
				}
				return $this->replace_reference( $matches );
			},
			$content
		);

		// Link "Bíblia" word if enabled.
		if ( ! empty( $this->options['link_biblia'] ) ) {
			$content = preg_replace_callback(
				'/' . $skip_tags . '|\b(Bíblia|Biblia|Bible)\b/iu',
				function ( $matches ) {
					if ( empty( $matches[1] ) ) {
						return $matches[0];
					}

					$url = BBMV_SITE_URL . '/' . $this->locale;

					$css_class = isset( $this->options['css_class'] ) ? $this->options['css_class'] : 'bbm-link';
					$new_tab   = isset( $this->options['new_tab'] ) ? $this->options['new_tab'] : true;
					$target    = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';

					return sprintf(
						'<a href="%s" class="%s"%s title="%s">%s</a>',
						esc_url( $url ),
						esc_attr( $css_class ),
						$target,
						esc_attr__( 'Online Bible', 'bible-by-midvash' ),
						esc_html( $matches[1] )
					);
				},
				$content
			);
		}

		return $content;
	}

	/**
	 * Replaces the reference with a link and data attributes for tooltip
	 *
	 * @param array $matches Regex matches from parse_content: full match at 0, book name at 1, chapter at 2, verse at 3, optional verse range end at 4.
	 */
	public function replace_reference( $matches ) {
		$original = $matches[0];

		// Delegate to the centralized parser — it re-runs the (cheap) regex on
		// the original substring and adds accent fallback + chapter validation
		// against the book's known chapter count. Sharing this path with the
		// API client and the Gutenberg block keeps behaviour aligned.
		$parsed = BBMV_Books::parse_reference( $original );
		if ( ! $parsed ) {
			return $original;
		}

		$book_id = $parsed['book_id'];
		$book    = $parsed['book'];

		// Track this book as referenced.
		if ( ! in_array( $book_id, $this->referenced_books, true ) ) {
			$this->referenced_books[] = $book_id;
		}

		// Track the reference itself for the footer tooltip hydration.
		if ( ! in_array( $original, self::$collected_refs, true ) ) {
			self::$collected_refs[] = $original;
		}

		// Get settings.
		$versao    = isset( $this->options['versao'] ) ? strtolower( $this->options['versao'] ) : 'nvt';
		$css_class = isset( $this->options['css_class'] ) ? $this->options['css_class'] : 'bbm-link';
		$new_tab   = isset( $this->options['new_tab'] ) ? $this->options['new_tab'] : true;

		// Get slug for the current locale.
		$book_slug = BBMV_Books::get_book_slug( $book_id, $this->locale );

		// Build URL with locale prefix.
		// Format: https://midvash.com/{locale}/{version}/{book_slug}/{chapter}/{verse}.
		$url = BBMV_SITE_URL . '/' . $this->locale . '/' . $versao . '/' . $book_slug . '/' . $parsed['chapter'];

		if ( $parsed['verse'] ) {
			if ( $parsed['verse_end'] && $parsed['verse_end'] !== $parsed['verse'] ) {
				$url .= '/' . $parsed['verse'] . '-' . $parsed['verse_end'];
			} else {
				$url .= '/' . $parsed['verse'];
			}
		}

		// Link attributes.
		$target = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';

		return sprintf(
			'<a href="%s" class="%s" data-midvash-ref="%s" data-midvash-book="%d"%s title="%s" itemscope itemtype="https://schema.org/Quotation"><span itemprop="name">%s</span></a>',
			esc_url( $url ),
			esc_attr( $css_class ),
			esc_attr( $original ),
			intval( $book_id ),
			$target,
			esc_attr(
				sprintf(
				/* translators: %s: Bible reference */
					__( 'Read %s on Midvash', 'bible-by-midvash' ),
					$original
				)
			),
			esc_html( $original )
		);
	}

	/**
	 * Get books that were referenced in the current post
	 */
	public function get_referenced_books() {
		return $this->referenced_books;
	}

	/**
	 * References linked during this request (unique, in order of appearance).
	 */
	public static function get_collected_refs() {
		return self::$collected_refs;
	}
}
