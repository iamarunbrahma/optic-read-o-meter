=== Optic Read-O-Meter ===
Contributors: arunbrahma
Tags: reading time, post, estimate, block
Requires at least: 6.3
Tested up to: 6.9
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Drops an "X min read" line above your posts. Five styles, color palettes, image-aware time, plural wording, shortcode and block.

== Description ==

Drops a small "X min read" line above each post on your site. Same little badge Medium uses and most blogs use.

The plugin counts the words, divides by reading speed, prepends the badge above the content. That is the whole thing. No tracking, no upsells, no third-party scripts.

What you can change from Settings, Optic Read-O-Meter:

* Words per minute (default 200, the global newspaper average).
* Style: pill, minimal, dark, outline, or none.
* Icon: clock, coffee, book, or none.
* Colors via the WordPress color picker, plus seven one-click palettes (Emerald, Sky, Rose, Amber, Violet, Slate, Dark).
* Position above, below, or both.
* Separate singular and plural templates, one for "1 minute read" and one for "5 minutes read".
* Optional image time. Each image adds 12 seconds by default, the same convention Medium uses.
* Minimum word count to skip the badge on short posts.
* Which post types get the auto-prepended badge.

Per post, an editor sidebar lets you hide the badge for a specific post or set a manual minute count.

A Reading Time Gutenberg block ships with the plugin. Drop it anywhere in the block editor and pick "Styled badge" or "Plain text" from the sidebar. There is also a shortcode for non-block contexts: `[optrom_reading_time]`, `[optrom_reading_time format="badge"]`, or `[optrom_reading_time template="About %s"]`.

Performance: word and image counts are cached in post meta on save. After that, rendering reads one integer. No external HTTP, no analytics.

For developers, two filters: `optrom_words_per_minute` to override the WPM per post, and `optrom_label` to replace the entire label string.

The plugin cleans up after itself. Option and post meta are deleted on uninstall.

== Installation ==

1. Upload the `optic-read-o-meter` folder to `/wp-content/plugins/`, or install from Plugins, Add New.
2. Activate.
3. Visit Settings, Optic Read-O-Meter to configure.

== Frequently Asked Questions ==

= Can I change the reading speed? =

Yes. Set it on the settings page, or hook the `optrom_words_per_minute` filter for per-post control.

= Does it work on pages or custom post types? =

Yes. Tick the post types you want on the settings page. The default is posts only. The block and shortcode work everywhere regardless.

= Can I hide the badge on a single post? =

Yes. Each supported post type has a Reading Time meta box on the editor with a hide toggle and a manual minutes override.

= My posts have lots of images. Are they counted? =

Off by default. Tick "Count images" on the settings page if you want each image to add a few seconds to the estimate (12 by default, same as Medium).

= Does it support non-Latin scripts? =

Yes. `str_word_count` covers most Latin scripts. For CJK and similar, the plugin falls back to a Unicode-aware split.

= Does it track visitors? =

No. No tracking, no analytics, no external requests.

== Screenshots ==

1. The reading-time badge above a post, Pill style with the Clock icon.
2. The settings page: reading speed, display, appearance, and wording, with a live preview.
3. Appearance options: badge style, icon, and seven one-click color palettes.
4. The Reading Time block in the editor, with badge or plain-text output.
5. A different look: Dark style with the Coffee icon.
6. The [optrom_reading_time] shortcode, rendered inline anywhere.

== Changelog ==

= 1.0.0 =
* Initial release.
* Auto-prepends a "X min read" badge on single-post views (and any other public post type you opt into).
* Reading Time Gutenberg block, with styled-badge or plain-text formats.
* `[optrom_reading_time]` shortcode.
* Sectioned settings page with a live preview that updates as you tweak.
* Seven one-click color palettes (Emerald, Sky, Rose, Amber, Violet, Slate, Dark) and the WordPress color picker.
* Five styles (pill, minimal, dark, outline, none) and four icons (clock, coffee, book, none).
* Optional image-aware estimate (12 seconds per image by default).
* Separate singular and plural label templates.
* Per-post hide toggle and minute override.
* Word and image counts cached in post meta on save.
* `uninstall.php` cleans up the plugin option and per-post meta.
* Filters: `optrom_words_per_minute`, `optrom_label`.
