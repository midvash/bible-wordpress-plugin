<?php
/**
 * Midvash API Client
 *
 * Responsible for making requests to the public API and managing cache.
 * Uses the new REST API format: /{version}/{book}/{chapter}/{verse}
 *
 * @package Bible_by_Midvash
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BBMV_API {

	/**
	 * API Base URL
	 */
	private $api_url;

	/**
	 * Plugin options
	 */
	private $options;

	/**
	 * Current locale
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
	 * @param array  $parsed Parsed reference data
	 * @param string $version Bible version
	 * @return string API path
	 */
	private function build_api_path( $parsed, $version ) {
		// Get slug in English for API (API uses English slugs)
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
	 * @param string $reference Reference to validate
	 * @return bool|string True if valid, error message if invalid
	 */
	private function validate_reference( $reference ) {
		if ( empty( trim( $reference ) ) ) {
			return 'Empty reference';
		}

		if ( strlen( $reference ) > 100 ) {
			return 'Reference too long (maximum 100 characters)';
		}

		// Check maximum range (50 verses)
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
	 * No-op kept as a hook point for future telemetry.
	 *
	 * An earlier version `sleep(2)`-ed when the remaining rate-limit budget
	 * fell below 5 — fine for cron, brutal for a synchronous tooltip handler
	 * because it lengthened the round-trip for the *next* visitor. We now
	 * let the upstream 429 (with our bounded backoff in make_request) handle
	 * real throttling, and don't react to soft-budget warnings at all.
	 */
	private function check_rate_limit( $response ) {
		// Intentionally empty.
	}

	/**
	 * Fetches a verse from the API
	 *
	 * @param string $reference Bible reference (e.g. "John 3:16")
	 * @param string $version Bible version (e.g. "nvt")
	 * @return array|null Verse data or null on error
	 */
	public function get_verse( $reference, $version = null ) {
		$version = $version ? $version : $this->options['versao'];
		$version = strtolower( $version );

		// Validate reference
		$validation = $this->validate_reference( $reference );
		if ( true !== $validation ) {
			return null;
		}

		// Parse reference (centralized in BBMV_Books)
		$parsed = BBMV_Books::parse_reference( $reference );
		if ( ! $parsed ) {
			return null;
		}

		// Build API path
		$path = $this->build_api_path( $parsed, $version );
		if ( ! $path ) {
			return null;
		}

		// Check cache first
		if ( $this->is_cache_enabled() ) {
			$cached = $this->get_from_cache( $path, $version );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		// Make API request
		$data = $this->make_request( $path );

		if ( $data ) {
			// Add reference info to response
			$data['reference'] = $parsed['book']['names'][ $this->locale ] . ' ' . $parsed['chapter'];
			if ( $parsed['verse'] ) {
				$data['reference'] .= ':' . $parsed['verse'];
				if ( $parsed['verse_end'] && $parsed['verse_end'] !== $parsed['verse'] ) {
					$data['reference'] .= '-' . $parsed['verse_end'];
				}
			}

			// Save to cache
			if ( $this->is_cache_enabled() ) {
				$this->save_to_cache( $path, $version, $data );
			}
			return $data;
		}

		return null;
	}

	/**
	 * Makes a GET request to the Midvash API.
	 *
	 * Retry policy is deliberately *minimal* (1 retry, 1s backoff): this code
	 * runs synchronously inside AJAX handlers and `the_content` filters, where
	 * long sleep() chains snowball into PHP-FPM timeouts and bad UX. For 429s
	 * we honour `X-RateLimit-Reset` but cap the wait at 2s, then give up.
	 *
	 * @param string $path    API endpoint path (with leading slash).
	 * @param array  $args    Query parameters.
	 * @param int    $attempt Internal — recursion guard.
	 * @return array|null Decoded JSON body or null on failure.
	 */
	private function make_request( $path, $args = array(), $attempt = 1 ) {
		$max_retries = 2;
		$timeout     = isset( $this->options['timeout'] ) ? intval( $this->options['timeout'] ) : 5;
		$timeout     = max( 1, min( 30, $timeout ) ); // matches admin field constraint

		$url = $this->api_url . $path;
		if ( ! empty( $args ) ) {
			$url = add_query_arg( $args, $url );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'             => $timeout,
				'sslverify'           => true,
				'reject_unsafe_urls'  => true, // hard-blocks local/private redirects (SSRF defence)
				'limit_response_size' => 256 * 1024, // 256 KB — verses are small JSON
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
		$this->check_rate_limit( $response );

		if ( 429 === $status_code ) {
			if ( $attempt < $max_retries ) {
				$reset_at = wp_remote_retrieve_header( $response, 'X-RateLimit-Reset' );
				$wait     = 1;
				if ( $reset_at ) {
					$reset_ts = strtotime( $reset_at );
					if ( $reset_ts ) {
						$wait = max( 1, min( 2, $reset_ts - time() ) ); // cap at 2s
					}
				}
				sleep( $wait );
				return $this->make_request( $path, $args, $attempt + 1 );
			}
			return null;
		}

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
	 */
	private function save_to_cache( $reference, $version, $data ) {
		$key = $this->get_cache_key( $reference, $version );
		$ttl = isset( $this->options['cache_ttl'] ) ? intval( $this->options['cache_ttl'] ) : 2592000;
		set_transient( $key, $data, $ttl );
		wp_cache_set( $key, $data, self::CACHE_GROUP, min( $ttl, HOUR_IN_SECONDS ) );
	}

	/**
	 * Fetches available Bible versions, optionally filtered by locale
	 *
	 * @param string|null $locale Filter versions by language (pt-br, en, es)
	 * @return array|null Array of versions or null on error
	 */
	public function get_versions( $locale = null ) {
		$locale = $locale ? $locale : $this->locale;
		$locale = BBMV_Books::normalize_locale( $locale );

		// Normalize locale for API (pt-br -> pt for filtering)
		$api_locale = ( 'pt-br' === $locale ) ? 'pt' : $locale;

		$cache_key = 'bbm_versions_' . $api_locale;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$result = $this->make_request( '/versions', array( 'locale' => $api_locale ) );

		if ( $result && isset( $result['versions'] ) ) {
			$versions = $result['versions'];
			set_transient( $cache_key, $versions, 7 * DAY_IN_SECONDS );
			return $versions;
		}

		// Fallback: fetch all and filter client-side
		$all_cache_key = 'bbm_versions_all';
		$all_cached    = get_transient( $all_cache_key );
		if ( false !== $all_cached ) {
			$filtered = array_filter(
				$all_cached,
				function ( $v ) use ( $locale ) {
					$version_locale = isset( $v['language'] ) ? $v['language'] : '';
					// Normalize for comparison
					if ( 'pt-br' === $version_locale || 'pt' === $version_locale ) {
						return ( 'pt-br' === $locale );
					}
					return ( $version_locale === $locale );
				}
			);
			if ( ! empty( $filtered ) ) {
				return array_values( $filtered );
			}
		}

		// Fetch all versions
		$all_result = $this->make_request( '/versions' );
		if ( $all_result && isset( $all_result['versions'] ) ) {
			set_transient( $all_cache_key, $all_result['versions'], 7 * DAY_IN_SECONDS );

			// Filter by locale
			$filtered = array_filter(
				$all_result['versions'],
				function ( $v ) use ( $locale ) {
					$version_locale = isset( $v['language'] ) ? $v['language'] : '';
					// Normalize for comparison
					if ( 'pt-br' === $version_locale || 'pt' === $version_locale ) {
						return ( 'pt-br' === $locale );
					}
					return ( $version_locale === $locale );
				}
			);

			if ( ! empty( $filtered ) ) {
				$filtered_versions = array_values( $filtered );
				set_transient( $cache_key, $filtered_versions, 7 * DAY_IN_SECONDS );
				return $filtered_versions;
			}
		}

		return null;
	}

	/**
	 * Fetches the verse of the day from the API
	 *
	 * @param string $locale  Content locale (pt-br, en, es…)
	 * @param string $version Bible version slug (nvt, kjv…)
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
			// Retry with locale default version if the chosen version returned an error
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
