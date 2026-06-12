<?php
/**
 * Self-hosted theme updater driven by a JSON manifest (e.g. on AWS S3).
 *
 * Disabled by default. Activate by setting updater.enabled = true and
 * updater.manifest_url in the theme settings once V1 is published.
 *
 * Manifest shape (public JSON object on S3/CDN):
 * {
 *   "version":      "1.1.0",
 *   "download_url": "https://cdn.example.com/pubweb-1.1.0.zip",
 *   "requires":     "6.2",
 *   "requires_php": "8.0",
 *   "tested":       "6.9",
 *   "changelog":    "Plain text or HTML changelog",
 *   "url":          "https://pubweb.ai"
 * }
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

final class PubWeb_Updater {

	private const CACHE_KEY = 'pubweb_update_manifest';
	private const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	public static function init(): void {
		if ( ! pubweb_settings( 'updater.enabled' ) ) {
			return;
		}
		add_filter( 'pre_set_site_transient_update_themes', array( self::class, 'inject_update' ) );
		add_action( 'after_theme_install', array( self::class, 'flush_cache' ) );
		add_action( 'upgrader_process_complete', array( self::class, 'flush_cache' ) );
	}

	/**
	 * Compare the remote manifest version to the installed one and, when
	 * newer, attach an update entry keyed by the theme directory slug.
	 *
	 * @param object $transient Core update_themes transient.
	 * @return object
	 */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}
		$manifest = self::fetch_manifest();
		if ( ! $manifest ) {
			return $transient;
		}

		$slug      = get_template();
		$installed = PUBWEB_VERSION;
		if ( version_compare( $manifest['version'], $installed, '<=' ) ) {
			return $transient;
		}

		$transient->response[ $slug ] = array(
			'theme'        => $slug,
			'new_version'  => $manifest['version'],
			'url'          => $manifest['url'] ?? 'https://pubweb.ai',
			'package'      => $manifest['download_url'],
			'requires'     => $manifest['requires'] ?? '',
			'requires_php' => $manifest['requires_php'] ?? '',
		);
		return $transient;
	}

	/**
	 * Fetch + validate the manifest, cached to avoid hammering S3.
	 *
	 * @return array<string,mixed>|null
	 */
	private static function fetch_manifest(): ?array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$url = (string) pubweb_settings( 'updater.manifest_url' );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return null;
		}

		$response = wp_safe_remote_get( $url, array(
			'timeout' => 8,
			'headers' => array( 'Accept' => 'application/json' ),
		) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['version'] ) || empty( $data['download_url'] ) ) {
			return null;
		}
		// Only trust HTTPS package URLs.
		if ( ! str_starts_with( (string) $data['download_url'], 'https://' ) ) {
			return null;
		}

		set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
		return $data;
	}

	public static function flush_cache(): void {
		delete_transient( self::CACHE_KEY );
	}
}

add_action( 'after_setup_theme', array( PubWeb_Updater::class, 'init' ) );
