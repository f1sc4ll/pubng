<?php
/**
 * AI-friendly discovery.
 *
 * Serves a generated /llms.txt (the emerging llmstxt.org convention) so
 * LLM crawlers get a clean, link-first map of the site without parsing
 * ad-heavy HTML. Built from live site data, cached briefly.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

/**
 * Intercept GET /llms.txt and emit a plain-text site map.
 */
add_action(
	'template_redirect',
	static function (): void {
		$path = strtok( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), '?' );
		if ( '/llms.txt' !== rtrim( (string) $path, '/' ) && '/llms.txt' !== $path ) {
			return;
		}

		$cached = get_transient( 'pubweb_llms_txt' );
		if ( ! is_string( $cached ) ) {
			$cached = pubweb_build_llms_txt();
			set_transient( 'pubweb_llms_txt', $cached, HOUR_IN_SECONDS );
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}
);

/**
 * Assemble the llms.txt body: H1, summary blockquote, key sections, and
 * the most recent posts as a link list.
 *
 * @return string
 */
function pubweb_build_llms_txt(): string {
	$name = get_bloginfo( 'name' );
	$desc = get_bloginfo( 'description' );

	$lines   = array();
	$lines[] = '# ' . $name;
	$lines[] = '';
	if ( $desc ) {
		$lines[] = '> ' . $desc;
		$lines[] = '';
	}
	$lines[] = 'This file helps AI agents navigate ' . home_url( '/' ) . ' efficiently.';
	$lines[] = '';

	// Top categories.
	$cats = get_categories( array(
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => 10,
		'hide_empty' => true,
	) );
	if ( ! empty( $cats ) ) {
		$lines[] = '## Sections';
		$lines[] = '';
		foreach ( $cats as $cat ) {
			$lines[] = sprintf( '- [%s](%s)', $cat->name, get_category_link( $cat->term_id ) );
		}
		$lines[] = '';
	}

	// Recent posts.
	$recent = get_posts( array(
		'numberposts'      => 30,
		'post_status'      => 'publish',
		'suppress_filters' => false,
	) );
	if ( ! empty( $recent ) ) {
		$lines[] = '## Recent articles';
		$lines[] = '';
		foreach ( $recent as $post ) {
			$lines[] = sprintf( '- [%s](%s)', get_the_title( $post ), get_permalink( $post ) );
		}
		$lines[] = '';
	}

	$lines[] = '## Optional';
	$lines[] = '';
	$lines[] = sprintf( '- [Sitemap](%s)', home_url( '/sitemap.xml' ) );

	return implode( "\n", $lines ) . "\n";
}

/** Bust the llms.txt cache whenever content changes. */
add_action( 'save_post', static fn() => delete_transient( 'pubweb_llms_txt' ) );

/**
 * Hint AI/social crawlers in <head>: advertise llms.txt.
 */
add_action(
	'wp_head',
	static function (): void {
		printf(
			'<link rel="alternate" type="text/plain" title="llms.txt" href="%s">' . "\n",
			esc_url( home_url( '/llms.txt' ) )
		);
	},
	2
);
