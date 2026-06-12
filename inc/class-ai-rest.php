<?php
/**
 * PubWeb headless configuration API — namespace pubweb/v1.
 *
 * Lets an AI agent read and edit the theme's behaviour (layout, ads,
 * schema, SEO, custom code) through structured, validated endpoints —
 * never raw file writes. There is deliberately NO filesystem endpoint:
 * writing PHP from a request is a remote-code-execution backdoor, so the
 * editable surface is the settings tree only. That tree drives every
 * template, so it is expressive enough to restyle the whole site.
 *
 * Every route is guarded by PubWeb_AI_Auth::authorize().
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

final class PubWeb_AI_REST {

	private const NS = 'pubweb/v1';

	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
		// Let our namespace through global REST locks (e.g. "Disable WP
		// REST API") when a valid token is presented. Priority 99 runs
		// after such plugins so we can clear their error for our routes.
		add_filter( 'rest_authentication_errors', array( self::class, 'allow_namespace' ), 99 );
	}

	/**
	 * Grant pubweb/v1 access when a valid token is present, even if an
	 * earlier filter locked REST to logged-in users. Strictly scoped to
	 * our namespace; the route's own permission_callback re-checks.
	 *
	 * @param mixed $result Auth result from earlier filters.
	 * @return mixed
	 */
	public static function allow_namespace( $result ) {
		if ( true === $result ) {
			return $result;
		}
		$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
		// Strict prefix on the RESOLVED route only (no REQUEST_URI fallback),
		// so we never clear a global lock for a non-pubweb route.
		if ( 0 !== strpos( $route, '/' . self::NS . '/' ) ) {
			return $result;
		}
		return PubWeb_AI_Auth::token_present_and_valid() ? true : $result;
	}

	public static function register_routes(): void {
		$guard = array( PubWeb_AI_Auth::class, 'authorize' );

		register_rest_route( self::NS, '/health', array(
			'methods'             => 'GET',
			'callback'            => array( self::class, 'health' ),
			'permission_callback' => $guard,
		) );

		register_rest_route( self::NS, '/settings', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_settings' ),
				'permission_callback' => $guard,
			),
			array(
				'methods'             => 'PATCH',
				'callback'            => array( self::class, 'patch_settings' ),
				'permission_callback' => $guard,
			),
		) );

		register_rest_route( self::NS, '/settings/schema', array(
			'methods'             => 'GET',
			'callback'            => array( self::class, 'get_schema' ),
			'permission_callback' => $guard,
		) );

		register_rest_route( self::NS, '/custom-code', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_custom_code' ),
				'permission_callback' => $guard,
			),
			array(
				'methods'             => 'PUT',
				'callback'            => array( self::class, 'put_custom_code' ),
				'permission_callback' => $guard,
			),
		) );

		register_rest_route( self::NS, '/translate', array(
			'methods'             => 'POST',
			'callback'            => array( self::class, 'translate' ),
			'permission_callback' => $guard,
		) );

		register_rest_route( self::NS, '/audit', array(
			'methods'             => 'GET',
			'callback'            => array( self::class, 'get_audit' ),
			'permission_callback' => $guard,
		) );

		register_rest_route( self::NS, '/token/rotate', array(
			'methods'             => 'POST',
			'callback'            => array( self::class, 'rotate_token' ),
			'permission_callback' => $guard,
		) );
	}

	/* ---- Handlers ------------------------------------------------- */

	public static function health( WP_REST_Request $req ): WP_REST_Response {
		$theme = wp_get_theme();
		return self::ok( $req, array(
			'theme'        => 'PubWeb',
			'version'      => PUBWEB_VERSION,
			'active'       => get_stylesheet() === get_template(),
			'wp_version'   => get_bloginfo( 'version' ),
			'php_version'  => PHP_VERSION,
			'is_child'     => is_child_theme(),
			'name'         => $theme->get( 'Name' ),
			'seo_plugin'   => pubweb_seo_plugin_active(),
		) );
	}

	public static function get_settings( WP_REST_Request $req ): WP_REST_Response {
		return self::ok( $req, self::redact( pubweb_settings() ) );
	}

	/**
	 * Never return secrets over the API. The translation API key is
	 * write-only: settable via PATCH, but masked on read.
	 *
	 * @param array<string,mixed> $settings Settings tree.
	 * @return array<string,mixed>
	 */
	private static function redact( array $settings ): array {
		if ( ! empty( $settings['translation']['api_key'] ) ) {
			$settings['translation']['api_key'] = '***set***';
		}
		return $settings;
	}

	public static function get_schema( WP_REST_Request $req ): WP_REST_Response {
		return self::ok( $req, PubWeb_Settings::schema() );
	}

	public static function patch_settings( WP_REST_Request $req ): WP_REST_Response|WP_Error {
		$body = $req->get_json_params();
		if ( ! is_array( $body ) || array() === $body ) {
			return self::bad_request( __( 'Body must be a non-empty JSON object.', 'pubweb' ) );
		}
		// Ignore the masked placeholder so a GET→PATCH round-trip can't
		// clobber the real key with "***set***".
		if ( isset( $body['translation']['api_key'] ) && '***set***' === $body['translation']['api_key'] ) {
			unset( $body['translation']['api_key'] );
		}
		$updated = PubWeb_Settings::update( $body );
		PubWeb_AI_Auth::audit( $req, 200, 'patch:' . implode( ',', array_keys( $body ) ) );
		return self::ok( $req, self::redact( $updated ) );
	}

	public static function get_custom_code( WP_REST_Request $req ): WP_REST_Response {
		return self::ok( $req, pubweb_settings( 'custom_code' ) );
	}

	/**
	 * Custom head/footer HTML is stored verbatim (it must carry <script>
	 * ad/analytics tags) — acceptable because the caller is admin-trust.
	 * Custom CSS is tag-stripped to block </style><script> breakouts.
	 */
	public static function put_custom_code( WP_REST_Request $req ): WP_REST_Response|WP_Error {
		$body = $req->get_json_params();
		if ( ! is_array( $body ) ) {
			return self::bad_request( __( 'Body must be a JSON object.', 'pubweb' ) );
		}
		$patch = array();
		if ( isset( $body['head_html'] ) ) {
			$patch['head_html'] = (string) $body['head_html'];
		}
		if ( isset( $body['footer_html'] ) ) {
			$patch['footer_html'] = (string) $body['footer_html'];
		}
		if ( isset( $body['custom_css'] ) ) {
			$patch['custom_css'] = wp_strip_all_tags( (string) $body['custom_css'] );
		}
		$updated = PubWeb_Settings::update( array( 'custom_code' => $patch ) );
		PubWeb_AI_Auth::audit( $req, 200, 'put:custom_code:' . implode( ',', array_keys( $patch ) ) );
		return self::ok( $req, $updated['custom_code'] );
	}

	/**
	 * Translate text via the configured AI provider.
	 * Body: { text, target, source? }.
	 */
	public static function translate( WP_REST_Request $req ): WP_REST_Response|WP_Error {
		$body   = (array) $req->get_json_params();
		$text   = isset( $body['text'] ) ? (string) $body['text'] : '';
		$target = isset( $body['target'] ) ? sanitize_text_field( (string) $body['target'] ) : '';
		if ( '' === $text || '' === $target ) {
			return self::bad_request( __( 'Provide "text" and "target" (BCP-47).', 'pubweb' ) );
		}
		if ( strlen( $text ) > 20000 ) {
			return self::bad_request( __( 'Text exceeds the 20,000-character limit.', 'pubweb' ) );
		}
		$source = isset( $body['source'] ) ? sanitize_text_field( (string) $body['source'] ) : '';
		$result = PubWeb_AI_Translate::translate( $text, $target, $source );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		PubWeb_AI_Auth::audit( $req, 200, 'translate:' . $target );
		return self::ok( $req, array(
			'translation' => $result,
			'target'      => $target,
		) );
	}

	public static function get_audit( WP_REST_Request $req ): WP_REST_Response {
		return self::ok( $req, PubWeb_AI_Auth::get_audit() );
	}

	/**
	 * Rotate the API token. Requires the current token (enforced by the
	 * permission callback). The new plaintext is returned exactly once.
	 */
	public static function rotate_token( WP_REST_Request $req ): WP_REST_Response|WP_Error {
		if ( defined( 'PUBWEB_AI_TOKEN' ) && '' !== (string) PUBWEB_AI_TOKEN ) {
			return self::bad_request(
				__( 'Token is pinned by the PUBWEB_AI_TOKEN constant; rotate it in wp-config.php.', 'pubweb' )
			);
		}
		$body  = (array) $req->get_json_params();
		$token = PubWeb_AI_Auth::rotate( isset( $body['token'] ) ? (string) $body['token'] : '' );
		PubWeb_AI_Auth::audit( $req, 200, 'token:rotate' );
		return self::ok( $req, array(
			'token'   => $token,
			'message' => __( 'Store this token now; it cannot be retrieved again.', 'pubweb' ),
		) );
	}

	/* ---- Response helpers ----------------------------------------- */

	/**
	 * @param mixed $data Payload.
	 */
	private static function ok( WP_REST_Request $req, mixed $data ): WP_REST_Response {
		return new WP_REST_Response( array(
			'ok'   => true,
			'data' => $data,
		), 200 );
	}

	private static function bad_request( string $message ): WP_Error {
		return new WP_Error( 'pubweb_bad_request', $message, array( 'status' => 400 ) );
	}
}

PubWeb_AI_REST::init();
