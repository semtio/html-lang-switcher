=== HTML Lang Switcher (Single Post/Page) ===
Contributors: 7on
Tags: language, html, lang, locale, rtl, metabox
Requires at least: 5.4
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Select the HTML `lang` (and `dir`) per post or page. Adds a small metabox to choose a locale.
Applies only on singular templates. Automatically disables itself when WPML or Polylang is active.

== Installation ==
1. Upload the ZIP via **Plugins → Add New → Upload Plugin** and activate it.
2. Edit a post or page: use the **Page language (HTML lang)** metabox to pick a locale.
3. Leave it blank to use the site default language.

== Filters ==
- `hls_supported_post_types` — change supported post types (default: post, page).
- `hls_supported_locales` — add/remove locales shown in the dropdown.

== Changelog ==
= 1.0.0 =
* Initial release.
