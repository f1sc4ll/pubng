<?php
/**
 * Lightweight meta output: description, canonical, OpenGraph, Twitter.
 *
 * Only runs when no dedicated SEO plugin is active, so the theme is
 * self-sufficient out of the box but never fights Yoast/RankMath.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

/**
 * Search-engine indexing. Default OFF (noindex): the operator must opt in to
 * being indexed via the theme settings. Uses the native wp_robots filter so it
 * composes with core and most SEO plugins. Runs late to win.
 *
 * @param array<string,mixed> $robots Robots directives.
 * @return array<string,mixed>
 */
add_filter(
	'wp_robots',
	static function ( array $robots ): array {
		if ( pubweb_settings( 'seo.noindex' ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
			unset( $robots['max-image-preview'], $robots['max-snippet'], $robots['max-video-preview'] );
		}
		return $robots;
	},
	99
);

add_action(
	'wp_head',
	static function (): void {
		if ( ! pubweb_settings( 'seo.enabled' ) || pubweb_seo_plugin_active() ) {
			return;
		}

		$desc  = pubweb_meta_description();
		$title = wp_get_document_title();
		$url   = pubweb_current_url();
		$image = pubweb_share_image();

		$tags = array(
			'<link rel="canonical" href="' . esc_url( $url ) . '">',
		);
		if ( $desc ) {
			$tags[] = '<meta name="description" content="' . esc_attr( $desc ) . '">';
		}

		// OpenGraph.
		$tags[] = '<meta property="og:type" content="' . ( is_singular( 'post' ) ? 'article' : 'website' ) . '">';
		$tags[] = '<meta property="og:title" content="' . esc_attr( $title ) . '">';
		$tags[] = '<meta property="og:url" content="' . esc_url( $url ) . '">';
		$tags[] = '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">';
		$tags[] = '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '">';
		if ( $desc ) {
			$tags[] = '<meta property="og:description" content="' . esc_attr( $desc ) . '">';
		}
		if ( $image ) {
			$tags[] = '<meta property="og:image" content="' . esc_url( $image ) . '">';
		}

		// Twitter.
		$tags[] = '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">';
		$twitter = (string) pubweb_settings( 'seo.twitter_site' );
		if ( $twitter ) {
			$tags[] = '<meta name="twitter:site" content="' . esc_attr( $twitter ) . '">';
		}

		echo "\n" . implode( "\n", $tags ) . "\n";
	},
	1
);

/** Best-effort meta description for the current view. */
function pubweb_meta_description(): string {
	if ( is_singular() ) {
		$excerpt = get_the_excerpt();
		return $excerpt ? wp_trim_words( wp_strip_all_tags( $excerpt ), 30, '' ) : '';
	}
	if ( is_category() || is_tag() || is_tax() ) {
		return wp_strip_all_tags( (string) term_description() );
	}
	return wp_strip_all_tags( (string) get_bloginfo( 'description' ) );
}

/** Canonical URL for the current request. */
function pubweb_current_url(): string {
	if ( is_singular() ) {
		return (string) get_permalink();
	}
	if ( is_category() || is_tag() || is_tax() ) {
		$link = get_term_link( get_queried_object() );
		return is_wp_error( $link ) ? home_url( add_query_arg( array() ) ) : $link;
	}
	if ( is_front_page() ) {
		return home_url( '/' );
	}
	return home_url( add_query_arg( array() ) );
}

/** Share image: featured image, else configured default. */
function pubweb_share_image(): string {
	if ( is_singular() && has_post_thumbnail() ) {
		$img = wp_get_attachment_image_src( get_post_thumbnail_id(), 'full' );
		if ( $img ) {
			return $img[0];
		}
	}
	$default = (int) pubweb_settings( 'seo.default_og_image' );
	if ( $default ) {
		$img = wp_get_attachment_image_src( $default, 'full' );
		if ( $img ) {
			return $img[0];
		}
	}
	return '';
}
