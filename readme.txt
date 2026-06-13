=== Bible by Midvash ===
Contributors: netogregorio
Tags: bible, biblia, bible verse, tooltip, gutenberg block
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 0.6.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bible references in posts become hover-tooltip links with full verse text. 9 languages, 50+ versions, no setup, no API key.

== Description ==

**Bible by Midvash** finds Bible references in your content and quietly turns each one into a styled link with a hover-tooltip that shows the verse text — fetched live from the Midvash Bible API and cached locally for performance. No account, no API key, no configuration required for the default experience.

It works on any post or page in any of nine languages, recognises both the full book name ("John 3:16") and abbreviations ("Jo 3:16", "Jn 3:16"), accepts the colon and dot separators, supports verse ranges ("John 3:16-18") and chapter-only references ("Psalms 23"), and links each reference to the matching verse on [midvash.com](https://midvash.com) using the locale-appropriate URL.

= What you get =

* **Auto-detection of Bible references** in `the_content` (posts and pages) — including localised book names and abbreviations across 9 languages.
* **Hover tooltip with the verse text**, fetched from the public Midvash API and cached for 30 days by default.
* **Gutenberg block** "Bible Verse — Midvash" (category: Text) to insert any verse explicitly in the block editor with per-block version, language and style overrides.
* **Verse of the Day** widget (Appearance → Widgets) and `[bbm_votd]` shortcode.
* **Schema.org Quotation microdata** on every reference link, helping search engines surface rich results for verse pages.
* **9 user-facing locales:** Portuguese (Brazil), English, Spanish, French, German, Italian, Russian, Korean, Chinese (Simplified) — each with localised book names, slugs, abbreviations and URLs.
* **50+ Bible versions** across those locales, listed dynamically from the API as new translations are added.
* **Dark mode** via `prefers-color-scheme` and matching opt-in body classes (`dark-mode`, `theme-dark`, `wp-dark-mode`).
* **Accessibility:** keyboard focus, ARIA roles, reduced-motion support, print styles.
* **Customisable link styling** — colour, underline (style + colour), open in new tab, show/hide version in tooltip.

= Supported reference formats =

* `John 3:16` / `João 3:16` / `Juan 3:16` / `Jean 3:16` / `Johannes 3:16` / `Giovanni 3:16` / `Иоанн 3:16` / `요한복음 3:16` / `约翰福音 3:16`
* `John 3.16` (alternative separator)
* `John 3:16-18` (verse ranges, up to 50 verses)
* `Gn 1:1`, `Jn 3:16` (abbreviations in every locale)
* `Psalms 23` / `Salmos 23` / `Psaumes 23` (chapter-only, no verse)

= Available Bible versions =

The version list below mirrors `api.midvash.com/versions` and may grow over time. The plugin's Settings → Bible by Midvash page always shows the current catalogue.

**Portuguese (Brazil) — 20:** NVT, NVI, ACF, AA, ARA, ARC, AS21, JFAA, KJA, KJF, NAA, NBV, NTLH, MSGPT, ALMEIDA-LIVRE, BPM, ONBV, NVA, BLPT, TFT
**English — 11:** NLT, NIV, ESV, KJV, NKJV, MSG, WEB, ASV, YLT, DRA, GENEVA1599
**Spanish — 5:** NTV, NVIES, RVR1960, RVR1909, RVG
**French — 5:** LSG, DARBY-FR, MARTIN1744, CRAMPON, FRASBL
**German — 5:** LUTH1912, SCHL1951, ELB1905, MEN, LUTH1545
**Italian — 3:** NRI, DIODATI, RIVEDUTA
**Russian — 1:** SYNODAL
**Korean — 1:** KOR
**Chinese (Simplified) — 2:** CUV, CUVS

Default version per locale: NVT (pt-br), NLT (en), NTV (es), LSG (fr), LUTH1912 (de), NRI (it), SYNODAL (ru), KOR (ko), CUV (zh).

== Installation ==

1. Upload the `bible-by-midvash` folder to `/wp-content/plugins/`, **or** install via Plugins → Add New → search "Bible by Midvash".
2. Activate the plugin through the Plugins screen.
3. (Optional) Go to **Settings → Bible by Midvash** to pick the language, default Bible version, link styling, and which words (Bíblia, version names, book names) should be auto-linked.
4. Write a post that mentions a verse like `John 3:16` — that's it. Hover the link to see the tooltip.

== Frequently Asked Questions ==

= Do I need an API key? =

No. The Midvash API is public and read-only. The plugin works out of the box.

= Does the tooltip load fast? =

Yes. The first fetch for a given verse hits the API; subsequent loads for any visitor read from a local cache (30 days by default, configurable in Settings → Cache & Performance). On installs with a persistent object cache (Redis, Memcached) the second hit stays in memory.

= Can I insert a specific verse manually instead of relying on auto-detection? =

Yes — use the **Bible Verse — Midvash** Gutenberg block, available in the Text category of the block inserter. You can override the version, the display language, and toggle the reference badge / link per block.

= How do I show a daily verse on the sidebar? =

Add the **Verse of the Day — Midvash** widget via Appearance → Widgets, or drop `[bbm_votd]` anywhere in your content. Both accept `locale` and `version` overrides; the daily verse is cached for 24 hours.

= Which post types does auto-detection cover? =

Single posts and pages (`is_singular()` true). The Gutenberg block and the VOTD shortcode work everywhere.

= How do I change the link colour or underline style? =

Settings → Bible by Midvash → General. Toggle "Enable custom color" and / or "Underline links", then pick the colour and style.

= What languages are supported? =

Nine: Portuguese (Brazil), English, Spanish, French, German, Italian, Russian, Korean and Chinese (Simplified). The plugin's UI strings are translated; book names, abbreviations and URL slugs are also localised in those nine languages.

== External services ==

This plugin relies on the **Midvash Bible API** (`api.midvash.com`), a third-party service operated by Midvash, to fetch verse content and the catalogue of available Bible versions.

* **What data is sent.** The plugin sends the Bible reference being looked up (e.g. "John 3:16") and the chosen version slug (e.g. "nvt"). No personal data is transmitted: no IP geolocation, no visitor ID, no analytics. The HTTP `User-Agent` header identifies the plugin and its version so the API team can debug compatibility issues (e.g. `Midvash-WP-Plugin/0.6.0`).
* **When it is sent.** A verse request is made the first time a visitor hovers a reference (or when the Gutenberg block / VOTD widget renders). Subsequent loads for the same verse are served from a local cache for the configured TTL (default 30 days).
* **Where it goes.** `https://api.midvash.com` over HTTPS, hosted on Cloudflare's edge.
* **Service provider.** Midvash (https://midvash.com)
* **Terms of Service.** https://midvash.com/terms
* **Privacy Policy.** https://midvash.com/privacy

If you disable the plugin or clear the cache, no further data is sent.

== Privacy ==

This plugin does **not** collect, store or transmit any visitor data. No cookies are set, no analytics scripts are loaded, no third-party trackers are injected. Verse lookups are anonymous: the upstream API receives only the verse reference being looked up, not who is looking it up. AJAX rate limiting buckets requests by a salted hash of the requester's IP that lives in the WordPress transient store for 60 seconds and is then discarded — the raw IP is never stored.

== Screenshots ==

1. Settings page — choose the display language, Bible version, link styling and which terms to auto-link.
2. A post showing automatically detected references with the verse tooltip on hover.
3. Gutenberg "Bible Verse — Midvash" block with per-block version and language overrides.
4. Verse of the Day widget in the sidebar.

== Changelog ==

= 0.6.1 =
* **Infrastructure.** Update and download endpoints moved from wordpress.midvash.com to midvash.app (the plugin now checks `midvash.app/api/wordpress/update-info.json`). No functional change; existing installs should be updated once to point at the new endpoint.

= 0.6.0 =
* **WordPress.org compliance.** Drops the explicit `load_plugin_textdomain` call (WP 4.6+ auto-loads `.mo` files by slug match and Plugin Check flags it as "discouraged"), wraps every `$_POST`/`$_GET` access in `wp_unslash()` + `sanitize_*`, validates the admin tab against a whitelist, and moves the inline admin JS into a properly enqueued asset with `wp_localize_script`. The settings page now passes WordPress Plugin Check with zero errors.
* **Security.** Adds `manage_options` capability check to the admin-only `bbm_get_versions` AJAX endpoint, adds a per-IP rate limit (120 verse lookups / minute) on the public `bbm_get_verse` endpoint, switches `wp_remote_get` to `reject_unsafe_urls` and `limit_response_size` to harden against SSRF and oversized payloads.
* **Performance.** Memoises the regex matching pattern and lookup table per request (one build vs N) — material gain on archive pages and themes that render multiple post bodies. Reads now go object-cache → transient → API (was transient → API). Retry budget on the synchronous request path cut from 3× / 4 s backoff to 2× / 1 s, eliminating PHP-FPM timeouts when the upstream is slow.
* **Robustness.** Centralised reference parsing in `BBM_Books::parse_reference()` — the parser, the API client and the Gutenberg block now share one implementation, one accent-tolerance pass and one chapter-range check.
* **i18n.** Front-end tooltip strings now flow through `__()` + the bundled `.mo` files instead of a hardcoded 9-locale lookup, so new locales come for free as `.po` files land. Adds an `mb_strtolower` fallback for shared hosts without the `mbstring` extension.
* **Cleanup.** Adds `uninstall.php` to remove plugin options and transients on deletion. Stops wiping the cache on deactivation (was punishing users who deactivate to debug). Removes dead `BBM_API::get_books()` and `BBM_API::clear_cache()` methods.
* **Catalogue.** README updated with the live list of Bible versions per locale (now 53 across 9 languages), including the new pt-br versions BPM, ONBV, NVA, BLPT, TFT, the new Spanish RVR1909 and RVG, the new French CRAMPON and FRASBL, and the new German MEN and LUTH1545.

= 0.5.0 =
* Added **Gutenberg block** `Bible Verse — Midvash` (category: Text). Authors can insert any Bible reference directly into the block editor; the verse is fetched and rendered server-side by PHP on each page load. Supports all 9 locales, version and language overrides per-block, show/hide reference, and optional link to Midvash. Schema.org `Quotation` microdata included in output. No build step required.

= 0.4.0 =
* Added **Verse of the Day** widget and `[bbm_votd]` shortcode powered by the Midvash API. Place the widget in any sidebar or use `[bbm_votd]` anywhere in your content. Supports all 9 locales, per-widget language and version overrides, and caches the daily verse for 24 hours.

= 0.3.0 =
* Added Schema.org `Quotation` microdata to all auto-detected Bible reference links. Search engines can now parse every verse reference as structured data, improving rich-result eligibility for sites using the plugin.

= 0.2.1 =
* The plugin brand name "Bible by Midvash" is now translated in the admin menu and settings page header — appears as "Bíblia by Midvash" (Portuguese), "Biblia by Midvash" (Spanish), "Bibel by Midvash" (German), "Bibbia by Midvash" (Italian), "Библия by Midvash" (Russian), "성경 by Midvash" (Korean), "圣经 by Midvash" (Chinese), keeping "Midvash" as the brand.

= 0.2.0 =
* Added full support for 6 new content locales: French, German, Italian, Russian, Korean, and Chinese (Simplified). All 9 locales (pt-br, en, es, fr, de, it, ru, ko, zh) now have working book detection, localized URLs, and matching Bible versions.
* Book data is now generated from the Midvash API via `scripts/sync-books.ts`, eliminating ~500 lines of hardcoded data.

= 0.1.0 =
* Added translations for 9 locales: Portuguese (Brazil), English, Spanish, French, German, Italian, Russian, and Chinese (Simplified).
* Refreshed `.pot` template to match the current source strings.

= 0.0.1 =
* Initial release of Bible by Midvash.
* Auto-detects Bible references in posts and pages, transforming them into links with verse tooltips on hover.
* 35+ Bible versions across Portuguese, English and Spanish.
* Customizable link styling (color, underline style, open in new tab).
* 30-day verse cache for tooltip performance.
* Powered by the public Midvash API at api.midvash.com.
* Distributed via https://wordpress.midvash.com.

== Upgrade Notice ==

= 0.6.0 =
WordPress.org compliance pass — security, performance and i18n cleanups. Significantly faster on archive pages, no more PHP notices on WP 6.7+. New uninstall.php cleans up after itself. Catalogue updated with 11 new Bible versions across pt-br, es, fr and de.

= 0.5.0 =
Adds the Gutenberg "Bible Verse" block — insert any verse directly into the block editor with version, language, and style controls.

= 0.4.0 =
Adds the Verse of the Day widget and `[bbm_votd]` shortcode — place daily verses in sidebars or posts with a single line.

= 0.3.0 =
Adds Schema.org Quotation microdata to every Bible reference link — no configuration needed, improves SEO structured data automatically.

= 0.2.1 =
Translates the plugin brand name into the 8 non-English locales (Bíblia, Biblia, Bibel, Bibbia, Библия, 성경, 圣经…) for a fully localized admin experience.

= 0.2.0 =
Adds full content support for French, German, Italian, Russian, Korean and Chinese.

= 0.1.0 =
Adds translations for 9 locales (pt_BR, en_US, es_ES, fr_FR, de_DE, it_IT, ru_RU, ko_KR, zh_CN).

= 0.0.1 =
First release.
