=== Bible by Midvash ===
Contributors: netogregorio
Tags: bible, biblia, reference, biblical, tooltip, multilingual
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 0.0.6
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically identifies Bible references in posts and creates links with tooltips via the Midvash service. Supports multiple languages (Portuguese, English, Spanish).

== Description ==

Midvash is a plugin that detects Bible references within your WordPress post content and automatically transforms them into links to the [Midvash](https://midvash.com) website.

**When hovering over a reference, a tooltip displays the full verse text!**

= Features =

* Automatic detection of Bible references
* **Tooltip with verse text** (via API)
* **Multi-language support** (Portuguese, English, Spanish)
* Support for ranges: John 3:16-18
* 35+ available Bible versions
* Cache for improved performance
* Dark mode support
* Simple configuration
* Language-specific URLs and book names

= Supported Formats =

* John 3:16 / João 3:16 / Juan 3:16
* John 3.16 / João 3.16 / Juan 3.16
* John 3:16-18 (ranges)
* Gn 1:1 / Gn 1:1 (abbreviations)
* Psalms 23 / Salmos 23 / Salmos 23 (entire chapter)

= Available Versions =

**Portuguese (20+):** NVT, NVI, ACF, AA, ARA, ARC, AS21, JFAA, KJA, KJF, NAA, NBV, NTLH, BPT, CLEM, MSGPT, NRI, NVIES, NVL, OSMH, RVR1960, VULG

**English (5+):** NIV, NLT, ESV, KJV, NKJV, MSGEN

**Spanish (5+):** NTV, NTVES, NVIES, RVR1960

= Supported Languages =

* **Portuguese (Brazil)** - Default version: NVT
* **English** - Default version: NLT
* **Spanish** - Default version: NTV

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

Go to Settings > Midvash and select your preferred language (Portuguese, English, or Spanish). This will affect the URLs generated and the book names used in links.

= How do I change the link color? =

Go to Settings > Midvash and use the color picker.

== Changelog ==

= 0.0.1 =
* Initial release of Bible by Midvash.
* Auto-detects Bible references in posts and pages, transforming them into links with verse tooltips on hover.
* 35+ Bible versions across Portuguese, English and Spanish.
* Customizable link styling (color, underline style, open in new tab).
* 30-day verse cache for tooltip performance.
* Powered by the public Midvash API at api.midvash.com.
* Distributed via https://wordpress.midvash.com.

== Upgrade Notice ==

= 0.0.1 =
First release.
