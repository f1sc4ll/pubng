<?php
/**
 * Performance hygiene: strip WordPress front-end bloat and add cheap
 * pageview multipliers. Every item here is toggled from settings.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function (): void {
		if ( pubweb_settings( 'performance.remove_emoji' ) ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
			add_filter( 'tiny_mce_plugins', static fn( $p ) => is_array( $p ) ? array_diff( $p, array( 'wpemoji' ) ) : $p );
		}

		if ( pubweb_settings( 'performance.disable_embeds' ) ) {
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
			remove_action( 'wp_head', 'wp_oembed_add_host_js' );
			add_filter( 'embed_oembed_discover', '__return_false' );
		}

		// Trim head clutter that adds bytes without front-end value.
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	}
);

/**
 * Drop the global inline SVG duotone/svg-filters block on the frontend
 * (WP injects ~2KB even when unused).
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
		remove_action( 'wp_footer', 'the_block_template_skip_link' );
	}
);

/**
 * Speculation Rules: prefetch the next page on hover/proximity. This is
 * the cheap pageview multiplier every reference site used. WordPress 6.8+
 * ships its own API; we only nudge the mode to "moderate"/"prefetch".
 *
 * @param array<string,mixed> $config Core speculation config.
 * @return array<string,mixed>
 */
add_filter(
	'wp_speculation_rules_configuration',
	static function ( $config ) {
		if ( ! pubweb_settings( 'performance.speculation_rules' ) ) {
			return null; // Disable entirely.
		}
		if ( is_array( $config ) ) {
			$config['mode']      = 'prefetch';
			$config['eagerness'] = 'moderate';
		}
		return $config;
	}
);

/**
 * Default-on lazy loading is already core behaviour; expose the kill
 * switch so an operator can force eager above-the-fold if desired.
 */
add_filter(
	'wp_lazy_loading_enabled',
	static function ( $enabled ) {
		return pubweb_settings( 'performance.lazy_images' ) ? $enabled : false;
	}
);
