<?php
/**
 * AI translation provider abstraction.
 *
 * Translates arbitrary text via a configurable LLM provider (OpenAI,
 * xAI Grok, Anthropic Claude, or OpenRouter). Used by the admin panel's
 * "test translation" action and the pubweb/v1 /translate endpoint, and
 * is the foundation for multilingual content (frontend switcher + per-
 * language post storage are a later layer).
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

final class PubWeb_AI_Translate {

	/**
	 * Provider endpoints + auth styles. Grok and OpenRouter are
	 * OpenAI-chat-compatible; Anthropic uses its Messages API.
	 *
	 * @return array<string,array<string,string>>
	 */
	private static function providers(): array {
		return array(
			'openai'     => array( 'url' => 'https://api.openai.com/v1/chat/completions', 'style' => 'openai', 'default_model' => 'gpt-4o-mini' ),
			'grok'       => array( 'url' => 'https://api.x.ai/v1/chat/completions', 'style' => 'openai', 'default_model' => 'grok-2-latest' ),
			'openrouter' => array( 'url' => 'https://openrouter.ai/api/v1/chat/completions', 'style' => 'openai', 'default_model' => 'openai/gpt-4o-mini' ),
			'claude'     => array( 'url' => 'https://api.anthropic.com/v1/messages', 'style' => 'anthropic', 'default_model' => 'claude-3-5-haiku-latest' ),
		);
	}

	/** Whether a provider + key are configured. */
	public static function is_configured(): bool {
		$p = (string) pubweb_settings( 'translation.provider', 'none' );
		return 'none' !== $p && '' !== (string) pubweb_settings( 'translation.api_key', '' );
	}

	/**
	 * Translate $text into $target_lang. Returns the translation or a
	 * WP_Error. Markup is preserved; only human-readable text changes.
	 *
	 * @param string $text        Source text (may contain HTML).
	 * @param string $target_lang BCP-47 target, e.g. "pt-BR".
	 * @param string $source_lang Optional source hint.
	 * @return string|WP_Error
	 */
	public static function translate( string $text, string $target_lang, string $source_lang = '' ): string|WP_Error {
		$text = trim( $text );
		if ( '' === $text ) {
			return '';
		}
		if ( ! self::is_configured() ) {
			return new WP_Error( 'pubweb_translate_unconfigured', __( 'No translation provider configured.', 'pubweb' ), array( 'status' => 503 ) );
		}

		$key       = (string) pubweb_settings( 'translation.api_key' );
		$provider  = (string) pubweb_settings( 'translation.provider' );
		$providers = self::providers();
		if ( ! isset( $providers[ $provider ] ) ) {
			return new WP_Error( 'pubweb_translate_provider', __( 'Unknown provider.', 'pubweb' ), array( 'status' => 400 ) );
		}
		$cfg   = $providers[ $provider ];
		$model = (string) pubweb_settings( 'translation.model' ) ?: $cfg['default_model'];

		$src    = $source_lang ?: (string) pubweb_settings( 'translation.source_lang', 'en' );
		$system = sprintf(
			'You are a professional translator. Translate the user text from %s to %s. Preserve all HTML tags, placeholders, and formatting exactly. Output ONLY the translation, with no preamble or quotes.',
			$src ?: 'the source language',
			$target_lang
		);

		$args = array(
			'timeout' => 30,
			'headers' => array( 'Content-Type' => 'application/json' ),
		);

		if ( 'anthropic' === $cfg['style'] ) {
			$args['headers']['x-api-key']         = $key;
			$args['headers']['anthropic-version'] = '2023-06-01';
			$args['body']                         = wp_json_encode( array(
				'model'      => $model,
				'max_tokens' => 4096,
				'system'     => $system,
				'messages'   => array( array( 'role' => 'user', 'content' => $text ) ),
			) );
		} else {
			$args['headers']['Authorization'] = 'Bearer ' . $key;
			$args['body']                     = wp_json_encode( array(
				'model'       => $model,
				'temperature' => 0.2,
				'messages'    => array(
					array( 'role' => 'system', 'content' => $system ),
					array( 'role' => 'user', 'content' => $text ),
				),
			) );
		}

		$res = wp_remote_post( $cfg['url'], $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = is_array( $body ) ? ( $body['error']['message'] ?? wp_remote_retrieve_response_message( $res ) ) : 'HTTP ' . $code;
			return new WP_Error( 'pubweb_translate_http', (string) $msg, array( 'status' => 502 ) );
		}

		$out = 'anthropic' === $cfg['style']
			? ( $body['content'][0]['text'] ?? '' )
			: ( $body['choices'][0]['message']['content'] ?? '' );

		$out = trim( (string) $out );
		if ( '' === $out ) {
			return new WP_Error( 'pubweb_translate_empty', __( 'Provider returned no translation.', 'pubweb' ), array( 'status' => 502 ) );
		}
		return $out;
	}

	/** Human-readable provider list for the admin UI. */
	public static function provider_labels(): array {
		return array(
			'none'       => __( 'None (disabled)', 'pubweb' ),
			'openai'     => 'OpenAI (ChatGPT)',
			'grok'       => 'xAI (Grok)',
			'claude'     => 'Anthropic (Claude)',
			'openrouter' => 'OpenRouter',
		);
	}
}
