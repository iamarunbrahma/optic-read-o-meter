# Optic Read-O-Meter

A small WordPress plugin that drops an "X min read" line above every post. Same little badge you've seen on Medium and most blogs.

That's the whole thing. Activate the plugin and the badge shows up. Tweak it from Settings, Optic Read-O-Meter if you want.

**Links:** [WordPress.org plugin page](https://wordpress.org/plugins/optic-read-o-meter/) (with an in-browser live preview) · [Project site](https://arunbrahma.com/optic-read-o-meter/)

## Install

Plugins, Add New, search "Optic Read-O-Meter", install, activate.

Or upload the .zip via Plugins, Add New, Upload Plugin.

## What you can change

Settings, Optic Read-O-Meter. One page, with a live preview pinned to the top.

- **Words per minute.** Default 200 (rough newspaper average). 250 reads breezier, 150 reads slower.
- **Style.** Pill, minimal, dark, outline, or none.
- **Icon.** Clock, coffee cup, book, or none.
- **Colors.** Two color pickers, plus seven preset palettes: Emerald, Sky, Rose, Amber, Violet, Slate, Dark.
- **Position.** Above, below, or both.
- **Wording.** Two templates, one for "1 minute read" and one for "5 minutes read". Each takes `%s` for the number.
- **Image time.** Off by default. Switch on and each image adds a few seconds (12 by default, same as Medium).
- **Post types.** Posts only by default. Tick pages, products, anything else you want.
- **Minimum word count.** Skip the badge on short posts.

Per post, an editor sidebar lets you hide the badge or set a manual minute count.

## Block and shortcode

A "Reading Time" block lives in the block inserter. Pick "Styled badge" or "Plain text" in the sidebar.

Shortcode for non-block contexts:

```
[optrom_reading_time]                       plain text
[optrom_reading_time format="badge"]        styled badge
[optrom_reading_time template="About %s"]   custom wrapper
```

## FAQ

**Will it slow my site?**

The word count gets cached in post meta on save. Front-end rendering reads one integer.

**Themes?**

Any standard WordPress theme. The badge inherits your typography and spacing.

**Tracking, analytics, third-party calls?**

None. Plain PHP and CSS, runs on your own server.

**Languages other than English?**

`str_word_count` handles Latin scripts. For CJK and similar, the plugin falls back to a Unicode-aware split.

**Different reading speed for one specific post?**

Hook `optrom_words_per_minute` and return a different number based on category, custom field, whatever.

## For developers

Two filters:

- `optrom_words_per_minute( int $wpm, WP_Post $post )` overrides the global WPM.
- `optrom_label( string $label, int $minutes, WP_Post $post )` replaces the whole label string.

Four post-meta keys:

- `_optrom_words` (int): cached word count, refreshed on `save_post`.
- `_optrom_images` (int): cached image count, refreshed on `save_post`.
- `_optrom_disabled` ('1' or absent): hides the badge on that specific post.
- `_optrom_override_minutes` (int): forces a specific minute count.

Pull requests, issues, and ideas welcome.

## License

GPLv2 or later. Same license WordPress uses.
