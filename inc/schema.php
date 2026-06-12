<?php
/**
 * Automatic JSON-LD structured data, emitted per page type.
 *
 * Half of the reference sites ship no Article/NewsArticle schema — this
 * is a cheap SEO edge. We build a schema.org @graph with stable @id
 * references (WebSite, Organization, WebPage, Article, BreadcrumbList,
 * Person) so entities cross-link correctly.
 *
 * Skipped automatically when an SEO plugin already outputs a @graph
 * (Yoast/RankMath/SEOPress) to avoid duplicate, conflicting markup.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_head',
	static function (): void {
		if ( ! pubweb_settings( 'schema.enabled' ) || pubweb_seo_plugin_active() ) {
			return;
		}

		$graph   = array();
		$site_id = home_url( '/#website' );
		$org_id  = home_url( '/#organization' );

		// Organization / Publisher.
		$org = array(
			'@type' => (string) pubweb_settings( 'schema.publisher_type', 'Organization' ),
			'@id'   => $org_id,
			'name'  => (string) ( pubweb_settings( 'schema.org_name' ) ?: get_bloginfo( 'name' ) ),
			'url'   => home_url( '/' ),
		);
		$logo_id = (int) pubweb_settings( 'schema.org_logo_id' );
		if ( $logo_id ) {
			$logo = wp_get_attachment_image_src( $logo_id, 'full' );
			if ( $logo ) {
				$org['logo'] = array(
					'@type'  => 'ImageObject',
					'url'    => $logo[0],
					'width'  => $logo[1],
					'height' => $logo[2],
				);
			}
		}
		$graph[] = $org;

		// WebSite + SearchAction (sitelinks searchbox).
		$graph[] = array(
			'@type'           => 'WebSite',
			'@id'             => $site_id,
			'url'             => home_url( '/' ),
			'name'            => get_bloginfo( 'name' ),
			'description'     => get_bloginfo( 'description' ),
			'publisher'       => array( '@id' => $org_id ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		);

		if ( is_singular( 'post' ) ) {
			$graph = array_merge( $graph, pubweb_schema_article( $org_id, $site_id ) );
		} elseif ( is_singular() ) {
			$graph[] = array(
				'@type'    => 'WebPage',
				'@id'      => get_permalink() . '#webpage',
				'url'      => get_permalink(),
				'name'     => get_the_title(),
				'isPartOf' => array( '@id' => $site_id ),
			);
		} elseif ( is_author() ) {
			$graph = array_merge( $graph, pubweb_schema_profile( $site_id ) );
		} elseif ( ( is_archive() || ( is_home() && ! is_front_page() ) ) ) {
			$graph = array_merge( $graph, pubweb_schema_collection( $site_id ) );
		}

		echo "\n" . '<script type="application/ld+json">'
			. wp_json_encode(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => $graph,
				),
				JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
			)
			. '</script>' . "\n";
	},
	5
);

/**
 * Build the Article + BreadcrumbList + Person nodes for a single post.
 *
 * @param string $org_id  Organization @id.
 * @param string $site_id WebSite @id.
 * @return array<int,array<string,mixed>>
 */
function pubweb_schema_article( string $org_id, string $site_id ): array {
	$nodes   = array();
	$url     = get_permalink();
	$type    = (string) pubweb_settings( 'schema.article_type', 'Article' );
	$page_id = $url . '#webpage';

	$article = array(
		'@type'            => $type,
		'@id'              => $url . '#article',
		'isPartOf'         => array( '@id' => $page_id ),
		'mainEntityOfPage' => array( '@id' => $page_id ),
		'headline'         => wp_strip_all_tags( get_the_title() ),
		'datePublished'    => get_the_date( DATE_W3C ),
		'dateModified'     => get_the_modified_date( DATE_W3C ),
		'author'           => array(
			'@type' => 'Person',
			'@id'   => home_url( '/#/author/' . get_the_author_meta( 'ID' ) ),
			'name'  => get_the_author(),
		),
		'publisher'        => array( '@id' => $org_id ),
		'description'      => wp_strip_all_tags( get_the_excerpt() ),
	);

	if ( has_post_thumbnail() ) {
		$img = wp_get_attachment_image_src( get_post_thumbnail_id(), 'full' );
		if ( $img ) {
			$article['image'] = array(
				'@type'  => 'ImageObject',
				'url'    => $img[0],
				'width'  => $img[1],
				'height' => $img[2],
			);
		}
	}
	$nodes[] = $article;

	// WebPage.
	$nodes[] = array(
		'@type'    => 'WebPage',
		'@id'      => $page_id,
		'url'      => $url,
		'name'     => get_the_title(),
		'isPartOf' => array( '@id' => $site_id ),
		'breadcrumb' => array( '@id' => $url . '#breadcrumb' ),
	);

	// BreadcrumbList: Home › Category › Post.
	$items = array(
		array(
			'@type'    => 'ListItem',
			'position' => 1,
			'name'     => __( 'Home', 'pubweb' ),
			'item'     => home_url( '/' ),
		),
	);
	$cats = get_the_category();
	$pos  = 2;
	if ( ! empty( $cats ) ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $pos++,
			'name'     => $cats[0]->name,
			'item'     => get_category_link( $cats[0]->term_id ),
		);
	}
	$items[] = array(
		'@type'    => 'ListItem',
		'position' => $pos,
		'name'     => get_the_title(),
	);
	$nodes[] = array(
		'@type'           => 'BreadcrumbList',
		'@id'             => $url . '#breadcrumb',
		'itemListElement' => $items,
	);

	return $nodes;
}

/**
 * Author archive → ProfilePage + Person.
 *
 * @param string $site_id WebSite @id.
 * @return array<int,array<string,mixed>>
 */
function pubweb_schema_profile( string $site_id ): array {
	$author = get_queried_object();
	if ( ! $author instanceof WP_User ) {
		return array();
	}
	$url = get_author_posts_url( $author->ID );
	return array(
		array(
			'@type'      => 'ProfilePage',
			'@id'        => $url . '#profilepage',
			'url'        => $url,
			'isPartOf'   => array( '@id' => $site_id ),
			'mainEntity' => array(
				'@type'       => 'Person',
				'@id'         => home_url( '/#/author/' . $author->ID ),
				'name'        => $author->display_name,
				'description' => wp_strip_all_tags( (string) get_the_author_meta( 'description', $author->ID ) ),
				'url'         => $url,
			),
		),
	);
}

/**
 * Category/tag/date/blog archive → CollectionPage + BreadcrumbList.
 *
 * @param string $site_id WebSite @id.
 * @return array<int,array<string,mixed>>
 */
function pubweb_schema_collection( string $site_id ): array {
	$url   = pubweb_current_url();
	$title = wp_strip_all_tags( get_the_archive_title() ?: get_bloginfo( 'name' ) );

	$items = array(
		array(
			'@type'    => 'ListItem',
			'position' => 1,
			'name'     => __( 'Home', 'pubweb' ),
			'item'     => home_url( '/' ),
		),
		array(
			'@type'    => 'ListItem',
			'position' => 2,
			'name'     => $title,
		),
	);

	return array(
		array(
			'@type'      => 'CollectionPage',
			'@id'        => $url . '#collection',
			'url'        => $url,
			'name'       => $title,
			'isPartOf'   => array( '@id' => $site_id ),
			'breadcrumb' => array( '@id' => $url . '#breadcrumb' ),
		),
		array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $url . '#breadcrumb',
			'itemListElement' => $items,
		),
	);
}

/**
 * Detect a major SEO plugin so we don't double-emit schema/meta.
 */
function pubweb_seo_plugin_active(): bool {
	return defined( 'WPSEO_VERSION' )       // Yoast.
		|| class_exists( 'RankMath' )       // Rank Math.
		|| defined( 'SEOPRESS_VERSION' )    // SEOPress.
		|| defined( 'AIOSEO_VERSION' );     // All in One SEO.
}
