=== Bible by Midvash ===
Contributors: netogregorio
Tags: bible, biblia, reference, biblical, tooltip, multilingual
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 0.4.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically identifies Bible references in posts and creates links with tooltips via the Midvash service. Supports 9 languages (Portuguese, English, Spanish, French, German, Italian, Russian, Korean, Chinese).

== Description ==

Midvash is a plugin that detects Bible references within your WordPress post content and automatically transforms them into links to the [Midvash](https://midvash.com) website.

**When hovering over a reference, a tooltip displays the full verse text!**

= Features =

* Automatic detection of Bible references
* **Tooltip with verse text** (via API)
* **Verse of the Day** widget and `[bbm_votd]` shortcode
* **Schema.org Quotation** structured data on every reference link
* **Multi-language support** (9 languages: Portuguese, English, Spanish, French, German, Italian, Russian, Korean, Chinese)
* Support for ranges: John 3:16-18
* 35+ available Bible versions
* Cache for improved performance
* Dark mode support
* Simple configuration
* Language-specific URLs and book names

= Supported Formats =

* John 3:16 / João 3:16 / Juan 3:16 / Jean 3:16 / Johannes 3:16 / Giovanni 3:16 / Иоанн 3:16 / 요한복음 3:16 / 约翰福音 3:16
* John 3.16 (alternative separator)
* John 3:16-18 (ranges)
* Gn 1:1 (abbreviations)
* Psalms 23 / Salmos 23 / Psaumes 23 / Psalmen 23 / Salmi 23 / Псалтирь 23 / 시편 23 / 诗篇 23 (entire chapter)

= Available Versions =

**Portuguese (15):** NVT, NVI, ACF, AA, ARA, ARC, AS21, JFAA, KJA, KJF, NAA, NBV, NTLH, MSGPT, ALMEIDA-LIVRE

**English (11):** NIV, NLT, ESV, KJV, NKJV, MSG, WEB, ASV, YLT, DRA, GENEVA1599

**Spanish (3):** NTV, NVIES, RVR1960

**French (3):** LSG, DARBY-FR, MARTIN1744

**German (3):** LUTH1912, SCHL1951, ELB1905

**Italian (3):** NRI, DIODATI, RIVEDUTA

**Russian (1):** SYNODAL

**Korean (1):** KOR

**Chinese (2):** CUV, CUVS

= Supported Languages =

* **Portuguese (Brazil)** - Default version: NVT
* **English** - Default version: NLT
* **Spanish** - Default version: NTV
* **French** - Default version: LSG
* **German** - Default version: LUTH1912
* **Italian** - Default version: NRI
* **Russian** - Default version: SYNODAL
* **Korean** - Default version: KOR
* **Chinese (Simplified)** - Default version: CUV

== External services ==

This plugin relies on the **Midvash API** to function. Midvash is a third-party service that provides the biblical content for the links and tooltips.

*   **What it is used for:** Fetching Bible verse content, book names, and abbreviations dynamically.
*   **What data is sent:** The plugin sends the Bible reference (e.g., "John 3:16") and the preferred version (e.g., "NVT") to the API whenever a tooltip is triggered or content is parsed. No personal user data is transmitted.
*   **Service Provider:** Midvash (https://midvash.com).
*   **Terms of Service:** [https://midvash.com/terms](https://midvash.com/terms)
*   **Privacy Policy:** [https://midvash.com/privacy](https://midvash.com/privacy)

== Installation ==

1. Upload the `bible-by-midvash` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Settings > Midvash** to adjust the options

Note: The plugin folder should be named `bible-by-midvash` to function correctly.

== Frequently Asked Questions ==

= Does the tooltip take long to appear? =

The plugin uses caching to improve performance. The first time a verse is accessed, it is fetched from the API. Subsequent times, it will be loaded from the cache.

= Can I use it on pages other than posts? =

The plugin works on single posts and pages. Support for other post types may be added in future versions.

= How do I change the language? =

Go to Settings > Midvash and select your preferred language from 9 supported locales (Portuguese, English, Spanish, French, German, Italian, Russian, Korean, Chinese). This determines the URLs generated and the book names used in links.

= How do I change the link color? =

Go to Settings > Midvash and use the color picker.

== Changelog ==

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
* Added translations for 9 locales: Portuguese (Brazil), English, Spanish, French, German, Italian, Russian, Korean, and Chinese (Simplified).
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
