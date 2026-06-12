<?php
/**
 * Ad scaffolding (V1).
 *
 * The theme ships ZERO ad markup in the static document body — exactly
 * like the reference network. Instead it:
 *   1. Reserves layout space for declared slots (anti-CLS wrappers) so
 *      ads paint without shifting content.
 *   2. Loads a single external loader script (e.g. ActView / a GAM
 *      bootstrap) that defines and injects the real GPT units.
 *
 * No visual ad editor in V1 — slots are declared in settings.ads.slots
 * (via the REST API). Rendering positions are exposed as theme hooks so
 * a future ad-inserter can target them without template edits.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validate/sanitize a list of ad-slot definitions. Called by the
 * settings store on every write (mass-assignment safe).
 *
 * @param array<int,mixed> $slots Raw slot list.
 * @return array<int,array<string,mixed>>
 */
function pubweb_sanitize_ad_slots( array $slots ): array {
	$allowed_positions = array( 'header', 'before_content', 'in_content', 'after_content', 'sidebar', 'footer', 'anchor' );
	$allowed_devices   = array( 'all', 'desktop', 'mobile' );
	$clean             = array();

	foreach ( $slots as $slot ) {
		if ( ! is_array( $slot ) || empty( $slot['id'] ) ) {
			continue;
		}
		$id = sanitize_key( (string) $slot['id'] );
		if ( '' === $id ) {
			continue;
		}

		$sizes = array();
		foreach ( (array) ( $slot['sizes'] ?? array() ) as $size ) {
			if ( is_array( $size ) && isset( $size[0], $size[1] ) ) {
				$sizes[] = array( max( 0, (int) $size[0] ), max( 0, (int) $size[1] ) );
			}
		}

		$clean[] = array(
			'id'       => $id,
			'device'   => in_array( $slot['device'] ?? 'all', $allowed_devices, true ) ? $slot['device'] : 'all',
			'position' => in_array( $slot['position'] ?? '', $allowed_positions, true ) ? $slot['position'] : 'in_content',
			'sizes'    => $sizes,
			'selector' => isset( $slot['selector'] ) ? sanitize_html_class( (string) $slot['selector'] ) : ( 'pw-ad-' . $id ),
			'in_content_after' => isset( $slot['in_content_after'] ) ? max( 1, (int) $slot['in_content_after'] ) : 3,
		);
	}
	return $clean;
}

/** Whether ads should render in the current context. */
function pubweb_ads_active(): bool {
	if ( ! pubweb_settings( 'ads.enabled' ) ) {
		return false;
	}
	if ( ( is_front_page() || is_home() ) && ! pubweb_settings( 'ads.ads_on_homepage' ) ) {
		return false;
	}
	return true;
}

/**
 * Render every declared slot for a position as a reserved-space wrapper.
 *
 * @param string $position One of the allowed slot positions.
 */
function pubweb_ad_slot( string $position ): void {
	if ( ! pubweb_ads_active() ) {
		return;
	}
	foreach ( (array) pubweb_settings( 'ads.slots', array() ) as $slot ) {
		if ( ( $slot['position'] ?? '' ) !== $position ) {
			continue;
		}
		$min_h = 0;
		foreach ( (array) ( $slot['sizes'] ?? array() ) as $size ) {
			$min_h = max( $min_h, (int) ( $size[1] ?? 0 ) );
		}
		$device_class = 'pw-ad--' . sanitize_html_class( (string) ( $slot['device'] ?? 'all' ) );
		printf(
			'<div class="pw-ad %1$s" id="%2$s" data-pw-slot="%3$s"%4$s aria-hidden="true"></div>' . "\n",
			esc_attr( $device_class ),
			esc_attr( (string) ( $slot['selector'] ?? 'pw-ad-' . $slot['id'] ) ),
			esc_attr( (string) $slot['id'] ),
			$min_h ? ' style="min-height:' . (int) $min_h . 'px"' : ''
		);
	}
}

/**
 * Inject the external ad loader once, deferred, near the end of <body>.
 */
add_action(
	'wp_footer',
	static function (): void {
		if ( ! pubweb_ads_active() ) {
			return;
		}
		$loader  = (string) pubweb_settings( 'ads.loader_script_url' );
		$network = preg_replace( '/[^0-9]/', '', (string) pubweb_settings( 'ads.gam_network_code' ) );

		if ( $network ) {
			printf(
				'<script>window.pubwebGAM=%s;</script>' . "\n",
				wp_json_encode( array( 'network' => $network ) )
			);
		}
		if ( $loader && wp_http_validate_url( $loader ) ) {
			printf(
				'<script async src="%s"></script>' . "\n",
				esc_url( $loader )
			);
		}
	},
	20
);

/**
 * Render any "anchor" slots as a dismissible sticky bottom bar. High
 * viewability, off the content flow (no CLS). Printed before the loader.
 */
add_action(
	'wp_footer',
	static function (): void {
		if ( ! pubweb_ads_active() ) {
			return;
		}
		$anchors = array_filter(
			(array) pubweb_settings( 'ads.slots', array() ),
			static fn( $s ) => 'anchor' === ( $s['position'] ?? '' )
		);
		if ( empty( $anchors ) ) {
			return;
		}
		echo '<div class="pw-anchor" id="pw-anchor" role="complementary" aria-label="' . esc_attr__( 'Advertisement', 'pubweb' ) . '">';
		echo '<button class="pw-anchor__close" aria-label="' . esc_attr__( 'Close ad', 'pubweb' ) . '">&times;</button>';
		foreach ( $anchors as $slot ) {
			$min_h = 0;
			foreach ( (array) ( $slot['sizes'] ?? array() ) as $size ) {
				$min_h = max( $min_h, (int) ( $size[1] ?? 0 ) );
			}
			printf(
				'<div class="pw-ad pw-ad--%1$s" id="%2$s" data-pw-slot="%3$s"%4$s></div>',
				esc_attr( (string) ( $slot['device'] ?? 'all' ) ),
				esc_attr( (string) ( $slot['selector'] ?? 'pw-ad-' . $slot['id'] ) ),
				esc_attr( (string) $slot['id'] ),
				$min_h ? ' style="min-height:' . (int) $min_h . 'px"' : ''
			);
		}
		echo '</div>';
	},
	5
);

/**
 * Auto-place "in_content" slots between paragraphs of single posts.
 *
 * @param string $content Post content HTML.
 * @return string
 */
add_filter(
	'the_content',
	static function ( string $content ): string {
		if ( ! is_singular( 'post' ) || ! pubweb_ads_active() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$slots = array_filter(
			(array) pubweb_settings( 'ads.slots', array() ),
			static fn( $s ) => 'in_content' === ( $s['position'] ?? '' )
		);
		if ( empty( $slots ) ) {
			return $content;
		}

		$parts = explode( '</p>', $content );
		$total = count( $parts );

		// Map: paragraph number (1-based) => markup to insert after it.
		$inserts = array();
		foreach ( $slots as $slot ) {
			$after = min( max( 1, (int) ( $slot['in_content_after'] ?? 3 ) ), max( 1, $total - 1 ) );
			$min_h = 0;
			foreach ( (array) ( $slot['sizes'] ?? array() ) as $size ) {
				$min_h = max( $min_h, (int) ( $size[1] ?? 0 ) );
			}
			$inserts[ $after ][] = sprintf(
				'<div class="pw-ad pw-ad--in-content" id="%1$s" data-pw-slot="%2$s"%3$s aria-hidden="true"></div>',
				esc_attr( (string) ( $slot['selector'] ?? 'pw-ad-' . $slot['id'] ) ),
				esc_attr( (string) $slot['id'] ),
				$min_h ? ' style="min-height:' . (int) $min_h . 'px"' : ''
			);
		}

		$out = '';
		foreach ( $parts as $i => $part ) {
			$out .= $part;
			if ( $i < $total - 1 ) {
				$out .= '</p>'; // Restore the delimiter consumed by explode().
			}
			$paragraph = $i + 1;
			if ( ! empty( $inserts[ $paragraph ] ) ) {
				$out .= implode( '', $inserts[ $paragraph ] );
			}
		}
		return $out;
	},
	20
);
