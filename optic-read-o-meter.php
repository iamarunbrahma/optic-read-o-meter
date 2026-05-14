<?php
/**
 * Plugin Name:       Optic Read-O-Meter
 * Plugin URI:        https://arunbrahma.com/optic-read-o-meter/
 * Description:       Adds an estimated reading time above post content with style / color / position options, a [optrom_reading_time] shortcode, and a Gutenberg block.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.2
 * Author:            Arun Brahma
 * Author URI:        https://arunbrahma.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       optic-read-o-meter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OPTROM_VERSION', '1.0.0' );
define( 'OPTROM_OPTION', 'optrom_settings' );
define( 'OPTROM_DEFAULT_WPM', 200 );
define( 'OPTROM_META_WORDS', '_optrom_words' );
define( 'OPTROM_META_IMAGES', '_optrom_images' );
define( 'OPTROM_META_DISABLED', '_optrom_disabled' );
define( 'OPTROM_META_OVERRIDE', '_optrom_override_minutes' );

/**
 * Option defaults.
 */
function optrom_defaults() {
	return array(
		'wpm'               => OPTROM_DEFAULT_WPM,
		'style'             => 'pill',     // pill | minimal | dark | outline | none
		'color'             => '#065F46',  // text color (emerald-800)
		'bg'                => '#D1FAE5',  // background color (emerald-100)
		'position'          => 'above',    // above | below | both
		'icon'              => 'clock',    // clock | coffee | book | none
		'template'          => '%s min read', // singular: %s = minute count
		'template_plural'   => '%s min read', // plural; same default for invisible upgrade
		'post_types'        => array( 'post' ), // post types that auto-prepend the badge
		'min_words'         => 0,          // skip the auto-badge below this word count (0 = no threshold)
		'count_images'      => false,      // add seconds_per_image for each <img> in the content
		'seconds_per_image' => 12,         // matches Medium's "12s per image" convention
	);
}

/**
 * Named color palettes shown as one-click swatches on the settings page.
 * The keys are stable identifiers; the labels render in the UI.
 */
function optrom_color_presets() {
	return array(
		'emerald' => array( 'label' => 'Emerald', 'color' => '#065F46', 'bg' => '#D1FAE5' ),
		'sky'     => array( 'label' => 'Sky',     'color' => '#075985', 'bg' => '#E0F2FE' ),
		'rose'    => array( 'label' => 'Rose',    'color' => '#9F1239', 'bg' => '#FFE4E6' ),
		'amber'   => array( 'label' => 'Amber',   'color' => '#92400E', 'bg' => '#FEF3C7' ),
		'violet'  => array( 'label' => 'Violet',  'color' => '#5B21B6', 'bg' => '#EDE9FE' ),
		'slate'   => array( 'label' => 'Slate',   'color' => '#1F2937', 'bg' => '#F1F5F9' ),
		'dark'    => array( 'label' => 'Dark',    'color' => '#F1F5F9', 'bg' => '#1F2937' ),
	);
}

/**
 * Public, badge-eligible post types (used by the post-types setting and the meta box).
 */
function optrom_eligible_post_types() {
	$types = get_post_types( array( 'public' => true ), 'names' );
	unset( $types['attachment'] );
	return array_values( $types );
}

function optrom_get_settings() {
	$opts = get_option( OPTROM_OPTION, array() );
	return wp_parse_args( is_array( $opts ) ? $opts : array(), optrom_defaults() );
}

/**
 * Count words in raw post content. Strips tags + shortcodes; falls back to a
 * Unicode-aware split when str_word_count returns 0 (non-Latin scripts).
 */
function optrom_count_words( $content ) {
	$text = wp_strip_all_tags( strip_shortcodes( (string) $content ) );
	$text = trim( $text );
	if ( '' === $text ) {
		return 0;
	}

	$words = str_word_count( $text );
	if ( $words <= 0 ) {
		$words = count( preg_split( '/\s+/u', $text ) );
	}
	return (int) $words;
}

/**
 * Count <img> tags in raw post content. Galleries / shortcode-rendered images
 * are not expanded. Keeps the count cheap and predictable.
 */
function optrom_count_images( $content ) {
	$content = (string) $content;
	if ( '' === $content || false === stripos( $content, '<img' ) ) {
		return 0;
	}
	return (int) preg_match_all( '/<img\b[^>]*>/i', $content );
}

/**
 * Cached word count for a post. Reads _optrom_words first, recomputes on miss.
 */
function optrom_get_word_count( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return 0;
	}
	$cached = get_post_meta( $post->ID, OPTROM_META_WORDS, true );
	if ( '' !== $cached && null !== $cached ) {
		return (int) $cached;
	}
	$words = optrom_count_words( $post->post_content );
	update_post_meta( $post->ID, OPTROM_META_WORDS, $words );
	return $words;
}

/**
 * Cached image count for a post. Reads _optrom_images first, recomputes on miss.
 */
function optrom_get_image_count( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return 0;
	}
	$cached = get_post_meta( $post->ID, OPTROM_META_IMAGES, true );
	if ( '' !== $cached && null !== $cached ) {
		return (int) $cached;
	}
	$images = optrom_count_images( $post->post_content );
	update_post_meta( $post->ID, OPTROM_META_IMAGES, $images );
	return $images;
}

/**
 * save_post handler: refresh cached word + image counts.
 */
function optrom_save_word_count( $post_id, $post ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! isset( $post->post_status ) || 'auto-draft' === $post->post_status ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, OPTROM_META_WORDS, optrom_count_words( $post->post_content ) );
	update_post_meta( $post_id, OPTROM_META_IMAGES, optrom_count_images( $post->post_content ) );
}
add_action( 'save_post', 'optrom_save_word_count', 10, 2 );

function optrom_icon_svg( $icon ) {
	switch ( $icon ) {
		case 'coffee':
			return '<svg class="optrom-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 8h1a4 4 0 010 8h-1"/><path d="M3 8h14v9a4 4 0 01-4 4H7a4 4 0 01-4-4V8z"/><line x1="6" y1="2" x2="6" y2="4"/><line x1="10" y1="2" x2="10" y2="4"/><line x1="14" y1="2" x2="14" y2="4"/></svg>';
		case 'book':
			return '<svg class="optrom-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>';
		case 'clock':
			return '<svg class="optrom-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
		default:
			return '';
	}
}

/**
 * Compute the reading minutes for a post, factoring in WPM, image-aware
 * seconds, and the per-post override.
 */
function optrom_compute_minutes( $post, $settings = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return 1;
	}
	if ( null === $settings ) {
		$settings = optrom_get_settings();
	}

	$override = get_post_meta( $post->ID, OPTROM_META_OVERRIDE, true );
	if ( '' !== $override && (int) $override > 0 ) {
		return (int) $override;
	}

	$wpm = (int) apply_filters( 'optrom_words_per_minute', $settings['wpm'], $post );
	if ( $wpm <= 0 ) {
		$wpm = OPTROM_DEFAULT_WPM;
	}

	$words         = optrom_get_word_count( $post );
	$total_seconds = ( $words > 0 ) ? ( $words / $wpm ) * 60 : 0;

	if ( ! empty( $settings['count_images'] ) ) {
		$sec_per_img    = max( 0, (int) $settings['seconds_per_image'] );
		$total_seconds += optrom_get_image_count( $post ) * $sec_per_img;
	}

	if ( $total_seconds <= 0 ) {
		return 1;
	}
	return max( 1, (int) ceil( $total_seconds / 60 ) );
}

/**
 * Build the reading-time label (text only, no icon).
 */
function optrom_get_label( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}

	$settings = optrom_get_settings();
	$minutes  = optrom_compute_minutes( $post, $settings );

	$singular = (string) $settings['template'];
	if ( '' === $singular || false === strpos( $singular, '%s' ) ) {
		$singular = '%s min read';
	}
	$plural = isset( $settings['template_plural'] ) ? (string) $settings['template_plural'] : '';
	if ( '' === $plural || false === strpos( $plural, '%s' ) ) {
		$plural = $singular;
	}

	$template = ( 1 === $minutes ) ? $singular : $plural;
	// str_replace (not sprintf): user templates may contain literal % or
	// extra %s tokens; sprintf would throw ArgumentCountError/ValueError.
	$label = str_replace( '%s', (string) $minutes, $template );

	return apply_filters( 'optrom_label', $label, $minutes, $post );
}

/**
 * Build the badge HTML (with icon + style class).
 */
function optrom_build_badge( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$settings = optrom_get_settings();
	$label    = optrom_get_label( $post );
	if ( '' === $label ) {
		return '';
	}

	$style_class = 'optrom-style-' . sanitize_html_class( $settings['style'] );
	$icon_html   = optrom_icon_svg( $settings['icon'] );

	return sprintf(
		'<p class="optrom-reading-time %1$s" aria-label="%2$s">%3$s<span class="optrom-text">%4$s</span></p>',
		esc_attr( $style_class ),
		esc_attr( $label ),
		wp_kses( $icon_html, optrom_svg_allowed_html() ),
		esc_html( $label )
	);
}

/**
 * the_content filter.
 */
function optrom_prepend_to_content( $content ) {
	$settings = optrom_get_settings();

	$post_types = ! empty( $settings['post_types'] ) ? $settings['post_types'] : array( 'post' );
	if ( ! is_singular( $post_types ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$post = get_post();
	if ( ! $post ) {
		return $content;
	}

	if ( '1' === (string) get_post_meta( $post->ID, OPTROM_META_DISABLED, true ) ) {
		return $content;
	}

	$min_words = (int) $settings['min_words'];
	if ( $min_words > 0 && optrom_get_word_count( $post ) < $min_words ) {
		return $content;
	}

	$badge = optrom_build_badge( $post );
	if ( '' === $badge ) {
		return $content;
	}

	switch ( $settings['position'] ) {
		case 'below':
			return $content . $badge;
		case 'both':
			return $badge . $content . $badge;
		case 'above':
		default:
			return $badge . $content;
	}
}
add_filter( 'the_content', 'optrom_prepend_to_content', 20 );

/**
 * [optrom_reading_time] shortcode.
 */
function optrom_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'post_id'  => 0,
			'template' => '',
			'format'   => 'text', // text | badge
		),
		$atts,
		'optrom_reading_time'
	);

	$post_id = (int) $atts['post_id'];
	$target  = $post_id ? $post_id : null;

	if ( 'badge' === $atts['format'] ) {
		return optrom_build_badge( $target );
	}

	$label = optrom_get_label( $target );
	if ( '' === $label ) {
		return '';
	}
	if ( '' !== $atts['template'] && false !== strpos( $atts['template'], '%s' ) ) {
		return esc_html( str_replace( '%s', $label, $atts['template'] ) );
	}
	return esc_html( $label );
}
add_shortcode( 'optrom_reading_time', 'optrom_shortcode' );

/* -------------------------------------------------------------------------
 * Block: optrom/reading-time
 * ------------------------------------------------------------------------- */

function optrom_register_block() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	register_block_type(
		__DIR__ . '/blocks/reading-time',
		array(
			'render_callback' => 'optrom_render_block',
		)
	);
}
add_action( 'init', 'optrom_register_block' );

/**
 * Server-side render for the reading-time block. Reuses the same label /
 * badge helpers the shortcode and the content filter use.
 */
function optrom_render_block( $attributes, $content, $block ) {
	$post_id = 0;
	if ( $block instanceof WP_Block && isset( $block->context['postId'] ) ) {
		$post_id = (int) $block->context['postId'];
	}
	if ( ! $post_id ) {
		$post_id = (int) get_the_ID();
	}
	if ( ! $post_id ) {
		return '';
	}

	$format   = isset( $attributes['format'] ) ? sanitize_key( $attributes['format'] ) : 'badge';
	$template = isset( $attributes['template'] ) ? wp_strip_all_tags( (string) $attributes['template'] ) : '';

	if ( 'text' === $format ) {
		$label = optrom_get_label( $post_id );
		if ( '' === $label ) {
			return '';
		}
		if ( '' !== $template && false !== strpos( $template, '%s' ) ) {
			return esc_html( str_replace( '%s', $label, $template ) );
		}
		return esc_html( $label );
	}

	return optrom_build_badge( $post_id );
}

/**
 * Mirror the frontend badge styles into the block editor so the
 * ServerSideRender preview matches the live output.
 */
function optrom_enqueue_block_editor_assets() {
	$handle = 'optic-read-o-meter-block-editor';
	wp_register_style( $handle, false, array(), OPTROM_VERSION );
	wp_enqueue_style( $handle );
	// optrom_build_css() returns CSS built from hardcoded literals plus hex
	// colors already escaped via esc_attr() — safe to add as inline style.
	wp_add_inline_style( $handle, optrom_build_css() );
}
add_action( 'enqueue_block_editor_assets', 'optrom_enqueue_block_editor_assets' );

/**
 * Build the badge stylesheet from the current settings.
 */
function optrom_build_css() {
	$settings = optrom_get_settings();
	$color    = sanitize_hex_color( $settings['color'] );
	$bg       = sanitize_hex_color( $settings['bg'] );
	if ( ! $color ) {
		$color = '#065F46';
	}
	if ( ! $bg ) {
		$bg = '#D1FAE5';
	}

	return '
.optrom-reading-time{--optrom-color:' . esc_attr( $color ) . ';--optrom-bg:' . esc_attr( $bg ) . ';display:inline-flex;align-items:center;gap:.4em;margin:0 0 1em;font-size:.85em;line-height:1;color:var(--optrom-color);}
.optrom-reading-time .optrom-icon{flex:none;}
.optrom-style-pill{padding:.35em .7em;background:var(--optrom-bg);border-radius:999px;}
.optrom-style-minimal{padding:0;background:transparent;opacity:.75;}
.optrom-style-dark{padding:.35em .7em;background:var(--optrom-bg);border-radius:4px;}
.optrom-style-outline{padding:.3em .65em;background:transparent;border:1px solid var(--optrom-color);border-radius:4px;}
.optrom-style-none{padding:0;background:transparent;}
';
}

/**
 * Frontend styles (base + per-style-preset + dynamic color vars).
 */
function optrom_enqueue_styles() {
	$handle = 'optic-read-o-meter';
	wp_register_style( $handle, false, array(), OPTROM_VERSION );
	wp_enqueue_style( $handle );
	wp_add_inline_style( $handle, optrom_build_css() );
}
add_action( 'wp_enqueue_scripts', 'optrom_enqueue_styles' );

/* -------------------------------------------------------------------------
 * Admin: Settings page
 * ------------------------------------------------------------------------- */

function optrom_register_settings() {
	register_setting(
		'optrom_settings_group',
		OPTROM_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'optrom_sanitize_settings',
			'default'           => optrom_defaults(),
		)
	);
}
add_action( 'admin_init', 'optrom_register_settings' );

function optrom_sanitize_settings( $input ) {
	$defaults = optrom_defaults();
	$out      = $defaults;
	if ( ! is_array( $input ) ) {
		return $out;
	}

	if ( isset( $input['wpm'] ) ) {
		$wpm = (int) $input['wpm'];
		$out['wpm'] = ( $wpm > 0 && $wpm <= 2000 ) ? $wpm : $defaults['wpm'];
	}

	$allowed_styles = array( 'pill', 'minimal', 'dark', 'outline', 'none' );
	if ( isset( $input['style'] ) && in_array( $input['style'], $allowed_styles, true ) ) {
		$out['style'] = $input['style'];
	}

	$allowed_positions = array( 'above', 'below', 'both' );
	if ( isset( $input['position'] ) && in_array( $input['position'], $allowed_positions, true ) ) {
		$out['position'] = $input['position'];
	}

	$allowed_icons = array( 'clock', 'coffee', 'book', 'none' );
	if ( isset( $input['icon'] ) && in_array( $input['icon'], $allowed_icons, true ) ) {
		$out['icon'] = $input['icon'];
	}

	if ( isset( $input['color'] ) ) {
		$c = sanitize_hex_color( $input['color'] );
		$out['color'] = $c ? $c : $defaults['color'];
	}
	if ( isset( $input['bg'] ) ) {
		$c = sanitize_hex_color( $input['bg'] );
		$out['bg'] = $c ? $c : $defaults['bg'];
	}

	if ( isset( $input['template'] ) ) {
		$tpl = wp_strip_all_tags( (string) $input['template'] );
		$out['template'] = ( '' !== $tpl && false !== strpos( $tpl, '%s' ) ) ? $tpl : $defaults['template'];
	}

	if ( isset( $input['template_plural'] ) ) {
		$tpl = wp_strip_all_tags( (string) $input['template_plural'] );
		// Allow blank; falls back to the singular template at render time.
		if ( '' === $tpl ) {
			$out['template_plural'] = '';
		} else {
			$out['template_plural'] = ( false !== strpos( $tpl, '%s' ) ) ? $tpl : $defaults['template_plural'];
		}
	}

	$out['count_images'] = ! empty( $input['count_images'] );

	if ( isset( $input['seconds_per_image'] ) ) {
		$spi = (int) $input['seconds_per_image'];
		$out['seconds_per_image'] = ( $spi >= 0 && $spi <= 300 ) ? $spi : $defaults['seconds_per_image'];
	}

	if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
		$eligible = optrom_eligible_post_types();
		$picked   = array_values( array_intersect( $eligible, array_map( 'sanitize_key', $input['post_types'] ) ) );
		$out['post_types'] = ! empty( $picked ) ? $picked : $defaults['post_types'];
	}

	if ( isset( $input['min_words'] ) ) {
		$mw = (int) $input['min_words'];
		$out['min_words'] = ( $mw >= 0 && $mw <= 100000 ) ? $mw : $defaults['min_words'];
	}

	return $out;
}

function optrom_add_settings_page() {
	add_options_page(
		__( 'Optic Read-O-Meter', 'optic-read-o-meter' ),
		__( 'Optic Read-O-Meter', 'optic-read-o-meter' ),
		'manage_options',
		'optic-read-o-meter',
		'optrom_render_settings_page'
	);
}
add_action( 'admin_menu', 'optrom_add_settings_page' );

/**
 * wp_kses allow-list for the bundled icon SVGs.
 */
function optrom_svg_allowed_html() {
	$attrs = array(
		'class'           => true,
		'viewbox'         => true,
		'width'           => true,
		'height'          => true,
		'fill'            => true,
		'stroke'          => true,
		'stroke-width'    => true,
		'stroke-linecap'  => true,
		'stroke-linejoin' => true,
		'aria-hidden'     => true,
	);
	return array(
		'svg'      => $attrs,
		'circle'   => array( 'cx' => true, 'cy' => true, 'r' => true ),
		'path'     => array( 'd' => true ),
		'line'     => array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true ),
		'polyline' => array( 'points' => true ),
	);
}

function optrom_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s         = optrom_get_settings();
	$opt       = OPTROM_OPTION;
	$pt_labels = array();
	foreach ( optrom_eligible_post_types() as $pt ) {
		$obj = get_post_type_object( $pt );
		$pt_labels[ $pt ] = $obj && isset( $obj->labels->name ) ? $obj->labels->name : $pt;
	}

	// Initial preview always shows the plural form (3 minutes); JS keeps it in sync from then on.
	$preview_template = isset( $s['template_plural'] ) ? (string) $s['template_plural'] : '';
	if ( '' === $preview_template || false === strpos( $preview_template, '%s' ) ) {
		$preview_template = (string) $s['template'];
	}
	if ( '' === $preview_template || false === strpos( $preview_template, '%s' ) ) {
		$preview_template = '%s min read';
	}
	$preview_label = str_replace( '%s', '3', $preview_template );
	$preview_class = 'optrom-style-' . sanitize_html_class( $s['style'] );
	$preview_icon  = optrom_icon_svg( $s['icon'] );

	$styles = array(
		'pill'    => __( 'Pill', 'optic-read-o-meter' ),
		'minimal' => __( 'Minimal', 'optic-read-o-meter' ),
		'dark'    => __( 'Dark', 'optic-read-o-meter' ),
		'outline' => __( 'Outline', 'optic-read-o-meter' ),
		'none'    => __( 'None (text only)', 'optic-read-o-meter' ),
	);
	$icons = array(
		'clock'  => __( 'Clock', 'optic-read-o-meter' ),
		'coffee' => __( 'Coffee', 'optic-read-o-meter' ),
		'book'   => __( 'Book', 'optic-read-o-meter' ),
		'none'   => __( 'No icon', 'optic-read-o-meter' ),
	);
	$positions = array(
		'above' => __( 'Above content', 'optic-read-o-meter' ),
		'below' => __( 'Below content', 'optic-read-o-meter' ),
		'both'  => __( 'Above and below', 'optic-read-o-meter' ),
	);
	?>
	<div class="wrap optrom-settings">
		<h1><?php esc_html_e( 'Optic Read-O-Meter', 'optic-read-o-meter' ); ?></h1>

		<div class="optrom-preview-card" id="optrom-preview-card">
			<p class="optrom-preview-eyebrow"><?php esc_html_e( 'Live preview', 'optic-read-o-meter' ); ?></p>
			<p class="optrom-reading-time <?php echo esc_attr( $preview_class ); ?>" id="optrom-preview-badge" aria-label="<?php echo esc_attr( $preview_label ); ?>" style="--optrom-color:<?php echo esc_attr( $s['color'] ); ?>;--optrom-bg:<?php echo esc_attr( $s['bg'] ); ?>;"><span class="optrom-icon-slot"><?php echo wp_kses( $preview_icon, optrom_svg_allowed_html() ); ?></span><span class="optrom-text"><?php echo esc_html( $preview_label ); ?></span></p>
			<p class="optrom-preview-help"><?php esc_html_e( 'Updates as you change settings. Click Save to apply on your site.', 'optic-read-o-meter' ); ?></p>
		</div>
		<template id="optrom-icon-templates">
			<span data-icon="clock"><?php echo wp_kses( optrom_icon_svg( 'clock' ), optrom_svg_allowed_html() ); ?></span>
			<span data-icon="coffee"><?php echo wp_kses( optrom_icon_svg( 'coffee' ), optrom_svg_allowed_html() ); ?></span>
			<span data-icon="book"><?php echo wp_kses( optrom_icon_svg( 'book' ), optrom_svg_allowed_html() ); ?></span>
			<span data-icon="none"></span>
		</template>

		<form method="post" action="options.php" id="optrom-form">
			<?php settings_fields( 'optrom_settings_group' ); ?>

			<div class="optrom-section">
				<h2><?php esc_html_e( 'Calculation', 'optic-read-o-meter' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="optrom_wpm"><?php esc_html_e( 'Words per minute', 'optic-read-o-meter' ); ?></label></th>
						<td><input name="<?php echo esc_attr( $opt ); ?>[wpm]" id="optrom_wpm" type="number" min="1" max="2000" value="<?php echo esc_attr( $s['wpm'] ); ?>" class="small-text" /> <p class="description"><?php esc_html_e( 'Average reading speed (default 200, the global newspaper average).', 'optic-read-o-meter' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Count images', 'optic-read-o-meter' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[count_images]" id="optrom_count_images" value="1" <?php checked( ! empty( $s['count_images'] ) ); ?> />
								<?php esc_html_e( 'Add image viewing time to the estimate', 'optic-read-o-meter' ); ?>
							</label>
							<p>
								<label for="optrom_spi" style="margin-right:6px;"><?php esc_html_e( 'Seconds per image:', 'optic-read-o-meter' ); ?></label>
								<input name="<?php echo esc_attr( $opt ); ?>[seconds_per_image]" id="optrom_spi" type="number" min="0" max="300" value="<?php echo esc_attr( $s['seconds_per_image'] ); ?>" class="small-text" />
							</p>
							<p class="description"><?php esc_html_e( 'Off by default. When enabled, each <img> in the post adds this many seconds. 12 matches Medium’s convention.', 'optic-read-o-meter' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="optrom_min_words"><?php esc_html_e( 'Minimum word count', 'optic-read-o-meter' ); ?></label></th>
						<td><input name="<?php echo esc_attr( $opt ); ?>[min_words]" id="optrom_min_words" type="number" min="0" max="100000" value="<?php echo esc_attr( $s['min_words'] ); ?>" class="small-text" /> <p class="description"><?php esc_html_e( 'Hide the badge on posts shorter than this. 0 disables the threshold.', 'optic-read-o-meter' ); ?></p></td>
					</tr>
				</table>
			</div>

			<div class="optrom-section">
				<h2><?php esc_html_e( 'Display', 'optic-read-o-meter' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Display on', 'optic-read-o-meter' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Post types where the badge appears automatically', 'optic-read-o-meter' ); ?></legend>
								<?php foreach ( $pt_labels as $pt => $label ) : ?>
									<label class="optrom-pt-label">
										<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[post_types][]" value="<?php echo esc_attr( $pt ); ?>" <?php checked( in_array( $pt, $s['post_types'], true ) ); ?> />
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
								<p class="description"><?php esc_html_e( 'The badge auto-prepends to single-view content for the selected post types. The block and shortcode work everywhere.', 'optic-read-o-meter' ); ?></p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="optrom_position"><?php esc_html_e( 'Position', 'optic-read-o-meter' ); ?></label></th>
						<td>
							<select name="<?php echo esc_attr( $opt ); ?>[position]" id="optrom_position">
								<?php foreach ( $positions as $k => $v ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['position'], $k ); ?>><?php echo esc_html( $v ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
			</div>

			<div class="optrom-section">
				<h2><?php esc_html_e( 'Appearance', 'optic-read-o-meter' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="optrom_style"><?php esc_html_e( 'Style', 'optic-read-o-meter' ); ?></label></th>
						<td>
							<select name="<?php echo esc_attr( $opt ); ?>[style]" id="optrom_style">
								<?php foreach ( $styles as $k => $v ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['style'], $k ); ?>><?php echo esc_html( $v ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="optrom_icon"><?php esc_html_e( 'Icon', 'optic-read-o-meter' ); ?></label></th>
						<td>
							<select name="<?php echo esc_attr( $opt ); ?>[icon]" id="optrom_icon">
								<?php foreach ( $icons as $k => $v ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['icon'], $k ); ?>><?php echo esc_html( $v ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Color presets', 'optic-read-o-meter' ); ?></th>
						<td>
							<div class="optrom-palette-grid" role="group" aria-label="<?php esc_attr_e( 'Preset color palettes', 'optic-read-o-meter' ); ?>">
								<?php foreach ( optrom_color_presets() as $key => $p ) : ?>
									<?php /* translators: %s is the palette name (e.g. Emerald). */ ?>
									<button type="button" class="optrom-palette-swatch" data-preset="<?php echo esc_attr( $key ); ?>" data-color="<?php echo esc_attr( $p['color'] ); ?>" data-bg="<?php echo esc_attr( $p['bg'] ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Apply %s palette', 'optic-read-o-meter' ), $p['label'] ) ); ?>">
										<span class="optrom-palette-chip" style="background:<?php echo esc_attr( $p['bg'] ); ?>;color:<?php echo esc_attr( $p['color'] ); ?>;border-color:<?php echo esc_attr( $p['color'] ); ?>;"><span><?php echo esc_html( $p['label'] ); ?></span></span>
									</button>
								<?php endforeach; ?>
							</div>
							<p class="description"><?php esc_html_e( 'Click any preset to fill the colors below. You can fine-tune them after.', 'optic-read-o-meter' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="optrom_color"><?php esc_html_e( 'Text color', 'optic-read-o-meter' ); ?></label></th>
						<td><input name="<?php echo esc_attr( $opt ); ?>[color]" id="optrom_color" type="text" value="<?php echo esc_attr( $s['color'] ); ?>" class="optrom-color-field" placeholder="#065F46" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="optrom_bg"><?php esc_html_e( 'Background color', 'optic-read-o-meter' ); ?></label></th>
						<td><input name="<?php echo esc_attr( $opt ); ?>[bg]" id="optrom_bg" type="text" value="<?php echo esc_attr( $s['bg'] ); ?>" class="optrom-color-field" placeholder="#D1FAE5" /></td>
					</tr>
				</table>
			</div>

			<div class="optrom-section">
				<h2><?php esc_html_e( 'Wording', 'optic-read-o-meter' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="optrom_template"><?php esc_html_e( 'Singular template', 'optic-read-o-meter' ); ?></label></th>
						<?php /* translators: %s is replaced by the minute count at render time. */ ?>
						<td><input name="<?php echo esc_attr( $opt ); ?>[template]" id="optrom_template" type="text" value="<?php echo esc_attr( $s['template'] ); ?>" class="regular-text" /> <p class="description"><?php esc_html_e( 'Used when the post is a 1-minute read. %s is the minute count.', 'optic-read-o-meter' ); ?> <code>%s min read</code> · <code>%s minute read</code></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="optrom_template_plural"><?php esc_html_e( 'Plural template', 'optic-read-o-meter' ); ?></label></th>
						<td><input name="<?php echo esc_attr( $opt ); ?>[template_plural]" id="optrom_template_plural" type="text" value="<?php echo esc_attr( isset( $s['template_plural'] ) ? $s['template_plural'] : '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( $s['template'] ); ?>" /> <p class="description"><?php esc_html_e( 'Used for 2+ minute posts. Leave blank to reuse the singular template.', 'optic-read-o-meter' ); ?> <code>%s min read</code> · <code>%s minutes read</code></p></td>
					</tr>
				</table>
			</div>

			<p class="optrom-actions">
				<?php submit_button( null, 'primary', 'submit', false ); ?>
				<button type="button" class="button-link optrom-reset" id="optrom-reset-btn"><?php esc_html_e( 'Reset to defaults', 'optic-read-o-meter' ); ?></button>
			</p>
		</form>
	</div>
	<?php
}

function optrom_enqueue_admin_assets( $hook ) {
	if ( 'settings_page_optic-read-o-meter' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'wp-color-picker' );

	// Settings-page chrome + frontend badge styles share one handle.
	$preview_handle = 'optic-read-o-meter-preview';
	wp_register_style( $preview_handle, false, array(), OPTROM_VERSION );
	wp_enqueue_style( $preview_handle );
	wp_add_inline_style( $preview_handle, optrom_build_css() . optrom_admin_css() );

	// Settings-page JS lives in assets/admin.js. Depending on wp-color-picker
	// pulls jQuery + iris into the dependency chain. Data is attached 'before'
	// so window.optromAdmin is defined when the script runs.
	$admin_handle = 'optic-read-o-meter-admin';
	wp_enqueue_script(
		$admin_handle,
		plugins_url( 'assets/admin.js', __FILE__ ),
		array( 'wp-color-picker' ),
		OPTROM_VERSION,
		true
	);
	$data = array(
		'presets'  => optrom_color_presets(),
		'defaults' => optrom_defaults(),
	);
	wp_add_inline_script( $admin_handle, 'window.optromAdmin = ' . wp_json_encode( $data ) . ';', 'before' );
}
add_action( 'admin_enqueue_scripts', 'optrom_enqueue_admin_assets' );

/**
 * Settings-page-only chrome: section cards, palette swatches, layout polish.
 * The badge styles themselves still come from optrom_build_css().
 */
function optrom_admin_css() {
	return '
.optrom-settings .optrom-preview-card{margin:1em 0 1.5em;padding:18px 20px;background:#fff;border:1px solid #c3c4c7;border-radius:6px;max-width:780px;}
.optrom-settings .optrom-preview-eyebrow{margin:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#646970;}
.optrom-settings .optrom-preview-help{margin:8px 0 0;color:#646970;font-size:12px;}
.optrom-section{margin:0 0 16px;padding:0 20px 4px;background:#fff;border:1px solid #c3c4c7;border-radius:6px;max-width:780px;}
.optrom-section>h2{margin:0;padding:14px 0 10px;font-size:14px;font-weight:600;color:#1d2327;border-bottom:1px solid #f0f0f1;}
.optrom-section .form-table{margin-top:0;}
.optrom-section .form-table th{padding-top:18px;padding-bottom:14px;}
.optrom-section .form-table td{padding-top:14px;padding-bottom:14px;}
.optrom-pt-label{display:inline-block;margin-right:18px;}
.optrom-palette-grid{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 6px;}
.optrom-palette-swatch{appearance:none;background:none;border:0;padding:0;cursor:pointer;border-radius:999px;}
.optrom-palette-swatch:focus-visible{outline:2px solid #2271b1;outline-offset:2px;}
.optrom-palette-chip{display:inline-flex;align-items:center;padding:.45em 1em;border-radius:999px;border:1px solid transparent;font-size:12px;font-weight:500;line-height:1;transition:transform .12s ease;}
.optrom-palette-swatch:hover .optrom-palette-chip{transform:translateY(-1px);}
.optrom-palette-swatch.is-active .optrom-palette-chip{box-shadow:0 0 0 2px #fff,0 0 0 4px #2271b1;}
.optrom-actions{display:flex;align-items:center;gap:14px;margin:1.5em 0 2em;}
.optrom-actions .button-link{color:#646970;text-decoration:underline;}
.optrom-actions .button-link:hover{color:#2271b1;}
';
}

/* -------------------------------------------------------------------------
 * Per-post meta box: hide toggle + minutes override
 * ------------------------------------------------------------------------- */

function optrom_register_meta_boxes() {
	$settings   = optrom_get_settings();
	$post_types = ! empty( $settings['post_types'] ) ? $settings['post_types'] : array( 'post' );
	foreach ( $post_types as $pt ) {
		add_meta_box(
			'optrom_meta',
			__( 'Reading Time', 'optic-read-o-meter' ),
			'optrom_render_meta_box',
			$pt,
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'optrom_register_meta_boxes' );

function optrom_render_meta_box( $post ) {
	wp_nonce_field( 'optrom_meta_save', 'optrom_meta_nonce' );
	$disabled = '1' === (string) get_post_meta( $post->ID, OPTROM_META_DISABLED, true );
	$override = get_post_meta( $post->ID, OPTROM_META_OVERRIDE, true );
	$override = ( '' === $override ) ? '' : (int) $override;
	?>
	<p>
		<label>
			<input type="checkbox" name="optrom_meta_disabled" value="1" <?php checked( $disabled ); ?> />
			<?php esc_html_e( 'Hide reading time on this post', 'optic-read-o-meter' ); ?>
		</label>
	</p>
	<p>
		<label for="optrom_meta_override"><?php esc_html_e( 'Override minutes', 'optic-read-o-meter' ); ?></label><br />
		<input type="number" id="optrom_meta_override" name="optrom_meta_override" min="0" max="999" value="<?php echo esc_attr( $override ); ?>" class="small-text" />
		<span class="description"><?php esc_html_e( '(blank = auto)', 'optic-read-o-meter' ); ?></span>
	</p>
	<?php
}

function optrom_save_meta_box( $post_id ) {
	if ( ! isset( $_POST['optrom_meta_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['optrom_meta_nonce'] ) ), 'optrom_meta_save' ) ) {
		return;
	}
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( ! empty( $_POST['optrom_meta_disabled'] ) ) {
		update_post_meta( $post_id, OPTROM_META_DISABLED, '1' );
	} else {
		delete_post_meta( $post_id, OPTROM_META_DISABLED );
	}

	$override = isset( $_POST['optrom_meta_override'] ) ? absint( wp_unslash( $_POST['optrom_meta_override'] ) ) : 0;
	if ( $override > 0 && $override <= 999 ) {
		update_post_meta( $post_id, OPTROM_META_OVERRIDE, $override );
	} else {
		delete_post_meta( $post_id, OPTROM_META_OVERRIDE );
	}
}
add_action( 'save_post', 'optrom_save_meta_box' );
