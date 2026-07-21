<?php
/**
 * Bible Books Data
 *
 * Centralized book definitions with multilingual support.
 * Based on api-publica/src/books.ts
 *
 * @package Bible_By_Midvash
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static catalog of Bible books with multilingual names, slugs and abbreviations.
 */
class BBMV_Books {

	/**
	 * Supported locales
	 */
	const LOCALES = array( 'en', 'pt-br', 'es', 'fr', 'de', 'it', 'ru', 'ko', 'zh' );

	/**
	 * Default versions per locale
	 * Generated from api.midvash.com/versions by scripts/sync-books.ts.
	 */
	const DEFAULT_VERSIONS = array(
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Sync-script marker, not commented-out code.
		// {{SYNCED_VERSIONS_START}}
		'en'    => 'nlt',
		'pt-br' => 'nvt',
		'es'    => 'ntv',
		'fr'    => 'lsg',
		'de'    => 'luth1912',
		'it'    => 'nri',
		'ru'    => 'synodal',
		'ko'    => 'kor',
		'zh'    => 'cuv',
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Sync-script marker, not commented-out code.
		// {{SYNCED_VERSIONS_END}}
	);

	/**
	 * Complete book definitions with multilingual support
	 * Each book has: id, chapters, testament, slugs (by locale), names (by locale), abbrev (by locale)
	 *
	 * @var array|null
	 */
	private static $books = null;

	/**
	 * Get all books
	 */
	public static function get_books() {
		if ( null === self::$books ) {
			self::$books = self::init_books();
		}
		return self::$books;
	}

	/**
	 * Get book by ID
	 *
	 * @param int $id Numeric book ID (1-66).
	 */
	public static function get_book_by_id( $id ) {
		$books = self::get_books();
		return isset( $books[ $id ] ) ? $books[ $id ] : null;
	}

	/**
	 * Get book by slug (searches all locales)
	 *
	 * @param string $slug URL slug of the book (e.g. 'matthew', 'mateus').
	 */
	public static function get_book_by_slug( $slug ) {
		$normalized = strtolower( trim( $slug ) );
		$books      = self::get_books();

		foreach ( $books as $book ) {
			foreach ( self::LOCALES as $locale ) {
				if ( isset( $book['slugs'][ $locale ] ) && $book['slugs'][ $locale ] === $normalized ) {
					return $book;
				}
			}
		}

		return null;
	}

	/**
	 * Get book by name or abbreviation (searches all locales)
	 *
	 * @param string $name Book name or abbreviation in any supported locale.
	 */
	public static function get_book_by_name( $name ) {
		$normalized = self::lower( trim( $name ) );
		$books      = self::get_books();

		foreach ( $books as $book ) {
			foreach ( self::LOCALES as $locale ) {
				if ( isset( $book['names'][ $locale ] ) && self::lower( $book['names'][ $locale ] ) === $normalized ) {
					return $book;
				}
			}
			foreach ( self::LOCALES as $locale ) {
				if ( isset( $book['abbrev'][ $locale ] ) && self::lower( $book['abbrev'][ $locale ] ) === $normalized ) {
					return $book;
				}
			}
		}

		return null;
	}

	/**
	 * Get slug for book in specific locale
	 *
	 * @param int    $book_id Numeric book ID (1-66).
	 * @param string $locale  Target locale code (defaults to 'en').
	 */
	public static function get_book_slug( $book_id, $locale = 'en' ) {
		$book = self::get_book_by_id( $book_id );
		if ( ! $book ) {
			return null;
		}
		$locale = self::normalize_locale( $locale );
		return isset( $book['slugs'][ $locale ] ) ? $book['slugs'][ $locale ] : $book['slugs']['en'];
	}

	/**
	 * Get name for book in specific locale
	 *
	 * @param int    $book_id Numeric book ID (1-66).
	 * @param string $locale  Target locale code (defaults to 'en').
	 */
	public static function get_book_name( $book_id, $locale = 'en' ) {
		$book = self::get_book_by_id( $book_id );
		if ( ! $book ) {
			return null;
		}
		$locale = self::normalize_locale( $locale );
		return isset( $book['names'][ $locale ] ) ? $book['names'][ $locale ] : $book['names']['en'];
	}

	/**
	 * Normalize locale string
	 *
	 * Maps WordPress locale codes (and other common variants) to the short
	 * locale codes we use internally (see self::LOCALES). Unknown inputs
	 * fall back to 'en'.
	 *
	 * @param string $locale Raw locale string (e.g. 'pt_BR', 'en-US').
	 */
	public static function normalize_locale( $locale ) {
		$locale = strtolower( trim( $locale ) );
		// Treat underscores and hyphens equivalently.
		$normalized = str_replace( '_', '-', $locale );

		$map = array(
			'pt'    => 'pt-br',
			'pt-br' => 'pt-br',
			'en'    => 'en',
			'en-us' => 'en',
			'en-gb' => 'en',
			'es'    => 'es',
			'es-es' => 'es',
			'es-mx' => 'es',
			'fr'    => 'fr',
			'fr-fr' => 'fr',
			'fr-ca' => 'fr',
			'de'    => 'de',
			'de-de' => 'de',
			'de-at' => 'de',
			'de-ch' => 'de',
			'it'    => 'it',
			'it-it' => 'it',
			'ru'    => 'ru',
			'ru-ru' => 'ru',
			'ko'    => 'ko',
			'ko-kr' => 'ko',
			'zh'    => 'zh',
			'zh-cn' => 'zh',
			'zh-tw' => 'zh',
			'zh-hk' => 'zh',
		);

		if ( isset( $map[ $normalized ] ) ) {
			return $map[ $normalized ];
		}
		if ( in_array( $normalized, self::LOCALES, true ) ) {
			return $normalized;
		}
		return 'en';
	}

	/**
	 * Get default version for locale
	 *
	 * @param string $locale Locale code to look up (normalized internally).
	 */
	public static function get_default_version( $locale ) {
		$locale = self::normalize_locale( $locale );
		return isset( self::DEFAULT_VERSIONS[ $locale ] ) ? self::DEFAULT_VERSIONS[ $locale ] : 'nlt';
	}

	/**
	 * Build pattern for regex matching (all names and abbreviations).
	 *
	 * Memoized per-locale: the pattern is built once per request, then served
	 * from a static cache. With 66 books × 9 locales × 2 fields, the original
	 * implementation rebuilt a ~1.2k-element string on every `the_content`
	 * filter call — wasteful for pages that render multiple post bodies
	 * (related posts, sticky posts, archives in custom themes…).
	 *
	 * @param string|null $locale Locale to restrict the pattern to, or null for all locales.
	 */
	public static function get_matching_pattern( $locale = null ) {
		static $cache = array();

		$key = $locale ? self::normalize_locale( $locale ) : '__all__';
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}

		$books            = self::get_books();
		$patterns         = array();
		$locales_to_check = $locale ? array( self::normalize_locale( $locale ) ) : self::LOCALES;

		foreach ( $books as $book ) {
			foreach ( $locales_to_check as $loc ) {
				if ( isset( $book['names'][ $loc ] ) ) {
					$patterns[] = preg_quote( $book['names'][ $loc ], '/' );
				}
				if ( isset( $book['abbrev'][ $loc ] ) ) {
					$patterns[] = preg_quote( $book['abbrev'][ $loc ], '/' );
				}
			}
		}

		// Sort by length descending to match longer names first.
		usort(
			$patterns,
			function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);

		$cache[ $key ] = implode( '|', array_unique( $patterns ) );
		return $cache[ $key ];
	}

	/**
	 * Lowercase a string, preferring the multibyte function when available.
	 * Some shared hosts ship PHP without `mbstring` enabled; we fall back to
	 * `strtolower()` which still works for the ASCII portion of book names.
	 *
	 * @param string $str String to lowercase.
	 */
	public static function lower( $str ) {
		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( $str );
		}
		return strtolower( $str );
	}

	/**
	 * Removes Latin accents from a string for tolerant book matching.
	 * Used as a second lookup pass when the verbatim lower-cased input misses
	 * (e.g. "Joao 3:16" → "joao" → match against "joão").
	 *
	 * @param string $str String to strip accents from.
	 */
	public static function strip_accents( $str ) {
		static $map = array(
			'á' => 'a',
			'à' => 'a',
			'ã' => 'a',
			'â' => 'a',
			'ä' => 'a',
			'é' => 'e',
			'è' => 'e',
			'ê' => 'e',
			'ë' => 'e',
			'í' => 'i',
			'ì' => 'i',
			'î' => 'i',
			'ï' => 'i',
			'ó' => 'o',
			'ò' => 'o',
			'õ' => 'o',
			'ô' => 'o',
			'ö' => 'o',
			'ú' => 'u',
			'ù' => 'u',
			'û' => 'u',
			'ü' => 'u',
			'ñ' => 'n',
			'ç' => 'c',
			'Á' => 'A',
			'À' => 'A',
			'Ã' => 'A',
			'Â' => 'A',
			'Ä' => 'A',
			'É' => 'E',
			'È' => 'E',
			'Ê' => 'E',
			'Ë' => 'E',
			'Í' => 'I',
			'Ì' => 'I',
			'Î' => 'I',
			'Ï' => 'I',
			'Ó' => 'O',
			'Ò' => 'O',
			'Õ' => 'O',
			'Ô' => 'O',
			'Ö' => 'O',
			'Ú' => 'U',
			'Ù' => 'U',
			'Û' => 'U',
			'Ü' => 'U',
			'Ñ' => 'N',
			'Ç' => 'C',
		);
		return strtr( $str, $map );
	}

	/**
	 * Centralized reference parser used by BBMV_Parser, BBMV_API and BBMV_Block.
	 *
	 * Accepts shapes like:
	 *   "John 3"            → entire chapter
	 *   "John 3:16"         → single verse
	 *   "John 3.16"         → alternative separator
	 *   "John 3:16-18"      → verse range
	 *
	 * Returns null when the book name cannot be resolved against any locale
	 * (with and without accents), or when the chapter number exceeds the
	 * book's total chapters.
	 *
	 * @param string $reference Raw reference text.
	 * @return array|null {book_id, book, chapter, verse, verse_end}
	 */
	public static function parse_reference( $reference ) {
		$reference = trim( $reference );
		if ( '' === $reference ) {
			return null;
		}
		if ( ! preg_match( '/^(.+?)\s+(\d{1,3})(?:[:\.](\d{1,3}))?(?:\s*[-–]\s*(\d{1,3}))?$/iu', $reference, $m ) ) {
			return null;
		}

		$book_input = self::lower( trim( $m[1] ) );
		$chapter    = intval( $m[2] );
		$verse      = ( isset( $m[3] ) && '' !== $m[3] ) ? intval( $m[3] ) : null;
		$verse_end  = ( isset( $m[4] ) && '' !== $m[4] ) ? intval( $m[4] ) : null;

		$lookup = self::get_lookup_table();
		if ( isset( $lookup[ $book_input ] ) ) {
			$book_id = $lookup[ $book_input ];
		} else {
			$book_no_accent = self::strip_accents( $book_input );
			if ( ! isset( $lookup[ $book_no_accent ] ) ) {
				return null;
			}
			$book_id = $lookup[ $book_no_accent ];
		}

		$book = self::get_book_by_id( $book_id );
		if ( ! $book ) {
			return null;
		}
		if ( $chapter < 1 || $chapter > $book['chapters'] ) {
			return null;
		}

		return array(
			'book_id'   => $book_id,
			'book'      => $book,
			'chapter'   => $chapter,
			'verse'     => $verse,
			'verse_end' => $verse_end,
		);
	}

	/**
	 * Initialize book data
	 * Generated from api.midvash.com/books by scripts/sync-books.ts.
	 * Do not edit the array between the markers below — regenerate with
	 * `npx tsx scripts/sync-books.ts` instead.
	 */
	private static function init_books() {
		return array(
			// {{SYNCED_BOOKS_START}}
			// Old Testament
			1  => array(
				'id'        => 1,
				'chapters'  => 50,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'genesis',
					'pt-br' => 'genesis',
					'es'    => 'genesis',
					'fr'    => 'genese',
					'de'    => 'genesis',
					'it'    => 'genesi',
					'ru'    => 'бытие',
					'ko'    => '창세기',
					'zh'    => '创世记',
				),
				'names'     => array(
					'en'    => 'Genesis',
					'pt-br' => 'Gênesis',
					'es'    => 'Génesis',
					'fr'    => 'Genèse',
					'de'    => 'Genesis',
					'it'    => 'Genesi',
					'ru'    => 'Бытие',
					'ko'    => '창세기',
					'zh'    => '创世记',
				),
				'abbrev'    => array(
					'en'    => 'Gen',
					'pt-br' => 'Gn',
					'es'    => 'Gén',
					'fr'    => 'Gn',
					'de'    => 'Gen',
					'it'    => 'Gen',
					'ru'    => 'Быт',
					'ko'    => '창',
					'zh'    => '创',
				),
			),
			2  => array(
				'id'        => 2,
				'chapters'  => 40,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'exodus',
					'pt-br' => 'exodo',
					'es'    => 'exodo',
					'fr'    => 'exode',
					'de'    => 'exodus',
					'it'    => 'esodo',
					'ru'    => 'исход',
					'ko'    => '출애굽기',
					'zh'    => '出埃及记',
				),
				'names'     => array(
					'en'    => 'Exodus',
					'pt-br' => 'Êxodo',
					'es'    => 'Éxodo',
					'fr'    => 'Exode',
					'de'    => 'Exodus',
					'it'    => 'Esodo',
					'ru'    => 'Исход',
					'ko'    => '출애굽기',
					'zh'    => '出埃及记',
				),
				'abbrev'    => array(
					'en'    => 'Exo',
					'pt-br' => 'Êx',
					'es'    => 'Éx',
					'fr'    => 'Ex',
					'de'    => 'Ex',
					'it'    => 'Es',
					'ru'    => 'Исх',
					'ko'    => '출',
					'zh'    => '出',
				),
			),
			3  => array(
				'id'        => 3,
				'chapters'  => 27,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'leviticus',
					'pt-br' => 'levitico',
					'es'    => 'levitico',
					'fr'    => 'levitique',
					'de'    => 'levitikus',
					'it'    => 'levitico',
					'ru'    => 'левит',
					'ko'    => '레위기',
					'zh'    => '利未记',
				),
				'names'     => array(
					'en'    => 'Leviticus',
					'pt-br' => 'Levítico',
					'es'    => 'Levítico',
					'fr'    => 'Lévitique',
					'de'    => 'Levitikus',
					'it'    => 'Levitico',
					'ru'    => 'Левит',
					'ko'    => '레위기',
					'zh'    => '利未记',
				),
				'abbrev'    => array(
					'en'    => 'Lev',
					'pt-br' => 'Lv',
					'es'    => 'Lv',
					'fr'    => 'Lv',
					'de'    => 'Lev',
					'it'    => 'Lv',
					'ru'    => 'Лев',
					'ko'    => '레',
					'zh'    => '利',
				),
			),
			4  => array(
				'id'        => 4,
				'chapters'  => 36,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'numbers',
					'pt-br' => 'numeros',
					'es'    => 'numeros',
					'fr'    => 'nombres',
					'de'    => 'numeri',
					'it'    => 'numeri',
					'ru'    => 'числа',
					'ko'    => '민수기',
					'zh'    => '民数记',
				),
				'names'     => array(
					'en'    => 'Numbers',
					'pt-br' => 'Números',
					'es'    => 'Números',
					'fr'    => 'Nombres',
					'de'    => 'Numeri',
					'it'    => 'Numeri',
					'ru'    => 'Числа',
					'ko'    => '민수기',
					'zh'    => '民数记',
				),
				'abbrev'    => array(
					'en'    => 'Num',
					'pt-br' => 'Nm',
					'es'    => 'Núm',
					'fr'    => 'Nb',
					'de'    => 'Num',
					'it'    => 'Nm',
					'ru'    => 'Чис',
					'ko'    => '민',
					'zh'    => '民',
				),
			),
			5  => array(
				'id'        => 5,
				'chapters'  => 34,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'deuteronomy',
					'pt-br' => 'deuteronomio',
					'es'    => 'deuteronomio',
					'fr'    => 'deuteronome',
					'de'    => 'deuteronomium',
					'it'    => 'deuteronomio',
					'ru'    => 'второзаконие',
					'ko'    => '신명기',
					'zh'    => '申命记',
				),
				'names'     => array(
					'en'    => 'Deuteronomy',
					'pt-br' => 'Deuteronômio',
					'es'    => 'Deuteronomio',
					'fr'    => 'Deutéronome',
					'de'    => 'Deuteronomium',
					'it'    => 'Deuteronomio',
					'ru'    => 'Второзаконие',
					'ko'    => '신명기',
					'zh'    => '申命记',
				),
				'abbrev'    => array(
					'en'    => 'Deu',
					'pt-br' => 'Dt',
					'es'    => 'Dt',
					'fr'    => 'Dt',
					'de'    => 'Dtn',
					'it'    => 'Dt',
					'ru'    => 'Втор',
					'ko'    => '신',
					'zh'    => '申',
				),
			),
			6  => array(
				'id'        => 6,
				'chapters'  => 24,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'joshua',
					'pt-br' => 'josue',
					'es'    => 'josue',
					'fr'    => 'josue',
					'de'    => 'josua',
					'it'    => 'giosue',
					'ru'    => 'иисус-навин',
					'ko'    => '여호수아',
					'zh'    => '约书亚记',
				),
				'names'     => array(
					'en'    => 'Joshua',
					'pt-br' => 'Josué',
					'es'    => 'Josué',
					'fr'    => 'Josué',
					'de'    => 'Josua',
					'it'    => 'Giosuè',
					'ru'    => 'Иисус Навин',
					'ko'    => '여호수아',
					'zh'    => '约书亚记',
				),
				'abbrev'    => array(
					'en'    => 'Jos',
					'pt-br' => 'Js',
					'es'    => 'Jos',
					'fr'    => 'Jos',
					'de'    => 'Jos',
					'it'    => 'Gs',
					'ru'    => 'Нав',
					'ko'    => '수',
					'zh'    => '书',
				),
			),
			7  => array(
				'id'        => 7,
				'chapters'  => 21,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'judges',
					'pt-br' => 'juizes',
					'es'    => 'jueces',
					'fr'    => 'juges',
					'de'    => 'richter',
					'it'    => 'giudici',
					'ru'    => 'судьи',
					'ko'    => '사사기',
					'zh'    => '士师记',
				),
				'names'     => array(
					'en'    => 'Judges',
					'pt-br' => 'Juízes',
					'es'    => 'Jueces',
					'fr'    => 'Juges',
					'de'    => 'Richter',
					'it'    => 'Giudici',
					'ru'    => 'Судьи',
					'ko'    => '사사기',
					'zh'    => '士师记',
				),
				'abbrev'    => array(
					'en'    => 'Jdg',
					'pt-br' => 'Jz',
					'es'    => 'Jue',
					'fr'    => 'Jg',
					'de'    => 'Ri',
					'it'    => 'Gdc',
					'ru'    => 'Суд',
					'ko'    => '삿',
					'zh'    => '士',
				),
			),
			8  => array(
				'id'        => 8,
				'chapters'  => 4,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'ruth',
					'pt-br' => 'rute',
					'es'    => 'rut',
					'fr'    => 'ruth',
					'de'    => 'rut',
					'it'    => 'rut',
					'ru'    => 'руфь',
					'ko'    => '룻기',
					'zh'    => '路得记',
				),
				'names'     => array(
					'en'    => 'Ruth',
					'pt-br' => 'Rute',
					'es'    => 'Rut',
					'fr'    => 'Ruth',
					'de'    => 'Rut',
					'it'    => 'Rut',
					'ru'    => 'Руфь',
					'ko'    => '룻기',
					'zh'    => '路得记',
				),
				'abbrev'    => array(
					'en'    => 'Rut',
					'pt-br' => 'Rt',
					'es'    => 'Rut',
					'fr'    => 'Rt',
					'de'    => 'Rut',
					'it'    => 'Rt',
					'ru'    => 'Руф',
					'ko'    => '룻',
					'zh'    => '得',
				),
			),
			9  => array(
				'id'        => 9,
				'chapters'  => 31,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => '1-samuel',
					'pt-br' => '1-samuel',
					'es'    => '1-samuel',
					'fr'    => '1-samuel',
					'de'    => '1-samuel',
					'it'    => '1-samuele',
					'ru'    => '1-царств',
					'ko'    => '사무엘상',
					'zh'    => '撒母耳记上',
				),
				'names'     => array(
					'en'    => '1 Samuel',
					'pt-br' => '1 Samuel',
					'es'    => '1 Samuel',
					'fr'    => '1 Samuel',
					'de'    => '1 Samuel',
					'it'    => '1 Samuele',
					'ru'    => '1 Царств',
					'ko'    => '사무엘상',
					'zh'    => '撒母耳记上',
				),
				'abbrev'    => array(
					'en'    => '1Sa',
					'pt-br' => '1Sm',
					'es'    => '1Sa',
					'fr'    => '1S',
					'de'    => '1Sam',
					'it'    => '1Sam',
					'ru'    => '1Цар',
					'ko'    => '삼상',
					'zh'    => '撒上',
				),
			),
			10 => array(
				'id'        => 10,
				'chapters'  => 24,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => '2-samuel',
					'pt-br' => '2-samuel',
					'es'    => '2-samuel',
					'fr'    => '2-samuel',
					'de'    => '2-samuel',
					'it'    => '2-samuele',
					'ru'    => '2-царств',
					'ko'    => '사무엘하',
					'zh'    => '撒母耳记下',
				),
				'names'     => array(
					'en'    => '2 Samuel',
					'pt-br' => '2 Samuel',
					'es'    => '2 Samuel',
					'fr'    => '2 Samuel',
					'de'    => '2 Samuel',
					'it'    => '2 Samuele',
					'ru'    => '2 Царств',
					'ko'    => '사무엘하',
					'zh'    => '撒母耳记下',
				),
				'abbrev'    => array(
					'en'    => '2Sa',
					'pt-br' => '2Sm',
					'es'    => '2Sa',
					'fr'    => '2S',
					'de'    => '2Sam',
					'it'    => '2Sam',
					'ru'    => '2Цар',
					'ko'    => '삼하',
					'zh'    => '撒下',
				),
			),
			11 => array(
				'id'        => 11,
				'chapters'  => 22,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => '1-kings',
					'pt-br' => '1-reis',
					'es'    => '1-reyes',
					'fr'    => '1-rois',
					'de'    => '1-koenige',
					'it'    => '1-re',
					'ru'    => '3-царств',
					'ko'    => '열왕기상',
					'zh'    => '列王纪上',
				),
				'names'     => array(
					'en'    => '1 Kings',
					'pt-br' => '1 Reis',
					'es'    => '1 Reyes',
					'fr'    => '1 Rois',
					'de'    => '1 Könige',
					'it'    => '1 Re',
					'ru'    => '3 Царств',
					'ko'    => '열왕기상',
					'zh'    => '列王纪上',
				),
				'abbrev'    => array(
					'en'    => '1Ki',
					'pt-br' => '1Rs',
					'es'    => '1Re',
					'fr'    => '1R',
					'de'    => '1Kön',
					'it'    => '1Re',
					'ru'    => '3Цар',
					'ko'    => '왕상',
					'zh'    => '王上',
				),
			),
			12 => array(
				'id'        => 12,
				'chapters'  => 25,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => '2-kings',
					'pt-br' => '2-reis',
					'es'    => '2-reyes',
					'fr'    => '2-rois',
					'de'    => '2-koenige',
					'it'    => '2-re',
					'ru'    => '4-царств',
					'ko'    => '열왕기하',
					'zh'    => '列王纪下',
				),
				'names'     => array(
					'en'    => '2 Kings',
					'pt-br' => '2 Reis',
					'es'    => '2 Reyes',
					'fr'    => '2 Rois',
					'de'    => '2 Könige',
					'it'    => '2 Re',
					'ru'    => '4 Царств',
					'ko'    => '열왕기하',
					'zh'    => '列王纪下',
				),
				'abbrev'    => array(
					'en'    => '2Ki',
					'pt-br' => '2Rs',
					'es'    => '2Re',
					'fr'    => '2R',
					'de'    => '2Kön',
					'it'    => '2Re',
					'ru'    => '4Цар',
					'ko'    => '왕하',
					'zh'    => '王下',
				),
			),
			13 => array(
				'id'        => 13,
				'chapters'  => 29,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => '1-chronicles',
					'pt-br' => '1-cronicas',
					'es'    => '1-cronicas',
					'fr'    => '1-chroniques',
					'de'    => '1-chronik',
					'it'    => '1-cronache',
					'ru'    => '1-паралипоменон',
					'ko'    => '역대상',
					'zh'    => '历代志上',
				),
				'names'     => array(
					'en'    => '1 Chronicles',
					'pt-br' => '1 Crônicas',
					'es'    => '1 Crónicas',
					'fr'    => '1 Chroniques',
					'de'    => '1 Chronik',
					'it'    => '1 Cronache',
					'ru'    => '1 Паралипоменон',
					'ko'    => '역대상',
					'zh'    => '历代志上',
				),
				'abbrev'    => array(
					'en'    => '1Ch',
					'pt-br' => '1Cr',
					'es'    => '1Cr',
					'fr'    => '1Ch',
					'de'    => '1Chr',
					'it'    => '1Cr',
					'ru'    => '1Пар',
					'ko'    => '대상',
					'zh'    => '代上',
				),
			),
			14 => array(
				'id'        => 14,
				'chapters'  => 36,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => '2-chronicles',
					'pt-br' => '2-cronicas',
					'es'    => '2-cronicas',
					'fr'    => '2-chroniques',
					'de'    => '2-chronik',
					'it'    => '2-cronache',
					'ru'    => '2-паралипоменон',
					'ko'    => '역대하',
					'zh'    => '历代志下',
				),
				'names'     => array(
					'en'    => '2 Chronicles',
					'pt-br' => '2 Crônicas',
					'es'    => '2 Crónicas',
					'fr'    => '2 Chroniques',
					'de'    => '2 Chronik',
					'it'    => '2 Cronache',
					'ru'    => '2 Паралипоменон',
					'ko'    => '역대하',
					'zh'    => '历代志下',
				),
				'abbrev'    => array(
					'en'    => '2Ch',
					'pt-br' => '2Cr',
					'es'    => '2Cr',
					'fr'    => '2Ch',
					'de'    => '2Chr',
					'it'    => '2Cr',
					'ru'    => '2Пар',
					'ko'    => '대하',
					'zh'    => '代下',
				),
			),
			15 => array(
				'id'        => 15,
				'chapters'  => 10,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'ezra',
					'pt-br' => 'esdras',
					'es'    => 'esdras',
					'fr'    => 'esdras',
					'de'    => 'esra',
					'it'    => 'esdra',
					'ru'    => 'ездра',
					'ko'    => '에스라',
					'zh'    => '以斯拉记',
				),
				'names'     => array(
					'en'    => 'Ezra',
					'pt-br' => 'Esdras',
					'es'    => 'Esdras',
					'fr'    => 'Esdras',
					'de'    => 'Esra',
					'it'    => 'Esdra',
					'ru'    => 'Ездра',
					'ko'    => '에스라',
					'zh'    => '以斯拉记',
				),
				'abbrev'    => array(
					'en'    => 'Ezr',
					'pt-br' => 'Ed',
					'es'    => 'Esd',
					'fr'    => 'Esd',
					'de'    => 'Esr',
					'it'    => 'Esd',
					'ru'    => 'Езд',
					'ko'    => '스',
					'zh'    => '拉',
				),
			),
			16 => array(
				'id'        => 16,
				'chapters'  => 13,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'nehemiah',
					'pt-br' => 'neemias',
					'es'    => 'nehemias',
					'fr'    => 'nehemie',
					'de'    => 'nehemia',
					'it'    => 'neemia',
					'ru'    => 'неемия',
					'ko'    => '느헤미야',
					'zh'    => '尼希米记',
				),
				'names'     => array(
					'en'    => 'Nehemiah',
					'pt-br' => 'Neemias',
					'es'    => 'Nehemías',
					'fr'    => 'Néhémie',
					'de'    => 'Nehemia',
					'it'    => 'Neemia',
					'ru'    => 'Неемия',
					'ko'    => '느헤미야',
					'zh'    => '尼希米记',
				),
				'abbrev'    => array(
					'en'    => 'Neh',
					'pt-br' => 'Ne',
					'es'    => 'Neh',
					'fr'    => 'Né',
					'de'    => 'Neh',
					'it'    => 'Ne',
					'ru'    => 'Неем',
					'ko'    => '느',
					'zh'    => '尼',
				),
			),
			17 => array(
				'id'        => 17,
				'chapters'  => 10,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'esther',
					'pt-br' => 'ester',
					'es'    => 'ester',
					'fr'    => 'esther',
					'de'    => 'ester',
					'it'    => 'ester',
					'ru'    => 'есфирь',
					'ko'    => '에스더',
					'zh'    => '以斯帖记',
				),
				'names'     => array(
					'en'    => 'Esther',
					'pt-br' => 'Ester',
					'es'    => 'Ester',
					'fr'    => 'Esther',
					'de'    => 'Ester',
					'it'    => 'Ester',
					'ru'    => 'Есфирь',
					'ko'    => '에스더',
					'zh'    => '以斯帖记',
				),
				'abbrev'    => array(
					'en'    => 'Est',
					'pt-br' => 'Et',
					'es'    => 'Est',
					'fr'    => 'Est',
					'de'    => 'Est',
					'it'    => 'Est',
					'ru'    => 'Есф',
					'ko'    => '에',
					'zh'    => '斯',
				),
			),
			18 => array(
				'id'        => 18,
				'chapters'  => 42,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'job',
					'pt-br' => 'jo',
					'es'    => 'job',
					'fr'    => 'job',
					'de'    => 'hiob',
					'it'    => 'giobbe',
					'ru'    => 'иов',
					'ko'    => '욥기',
					'zh'    => '约伯记',
				),
				'names'     => array(
					'en'    => 'Job',
					'pt-br' => 'Jó',
					'es'    => 'Job',
					'fr'    => 'Job',
					'de'    => 'Hiob',
					'it'    => 'Giobbe',
					'ru'    => 'Иов',
					'ko'    => '욥기',
					'zh'    => '约伯记',
				),
				'abbrev'    => array(
					'en'    => 'Job',
					'pt-br' => 'Jó',
					'es'    => 'Job',
					'fr'    => 'Jb',
					'de'    => 'Hi',
					'it'    => 'Gb',
					'ru'    => 'Иов',
					'ko'    => '욥',
					'zh'    => '伯',
				),
			),
			19 => array(
				'id'        => 19,
				'chapters'  => 150,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'psalms',
					'pt-br' => 'salmos',
					'es'    => 'salmos',
					'fr'    => 'psaumes',
					'de'    => 'psalmen',
					'it'    => 'salmi',
					'ru'    => 'псалтирь',
					'ko'    => '시편',
					'zh'    => '诗篇',
				),
				'names'     => array(
					'en'    => 'Psalms',
					'pt-br' => 'Salmos',
					'es'    => 'Salmos',
					'fr'    => 'Psaumes',
					'de'    => 'Psalmen',
					'it'    => 'Salmi',
					'ru'    => 'Псалтирь',
					'ko'    => '시편',
					'zh'    => '诗篇',
				),
				'abbrev'    => array(
					'en'    => 'Psa',
					'pt-br' => 'Sl',
					'es'    => 'Sal',
					'fr'    => 'Ps',
					'de'    => 'Ps',
					'it'    => 'Sal',
					'ru'    => 'Пс',
					'ko'    => '시',
					'zh'    => '诗',
				),
			),
			20 => array(
				'id'        => 20,
				'chapters'  => 31,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'proverbs',
					'pt-br' => 'proverbios',
					'es'    => 'proverbios',
					'fr'    => 'proverbes',
					'de'    => 'sprueche',
					'it'    => 'proverbi',
					'ru'    => 'притчи',
					'ko'    => '잠언',
					'zh'    => '箴言',
				),
				'names'     => array(
					'en'    => 'Proverbs',
					'pt-br' => 'Provérbios',
					'es'    => 'Proverbios',
					'fr'    => 'Proverbes',
					'de'    => 'Sprüche',
					'it'    => 'Proverbi',
					'ru'    => 'Притчи',
					'ko'    => '잠언',
					'zh'    => '箴言',
				),
				'abbrev'    => array(
					'en'    => 'Pro',
					'pt-br' => 'Pv',
					'es'    => 'Pr',
					'fr'    => 'Pr',
					'de'    => 'Spr',
					'it'    => 'Pr',
					'ru'    => 'Прит',
					'ko'    => '잠',
					'zh'    => '箴',
				),
			),
			21 => array(
				'id'        => 21,
				'chapters'  => 12,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'ecclesiastes',
					'pt-br' => 'eclesiastes',
					'es'    => 'eclesiastes',
					'fr'    => 'ecclesiaste',
					'de'    => 'prediger',
					'it'    => 'ecclesiaste',
					'ru'    => 'екклесиаст',
					'ko'    => '전도서',
					'zh'    => '传道书',
				),
				'names'     => array(
					'en'    => 'Ecclesiastes',
					'pt-br' => 'Eclesiastes',
					'es'    => 'Eclesiastés',
					'fr'    => 'Ecclésiaste',
					'de'    => 'Prediger',
					'it'    => 'Ecclesiaste',
					'ru'    => 'Екклесиаст',
					'ko'    => '전도서',
					'zh'    => '传道书',
				),
				'abbrev'    => array(
					'en'    => 'Ecc',
					'pt-br' => 'Ec',
					'es'    => 'Ecl',
					'fr'    => 'Ec',
					'de'    => 'Pred',
					'it'    => 'Qo',
					'ru'    => 'Еккл',
					'ko'    => '전',
					'zh'    => '传',
				),
			),
			22 => array(
				'id'        => 22,
				'chapters'  => 8,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'song-of-solomon',
					'pt-br' => 'canticos',
					'es'    => 'cantares',
					'fr'    => 'cantique-des-cantiques',
					'de'    => 'hohelied',
					'it'    => 'cantico-dei-cantici',
					'ru'    => 'песнь-песней',
					'ko'    => '아가',
					'zh'    => '雅歌',
				),
				'names'     => array(
					'en'    => 'Song of Solomon',
					'pt-br' => 'Cânticos',
					'es'    => 'Cantares',
					'fr'    => 'Cantique des Cantiques',
					'de'    => 'Hohelied',
					'it'    => 'Cantico dei Cantici',
					'ru'    => 'Песнь Песней',
					'ko'    => '아가',
					'zh'    => '雅歌',
				),
				'abbrev'    => array(
					'en'    => 'Sng',
					'pt-br' => 'Ct',
					'es'    => 'Cnt',
					'fr'    => 'Ct',
					'de'    => 'Hld',
					'it'    => 'Ct',
					'ru'    => 'Песн',
					'ko'    => '아',
					'zh'    => '歌',
				),
			),
			23 => array(
				'id'        => 23,
				'chapters'  => 66,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'isaiah',
					'pt-br' => 'isaias',
					'es'    => 'isaias',
					'fr'    => 'esaie',
					'de'    => 'jesaja',
					'it'    => 'isaia',
					'ru'    => 'исаия',
					'ko'    => '이사야',
					'zh'    => '以赛亚书',
				),
				'names'     => array(
					'en'    => 'Isaiah',
					'pt-br' => 'Isaías',
					'es'    => 'Isaías',
					'fr'    => 'Ésaïe',
					'de'    => 'Jesaja',
					'it'    => 'Isaia',
					'ru'    => 'Исаия',
					'ko'    => '이사야',
					'zh'    => '以赛亚书',
				),
				'abbrev'    => array(
					'en'    => 'Isa',
					'pt-br' => 'Is',
					'es'    => 'Is',
					'fr'    => 'És',
					'de'    => 'Jes',
					'it'    => 'Is',
					'ru'    => 'Ис',
					'ko'    => '사',
					'zh'    => '赛',
				),
			),
			24 => array(
				'id'        => 24,
				'chapters'  => 52,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'jeremiah',
					'pt-br' => 'jeremias',
					'es'    => 'jeremias',
					'fr'    => 'jeremie',
					'de'    => 'jeremia',
					'it'    => 'geremia',
					'ru'    => 'иеремия',
					'ko'    => '예레미야',
					'zh'    => '耶利米书',
				),
				'names'     => array(
					'en'    => 'Jeremiah',
					'pt-br' => 'Jeremias',
					'es'    => 'Jeremías',
					'fr'    => 'Jérémie',
					'de'    => 'Jeremia',
					'it'    => 'Geremia',
					'ru'    => 'Иеремия',
					'ko'    => '예레미야',
					'zh'    => '耶利米书',
				),
				'abbrev'    => array(
					'en'    => 'Jer',
					'pt-br' => 'Jr',
					'es'    => 'Jer',
					'fr'    => 'Jr',
					'de'    => 'Jer',
					'it'    => 'Ger',
					'ru'    => 'Иер',
					'ko'    => '렘',
					'zh'    => '耶',
				),
			),
			25 => array(
				'id'        => 25,
				'chapters'  => 5,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'lamentations',
					'pt-br' => 'lamentacoes',
					'es'    => 'lamentaciones',
					'fr'    => 'lamentations',
					'de'    => 'klagelieder',
					'it'    => 'lamentazioni',
					'ru'    => 'плач-иеремии',
					'ko'    => '예레미야애가',
					'zh'    => '耶利米哀歌',
				),
				'names'     => array(
					'en'    => 'Lamentations',
					'pt-br' => 'Lamentações',
					'es'    => 'Lamentaciones',
					'fr'    => 'Lamentations',
					'de'    => 'Klagelieder',
					'it'    => 'Lamentazioni',
					'ru'    => 'Плач Иеремии',
					'ko'    => '예레미야애가',
					'zh'    => '耶利米哀歌',
				),
				'abbrev'    => array(
					'en'    => 'Lam',
					'pt-br' => 'Lm',
					'es'    => 'Lam',
					'fr'    => 'Lm',
					'de'    => 'Klgl',
					'it'    => 'Lam',
					'ru'    => 'Плач',
					'ko'    => '애',
					'zh'    => '哀',
				),
			),
			26 => array(
				'id'        => 26,
				'chapters'  => 48,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'ezekiel',
					'pt-br' => 'ezequiel',
					'es'    => 'ezequiel',
					'fr'    => 'ezechiel',
					'de'    => 'hesekiel',
					'it'    => 'ezechiele',
					'ru'    => 'иезекииль',
					'ko'    => '에스겔',
					'zh'    => '以西结书',
				),
				'names'     => array(
					'en'    => 'Ezekiel',
					'pt-br' => 'Ezequiel',
					'es'    => 'Ezequiel',
					'fr'    => 'Ézéchiel',
					'de'    => 'Hesekiel',
					'it'    => 'Ezechiele',
					'ru'    => 'Иезекииль',
					'ko'    => '에스겔',
					'zh'    => '以西结书',
				),
				'abbrev'    => array(
					'en'    => 'Eze',
					'pt-br' => 'Ez',
					'es'    => 'Ez',
					'fr'    => 'Éz',
					'de'    => 'Hes',
					'it'    => 'Ez',
					'ru'    => 'Иез',
					'ko'    => '겔',
					'zh'    => '结',
				),
			),
			27 => array(
				'id'        => 27,
				'chapters'  => 12,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'daniel',
					'pt-br' => 'daniel',
					'es'    => 'daniel',
					'fr'    => 'daniel',
					'de'    => 'daniel',
					'it'    => 'daniele',
					'ru'    => 'даниил',
					'ko'    => '다니엘',
					'zh'    => '但以理书',
				),
				'names'     => array(
					'en'    => 'Daniel',
					'pt-br' => 'Daniel',
					'es'    => 'Daniel',
					'fr'    => 'Daniel',
					'de'    => 'Daniel',
					'it'    => 'Daniele',
					'ru'    => 'Даниил',
					'ko'    => '다니엘',
					'zh'    => '但以理书',
				),
				'abbrev'    => array(
					'en'    => 'Dan',
					'pt-br' => 'Dn',
					'es'    => 'Dn',
					'fr'    => 'Dn',
					'de'    => 'Dan',
					'it'    => 'Dn',
					'ru'    => 'Дан',
					'ko'    => '단',
					'zh'    => '但',
				),
			),
			28 => array(
				'id'        => 28,
				'chapters'  => 14,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'hosea',
					'pt-br' => 'oseias',
					'es'    => 'oseas',
					'fr'    => 'osee',
					'de'    => 'hosea',
					'it'    => 'osea',
					'ru'    => 'осия',
					'ko'    => '호세아',
					'zh'    => '何西阿书',
				),
				'names'     => array(
					'en'    => 'Hosea',
					'pt-br' => 'Oseias',
					'es'    => 'Oseas',
					'fr'    => 'Osée',
					'de'    => 'Hosea',
					'it'    => 'Osea',
					'ru'    => 'Осия',
					'ko'    => '호세아',
					'zh'    => '何西阿书',
				),
				'abbrev'    => array(
					'en'    => 'Hos',
					'pt-br' => 'Os',
					'es'    => 'Os',
					'fr'    => 'Os',
					'de'    => 'Hos',
					'it'    => 'Os',
					'ru'    => 'Ос',
					'ko'    => '호',
					'zh'    => '何',
				),
			),
			29 => array(
				'id'        => 29,
				'chapters'  => 3,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'joel',
					'pt-br' => 'joel',
					'es'    => 'joel',
					'fr'    => 'joel',
					'de'    => 'joel',
					'it'    => 'gioele',
					'ru'    => 'иоиль',
					'ko'    => '요엘',
					'zh'    => '约珥书',
				),
				'names'     => array(
					'en'    => 'Joel',
					'pt-br' => 'Joel',
					'es'    => 'Joel',
					'fr'    => 'Joël',
					'de'    => 'Joel',
					'it'    => 'Gioele',
					'ru'    => 'Иоиль',
					'ko'    => '요엘',
					'zh'    => '约珥书',
				),
				'abbrev'    => array(
					'en'    => 'Joe',
					'pt-br' => 'Jl',
					'es'    => 'Jl',
					'fr'    => 'Jl',
					'de'    => 'Joel',
					'it'    => 'Gl',
					'ru'    => 'Иоил',
					'ko'    => '욜',
					'zh'    => '珥',
				),
			),
			30 => array(
				'id'        => 30,
				'chapters'  => 9,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'amos',
					'pt-br' => 'amos',
					'es'    => 'amos',
					'fr'    => 'amos',
					'de'    => 'amos',
					'it'    => 'amos',
					'ru'    => 'амос',
					'ko'    => '아모스',
					'zh'    => '阿摩司书',
				),
				'names'     => array(
					'en'    => 'Amos',
					'pt-br' => 'Amós',
					'es'    => 'Amós',
					'fr'    => 'Amos',
					'de'    => 'Amos',
					'it'    => 'Amos',
					'ru'    => 'Амос',
					'ko'    => '아모스',
					'zh'    => '阿摩司书',
				),
				'abbrev'    => array(
					'en'    => 'Amo',
					'pt-br' => 'Am',
					'es'    => 'Am',
					'fr'    => 'Am',
					'de'    => 'Am',
					'it'    => 'Am',
					'ru'    => 'Ам',
					'ko'    => '암',
					'zh'    => '摩',
				),
			),
			31 => array(
				'id'        => 31,
				'chapters'  => 1,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'obadiah',
					'pt-br' => 'obadias',
					'es'    => 'abdias',
					'fr'    => 'abdias',
					'de'    => 'obadja',
					'it'    => 'abdia',
					'ru'    => 'авдий',
					'ko'    => '오바댜',
					'zh'    => '俄巴底亚书',
				),
				'names'     => array(
					'en'    => 'Obadiah',
					'pt-br' => 'Obadias',
					'es'    => 'Abdías',
					'fr'    => 'Abdias',
					'de'    => 'Obadja',
					'it'    => 'Abdia',
					'ru'    => 'Авдий',
					'ko'    => '오바댜',
					'zh'    => '俄巴底亚书',
				),
				'abbrev'    => array(
					'en'    => 'Oba',
					'pt-br' => 'Ob',
					'es'    => 'Abd',
					'fr'    => 'Ab',
					'de'    => 'Obd',
					'it'    => 'Abd',
					'ru'    => 'Авд',
					'ko'    => '옵',
					'zh'    => '俄',
				),
			),
			32 => array(
				'id'        => 32,
				'chapters'  => 4,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'jonah',
					'pt-br' => 'jonas',
					'es'    => 'jonas',
					'fr'    => 'jonas',
					'de'    => 'jona',
					'it'    => 'giona',
					'ru'    => 'иона',
					'ko'    => '요나',
					'zh'    => '约拿书',
				),
				'names'     => array(
					'en'    => 'Jonah',
					'pt-br' => 'Jonas',
					'es'    => 'Jonás',
					'fr'    => 'Jonas',
					'de'    => 'Jona',
					'it'    => 'Giona',
					'ru'    => 'Иона',
					'ko'    => '요나',
					'zh'    => '约拿书',
				),
				'abbrev'    => array(
					'en'    => 'Jon',
					'pt-br' => 'Jn',
					'es'    => 'Jon',
					'fr'    => 'Jon',
					'de'    => 'Jona',
					'it'    => 'Gio',
					'ru'    => 'Ион',
					'ko'    => '욘',
					'zh'    => '拿',
				),
			),
			33 => array(
				'id'        => 33,
				'chapters'  => 7,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'micah',
					'pt-br' => 'miqueias',
					'es'    => 'miqueas',
					'fr'    => 'michee',
					'de'    => 'micha',
					'it'    => 'michea',
					'ru'    => 'михей',
					'ko'    => '미가',
					'zh'    => '弥迦书',
				),
				'names'     => array(
					'en'    => 'Micah',
					'pt-br' => 'Miqueias',
					'es'    => 'Miqueas',
					'fr'    => 'Michée',
					'de'    => 'Micha',
					'it'    => 'Michea',
					'ru'    => 'Михей',
					'ko'    => '미가',
					'zh'    => '弥迦书',
				),
				'abbrev'    => array(
					'en'    => 'Mic',
					'pt-br' => 'Mq',
					'es'    => 'Miq',
					'fr'    => 'Mi',
					'de'    => 'Mi',
					'it'    => 'Mi',
					'ru'    => 'Мих',
					'ko'    => '미',
					'zh'    => '弥',
				),
			),
			34 => array(
				'id'        => 34,
				'chapters'  => 3,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'nahum',
					'pt-br' => 'naum',
					'es'    => 'nahum',
					'fr'    => 'nahum',
					'de'    => 'nahum',
					'it'    => 'naum',
					'ru'    => 'наум',
					'ko'    => '나훔',
					'zh'    => '那鸿书',
				),
				'names'     => array(
					'en'    => 'Nahum',
					'pt-br' => 'Naum',
					'es'    => 'Nahúm',
					'fr'    => 'Nahum',
					'de'    => 'Nahum',
					'it'    => 'Naum',
					'ru'    => 'Наум',
					'ko'    => '나훔',
					'zh'    => '那鸿书',
				),
				'abbrev'    => array(
					'en'    => 'Nah',
					'pt-br' => 'Na',
					'es'    => 'Nah',
					'fr'    => 'Na',
					'de'    => 'Nah',
					'it'    => 'Na',
					'ru'    => 'Наум',
					'ko'    => '나',
					'zh'    => '鸿',
				),
			),
			35 => array(
				'id'        => 35,
				'chapters'  => 3,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'habakkuk',
					'pt-br' => 'habacuque',
					'es'    => 'habacuc',
					'fr'    => 'habacuc',
					'de'    => 'habakuk',
					'it'    => 'abacuc',
					'ru'    => 'аввакум',
					'ko'    => '하박국',
					'zh'    => '哈巴谷书',
				),
				'names'     => array(
					'en'    => 'Habakkuk',
					'pt-br' => 'Habacuque',
					'es'    => 'Habacuc',
					'fr'    => 'Habacuc',
					'de'    => 'Habakuk',
					'it'    => 'Abacuc',
					'ru'    => 'Аввакум',
					'ko'    => '하박국',
					'zh'    => '哈巴谷书',
				),
				'abbrev'    => array(
					'en'    => 'Hab',
					'pt-br' => 'Hc',
					'es'    => 'Hab',
					'fr'    => 'Ha',
					'de'    => 'Hab',
					'it'    => 'Ab',
					'ru'    => 'Авв',
					'ko'    => '합',
					'zh'    => '哈',
				),
			),
			36 => array(
				'id'        => 36,
				'chapters'  => 3,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'zephaniah',
					'pt-br' => 'sofonias',
					'es'    => 'sofonias',
					'fr'    => 'sophonie',
					'de'    => 'zefanja',
					'it'    => 'sofonia',
					'ru'    => 'софония',
					'ko'    => '스바냐',
					'zh'    => '西番雅书',
				),
				'names'     => array(
					'en'    => 'Zephaniah',
					'pt-br' => 'Sofonias',
					'es'    => 'Sofonías',
					'fr'    => 'Sophonie',
					'de'    => 'Zefanja',
					'it'    => 'Sofonia',
					'ru'    => 'Софония',
					'ko'    => '스바냐',
					'zh'    => '西番雅书',
				),
				'abbrev'    => array(
					'en'    => 'Zep',
					'pt-br' => 'Sf',
					'es'    => 'Sof',
					'fr'    => 'So',
					'de'    => 'Zef',
					'it'    => 'Sof',
					'ru'    => 'Соф',
					'ko'    => '습',
					'zh'    => '番',
				),
			),
			37 => array(
				'id'        => 37,
				'chapters'  => 2,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'haggai',
					'pt-br' => 'ageu',
					'es'    => 'hageo',
					'fr'    => 'aggee',
					'de'    => 'haggai',
					'it'    => 'aggeo',
					'ru'    => 'аггей',
					'ko'    => '학개',
					'zh'    => '哈该书',
				),
				'names'     => array(
					'en'    => 'Haggai',
					'pt-br' => 'Ageu',
					'es'    => 'Hageo',
					'fr'    => 'Aggée',
					'de'    => 'Haggai',
					'it'    => 'Aggeo',
					'ru'    => 'Аггей',
					'ko'    => '학개',
					'zh'    => '哈该书',
				),
				'abbrev'    => array(
					'en'    => 'Hag',
					'pt-br' => 'Ag',
					'es'    => 'Hag',
					'fr'    => 'Ag',
					'de'    => 'Hag',
					'it'    => 'Ag',
					'ru'    => 'Агг',
					'ko'    => '학',
					'zh'    => '该',
				),
			),
			38 => array(
				'id'        => 38,
				'chapters'  => 14,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'zechariah',
					'pt-br' => 'zacarias',
					'es'    => 'zacarias',
					'fr'    => 'zacharie',
					'de'    => 'sacharja',
					'it'    => 'zaccaria',
					'ru'    => 'захария',
					'ko'    => '스가랴',
					'zh'    => '撒迦利亚书',
				),
				'names'     => array(
					'en'    => 'Zechariah',
					'pt-br' => 'Zacarias',
					'es'    => 'Zacarías',
					'fr'    => 'Zacharie',
					'de'    => 'Sacharja',
					'it'    => 'Zaccaria',
					'ru'    => 'Захария',
					'ko'    => '스가랴',
					'zh'    => '撒迦利亚书',
				),
				'abbrev'    => array(
					'en'    => 'Zec',
					'pt-br' => 'Zc',
					'es'    => 'Zac',
					'fr'    => 'Za',
					'de'    => 'Sach',
					'it'    => 'Zc',
					'ru'    => 'Зах',
					'ko'    => '슥',
					'zh'    => '亚',
				),
			),
			39 => array(
				'id'        => 39,
				'chapters'  => 4,
				'testament' => 'old',
				'slugs'     => array(
					'en'    => 'malachi',
					'pt-br' => 'malaquias',
					'es'    => 'malaquias',
					'fr'    => 'malachie',
					'de'    => 'maleachi',
					'it'    => 'malachia',
					'ru'    => 'малахия',
					'ko'    => '말라기',
					'zh'    => '玛拉基书',
				),
				'names'     => array(
					'en'    => 'Malachi',
					'pt-br' => 'Malaquias',
					'es'    => 'Malaquías',
					'fr'    => 'Malachie',
					'de'    => 'Maleachi',
					'it'    => 'Malachia',
					'ru'    => 'Малахия',
					'ko'    => '말라기',
					'zh'    => '玛拉基书',
				),
				'abbrev'    => array(
					'en'    => 'Mal',
					'pt-br' => 'Ml',
					'es'    => 'Mal',
					'fr'    => 'Ml',
					'de'    => 'Mal',
					'it'    => 'Ml',
					'ru'    => 'Мал',
					'ko'    => '말',
					'zh'    => '玛',
				),
			),

			// New Testament.
			40 => array(
				'id'        => 40,
				'chapters'  => 28,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => 'matthew',
					'pt-br' => 'mateus',
					'es'    => 'mateo',
					'fr'    => 'matthieu',
					'de'    => 'matthaeus',
					'it'    => 'matteo',
					'ru'    => 'матфей',
					'ko'    => '마태복음',
					'zh'    => '马太福音',
				),
				'names'     => array(
					'en'    => 'Matthew',
					'pt-br' => 'Mateus',
					'es'    => 'Mateo',
					'fr'    => 'Matthieu',
					'de'    => 'Matthäus',
					'it'    => 'Matteo',
					'ru'    => 'Матфей',
					'ko'    => '마태복음',
					'zh'    => '马太福音',
				),
				'abbrev'    => array(
					'en'    => 'Mat',
					'pt-br' => 'Mt',
					'es'    => 'Mt',
					'fr'    => 'Mt',
					'de'    => 'Mt',
					'it'    => 'Mt',
					'ru'    => 'Мф',
					'ko'    => '마',
					'zh'    => '太',
				),
			),
			41 => array(
				'id'        => 41,
				'chapters'  => 16,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => 'mark',
					'pt-br' => 'marcos',
					'es'    => 'marcos',
					'fr'    => 'marc',
					'de'    => 'markus',
					'it'    => 'marco',
					'ru'    => 'марк',
					'ko'    => '마가복음',
					'zh'    => '马可福音',
				),
				'names'     => array(
					'en'    => 'Mark',
					'pt-br' => 'Marcos',
					'es'    => 'Marcos',
					'fr'    => 'Marc',
					'de'    => 'Markus',
					'it'    => 'Marco',
					'ru'    => 'Марк',
					'ko'    => '마가복음',
					'zh'    => '马可福音',
				),
				'abbrev'    => array(
					'en'    => 'Mar',
					'pt-br' => 'Mc',
					'es'    => 'Mr',
					'fr'    => 'Mc',
					'de'    => 'Mk',
					'it'    => 'Mc',
					'ru'    => 'Мк',
					'ko'    => '막',
					'zh'    => '可',
				),
			),
			42 => array(
				'id'        => 42,
				'chapters'  => 24,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => 'luke',
					'pt-br' => 'lucas',
					'es'    => 'lucas',
					'fr'    => 'luc',
					'de'    => 'lukas',
					'it'    => 'luca',
					'ru'    => 'лука',
					'ko'    => '누가복음',
					'zh'    => '路加福音',
				),
				'names'     => array(
					'en'    => 'Luke',
					'pt-br' => 'Lucas',
					'es'    => 'Lucas',
					'fr'    => 'Luc',
					'de'    => 'Lukas',
					'it'    => 'Luca',
					'ru'    => 'Лука',
					'ko'    => '누가복음',
					'zh'    => '路加福音',
				),
				'abbrev'    => array(
					'en'    => 'Luk',
					'pt-br' => 'Lc',
					'es'    => 'Lc',
					'fr'    => 'Lc',
					'de'    => 'Lk',
					'it'    => 'Lc',
					'ru'    => 'Лк',
					'ko'    => '눅',
					'zh'    => '路',
				),
			),
			43 => array(
				'id'        => 43,
				'chapters'  => 21,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => 'john',
					'pt-br' => 'joao',
					'es'    => 'juan',
					'fr'    => 'jean',
					'de'    => 'johannes',
					'it'    => 'giovanni',
					'ru'    => 'иоанн',
					'ko'    => '요한복음',
					'zh'    => '约翰福音',
				),
				'names'     => array(
					'en'    => 'John',
					'pt-br' => 'João',
					'es'    => 'Juan',
					'fr'    => 'Jean',
					'de'    => 'Johannes',
					'it'    => 'Giovanni',
					'ru'    => 'Иоанн',
					'ko'    => '요한복음',
					'zh'    => '约翰福音',
				),
				'abbrev'    => array(
					'en'    => 'Joh',
					'pt-br' => 'Jo',
					'es'    => 'Jn',
					'fr'    => 'Jn',
					'de'    => 'Joh',
					'it'    => 'Gv',
					'ru'    => 'Ин',
					'ko'    => '요',
					'zh'    => '约',
				),
			),
			44 => array(
				'id'        => 44,
				'chapters'  => 28,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => 'acts',
					'pt-br' => 'atos',
					'es'    => 'hechos',
					'fr'    => 'actes',
					'de'    => 'apostelgeschichte',
					'it'    => 'atti',
					'ru'    => 'деяния',
					'ko'    => '사도행전',
					'zh'    => '使徒行传',
				),
				'names'     => array(
					'en'    => 'Acts',
					'pt-br' => 'Atos',
					'es'    => 'Hechos',
					'fr'    => 'Actes',
					'de'    => 'Apostelgeschichte',
					'it'    => 'Atti',
					'ru'    => 'Деяния',
					'ko'    => '사도행전',
					'zh'    => '使徒行传',
				),
				'abbrev'    => array(
					'en'    => 'Act',
					'pt-br' => 'At',
					'es'    => 'Hch',
					'fr'    => 'Ac',
					'de'    => 'Apg',
					'it'    => 'At',
					'ru'    => 'Деян',
					'ko'    => '행',
					'zh'    => '徒',
				),
			),
			45 => array(
				'id'        => 45,
				'chapters'  => 16,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => 'romans',
					'pt-br' => 'romanos',
					'es'    => 'romanos',
					'fr'    => 'romains',
					'de'    => 'roemer',
					'it'    => 'romani',
					'ru'    => 'римлянам',
					'ko'    => '로마서',
					'zh'    => '罗马书',
				),
				'names'     => array(
					'en'    => 'Romans',
					'pt-br' => 'Romanos',
					'es'    => 'Romanos',
					'fr'    => 'Romains',
					'de'    => 'Römer',
					'it'    => 'Romani',
					'ru'    => 'Римлянам',
					'ko'    => '로마서',
					'zh'    => '罗马书',
				),
				'abbrev'    => array(
					'en'    => 'Rom',
					'pt-br' => 'Rm',
					'es'    => 'Ro',
					'fr'    => 'Rm',
					'de'    => 'Röm',
					'it'    => 'Rm',
					'ru'    => 'Рим',
					'ko'    => '롬',
					'zh'    => '罗',
				),
			),
			46 => array(
				'id'        => 46,
				'chapters'  => 16,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => '1-corinthians',
					'pt-br' => '1-corintios',
					'es'    => '1-corintios',
					'fr'    => '1-corinthiens',
					'de'    => '1-korinther',
					'it'    => '1-corinzi',
					'ru'    => '1-коринфянам',
					'ko'    => '고린도전서',
					'zh'    => '哥林多前书',
				),
				'names'     => array(
					'en'    => '1 Corinthians',
					'pt-br' => '1 Coríntios',
					'es'    => '1 Corintios',
					'fr'    => '1 Corinthiens',
					'de'    => '1 Korinther',
					'it'    => '1 Corinzi',
					'ru'    => '1 Коринфянам',
					'ko'    => '고린도전서',
					'zh'    => '哥林多前书',
				),
				'abbrev'    => array(
					'en'    => '1Co',
					'pt-br' => '1Co',
					'es'    => '1Co',
					'fr'    => '1Co',
					'de'    => '1Kor',
					'it'    => '1Cor',
					'ru'    => '1Кор',
					'ko'    => '고전',
					'zh'    => '林前',
				),
			),
			47 => array(
				'id'        => 47,
				'chapters'  => 13,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => '2-corinthians',
					'pt-br' => '2-corintios',
					'es'    => '2-corintios',
					'fr'    => '2-corinthiens',
					'de'    => '2-korinther',
					'it'    => '2-corinzi',
					'ru'    => '2-коринфянам',
					'ko'    => '고린도후서',
					'zh'    => '哥林多后书',
				),
				'names'     => array(
					'en'    => '2 Corinthians',
					'pt-br' => '2 Coríntios',
					'es'    => '2 Corintios',
					'fr'    => '2 Corinthiens',
					'de'    => '2 Korinther',
					'it'    => '2 Corinzi',
					'ru'    => '2 Коринфянам',
					'ko'    => '고린도후서',
					'zh'    => '哥林多后书',
				),
				'abbrev'    => array(
					'en'    => '2Co',
					'pt-br' => '2Co',
					'es'    => '2Co',
					'fr'    => '2Co',
					'de'    => '2Kor',
					'it'    => '2Cor',
					'ru'    => '2Кор',
					'ko'    => '고후',
					'zh'    => '林后',
				),
			),
			48 => array(
				'id'        => 48,
				'chapters'  => 6,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => 'galatians',
					'pt-br' => 'galatas',
					'es'    => 'galatas',
					'fr'    => 'galates',
					'de'    => 'galater',
					'it'    => 'galati',
					'ru'    => 'галатам',
					'ko'    => '갈라디아서',
					'zh'    => '加拉太书',
				),
				'names'     => array(
					'en'    => 'Galatians',
					'pt-br' => 'Gálatas',
					'es'    => 'Gálatas',
					'fr'    => 'Galates',
					'de'    => 'Galater',
					'it'    => 'Galati',
					'ru'    => 'Галатам',
					'ko'    => '갈라디아서',
					'zh'    => '加拉太书',
				),
				'abbrev'    => array(
					'en'    => 'Gal',
					'pt-br' => 'Gl',
					'es'    => 'Gá',
					'fr'    => 'Ga',
					'de'    => 'Gal',
					'it'    => 'Gal',
					'ru'    => 'Гал',
					'ko'    => '갈',
					'zh'    => '加',
				),
			),
			49 => array(
				'id'        => 49,
				'chapters'  => 6,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => 'ephesians',
					'pt-br' => 'efesios',
					'es'    => 'efesios',
					'fr'    => 'ephesiens',
					'de'    => 'epheser',
					'it'    => 'efesini',
					'ru'    => 'ефесянам',
					'ko'    => '에베소서',
					'zh'    => '以弗所书',
				),
				'names'     => array(
					'en'    => 'Ephesians',
					'pt-br' => 'Efésios',
					'es'    => 'Efesios',
					'fr'    => 'Éphésiens',
					'de'    => 'Epheser',
					'it'    => 'Efesini',
					'ru'    => 'Ефесянам',
					'ko'    => '에베소서',
					'zh'    => '以弗所书',
				),
				'abbrev'    => array(
					'en'    => 'Eph',
					'pt-br' => 'Ef',
					'es'    => 'Ef',
					'fr'    => 'Ép',
					'de'    => 'Eph',
					'it'    => 'Ef',
					'ru'    => 'Еф',
					'ko'    => '엡',
					'zh'    => '弗',
				),
			),
			50 => array(
				'id'        => 50,
				'chapters'  => 4,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => 'philippians',
					'pt-br' => 'filipenses',
					'es'    => 'filipenses',
					'fr'    => 'philippiens',
					'de'    => 'philipper',
					'it'    => 'filippesi',
					'ru'    => 'филиппийцам',
					'ko'    => '빌립보서',
					'zh'    => '腓立比书',
				),
				'names'     => array(
					'en'    => 'Philippians',
					'pt-br' => 'Filipenses',
					'es'    => 'Filipenses',
					'fr'    => 'Philippiens',
					'de'    => 'Philipper',
					'it'    => 'Filippesi',
					'ru'    => 'Филиппийцам',
					'ko'    => '빌립보서',
					'zh'    => '腓立比书',
				),
				'abbrev'    => array(
					'en'    => 'Php',
					'pt-br' => 'Fp',
					'es'    => 'Fil',
					'fr'    => 'Ph',
					'de'    => 'Phil',
					'it'    => 'Fil',
					'ru'    => 'Флп',
					'ko'    => '빌',
					'zh'    => '腓',
				),
			),
			51 => array(
				'id'        => 51,
				'chapters'  => 4,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => 'colossians',
					'pt-br' => 'colossenses',
					'es'    => 'colosenses',
					'fr'    => 'colossiens',
					'de'    => 'kolosser',
					'it'    => 'colossesi',
					'ru'    => 'колоссянам',
					'ko'    => '골로새서',
					'zh'    => '歌罗西书',
				),
				'names'     => array(
					'en'    => 'Colossians',
					'pt-br' => 'Colossenses',
					'es'    => 'Colosenses',
					'fr'    => 'Colossiens',
					'de'    => 'Kolosser',
					'it'    => 'Colossesi',
					'ru'    => 'Колоссянам',
					'ko'    => '골로새서',
					'zh'    => '歌罗西书',
				),
				'abbrev'    => array(
					'en'    => 'Col',
					'pt-br' => 'Cl',
					'es'    => 'Col',
					'fr'    => 'Col',
					'de'    => 'Kol',
					'it'    => 'Col',
					'ru'    => 'Кол',
					'ko'    => '골',
					'zh'    => '西',
				),
			),
			52 => array(
				'id'        => 52,
				'chapters'  => 5,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => '1-thessalonians',
					'pt-br' => '1-tessalonicenses',
					'es'    => '1-tesalonicenses',
					'fr'    => '1-thessaloniciens',
					'de'    => '1-thessalonicher',
					'it'    => '1-tessalonicesi',
					'ru'    => '1-фессалоникийцам',
					'ko'    => '데살로니가전서',
					'zh'    => '帖撒罗尼迦前书',
				),
				'names'     => array(
					'en'    => '1 Thessalonians',
					'pt-br' => '1 Tessalonicenses',
					'es'    => '1 Tesalonicenses',
					'fr'    => '1 Thessaloniciens',
					'de'    => '1 Thessalonicher',
					'it'    => '1 Tessalonicesi',
					'ru'    => '1 Фессалоникийцам',
					'ko'    => '데살로니가전서',
					'zh'    => '帖撒罗尼迦前书',
				),
				'abbrev'    => array(
					'en'    => '1Th',
					'pt-br' => '1Ts',
					'es'    => '1Ts',
					'fr'    => '1Th',
					'de'    => '1Thess',
					'it'    => '1Tes',
					'ru'    => '1Фес',
					'ko'    => '살전',
					'zh'    => '帖前',
				),
			),
			53 => array(
				'id'        => 53,
				'chapters'  => 3,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => '2-thessalonians',
					'pt-br' => '2-tessalonicenses',
					'es'    => '2-tesalonicenses',
					'fr'    => '2-thessaloniciens',
					'de'    => '2-thessalonicher',
					'it'    => '2-tessalonicesi',
					'ru'    => '2-фессалоникийцам',
					'ko'    => '데살로니가후서',
					'zh'    => '帖撒罗尼迦后书',
				),
				'names'     => array(
					'en'    => '2 Thessalonians',
					'pt-br' => '2 Tessalonicenses',
					'es'    => '2 Tesalonicenses',
					'fr'    => '2 Thessaloniciens',
					'de'    => '2 Thessalonicher',
					'it'    => '2 Tessalonicesi',
					'ru'    => '2 Фессалоникийцам',
					'ko'    => '데살로니가후서',
					'zh'    => '帖撒罗尼迦后书',
				),
				'abbrev'    => array(
					'en'    => '2Th',
					'pt-br' => '2Ts',
					'es'    => '2Ts',
					'fr'    => '2Th',
					'de'    => '2Thess',
					'it'    => '2Tes',
					'ru'    => '2Фес',
					'ko'    => '살후',
					'zh'    => '帖后',
				),
			),
			54 => array(
				'id'        => 54,
				'chapters'  => 6,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => '1-timothy',
					'pt-br' => '1-timoteo',
					'es'    => '1-timoteo',
					'fr'    => '1-timothee',
					'de'    => '1-timotheus',
					'it'    => '1-timoteo',
					'ru'    => '1-тимофею',
					'ko'    => '디모데전서',
					'zh'    => '提摩太前书',
				),
				'names'     => array(
					'en'    => '1 Timothy',
					'pt-br' => '1 Timóteo',
					'es'    => '1 Timoteo',
					'fr'    => '1 Timothée',
					'de'    => '1 Timotheus',
					'it'    => '1 Timoteo',
					'ru'    => '1 Тимофею',
					'ko'    => '디모데전서',
					'zh'    => '提摩太前书',
				),
				'abbrev'    => array(
					'en'    => '1Ti',
					'pt-br' => '1Tm',
					'es'    => '1Ti',
					'fr'    => '1Tm',
					'de'    => '1Tim',
					'it'    => '1Tm',
					'ru'    => '1Тим',
					'ko'    => '딤전',
					'zh'    => '提前',
				),
			),
			55 => array(
				'id'        => 55,
				'chapters'  => 4,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => '2-timothy',
					'pt-br' => '2-timoteo',
					'es'    => '2-timoteo',
					'fr'    => '2-timothee',
					'de'    => '2-timotheus',
					'it'    => '2-timoteo',
					'ru'    => '2-тимофею',
					'ko'    => '디모데후서',
					'zh'    => '提摩太后书',
				),
				'names'     => array(
					'en'    => '2 Timothy',
					'pt-br' => '2 Timóteo',
					'es'    => '2 Timoteo',
					'fr'    => '2 Timothée',
					'de'    => '2 Timotheus',
					'it'    => '2 Timoteo',
					'ru'    => '2 Тимофею',
					'ko'    => '디모데후서',
					'zh'    => '提摩太后书',
				),
				'abbrev'    => array(
					'en'    => '2Ti',
					'pt-br' => '2Tm',
					'es'    => '2Ti',
					'fr'    => '2Tm',
					'de'    => '2Tim',
					'it'    => '2Tm',
					'ru'    => '2Тим',
					'ko'    => '딤후',
					'zh'    => '提后',
				),
			),
			56 => array(
				'id'        => 56,
				'chapters'  => 3,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => 'titus',
					'pt-br' => 'tito',
					'es'    => 'tito',
					'fr'    => 'tite',
					'de'    => 'titus',
					'it'    => 'tito',
					'ru'    => 'титу',
					'ko'    => '디도서',
					'zh'    => '提多书',
				),
				'names'     => array(
					'en'    => 'Titus',
					'pt-br' => 'Tito',
					'es'    => 'Tito',
					'fr'    => 'Tite',
					'de'    => 'Titus',
					'it'    => 'Tito',
					'ru'    => 'Титу',
					'ko'    => '디도서',
					'zh'    => '提多书',
				),
				'abbrev'    => array(
					'en'    => 'Tit',
					'pt-br' => 'Tt',
					'es'    => 'Tit',
					'fr'    => 'Tt',
					'de'    => 'Tit',
					'it'    => 'Tt',
					'ru'    => 'Тит',
					'ko'    => '딛',
					'zh'    => '多',
				),
			),
			57 => array(
				'id'        => 57,
				'chapters'  => 1,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => 'philemon',
					'pt-br' => 'filemom',
					'es'    => 'filemon',
					'fr'    => 'philemon',
					'de'    => 'philemon',
					'it'    => 'filemone',
					'ru'    => 'филимону',
					'ko'    => '빌레몬서',
					'zh'    => '腓利门书',
				),
				'names'     => array(
					'en'    => 'Philemon',
					'pt-br' => 'Filemom',
					'es'    => 'Filemón',
					'fr'    => 'Philémon',
					'de'    => 'Philemon',
					'it'    => 'Filemone',
					'ru'    => 'Филимону',
					'ko'    => '빌레몬서',
					'zh'    => '腓利门书',
				),
				'abbrev'    => array(
					'en'    => 'Phm',
					'pt-br' => 'Fm',
					'es'    => 'Flm',
					'fr'    => 'Phm',
					'de'    => 'Phlm',
					'it'    => 'Fm',
					'ru'    => 'Флм',
					'ko'    => '몬',
					'zh'    => '门',
				),
			),
			58 => array(
				'id'        => 58,
				'chapters'  => 13,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => 'hebrews',
					'pt-br' => 'hebreus',
					'es'    => 'hebreos',
					'fr'    => 'hebreux',
					'de'    => 'hebraeer',
					'it'    => 'ebrei',
					'ru'    => 'евреям',
					'ko'    => '히브리서',
					'zh'    => '希伯来书',
				),
				'names'     => array(
					'en'    => 'Hebrews',
					'pt-br' => 'Hebreus',
					'es'    => 'Hebreos',
					'fr'    => 'Hébreux',
					'de'    => 'Hebräer',
					'it'    => 'Ebrei',
					'ru'    => 'Евреям',
					'ko'    => '히브리서',
					'zh'    => '希伯来书',
				),
				'abbrev'    => array(
					'en'    => 'Heb',
					'pt-br' => 'Hb',
					'es'    => 'He',
					'fr'    => 'Hé',
					'de'    => 'Hebr',
					'it'    => 'Eb',
					'ru'    => 'Евр',
					'ko'    => '히',
					'zh'    => '来',
				),
			),
			59 => array(
				'id'        => 59,
				'chapters'  => 5,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => 'james',
					'pt-br' => 'tiago',
					'es'    => 'santiago',
					'fr'    => 'jacques',
					'de'    => 'jakobus',
					'it'    => 'giacomo',
					'ru'    => 'иаков',
					'ko'    => '야고보서',
					'zh'    => '雅各书',
				),
				'names'     => array(
					'en'    => 'James',
					'pt-br' => 'Tiago',
					'es'    => 'Santiago',
					'fr'    => 'Jacques',
					'de'    => 'Jakobus',
					'it'    => 'Giacomo',
					'ru'    => 'Иаков',
					'ko'    => '야고보서',
					'zh'    => '雅各书',
				),
				'abbrev'    => array(
					'en'    => 'Jam',
					'pt-br' => 'Tg',
					'es'    => 'Stg',
					'fr'    => 'Jc',
					'de'    => 'Jak',
					'it'    => 'Gc',
					'ru'    => 'Иак',
					'ko'    => '약',
					'zh'    => '雅',
				),
			),
			60 => array(
				'id'        => 60,
				'chapters'  => 5,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => '1-peter',
					'pt-br' => '1-pedro',
					'es'    => '1-pedro',
					'fr'    => '1-pierre',
					'de'    => '1-petrus',
					'it'    => '1-pietro',
					'ru'    => '1-петра',
					'ko'    => '베드로전서',
					'zh'    => '彼得前书',
				),
				'names'     => array(
					'en'    => '1 Peter',
					'pt-br' => '1 Pedro',
					'es'    => '1 Pedro',
					'fr'    => '1 Pierre',
					'de'    => '1 Petrus',
					'it'    => '1 Pietro',
					'ru'    => '1 Петра',
					'ko'    => '베드로전서',
					'zh'    => '彼得前书',
				),
				'abbrev'    => array(
					'en'    => '1Pe',
					'pt-br' => '1Pe',
					'es'    => '1Pe',
					'fr'    => '1P',
					'de'    => '1Petr',
					'it'    => '1Pt',
					'ru'    => '1Пет',
					'ko'    => '벧전',
					'zh'    => '彼前',
				),
			),
			61 => array(
				'id'        => 61,
				'chapters'  => 3,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => '2-peter',
					'pt-br' => '2-pedro',
					'es'    => '2-pedro',
					'fr'    => '2-pierre',
					'de'    => '2-petrus',
					'it'    => '2-pietro',
					'ru'    => '2-петра',
					'ko'    => '베드로후서',
					'zh'    => '彼得后书',
				),
				'names'     => array(
					'en'    => '2 Peter',
					'pt-br' => '2 Pedro',
					'es'    => '2 Pedro',
					'fr'    => '2 Pierre',
					'de'    => '2 Petrus',
					'it'    => '2 Pietro',
					'ru'    => '2 Петра',
					'ko'    => '베드로후서',
					'zh'    => '彼得后书',
				),
				'abbrev'    => array(
					'en'    => '2Pe',
					'pt-br' => '2Pe',
					'es'    => '2Pe',
					'fr'    => '2P',
					'de'    => '2Petr',
					'it'    => '2Pt',
					'ru'    => '2Пет',
					'ko'    => '벧후',
					'zh'    => '彼后',
				),
			),
			62 => array(
				'id'        => 62,
				'chapters'  => 5,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => '1-john',
					'pt-br' => '1-joao',
					'es'    => '1-juan',
					'fr'    => '1-jean',
					'de'    => '1-johannes',
					'it'    => '1-giovanni',
					'ru'    => '1-иоанна',
					'ko'    => '요한1서',
					'zh'    => '约翰一书',
				),
				'names'     => array(
					'en'    => '1 John',
					'pt-br' => '1 João',
					'es'    => '1 Juan',
					'fr'    => '1 Jean',
					'de'    => '1 Johannes',
					'it'    => '1 Giovanni',
					'ru'    => '1 Иоанна',
					'ko'    => '요한1서',
					'zh'    => '约翰一书',
				),
				'abbrev'    => array(
					'en'    => '1Jo',
					'pt-br' => '1Jo',
					'es'    => '1Jn',
					'fr'    => '1Jn',
					'de'    => '1Joh',
					'it'    => '1Gv',
					'ru'    => '1Ин',
					'ko'    => '요일',
					'zh'    => '约一',
				),
			),
			63 => array(
				'id'        => 63,
				'chapters'  => 1,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => '2-john',
					'pt-br' => '2-joao',
					'es'    => '2-juan',
					'fr'    => '2-jean',
					'de'    => '2-johannes',
					'it'    => '2-giovanni',
					'ru'    => '2-иоанна',
					'ko'    => '요한2서',
					'zh'    => '约翰二书',
				),
				'names'     => array(
					'en'    => '2 John',
					'pt-br' => '2 João',
					'es'    => '2 Juan',
					'fr'    => '2 Jean',
					'de'    => '2 Johannes',
					'it'    => '2 Giovanni',
					'ru'    => '2 Иоанна',
					'ko'    => '요한2서',
					'zh'    => '约翰二书',
				),
				'abbrev'    => array(
					'en'    => '2Jo',
					'pt-br' => '2Jo',
					'es'    => '2Jn',
					'fr'    => '2Jn',
					'de'    => '2Joh',
					'it'    => '2Gv',
					'ru'    => '2Ин',
					'ko'    => '요이',
					'zh'    => '约二',
				),
			),
			64 => array(
				'id'        => 64,
				'chapters'  => 1,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => '3-john',
					'pt-br' => '3-joao',
					'es'    => '3-juan',
					'fr'    => '3-jean',
					'de'    => '3-johannes',
					'it'    => '3-giovanni',
					'ru'    => '3-иоанна',
					'ko'    => '요한3서',
					'zh'    => '约翰三书',
				),
				'names'     => array(
					'en'    => '3 John',
					'pt-br' => '3 João',
					'es'    => '3 Juan',
					'fr'    => '3 Jean',
					'de'    => '3 Johannes',
					'it'    => '3 Giovanni',
					'ru'    => '3 Иоанна',
					'ko'    => '요한3서',
					'zh'    => '约翰三书',
				),
				'abbrev'    => array(
					'en'    => '3Jo',
					'pt-br' => '3Jo',
					'es'    => '3Jn',
					'fr'    => '3Jn',
					'de'    => '3Joh',
					'it'    => '3Gv',
					'ru'    => '3Ин',
					'ko'    => '요삼',
					'zh'    => '约三',
				),
			),
			65 => array(
				'id'        => 65,
				'chapters'  => 1,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => 'jude',
					'pt-br' => 'judas',
					'es'    => 'judas',
					'fr'    => 'jude',
					'de'    => 'judas',
					'it'    => 'giuda',
					'ru'    => 'иуда',
					'ko'    => '유다서',
					'zh'    => '犹大书',
				),
				'names'     => array(
					'en'    => 'Jude',
					'pt-br' => 'Judas',
					'es'    => 'Judas',
					'fr'    => 'Jude',
					'de'    => 'Judas',
					'it'    => 'Giuda',
					'ru'    => 'Иуда',
					'ko'    => '유다서',
					'zh'    => '犹大书',
				),
				'abbrev'    => array(
					'en'    => 'Jud',
					'pt-br' => 'Jd',
					'es'    => 'Jud',
					'fr'    => 'Jude',
					'de'    => 'Jud',
					'it'    => 'Gd',
					'ru'    => 'Иуд',
					'ko'    => '유',
					'zh'    => '犹',
				),
			),
			66 => array(
				'id'        => 66,
				'chapters'  => 22,
				'testament' => 'new',
				'slugs'     => array(
					'en'    => 'revelation',
					'pt-br' => 'apocalipse',
					'es'    => 'apocalipsis',
					'fr'    => 'apocalypse',
					'de'    => 'offenbarung',
					'it'    => 'apocalisse',
					'ru'    => 'откровение',
					'ko'    => '요한계시록',
					'zh'    => '启示录',
				),
				'names'     => array(
					'en'    => 'Revelation',
					'pt-br' => 'Apocalipse',
					'es'    => 'Apocalipsis',
					'fr'    => 'Apocalypse',
					'de'    => 'Offenbarung',
					'it'    => 'Apocalisse',
					'ru'    => 'Откровение',
					'ko'    => '요한계시록',
					'zh'    => '启示录',
				),
				'abbrev'    => array(
					'en'    => 'Rev',
					'pt-br' => 'Ap',
					'es'    => 'Ap',
					'fr'    => 'Ap',
					'de'    => 'Offb',
					'it'    => 'Ap',
					'ru'    => 'Откр',
					'ko'    => '계',
					'zh'    => '启',
				),
			),
			// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Sync-script marker, not commented-out code.
			// {{SYNCED_BOOKS_END}}
		);
	}

	/**
	 * Build lookup table for fast name/abbreviation/slug → book_id mapping.
	 * Memoized per-locale for the same reason as get_matching_pattern().
	 *
	 * @param string|null $locale Locale to restrict the table to, or null for all locales.
	 */
	public static function get_lookup_table( $locale = null ) {
		static $cache = array();

		$key = $locale ? self::normalize_locale( $locale ) : '__all__';
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}

		$books            = self::get_books();
		$lookup           = array();
		$locales_to_check = $locale ? array( self::normalize_locale( $locale ) ) : self::LOCALES;

		foreach ( $books as $book ) {
			foreach ( $locales_to_check as $loc ) {
				if ( isset( $book['names'][ $loc ] ) ) {
					$lookup[ self::lower( $book['names'][ $loc ] ) ] = $book['id'];
				}
				if ( isset( $book['abbrev'][ $loc ] ) ) {
					$lookup[ self::lower( $book['abbrev'][ $loc ] ) ] = $book['id'];
				}
				if ( isset( $book['slugs'][ $loc ] ) ) {
					$lookup[ $book['slugs'][ $loc ] ] = $book['id'];
				}
			}
		}

		$cache[ $key ] = $lookup;
		return $lookup;
	}
}
