<?php
/**
 * Asset loading — the speed-critical path.
 *
 * Strategy (validated against the reference publisher sites):
 *   1. Inline critical CSS in <head> so first paint never waits on a
 *      stylesheet request.
 *   2. Load the full stylesheet asynchronously (print-media swap).
 *   3. Emit ad-stack resource hints (preconnect + optional gpt.js
 *      preload) as early as possible — time-to-first-ad is revenue.
 *   4. Ship theme JS with `defer`, no jQuery on the frontend.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resource hints: print BEFORE everything else in <head> so the browser
 * opens ad/CDN connections during HTML parse. Hooked at priority 0.
 */
add_action(
	'wp_head',
	static function (): void {
		$ads = pubweb_settings( 'ads' );

		$origins = array();
		if ( pubweb_settings( 'performance.system_fonts' ) ) {
			// System fonts → no font origin needed.
		} else {
			$origins[] = 'https://fonts.gstatic.com';
		}

		if ( ! empty( $ads['enabled'] ) ) {
			foreach ( (array) ( $ads['preconnect_origins'] ?? array() ) as $origin ) {
				$origins[] = $origin;
			}
			if ( ! empty( $ads['loader_script_url'] ) ) {
				$parts = wp_parse_url( $ads['loader_script_url'] );
				if ( ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
					$origins[] = $parts['scheme'] . '://' . $parts['host'];
				}
			}
		}

		foreach ( array_unique( array_filter( $origins ) ) as $origin ) {
			printf(
				'<link rel="preconnect" href="%s" crossorigin>' . "\n",
				esc_url( $origin )
			);
		}

		// Preload the GPT library so the first ad request is not gated
		// behind script discovery. Only when ads are on and requested.
		if ( ! empty( $ads['enabled'] ) && pubweb_settings( 'performance.preload_gpt' ) ) {
			echo '<link rel="preload" as="script" href="https://securepubads.g.doubleclick.net/tag/js/gpt.js" crossorigin>' . "\n";
		}
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
			// Inject the operator's accent as a custom property both stylesheets read.
			$css = ':root{--pw-accent:' . $accent . '}' . $css;
			wp_add_inline_style( 'pubweb-critical', $css );
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
