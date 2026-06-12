<?php
/**
 * PubWeb settings store.
 *
 * A single autoloaded option (`pubweb_settings`) holds the entire theme
 * configuration as a typed tree. Templates read it; the REST API writes
 * it. There is exactly one source of truth, which is what makes the
 * theme safely editable by an AI agent without touching files.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

/**
 * Static façade over the settings option.
 */
final class PubWeb_Settings {

	public const OPTION = 'pubweb_settings';

	/**
	 * In-request cache to avoid repeated option hydration.
	 *
	 * @var array<string,mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Default configuration tree. Also serves as the allowlist: only
	 * keys present here can be written through the REST API.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'branding'    => array(
				'accent_color'      => '#1769ff',
				'footer_disclaimer' => '',
				'logo_id'           => 0,    // Attachment ID (media library).
				'logo_max_width'    => 180,
			),
			'layout'      => array(
				'homepage_style'     => 'grid',     // grid | list.
				'card_style'         => 'classic',  // classic | overlay (poster cards).
				'show_sidebar'       => false,       // Full-width by default (max ad real estate).
				'sticky_header'      => true,
				'sticky_shrink'      => true,        // Shrink header on scroll.
				'posts_columns'      => 3,           // 2 or 3 (desktop). Mobile is always 1-2.
				'excerpt_words'      => 24,
				'featured_hero'      => true,        // Featured lead post above the grid.
				'section_heading'    => true,        // "Latest" heading with accent bar.
				'show_category_chip' => true,        // Colored category chip on cards.
				'back_to_top'        => true,
				'reading_progress'   => true,        // Progress bar on single posts.
			),
			'colors'      => array(
				'header_bg' => '#ffffff',
				'footer_bg' => '#0e1116',
				'body_bg'   => '#ffffff',
			),
			'performance' => array(
				'speculation_rules' => true,   // Prefetch next page on hover/viewport.
				'lazy_images'       => true,
				'remove_emoji'      => true,
				'system_fonts'      => true,   // Zero web-font latency by default.
				'disable_embeds'    => true,
			),
			'schema'      => array(
				'enabled'        => true,
				'org_name'       => '',
				'org_logo_id'    => 0,
				'publisher_type' => 'Organization', // Organization | NewsMediaOrganization.
				'article_type'   => 'Article',       // Article | BlogPosting | NewsArticle.
			),
			'seo'         => array(
				'enabled'           => true,  // Auto-disabled if Yoast/RankMath/SEOPress is active.
				'title_separator'   => '–',
				'default_og_image'  => 0,
				'twitter_site'      => '',
			),
			'custom_code' => array(
				'head_html'   => '',
				'footer_html' => '',
				'custom_css'  => '',
			),
			'translation' => array(
				'provider'       => 'none',  // none | openai | grok | claude | openrouter.
				'api_key'        => '',      // Provider API key (admin-trust).
				'model'          => '',      // e.g. gpt-4o-mini, grok-2, claude-3-5-haiku, etc.
				'source_lang'    => 'en',    // BCP-47 source language.
				'target_langs'   => array(), // e.g. ['pt-BR','es','fr'].
				'auto_translate' => false,   // Translate new posts on publish.
			),
			'updater'     => array(
				'enabled'      => false, // Activate when V1 ships.
				'manifest_url' => '',    // JSON manifest on S3 (see class-updater.php).
			),
		);
	}

	/**
	 * Hydrate and merge stored settings over defaults.
	 *
	 * @return array<string,mixed>
	 */
	private static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		self::$cache = self::deep_merge( self::defaults(), $stored );
		return self::$cache;
	}

	/**
	 * Read the whole tree or a single dotted path.
	 *
	 * @param string|null $key     Dot path, e.g. "layout.card_style".
	 * @param mixed       $default Fallback when missing.
	 * @return mixed
	 */
	public static function get( ?string $key = null, mixed $default = null ): mixed {
		$all = self::all();
		if ( null === $key ) {
			return $all;
		}
		$node = $all;
		foreach ( explode( '.', $key ) as $segment ) {
			if ( is_array( $node ) && array_key_exists( $segment, $node ) ) {
				$node = $node[ $segment ];
			} else {
				return $default;
			}
		}
		return $node;
	}

	/**
	 * Persist a partial settings patch. Only keys that exist in the
	 * defaults tree are accepted; unknown keys are dropped. Values are
	 * coerced to the type of their default, blocking type confusion.
	 *
	 * @param array<string,mixed> $patch Partial tree to merge in.
	 * @return array<string,mixed> The new, full settings tree.
	 */
	public static function update( array $patch ): array {
		$current  = self::all();
		$filtered = self::filter_against_defaults( self::defaults(), $patch );
		$merged   = self::deep_merge( $current, $filtered );
		update_option( self::OPTION, $merged, true );
		self::$cache = $merged;
		return $merged;
	}

	/**
	 * Recursively keep only keys present in the schema, coercing each
	 * leaf to the schema's type. This is the write-side guardrail.
	 *
	 * @param array<string,mixed> $schema Defaults subtree (allowlist + types).
	 * @param array<string,mixed> $patch  Incoming subtree.
	 * @return array<string,mixed>
	 */
	private static function filter_against_defaults( array $schema, array $patch ): array {
		$out = array();
		foreach ( $patch as $key => $value ) {
			if ( ! array_key_exists( $key, $schema ) ) {
				continue; // Reject unknown keys (mass-assignment guard).
			}
			$default = $schema[ $key ];

			if ( is_array( $default ) && self::is_assoc( $default ) ) {
				$out[ $key ] = self::filter_against_defaults(
					$default,
					is_array( $value ) ? $value : array()
				);
				continue;
			}
			$out[ $key ] = self::coerce( $default, $value );
		}
		return $out;
	}

	/**
	 * Coerce $value to the type of $default and sanitize it.
	 *
	 * @param mixed $default Reference value (defines target type).
	 * @param mixed $value   Incoming value.
	 * @return mixed
	 */
	private static function coerce( mixed $default, mixed $value ): mixed {
		if ( is_bool( $default ) ) {
			return (bool) rest_sanitize_boolean( $value );
		}
		if ( is_int( $default ) ) {
			return (int) $value;
		}
		if ( is_array( $default ) ) {
			return is_array( $value ) ? array_values( array_map( 'sanitize_text_field', $value ) ) : $default;
		}
		// Strings: custom_code is handled by the REST layer (needs raw HTML/CSS).
		return is_string( $value ) ? $value : $default;
	}

	/**
	 * Machine-readable description of every editable key. Returned by
	 * GET /pubweb/v1/settings/schema so an AI knows the exact surface.
	 *
	 * @return array<string,mixed>
	 */
	public static function schema(): array {
		return self::describe( self::defaults() );
	}

	/**
	 * Build a {type, default} description tree from the defaults.
	 *
	 * @param array<string,mixed> $node Defaults subtree.
	 * @return array<string,mixed>
	 */
	private static function describe( array $node ): array {
		$out = array();
		foreach ( $node as $key => $value ) {
			if ( is_array( $value ) && self::is_assoc( $value ) ) {
				$out[ $key ] = self::describe( $value );
			} else {
				$out[ $key ] = array(
					'type'    => get_debug_type( $value ),
					'default' => $value,
				);
			}
		}
		return $out;
	}

	/**
	 * Recursive array merge where scalar/list values from $over win.
	 *
	 * @param array<string,mixed> $base Base tree.
	 * @param array<string,mixed> $over Overriding tree.
	 * @return array<string,mixed>
	 */
	private static function deep_merge( array $base, array $over ): array {
		foreach ( $over as $key => $value ) {
			if (
				isset( $base[ $key ] ) && is_array( $base[ $key ] ) && self::is_assoc( $base[ $key ] )
				&& is_array( $value )
			) {
				$base[ $key ] = self::deep_merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}

	/**
	 * Whether an array is associative (a map) vs a list.
	 *
	 * @param array<mixed> $arr Array to test.
	 * @return bool
	 */
	private static function is_assoc( array $arr ): bool {
		return array() !== $arr && ! array_is_list( $arr );
	}

	/** Flush the in-request cache (used after external option writes). */
	public static function flush(): void {
		self::$cache = null;
	}
}
