<?php
/**
 * Authentication, rate limiting, and audit logging for the pubweb/v1 API.
 *
 * Trust model: a request bearing the secret token is treated as an
 * administrator-equivalent caller. The token is therefore the only thing
 * standing between the public internet and theme configuration, so it is
 * compared in constant time and the API is OFF until a token is set.
 *
 * Token source (first match wins):
 *   1. Constant PUBWEB_AI_TOKEN defined in wp-config.php (recommended).
 *   2. Option pubweb_ai_token_hash (sha256 hex), set via token rotation.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

final class PubWeb_AI_Auth {

	private const HASH_OPTION    = 'pubweb_ai_token_hash';
	private const AUDIT_OPTION   = 'pubweb_ai_audit';
	private const AUDIT_MAX      = 50;
	private const RATE_WINDOW    = 60;   // Seconds.
	private const RATE_MAX       = 60;   // Requests per window per IP.

	/** Whether the API has a configured token (otherwise it stays disabled). */
	public static function is_configured(): bool {
		return ( defined( 'PUBWEB_AI_TOKEN' ) && '' !== (string) PUBWEB_AI_TOKEN )
			|| '' !== (string) get_option( self::HASH_OPTION, '' );
	}

	/**
	 * REST permission callback. Returns true or a WP_Error with the
	 * correct HTTP status so the controller never has to re-check auth.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return true|WP_Error
	 */
	public static function authorize( WP_REST_Request $request ): bool|WP_Error {
		if ( ! self::is_configured() ) {
			return new WP_Error(
				'pubweb_api_disabled',
				__( 'PubWeb API is disabled: no token configured.', 'pubweb' ),
				array( 'status' => 503 )
			);
		}

		$ip = self::client_ip();
		if ( self::is_rate_limited( $ip ) ) {
			return new WP_Error(
				'pubweb_rate_limited',
				__( 'Too many requests.', 'pubweb' ),
				array( 'status' => 429 )
			);
		}

		$provided = self::extract_token( $request );
		if ( '' === $provided || ! self::token_matches( $provided ) ) {
			self::audit( $request, 401 );
			return new WP_Error(
				'pubweb_unauthorized',
				__( 'Invalid or missing token.', 'pubweb' ),
				array( 'status' => 401 )
			);
		}
		return true;
	}

	/**
	 * Constant-time token verification against the active source.
	 *
	 * @param string $provided Caller-supplied token.
	 * @return bool
	 */
	private static function token_matches( string $provided ): bool {
		if ( defined( 'PUBWEB_AI_TOKEN' ) && '' !== (string) PUBWEB_AI_TOKEN ) {
			return hash_equals( (string) PUBWEB_AI_TOKEN, $provided );
		}
		$stored_hash = (string) get_option( self::HASH_OPTION, '' );
		if ( '' === $stored_hash ) {
			return false;
		}
		return hash_equals( $stored_hash, hash( 'sha256', $provided ) );
	}

	/**
	 * Pull the token from Authorization: Bearer or X-PubWeb-Token.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string
	 */
	private static function extract_token( WP_REST_Request $request ): string {
		$auth = (string) $request->get_header( 'authorization' );
		if ( 0 === stripos( $auth, 'bearer ' ) ) {
			return trim( substr( $auth, 7 ) );
		}
		return trim( (string) $request->get_header( 'x-pubweb-token' ) );
	}

	/**
	 * Rotate the stored token. Accepts an explicit new token or, when
	 * empty, generates a 256-bit one. Returns the plaintext ONCE.
	 *
	 * @param string $new_token Optional caller-supplied token.
	 * @return string The new plaintext token (store it; it is not recoverable).
	 */
	public static function rotate( string $new_token = '' ): string {
		if ( '' === $new_token ) {
			$new_token = bin2hex( random_bytes( 32 ) );
		}
		update_option( self::HASH_OPTION, hash( 'sha256', $new_token ), false );
		return $new_token;
	}

	/* --------------------------------------------------------------- *
	 * Rate limiting (per IP, transient-backed sliding count).
	 * --------------------------------------------------------------- */

	private static function is_rate_limited( string $ip ): bool {
		$key   = 'pubweb_rl_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_MAX ) {
			return true;
		}
		set_transient( $key, $count + 1, self::RATE_WINDOW );
		return false;
	}

	private static function client_ip(): string {
		$raw = (string) ( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
		return filter_var( $raw, FILTER_VALIDATE_IP ) ? $raw : '0.0.0.0';
	}

	/* --------------------------------------------------------------- *
	 * Audit log (capped ring buffer in an option).
	 * --------------------------------------------------------------- */

	/**
	 * Record an API call outcome.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $status  HTTP status returned.
	 * @param string          $note    Optional detail (e.g. changed keys).
	 */
	public static function audit( WP_REST_Request $request, int $status, string $note = '' ): void {
		$log   = get_option( self::AUDIT_OPTION, array() );
		$log   = is_array( $log ) ? $log : array();
		$log[] = array(
			'time'   => gmdate( 'c' ),
			'ip'     => self::client_ip(),
			'method' => $request->get_method(),
			'route'  => $request->get_route(),
			'status' => $status,
			'note'   => mb_substr( $note, 0, 200 ),
		);
		if ( count( $log ) > self::AUDIT_MAX ) {
			$log = array_slice( $log, -self::AUDIT_MAX );
		}
		update_option( self::AUDIT_OPTION, $log, false );
	}

	/** @return array<int,array<string,mixed>> Recent audit entries (newest last). */
	public static function get_audit(): array {
		$log = get_option( self::AUDIT_OPTION, array() );
		return is_array( $log ) ? $log : array();
	}
}
