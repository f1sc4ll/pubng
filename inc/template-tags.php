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
	if ( is_page_template( 'full-width' ) || is_front_page() ) {
		return false;
	}
	return (bool) pubweb_settings( 'layout.show_sidebar' ) && is_active_sidebar( 'sidebar-1' );
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
		$classes[] = 'layout-' . sanitize_html_class( (string) pubweb_settings( 'layout.homepage_style', 'grid' ) );
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
