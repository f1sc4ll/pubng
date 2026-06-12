<?php
/**
 * Template helper functions used across the theme templates.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

/**
 * Output the site logo (custom-logo) or a text fallback.
 */
function pubweb_branding(): void {
	if ( function_exists( 'the_custom_logo' ) && has_custom_logo() ) {
		the_custom_logo();
		return;
	}
	$logo_id = (int) pubweb_settings( 'branding.logo_id' );
	if ( $logo_id ) {
		printf(
			'<a class="custom-logo-link" href="%s" rel="home">%s</a>',
			esc_url( home_url( '/' ) ),
			wp_get_attachment_image( $logo_id, 'medium', false, array( 'class' => 'custom-logo', 'alt' => get_bloginfo( 'name' ) ) )
		);
		return;
	}
	printf(
		'<a class="site-title" href="%s" rel="home">%s</a>',
		esc_url( home_url( '/' ) ),
		esc_html( get_bloginfo( 'name' ) )
	);
}

/**
 * Compact entry meta line: date + author. Kept minimal for scanning.
 */
function pubweb_entry_meta(): void {
	printf(
		'<div class="entry-meta"><time class="published" datetime="%s">%s</time><span class="byline"> · %s</span></div>',
		esc_attr( get_the_date( DATE_W3C ) ),
		esc_html( get_the_date() ),
		esc_html( get_the_author() )
	);
}

/**
 * Whether the sidebar layout is active for the current view.
 */
function pubweb_has_sidebar(): bool {
	if ( is_front_page() || is_home() ) {
		return false; // Home never has a sidebar.
	}
	if ( is_singular( 'post' ) ) {
		$has = 'sidebar' === pubweb_settings( 'layout.single_variant' );
	} else {
		$has = (bool) pubweb_settings( 'layout.show_sidebar' );
	}
	return $has && is_active_sidebar( 'sidebar-1' );
}

/**
 * Body class additions reflecting layout settings.
 *
 * @param string[] $classes Existing classes.
 * @return string[]
 */
add_filter(
	'body_class',
	static function ( array $classes ): array {
		$classes[] = pubweb_has_sidebar() ? 'has-sidebar' : 'no-sidebar';
		// Per-page-type layout variant (CSS switches off these classes).
		if ( is_front_page() || is_home() ) {
			$classes[] = 'pw-home-' . sanitize_html_class( (string) pubweb_settings( 'layout.home_variant', 'grid' ) );
		} elseif ( is_singular( 'post' ) ) {
			$classes[] = 'pw-single-' . sanitize_html_class( (string) pubweb_settings( 'layout.single_variant', 'centered' ) );
		} elseif ( is_archive() || is_search() ) {
			$classes[] = 'pw-archive-' . sanitize_html_class( (string) pubweb_settings( 'layout.archive_variant', 'grid' ) );
		}
		if ( pubweb_settings( 'layout.sticky_header' ) ) {
			$classes[] = 'sticky-header';
			if ( pubweb_settings( 'layout.sticky_shrink' ) ) {
				$classes[] = 'pw-shrink';
			}
		}
		$classes[] = 'cols-' . (int) pubweb_settings( 'layout.posts_columns', 3 );
		return $classes;
	}
);

/**
 * Trim excerpts to the configured word count.
 *
 * @param int $length Default length.
 * @return int
 */
add_filter(
	'excerpt_length',
	static fn( int $length ): int => (int) pubweb_settings( 'layout.excerpt_words', 24 )
);

add_filter( 'excerpt_more', static fn(): string => '…' );

/**
 * Reading-time estimate in minutes for the current post.
 */
function pubweb_reading_time(): int {
	$words = str_word_count( wp_strip_all_tags( get_the_content() ) );
	return max( 1, (int) ceil( $words / 220 ) );
}

/**
 * Colored category chip (first category). Color is derived
 * deterministically from the category slug so each category keeps a
 * stable hue — the signature "not a generic blog" detail.
 */
function pubweb_category_chip(): void {
	if ( ! pubweb_settings( 'layout.show_category_chip' ) ) {
		return;
	}
	$cats = get_the_category();
	if ( empty( $cats ) ) {
		return;
	}
	$cat = $cats[0];
	$hue = abs( crc32( $cat->slug ) ) % 360;
	printf(
		'<a class="pw-chip" href="%s" style="--chip:hsl(%d,62%%,45%%)">%s</a>',
		esc_url( get_category_link( $cat->term_id ) ),
		$hue,
		esc_html( $cat->name )
	);
}

/**
 * Section heading with an accent underline bar.
 *
 * @param string $text Heading text.
 */
function pubweb_section_heading( string $text ): void {
	if ( ! pubweb_settings( 'layout.section_heading' ) ) {
		return;
	}
	printf( '<h2 class="pw-section-heading"><span>%s</span></h2>', esc_html( $text ) );
}

/** Card CSS class reflecting the configured card style. */
function pubweb_card_class(): string {
	return 'overlay' === pubweb_settings( 'layout.card_style' ) ? 'card card--overlay' : 'card card--classic';
}

/**
 * Expand safe {{tokens}} in operator custom code (head/footer). NO PHP is
 * executed — only a fixed allowlist of context values, each escaped, so
 * there is zero code-execution surface.
 *
 * Tokens: {{site_name}} {{site_url}} {{year}} {{lang}} {{url}}
 *         {{post_id}} {{post_title}} {{category}}
 *
 * @param string $html Raw custom code.
 * @return string
 */
function pubweb_expand_tokens( string $html ): string {
	if ( '' === $html || ! str_contains( $html, '{{' ) ) {
		return $html;
	}
	$cat = '';
	if ( is_singular( 'post' ) ) {
		$cats = get_the_category();
		$cat  = ! empty( $cats ) ? $cats[0]->name : '';
	} elseif ( is_category() ) {
		$obj = get_queried_object();
		$cat = $obj instanceof WP_Term ? $obj->name : '';
	}
	$map = array(
		'{{site_name}}'  => esc_html( get_bloginfo( 'name' ) ),
		'{{site_url}}'   => esc_url( home_url( '/' ) ),
		'{{year}}'       => esc_html( gmdate( 'Y' ) ),
		'{{lang}}'       => esc_html( get_locale() ),
		'{{url}}'        => esc_url( home_url( add_query_arg( array() ) ) ),
		'{{post_id}}'    => is_singular() ? (string) get_the_ID() : '',
		'{{post_title}}' => is_singular() ? esc_html( get_the_title() ) : '',
		'{{category}}'   => esc_html( $cat ),
	);
	return strtr( $html, $map );
}
