<?php
/**
 * Midvash API Client
 * 
 * Responsible for making requests to the public API and managing cache.
 * Uses the new REST API format: /{version}/{book}/{chapter}/{verse}
 * 
 * @package Bible_by_Midvash
 */

if (!defined('ABSPATH')) {
    exit;
}

class BBM_API
{
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
     * Timestamp of the last request (for burst limit)
     */
    private static $last_request_time = 0;

    /**
     * Minimum delay between requests in microseconds (100ms)
     */
    private $min_request_delay = 100000;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->api_url = BBM_API_BASE_URL;
        $this->options = get_option('bbm_options', array(
            'cache_enabled' => true,
            'cache_ttl' => 2592000,
            'timeout' => 5,
            'versao' => 'nvt',
            'locale' => 'pt-br',
        ));
        $this->locale = isset($this->options['locale']) ? $this->options['locale'] : 'pt-br';
        $this->locale = BBM_Books::normalize_locale($this->locale);
    }

    /**
     * Parses a reference string and extracts book, chapter, verse
     * 
     * @param string $reference Reference like "João 3:16" or "Jo 3:16"
     * @return array|null Parsed data or null if invalid
     */
    private function parse_reference($reference)
    {
        $reference = trim($reference);

        // Match pattern: BookName Chapter:Verse(-VerseEnd)?
        if (!preg_match('/^(.+?)\s+(\d{1,3})(?:[:\.](\d{1,3}))?(?:\s*[-–]\s*(\d{1,3}))?$/iu', $reference, $matches)) {
            return null;
        }

        $book_input = mb_strtolower(trim($matches[1]));
        $chapter = intval($matches[2]);
        $verse = isset($matches[3]) && $matches[3] !== '' ? intval($matches[3]) : null;
        $verse_end = isset($matches[4]) && $matches[4] !== '' ? intval($matches[4]) : null;

        // Find book using centralized lookup
        $lookup_table = BBM_Books::get_lookup_table();

        if (!isset($lookup_table[$book_input])) {
            // Try without accents
            $book_input_no_accent = $this->remove_accents($book_input);
            if (!isset($lookup_table[$book_input_no_accent])) {
                return null;
            }
            $book_id = $lookup_table[$book_input_no_accent];
        } else {
            $book_id = $lookup_table[$book_input];
        }

        $book = BBM_Books::get_book_by_id($book_id);
        if (!$book) {
            return null;
        }

        return array(
            'book_id' => $book_id,
            'book' => $book,
            'chapter' => $chapter,
            'verse' => $verse,
            'verse_end' => $verse_end,
        );
    }

    /**
     * Remove accents from string
     */
    private function remove_accents($string)
    {
        $accents = array(
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        );

        return strtr(mb_strtolower($string), $accents);
    }

    /**
     * Builds API path from parsed reference
     * 
     * @param array $parsed Parsed reference data
     * @param string $version Bible version
     * @return string API path
     */
    private function build_api_path($parsed, $version)
    {
        // Get slug in English for API (API uses English slugs)
        $book_slug = BBM_Books::get_book_slug($parsed['book_id'], 'en');
        $path = '/' . strtolower($version) . '/' . $book_slug . '/' . $parsed['chapter'];

        if ($parsed['verse']) {
            if ($parsed['verse_end'] && $parsed['verse_end'] !== $parsed['verse']) {
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
    private function validate_reference($reference)
    {
        if (empty(trim($reference))) {
            return 'Empty reference';
        }

        if (strlen($reference) > 100) {
            return 'Reference too long (maximum 100 characters)';
        }

        // Check maximum range (50 verses)
        if (preg_match('/(\d+):(\d+)-(\d+)/', $reference, $matches)) {
            $start = intval($matches[2]);
            $end = intval($matches[3]);
            if ($end - $start + 1 > 50) {
                return 'Interval too large (maximum 50 verses)';
            }
        }

        return true;
    }

    /**
     * Waits for minimum delay between requests (avoids burst limit)
     */
    private function wait_if_needed()
    {
        $now = microtime(true) * 1000000;
        $elapsed = $now - self::$last_request_time;

        if (self::$last_request_time > 0 && $elapsed < $this->min_request_delay) {
            usleep((int) ($this->min_request_delay - $elapsed));
        }

        self::$last_request_time = microtime(true) * 1000000;
    }

    /**
     * Checks rate limit in response headers
     * 
     * @param array $response Response from wp_remote_get
     */
    private function check_rate_limit($response)
    {
        $remaining = wp_remote_retrieve_header($response, 'X-RateLimit-Remaining');

        if ($remaining !== '') {
            $remaining_int = intval($remaining);

            if ($remaining_int < 10) {
                if ($remaining_int < 5) {
                    sleep(2);
                }
            }
        }
    }

    /**
     * Fetches a verse from the API
     * 
     * @param string $reference Bible reference (e.g. "John 3:16")
     * @param string $version Bible version (e.g. "nvt")
     * @return array|null Verse data or null on error
     */
    public function get_verse($reference, $version = null)
    {
        $version = $version ?: $this->options['versao'];
        $version = strtolower($version);

        // Validate reference
        $validation = $this->validate_reference($reference);
        if ($validation !== true) {
            return null;
        }

        // Parse reference
        $parsed = $this->parse_reference($reference);
        if (!$parsed) {
            return null;
        }

        // Build API path
        $path = $this->build_api_path($parsed, $version);
        if (!$path) {
            return null;
        }

        // Check cache first
        if ($this->is_cache_enabled()) {
            $cached = $this->get_from_cache($path, $version);
            if ($cached !== false) {
                return $cached;
            }
        }

        // Make API request
        $data = $this->make_request($path);

        if ($data) {
            // Add reference info to response
            $data['reference'] = $parsed['book']['names'][$this->locale] . ' ' . $parsed['chapter'];
            if ($parsed['verse']) {
                $data['reference'] .= ':' . $parsed['verse'];
                if ($parsed['verse_end'] && $parsed['verse_end'] !== $parsed['verse']) {
                    $data['reference'] .= '-' . $parsed['verse_end'];
                }
            }

            // Save to cache
            if ($this->is_cache_enabled()) {
                $this->save_to_cache($path, $version, $data);
            }
            return $data;
        }

        return null;
    }

    /**
     * Makes API request with retry and exponential backoff
     * 
     * @param string $path API endpoint path
     * @param array $args Query parameters
     * @param int $attempt Current attempt number
     * @return array|null
     */
    private function make_request($path, $args = array(), $attempt = 1)
    {
        $max_retries = 3;
        $timeout = isset($this->options['timeout']) ? intval($this->options['timeout']) : 10;
        if ($timeout < 5) {
            $timeout = 10;
        }

        $this->wait_if_needed();

        $url = $this->api_url . $path;
        if (!empty($args)) {
            $url = add_query_arg($args, $url);
        }

        $response = wp_remote_get($url, array(
            'timeout' => $timeout,
            'sslverify' => true,
            'headers' => array(
                'Accept' => 'application/json',
                'User-Agent' => 'Midvash-WP-Plugin/' . BBM_VERSION,
            ),
        ));

        // Connection error
        if (is_wp_error($response)) {
            if ($attempt < $max_retries) {
                sleep(pow(2, $attempt - 1));
                return $this->make_request($path, $args, $attempt + 1);
            }
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $this->check_rate_limit($response);

        // Rate limit exceeded
        if ($status_code === 429) {
            if ($attempt < $max_retries) {
                $reset_at = wp_remote_retrieve_header($response, 'X-RateLimit-Reset');
                if ($reset_at) {
                    $reset_timestamp = strtotime($reset_at);
                    $wait_seconds = max(1, min(60, $reset_timestamp - time()));
                    sleep($wait_seconds);
                } else {
                    sleep(pow(2, $attempt));
                }
                return $this->make_request($path, $args, $attempt + 1);
            }
            return null;
        }

        // Server error
        if ($status_code >= 500 && $attempt < $max_retries) {
            sleep(pow(2, $attempt - 1));
            return $this->make_request($path, $args, $attempt + 1);
        }

        // Success
        if ($status_code === 200) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            if ($data) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Checks if cache is enabled
     */
    private function is_cache_enabled()
    {
        return isset($this->options['cache_enabled']) && $this->options['cache_enabled'];
    }

    /**
     * Generates cache key
     */
    private function get_cache_key($reference, $version)
    {
        return 'bbm_' . md5($reference . '_' . $version);
    }

    /**
     * Fetches from cache
     */
    private function get_from_cache($reference, $version)
    {
        $key = $this->get_cache_key($reference, $version);
        return get_transient($key);
    }

    /**
     * Saves to cache
     */
    private function save_to_cache($reference, $version, $data)
    {
        $key = $this->get_cache_key($reference, $version);
        $ttl = isset($this->options['cache_ttl']) ? intval($this->options['cache_ttl']) : 2592000;
        set_transient($key, $data, $ttl);
    }

    /**
     * Fetches available Bible versions, optionally filtered by locale
     * 
     * @param string|null $locale Filter versions by language (pt-br, en, es)
     * @return array|null Array of versions or null on error
     */
    public function get_versions($locale = null)
    {
        $locale = $locale ?: $this->locale;
        $locale = BBM_Books::normalize_locale($locale);
        
        // Normalize locale for API (pt-br -> pt for filtering)
        $api_locale = ($locale === 'pt-br') ? 'pt' : $locale;
        
        $cache_key = 'bbm_versions_' . $api_locale;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $result = $this->make_request('/versions', array('locale' => $api_locale));

        if ($result && isset($result['versions'])) {
            $versions = $result['versions'];
            set_transient($cache_key, $versions, 7 * DAY_IN_SECONDS);
            return $versions;
        }

        // Fallback: fetch all and filter client-side
        $all_cache_key = 'bbm_versions_all';
        $all_cached = get_transient($all_cache_key);
        if ($all_cached !== false) {
            $filtered = array_filter($all_cached, function($v) use ($locale) {
                $version_locale = isset($v['language']) ? $v['language'] : '';
                // Normalize for comparison
                if ($version_locale === 'pt-br' || $version_locale === 'pt') {
                    return ($locale === 'pt-br');
                }
                return ($version_locale === $locale);
            });
            if (!empty($filtered)) {
                return array_values($filtered);
            }
        }

        // Fetch all versions
        $all_result = $this->make_request('/versions');
        if ($all_result && isset($all_result['versions'])) {
            set_transient($all_cache_key, $all_result['versions'], 7 * DAY_IN_SECONDS);
            
            // Filter by locale
            $filtered = array_filter($all_result['versions'], function($v) use ($locale) {
                $version_locale = isset($v['language']) ? $v['language'] : '';
                // Normalize for comparison
                if ($version_locale === 'pt-br' || $version_locale === 'pt') {
                    return ($locale === 'pt-br');
                }
                return ($version_locale === $locale);
            });
            
            if (!empty($filtered)) {
                $filtered_versions = array_values($filtered);
                set_transient($cache_key, $filtered_versions, 7 * DAY_IN_SECONDS);
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
    public function get_votd($locale = null, $version = null)
    {
        $locale  = $locale  ?: $this->locale;
        $locale  = BBM_Books::normalize_locale($locale);
        $version = $version ?: BBM_Books::get_default_version($locale);

        $cache_key = 'bbm_votd_' . $locale . '_' . $version . '_' . gmdate('Y-m-d');
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $result = $this->make_request('/votd', array(
            'language' => $locale,
            'version'  => $version,
        ));

        if ($result && isset($result['text'])) {
            // Retry with locale default version if the chosen version returned an error
            if (isset($result['error'])) {
                $fallback = BBM_Books::get_default_version($locale);
                if ($fallback !== $version) {
                    $result = $this->make_request('/votd', array(
                        'language' => $locale,
                        'version'  => $fallback,
                    ));
                }
            }
        }

        if ($result && isset($result['text']) && !isset($result['error'])) {
            set_transient($cache_key, $result, DAY_IN_SECONDS);
            return $result;
        }

        return null;
    }

    /**
     * Fetches available Bible books
     */
    public function get_books()
    {
        $cache_key = 'bbm_books';
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $result = $this->make_request('/books');

        if ($result) {
            set_transient($cache_key, $result, 30 * DAY_IN_SECONDS);
            return $result;
        }

        return null;
    }

    /**
     * Clears all plugin cache
     */
    public function clear_cache()
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bbm_%'");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bbm_%'");
    }
}
