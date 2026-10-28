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
	 * Initializes the parser
	 */
	public function init() {
		$this->options = get_option(
			'bbm_options',
			array(
				'locale'           => 'pt-br',
				'versao'           => 'onbv',
				'use_custom_color' => false,
				'link_color'       => '#B17027',
				'underline_link'   => false,
				'underline_color'  => '#B17027',
				'underline_style'  => 'solid',
				'new_tab'          => true,
				'css_class'        => 'bbm-link',
				'show_version'     => true,
				'link_biblia'      => false,
			)
		);

		$this->locale = isset( $this->options['locale'] ) ? $this->options['locale'] : 'pt-br';
		$this->locale = BBMV_Books::normalize_locale( $this->locale );

		// Add filter to content.
		add_filter( 'the_content', array( $this, 'parse_content' ), 20 );
	}

	/**
	 * Tags whose text is never linked: existing links, headings, code and
	 * anything that is not prose.
	 */
	const SKIP_TAGS = array( 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'script', 'style', 'code', 'pre', 'kbd', 'textarea', 'button', 'select', 'svg' );

	/**
	 * How many times the word "Bible" is linked in one post.
	 */
	const MAX_BIBLE_WORD_LINKS = 1;

	/**
	 * "Bible" links still allowed in the post being parsed.
	 *
	 * @var int
	 */
	private $bible_links_left = 0;

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

		// Only the post being viewed. Page builders run the_content on other
		// posts too (an Elementor posts widget turns them into excerpts and
		// strips the links), which would load the tooltip for nothing.
		if ( (int) get_the_ID() !== (int) get_queried_object_id() ) {
			return $content;
		}

		return $this->link_content( $content );
	}

	/**
	 * Links references (and optionally the word "Bible") in an HTML string.
	 *
	 * Only text between tags is touched, so attributes such as `src`, `alt`
	 * or `srcset` that happen to contain a book name or "Bíblia" stay intact.
	 *
	 * @param string $content HTML.
	 * @return string HTML with links.
	 */
	public function link_content( $content ) {
		$this->referenced_books = array();

		$regex   = BBMV_Books::get_reference_regex( $this->locale );
		$content = $this->map_text_nodes(
			$content,
			function ( $text ) use ( $regex ) {
				return preg_replace_callback( $regex, array( $this, 'replace_reference' ), $text );
			}
		);

		if ( ! empty( $this->options['link_biblia'] ) ) {
			$this->bible_links_left = self::MAX_BIBLE_WORD_LINKS;
			$content                = $this->map_text_nodes(
				$content,
				function ( $text ) {
					if ( $this->bible_links_left < 1 ) {
						return $text;
					}
					return preg_replace_callback(
						'/(?<![\p{L}\p{N}])(Bíblia|Biblia|Bible)(?![\p{L}\p{N}])/iu',
						array( $this, 'replace_bible_word' ),
						$text,
						$this->bible_links_left
					);
				}
			);
		}

		return $content;
	}

	/**
	 * Runs a callback on every text node outside SKIP_TAGS.
	 *
	 * @param string   $html     HTML.
	 * @param callable $callback Receives and returns a text fragment.
	 * @return string
	 */
	private function map_text_nodes( $html, $callback ) {
		$parts = preg_split( '/(<!--.*?-->|<[^>]*>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( false === $parts ) {
			return $html;
		}

		$open = array();
		foreach ( $parts as $i => $part ) {
			if ( '' === $part ) {
				continue;
			}
			if ( '<' === $part[0] ) {
				if ( preg_match( '/^<(\/?)([a-z][a-z0-9]*)/i', $part, $tag ) ) {
					$name = strtolower( $tag[2] );
					if ( in_array( $name, self::SKIP_TAGS, true ) && '/>' !== substr( $part, -2 ) ) {
						if ( '/' === $tag[1] ) {
							$open[ $name ] = isset( $open[ $name ] ) ? max( 0, $open[ $name ] - 1 ) : 0;
						} else {
							$open[ $name ] = isset( $open[ $name ] ) ? $open[ $name ] + 1 : 1;
						}
					}
				}
				continue;
			}
			if ( array_sum( $open ) > 0 ) {
				continue;
			}
			$parts[ $i ] = call_user_func( $callback, $part );
		}

		return implode( '', $parts );
	}

	/**
	 * Replaces one occurrence of the word "Bible" with a link to Midvash.
	 *
	 * @param array $matches Regex matches; the word is at 1.
	 * @return string
	 */
	public function replace_bible_word( $matches ) {
		--$this->bible_links_left;
		wp_enqueue_style( 'bbm-style' );

		$css_class = isset( $this->options['css_class'] ) ? $this->options['css_class'] : 'bbm-link';
		$new_tab   = isset( $this->options['new_tab'] ) ? $this->options['new_tab'] : true;
		$target    = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';

		return sprintf(
			'<a href="%s" class="%s"%s title="%s">%s</a>',
			esc_url( BBMV_Books::site_url( $this->locale ) ),
			esc_attr( $css_class ),
			$target,
			esc_attr__( 'Online Bible', 'bible-by-midvash' ),
			esc_html( $matches[1] )
		);
	}

	/**
	 * Replaces the reference with a link and data attributes for tooltip
	 *
	 * @param array $matches Matches of BBMV_Books::get_reference_regex(): full match at 0, `abbr` set when the book was abbreviated, `v` the verse.
	 */
	public function replace_reference( $matches ) {
		$original = $matches[0];

		// An abbreviation alone is too often a plain word ("Os 13", "Am 5"):
		// only link it with chapter and verse.
		if ( ! empty( $matches['abbr'] ) && empty( $matches['v'] ) ) {
			return $original;
		}

		// Delegate to the centralized parser — it re-runs the (cheap) regex on
		// the original substring and adds accent fallback + chapter validation
		// against the book's known chapter count. Sharing this path with the
		// API client and the Gutenberg block keeps behaviour aligned.
		$parsed = BBMV_Books::parse_reference( $original, $this->locale );
		if ( ! $parsed ) {
			return $original;
		}

		$book_id = $parsed['book_id'];

		// The tooltip assets only load on posts that have a reference.
		wp_enqueue_style( 'bbm-style' );
		wp_enqueue_script( 'bbm-tooltip' );

		// Track this book as referenced.
		if ( ! in_array( $book_id, $this->referenced_books, true ) ) {
			$this->referenced_books[] = $book_id;
		}

		// Get settings.
		$versao    = isset( $this->options['versao'] ) ? BBMV_Books::resolve_version( $this->options['versao'] ) : BBMV_Books::get_default_version( $this->locale );
		$css_class = isset( $this->options['css_class'] ) ? $this->options['css_class'] : 'bbm-link';
		$new_tab   = isset( $this->options['new_tab'] ) ? $this->options['new_tab'] : true;

		// Get slug for the current locale.
		$book_slug = BBMV_Books::get_book_slug( $book_id, $this->locale );

		// Format: https://midvash.com/{locale}/{version}/{book_slug}/{chapter}#v={verse}
		// (no locale segment in English). The site anchors verses with #v=,
		// so linking straight to it skips a redirect.
		$url = BBMV_Books::site_url( $this->locale ) . '/' . $versao . '/' . $book_slug . '/' . $parsed['chapter'];

		if ( $parsed['verse'] ) {
			if ( $parsed['verse_end'] && $parsed['verse_end'] > $parsed['verse'] ) {
				$url .= '#v=' . $parsed['verse'] . '-' . $parsed['verse_end'];
			} else {
				$url .= '#v=' . $parsed['verse'];
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
}
