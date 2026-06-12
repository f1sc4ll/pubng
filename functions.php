<?php
/**
 * PubWeb theme bootstrap.
 *
 * Loads theme modules in dependency order and exposes the global
 * settings accessor. Designed to stay tiny: every concern lives in
 * its own file under /inc and is required once here.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

define( 'PUBWEB_VERSION', '1.0.0' );
define( 'PUBWEB_DIR', trailingslashit( get_template_directory() ) );
define( 'PUBWEB_URI', trailingslashit( get_template_directory_uri() ) );

/**
 * Minimum runtime guard. Bail loudly in admin if PHP is too old
 * instead of fataling on a syntax feature mid-request.
 */
if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'PubWeb requires PHP 8.1 or newer.', 'pubweb' )
			);
		}
	);
	return;
}

/**
 * Module load order matters: settings first (everything reads it),
 * then setup/assets/performance, then output layers (schema/seo/ads),
 * then the REST API and updater.
 *
 * @var string[] $pubweb_modules
 */
$pubweb_modules = array(
	'inc/settings.php',        // PubWeb_Settings — single source of config truth.
	'inc/setup.php',           // Theme supports, menus, image sizes.
	'inc/assets.php',          // Enqueue, critical CSS, preconnect/preload.
	'inc/performance.php',     // WP bloat removal, speculation rules, lazy.
	'inc/template-tags.php',   // Template helper functions.
	'inc/schema.php',          // JSON-LD per page type.
	'inc/seo.php',             // Meta/OG/canonical (only without an SEO plugin).
	'inc/ads.php',             // Ad slot config + client-side loader wiring.
	'inc/ai-discovery.php',    // /llms.txt for AI crawlers.
	'inc/class-ai-auth.php',   // Token auth, rate limit, audit log.
	'inc/class-ai-rest.php',   // REST controller for pubweb/v1.
	'inc/class-updater.php',   // S3/JSON theme updater (gated off by default).
);

foreach ( $pubweb_modules as $pubweb_module ) {
	require_once PUBWEB_DIR . $pubweb_module;
}
unset( $pubweb_modules, $pubweb_module );

/**
 * Global settings accessor.
 *
 * @param string|null $key     Optional dot.path into the settings tree.
 * @param mixed       $default Fallback when the key is absent.
 * @return mixed The full settings array, or the value at $key.
 */
function pubweb_settings( ?string $key = null, mixed $default = null ): mixed {
	return PubWeb_Settings::get( $key, $default );
}
