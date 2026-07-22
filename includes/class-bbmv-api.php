<?php
/**
 * Midvash API Client
 *
 * Responsible for making requests to the public API and managing cache.
 * Uses the new REST API format: /{version}/{book}/{chapter}/{verse}
 *
 * @package Bible_By_Midvash
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTTP client for the Midvash Bible API with transient/object caching.
 */
class BBMV_API {

	/**
	 * API Base URL.
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * Plugin options.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Current locale.
	 *
	 * @var string
	 */
	private $locale;

	/**
	 * Object cache group used as in-request fast path before transients.
	 *
	 * On sites with a persistent object cache (Redis, Memcached, MemCachier…),
	 * lookups stay in-memory; on vanilla installs transients hit the options
	 * table — still fine, just slower. Group names ending in "-bbm" keep
	 * keys isolated from other plugins.
	 */
	const CACHE_GROUP = 'bbm';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->api_url = BBMV_API_BASE_URL;
		$this->options = get_option(
			'bbm_options',
			array(
				'cache_enabled' => true,
				'cache_ttl'     => 2592000,
				'timeout'       => 5,
				'versao'        => 'nvt',
				'locale'        => 'pt-br',
			)
		);
		$this->locale  = isset( $this->options['locale'] ) ? $this->options['locale'] : 'pt-br';
		$this->locale  = BBMV_Books::normalize_locale( $this->locale );
	}

	/**
	 * Builds API path from parsed reference
	 *
	 * @param array  $parsed Parsed reference data.
	 * @param string $version Bible version.
	 * @return string API path
	 */
	private function build_api_path( $parsed, $version ) {
		// Get slug in English for API (API uses English slugs).
		$book_slug = BBMV_Books::get_book_slug( $parsed['book_id'], 'en' );
		$path      = '/' . strtolower( $version ) . '/' . $book_slug . '/' . $parsed['chapter'];

		if ( $parsed['verse'] ) {
			if ( $parsed['verse_end'] && $parsed['verse_end'] !== $parsed['verse'] ) {
				$path .= '/' . $parsed['verse'] . '-' . $parsed['verse_end'];
			} else {
				$path .= '/' . $parsed['verse'];
			}
		}

		return $path;
	}

	/**
	 * Validates reference before making request
	 *
	 * @param string $reference Reference to validate.
	 * @return bool|string True if valid, error message if invalid
	 */
	private function validate_reference( $reference ) {
		if ( empty( trim( $reference ) ) ) {
			return 'Empty reference';
		}

		if ( strlen( $reference ) > 100 ) {
			return 'Reference too long (maximum 100 characters)';
		}

		// Check maximum range (50 verses).
		if ( preg_match( '/(\d+):(\d+)-(\d+)/', $reference, $matches ) ) {
			$start = intval( $matches[2] );
			$end   = intval( $matches[3] );
			if ( $end - $start + 1 > 50 ) {
				return 'Interval too large (maximum 50 verses)';
			}
		}

		return true;
	}

	/**
	 * Fetches a verse from the API
	 *
	 * @param string $reference Bible reference (e.g. "John 3:16").
	 * @param string $version Bible version (e.g. "nvt").
	 * @return array|null Verse data or null on error
	 */
	public function get_verse( $reference, $version = null ) {
		$version = $version ? $version : $this->options['versao'];
		$version = strtolower( $version );

		// Validate reference.
		$validation = $this->validate_reference( $reference );
		if ( true !== $validation ) {
			return null;
		}

		// Parse reference (centralized in BBMV_Books).
		$parsed = BBMV_Books::parse_reference( $reference );
		if ( ! $parsed ) {
			return null;
		}

		// Build API path.
		$path = $this->build_api_path( $parsed, $version );
		if ( ! $path ) {
			return null;
		}

		// Check cache first.
		if ( $this->is_cache_enabled() ) {
			$cached = $this->get_from_cache( $path, $version );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		// Make API request.
		$data = $this->make_request( $path );

		if ( $data ) {
			// Add reference info to response.
			$data['reference'] = $parsed['book']['names'][ $this->locale ] . ' ' . $parsed['chapter'];
			if ( $parsed['verse'] ) {
				$data['reference'] .= ':' . $parsed['verse'];
				if ( $parsed['verse_end'] && $parsed['verse_end'] !== $parsed['verse'] ) {
					$data['reference'] .= '-' . $parsed['verse_end'];
				}
			}

			// Save to cache.
			if ( $this->is_cache_enabled() ) {
				$this->save_to_cache( $path, $version, $data );
			}
			return $data;
		}

		return null;
	}

	/**
	 * Fetches several references in one round-trip via /v1/passages.
	 *
	 * Cache-aware: references already cached (same keys used by get_verse)
	 * are served locally; only misses go upstream, chunked at the API's
	 * 50-refs-per-request limit. Refs are sent in canonical English-slug
	 * form ("john 3:16-18") built from the parsed reference, never as raw
	 * user text.
	 *
	 * @param array  $references List of Bible references as typed in content.
	 * @param string $version    Bible version slug; defaults to the configured one.
	 * @return array Map of input reference => verse data array (or null when unresolvable).
	 */
	public function get_passages( $references, $version = null ) {
		$version = $version ? strtolower( $version ) : strtolower( $this->options['versao'] );
		$results = array();
		$misses  = array(); // canonical ref => input ref.

		foreach ( $references as $reference ) {
			$reference = trim( (string) $reference );
			if ( '' === $reference || true !== $this->validate_reference( $reference ) ) {
				continue;
			}
			$parsed = BBMV_Books::parse_reference( $reference );
			if ( ! $parsed ) {
				continue;
			}

			$path = $this->build_api_path( $parsed, $version );
			if ( $this->is_cache_enabled() ) {
				$cached = $this->get_from_cache( $path, $version );
				if ( false !== $cached ) {
					$results[ $reference ] = $cached;
					continue;
				}
			}

			$canonical = BBMV_Books::get_book_slug( $parsed['book_id'], 'en' ) . ' ' . $parsed['chapter'];
			if ( $parsed['verse'] ) {
				$canonical .= ':' . $parsed['verse'];
				if ( $parsed['verse_end'] && $parsed['verse_end'] !== $parsed['verse'] ) {
					$canonical .= '-' . $parsed['verse_end'];
				}
			}
			$misses[ $canonical ] = array(
				'input'  => $reference,
				'parsed' => $parsed,
				'path'   => $path,
			);
		}

		foreach ( array_chunk( array_keys( $misses ), 50 ) as $chunk ) {
			$response = $this->make_request(
				'/v1/passages',
				array(
					'refs'    => implode( ',', $chunk ),
					'version' => $version,
				)
			);
			if ( ! $response || ! isset( $response['data'] ) || ! is_array( $response['data'] ) ) {
				continue;
			}
			foreach ( $response['data'] as $item ) {
				if ( ! isset( $item['ref'] ) || ! isset( $misses[ $item['ref'] ] ) || isset( $item['error'] ) ) {
					continue;
				}
				$miss   = $misses[ $item['ref'] ];
				$parsed = $miss['parsed'];
				unset( $item['ref'] );

				// Localized display reference, mirroring get_verse().
				$item['reference'] = $parsed['book']['names'][ $this->locale ] . ' ' . $parsed['chapter'];
				if ( $parsed['verse'] ) {
					$item['reference'] .= ':' . $parsed['verse'];
					if ( $parsed['verse_end'] && $parsed['verse_end'] !== $parsed['verse'] ) {
						$item['reference'] .= '-' . $parsed['verse_end'];
					}
				}

				$results[ $miss['input'] ] = $item;
				if ( $this->is_cache_enabled() ) {
					$this->save_to_cache( $miss['path'], $version, $item );
				}
			}
		}

		return $results;
	}

	/**
	 * Makes a GET request to the Midvash API.
	 *
	 * Retry policy is deliberately *minimal* (1 retry, 1s backoff): this code
	 * runs synchronously inside AJAX handlers and `the_content` filters, where
	 * long sleep() chains snowball into PHP-FPM timeouts and bad UX. The API
	 * documents that it has no rate limit (no 429s, no X-RateLimit-* headers),
	 * so only transport errors and 5xx get the single retry.
	 *
	 * @param string $path    API endpoint path (with leading slash).
	 * @param array  $args    Query parameters.
	 * @param int    $attempt Internal — recursion guard.
	 * @return array|null Decoded JSON body or null on failure.
	 */
	private function make_request( $path, $args = array(), $attempt = 1 ) {
		$max_retries = 2;
		$timeout     = isset( $this->options['timeout'] ) ? intval( $this->options['timeout'] ) : 5;
		$timeout     = max( 1, min( 30, $timeout ) ); // Matches admin field constraint.

		$url = $this->api_url . $path;
		if ( ! empty( $args ) ) {
			$url = add_query_arg( $args, $url );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'             => $timeout,
				'sslverify'           => true,
				'reject_unsafe_urls'  => true, // Hard-blocks local/private redirects (SSRF defence).
				'limit_response_size' => 256 * 1024, // 256 KB — verses are small JSON.
				'headers'             => array(
					'Accept'     => 'application/json',
					'User-Agent' => 'Midvash-WP-Plugin/' . BBMV_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( $attempt < $max_retries ) {
				sleep( 1 );
				return $this->make_request( $path, $args, $attempt + 1 );
			}
			return null;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 500 && $attempt < $max_retries ) {
			sleep( 1 );
			return $this->make_request( $path, $args, $attempt + 1 );
		}

		if ( 200 === $status_code ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			if ( is_array( $data ) ) {
				return $data;
			}
		}

		return null;
	}

	/**
	 * Checks if cache is enabled
	 */
	private function is_cache_enabled() {
		return isset( $this->options['cache_enabled'] ) && $this->options['cache_enabled'];
	}

	/**
	 * Generates cache key
	 *
	 * @param string $reference API path or reference used to build the key.
	 * @param string $version Bible version slug.
	 */
	private function get_cache_key( $reference, $version ) {
		return 'bbm_' . md5( $reference . '_' . $version );
	}

	/**
	 * Read-through cache: object cache (in-memory) → transient (persistent).
	 *
	 * On WPCom / managed hosts with a persistent object cache (Redis…), reads
	 * stay in-memory between requests — significantly faster than the options
	 * table round-trip that transients fall back to.
	 *
	 * @param string $reference API path or reference used to build the key.
	 * @param string $version Bible version slug.
	 */
	private function get_from_cache( $reference, $version ) {
		$key = $this->get_cache_key( $reference, $version );
		$hit = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false !== $hit ) {
			return $hit;
		}
		$stored = get_transient( $key );
		if ( false !== $stored ) {
			wp_cache_set( $key, $stored, self::CACHE_GROUP, HOUR_IN_SECONDS );
			return $stored;
		}
		return false;
	}

	/**
	 * Write-through cache: persist via transient, mirror in object cache.
	 *
	 * @param string $reference API path or reference used to build the key.
	 * @param string $version Bible version slug.
	 * @param array  $data Verse data to cache.
	 */
	private function save_to_cache( $reference, $version, $data ) {
		$key = $this->get_cache_key( $reference, $version );
		$ttl = isset( $this->options['cache_ttl'] ) ? intval( $this->options['cache_ttl'] ) : 2592000;
		set_transient( $key, $data, $ttl );
		wp_cache_set( $key, $data, self::CACHE_GROUP, min( $ttl, HOUR_IN_SECONDS ) );
	}

	/**
	 * Fetches the full version catalogue from /v1/versions (enriched with
	 * localizedNames and copyright), cached for 7 days.
	 *
	 * @return array|null List of version arrays or null on error.
	 */
	private function get_catalog() {
		$cache_key = 'bbm_versions_v1';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$result = $this->make_request( '/v1/versions' );
		if ( $result && isset( $result['data'] ) && is_array( $result['data'] ) && ! empty( $result['data'] ) ) {
			set_transient( $cache_key, $result['data'], 7 * DAY_IN_SECONDS );
			return $result['data'];
		}

		return null;
	}

	/**
	 * Fetches available Bible versions, optionally filtered by locale.
	 *
	 * Same array-of-versions shape as before, now with the additive
	 * `localizedNames` and `copyright` fields from /v1/versions.
	 *
	 * @param string|null $locale Filter versions by language (pt-br, en, es…).
	 * @return array|null Array of versions or null on error
	 */
	public function get_versions( $locale = null ) {
		$locale = $locale ? $locale : $this->locale;
		$locale = BBMV_Books::normalize_locale( $locale );

		$catalog = $this->get_catalog();
		if ( ! $catalog ) {
			return null;
		}

		$filtered = array_filter(
			$catalog,
			function ( $v ) use ( $locale ) {
				$version_locale = isset( $v['language'] ) ? $v['language'] : '';
				// pt-pt groups with pt-br, matching the API's legacy locale rule.
				if ( 'pt-br' === $version_locale || 'pt' === $version_locale || 'pt-pt' === $version_locale ) {
					return ( 'pt-br' === $locale );
				}
				return ( $version_locale === $locale );
			}
		);

		return ! empty( $filtered ) ? array_values( $filtered ) : null;
	}

	/**
	 * Returns the catalogue entry for a single version slug, or null.
	 *
	 * Used to surface the localized version name and copyright attribution
	 * in the admin and in the tooltip footer.
	 *
	 * @param string $version_slug Version slug (nvt, kjv…).
	 * @return array|null Version array with localizedNames/copyright, or null.
	 */
	public function get_version_meta( $version_slug ) {
		$version_slug = strtolower( (string) $version_slug );
		$catalog      = $this->get_catalog();
		if ( ! $catalog ) {
			return null;
		}
		foreach ( $catalog as $v ) {
			if ( isset( $v['slug'] ) && strtolower( $v['slug'] ) === $version_slug ) {
				return $v;
			}
		}
		return null;
	}

	/**
	 * Fetches the verse of the day from the API
	 *
	 * @param string $locale  Content locale (pt-br, en, es…).
	 * @param string $version Bible version slug (nvt, kjv…).
	 * @return array|null Verse data or null on error
	 */
	public function get_votd( $locale = null, $version = null ) {
		$locale  = $locale ? $locale : $this->locale;
		$locale  = BBMV_Books::normalize_locale( $locale );
		$version = $version ? $version : BBMV_Books::get_default_version( $locale );

		$cache_key = 'bbm_votd_' . $locale . '_' . $version . '_' . gmdate( 'Y-m-d' );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$result = $this->make_request(
			'/votd',
			array(
				'language' => $locale,
				'version'  => $version,
			)
		);

		if ( $result && isset( $result['text'] ) ) {
			// Retry with locale default version if the chosen version returned an error.
			if ( isset( $result['error'] ) ) {
				$fallback = BBMV_Books::get_default_version( $locale );
				if ( $fallback !== $version ) {
					$result = $this->make_request(
						'/votd',
						array(
							'language' => $locale,
							'version'  => $fallback,
						)
					);
				}
			}
		}

		if ( $result && isset( $result['text'] ) && ! isset( $result['error'] ) ) {
			set_transient( $cache_key, $result, DAY_IN_SECONDS );
			return $result;
		}

		return null;
	}

	// Note: an HTTP-backed get_books() and a direct-SQL clear_cache() lived
	// here in earlier versions but had zero callers (the parser uses the
	// static BBMV_Books data, and cache wiping moved to uninstall.php). They
	// were removed to keep the API surface minimal.
}
