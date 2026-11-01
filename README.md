# Midvash Bible References — WordPress plugin

> 🌐 **English** · [Português (BR)](./README.pt-BR.md) · [Español](./README.es.md)

[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](LICENSE)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b.svg)](https://wordpress.org/)

Auto-detect Bible references in your WordPress posts and turn them into hover-tooltip links — no API key, no signup, no setup beyond installing the plugin.

> Download and docs at **[midvash.com/wordpress-plugin](https://midvash.com/wordpress-plugin)**

## What it does

- Recognizes references like `John 3:16`, `Jo 3.16`, `Salmos 23`, `Rom 8:28-30` — in **English, Portuguese, and Spanish**, with accents and book abbreviations.
- Replaces them with subtle links to [midvash.com](https://midvash.com) on the frontend, opening the full verse in a hover tooltip.
- 48 openly licensed Bible versions to pick as your site default (ONBV, Almeida Livre, BSB, KJV, RVR1909, and more), each shown with its licence attribution.
- Customizable link color, underline style, and tooltip behavior.
- Free forever, no account required.

## Install

### From the [midvash.com/wordpress-plugin](https://midvash.com/wordpress-plugin) site

1. Download the latest `.zip` from [midvash.com/wordpress-plugin](https://midvash.com/wordpress-plugin)
2. WordPress admin → Plugins → Add New → Upload Plugin → select the zip
3. Activate, configure under **Settings → Midvash Bible References**

### From source (this repo)

```bash
cd wp-content/plugins/
git clone https://github.com/midvash/bible-wordpress-plugin.git midvash-bible-references
```

Then activate in the WordPress admin.

## Updates

Once the plugin is listed in the WordPress.org directory, updates arrive through WordPress like any other directory plugin. Until then, new versions are attached to each [GitHub Release](https://github.com/midvash/bible-by-midvash/releases).

## Architecture

- **`midvash-bible-references.php`** — main plugin file, hooks, AJAX endpoints, transient cache cleanup
- **`includes/class-mvbr-parser.php`** — reference detection (regex + book-name dictionaries per locale)
- **`includes/class-mvbr-api.php`** — calls the Midvash public API (read-only, no auth) with rate-limit handling and transient cache
- **`includes/class-mvbr-admin.php`** — settings screen
- **`includes/class-mvbr-books.php`** — book metadata (names, abbreviations, chapters) per language
- **`assets/js/mvbr-tooltip.js`** — frontend tooltip rendering
- **`translations/`** — `.po` files for import into translate.wordpress.org (not shipped in the ZIP)

## Security

Standard WordPress security hygiene applied:

- All AJAX endpoints protected by `wp_nonce` (`check_ajax_referer`)
- All file entry points guarded by `ABSPATH`
- All user input sanitized (`sanitize_text_field` + `wp_unslash`)
- All output escaped (`esc_html`, `esc_attr`, `esc_url`)
- Admin settings require `manage_options` capability
- No external code execution, no `eval`, no shell calls
- HTTPS-only API calls with `sslverify = true`

The plugin only **reads** from the Midvash public API — it doesn't send any post content, user data, or telemetry.

## Contributing

PRs welcome — especially for:

- New language support (book names + regex tweaks in `class-mvbr-books.php` and `class-mvbr-parser.php`)
- Translations — on translate.wordpress.org once the plugin is listed (source `.po` files in `translations/`)
- Performance improvements

Open an issue first for larger changes.

## License

GPL v2 or later — see [LICENSE](LICENSE).

## Related projects

- **[bible-data](https://github.com/midvash/bible-data)** — the public-domain Bible dataset (33 versions, 22 languages) behind the Midvash reader
- **[bible-data-js](https://github.com/midvash/bible-data-js)** — TypeScript SDK for the dataset
- **[bible-cross-references](https://github.com/midvash/bible-cross-references)** — 453 curated thematic cross-references
- **[Midvash](https://midvash.com)** — the online Bible reader this plugin links to

## The Midvash ecosystem

Part of [**Midvash**](https://midvash.com) — a free Bible reading & study platform. Everything is open and interlinks:

| | |
|---|---|
| 📖 **Reader (web)** | [midvash.com](https://midvash.com) — 9 languages |
| 📱 **iOS app** | [midvash.com/ios](https://midvash.com/ios) |
| 🔌 **API** | [api.midvash.com](https://api.midvash.com) · [`bible-api`](https://github.com/midvash/bible-api) |
| 🤖 **MCP server** | [mcp.midvash.com](https://mcp.midvash.com) · [`bible-mcp`](https://github.com/midvash/bible-mcp) |
| 🧩 **WordPress plugin** | [midvash.com/wordpress-plugin](https://midvash.com/wordpress-plugin) · [`bible-wordpress-plugin`](https://github.com/midvash/bible-wordpress-plugin) |
| 🧩 **EmDash plugin** | [midvash.com/emdash-plugin](https://midvash.com/emdash-plugin) · [`emdash-plugin-bible`](https://github.com/midvash/emdash-plugin-bible) |
| 🌐 **Chrome extension** | [midvash.com/chrome-extension](https://midvash.com/chrome-extension) · [`bible-chrome-extension`](https://github.com/midvash/bible-chrome-extension) |
| 📦 **Open data** | [`bible-data`](https://github.com/midvash/bible-data) · [`bible-data-js`](https://github.com/midvash/bible-data-js) · [`bible-cross-references`](https://github.com/midvash/bible-cross-references) |

<sub>Free & open, built by [Midvash](https://midvash.com) · [midvash.com](https://midvash.com)</sub>

