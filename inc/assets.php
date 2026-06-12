<?php
/**
 * Asset loading — the speed-critical path.
 *
 * Strategy:
 *   1. Inline critical CSS in <head> so first paint never waits on a
 *      stylesheet request.
 *   2. Load the full stylesheet asynchronously (print-media swap).
 *   3. Ship theme JS with `defer`, no jQuery on the frontend.
 *
 * Ad delivery is intentionally NOT a theme concern — it is injected
 * automatically (Ad Inserter / the ad network loader), so the theme
 * ships no GPT preload or ad-origin hints.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

/**
 * Preconnect to the web-font origin only when system fonts are disabled.
 * Hooked at priority 0 so the hint lands early in <head>.
 */
add_action(
	'wp_head',
	static function (): void {
		if ( pubweb_settings( 'performance.system_fonts' ) ) {
			return; // System fonts → no external origin to warm.
		}
		echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
	},
	0
);

/**
 * Inline critical CSS, then enqueue the rest asynchronously.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		// Critical CSS inlined — never render-blocking.
		$critical = PUBWEB_DIR . 'assets/css/critical.css';
		if ( is_readable( $critical ) ) {
			wp_register_style( 'pubweb-critical', false );
			wp_enqueue_style( 'pubweb-critical' );
			$css    = (string) file_get_contents( $critical ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$accent = sanitize_hex_color( (string) pubweb_settings( 'branding.accent_color' ) ) ?: '#1769ff';
			$hbg    = sanitize_hex_color( (string) pubweb_settings( 'colors.header_bg' ) ) ?: '#ffffff';
			$htx    = sanitize_hex_color( (string) pubweb_settings( 'colors.header_text' ) ) ?: '#16181d';
			$fbg    = sanitize_hex_color( (string) pubweb_settings( 'colors.footer_bg' ) ) ?: '#0e1116';
			$ftx    = sanitize_hex_color( (string) pubweb_settings( 'colors.footer_text' ) ) ?: '#c5c9d1';
			$bbg    = sanitize_hex_color( (string) pubweb_settings( 'colors.body_bg' ) ) ?: '#ffffff';
			$lw     = (int) pubweb_settings( 'branding.logo_max_width', 180 );
			// Inject the operator's design tokens as custom properties both stylesheets read.
			$tokens = sprintf(
				':root{--pw-accent:%s;--pw-header-bg:%s;--pw-header-text:%s;--pw-footer-bg:%s;--pw-footer-text:%s;--pw-body-bg:%s;--pw-logo-w:%dpx}',
				$accent, $hbg, $htx, $fbg, $ftx, $bbg, $lw
			);
			wp_add_inline_style( 'pubweb-critical', $tokens . $css );
		}

		// Full stylesheet — loaded async via the filter below.
		wp_enqueue_style(
			'pubweb-main',
			PUBWEB_URI . 'assets/css/main.css',
			array(),
			PUBWEB_VERSION
		);

		// Frontend JS — deferred, dependency-free.
		wp_enqueue_script(
			'pubweb',
			PUBWEB_URI . 'assets/js/pubweb.js',
			array(),
			PUBWEB_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}
	}
);

/**
 * Turn the main stylesheet into an async (non-blocking) load.
 *
 * @param string $html   The full <link> tag.
 * @param string $handle Stylesheet handle.
 * @return string
 */
add_filter(
	'style_loader_tag',
	static function ( string $html, string $handle ): string {
		if ( 'pubweb-main' !== $handle ) {
			return $html;
		}
		// Load as print (non-blocking), then promote to all once loaded.
		$async = str_replace(
			"media='all'",
			"media='print' onload=\"this.media='all'\"",
			$html
		);
		return $async . sprintf( '<noscript>%s</noscript>', $html );
	},
	10,
	2
);
