<?php
/**
 * Bridge to wp-ai-client plugin for credential management.
 *
 * Reads API credentials from wp-ai-client and maps them to OpenClaw environment variables.
 *
 * @package Spawn
 */

declare(strict_types=1);

namespace Spawn;

/**
 * Class WP_AI_Client_Bridge
 *
 * Provides integration with the wp-ai-client plugin for AI provider credentials.
 *
 * @since 1.0.0
 */
class WP_AI_Client_Bridge {

	/**
	 * WordPress option name where wp-ai-client stores credentials.
	 *
	 * @var string
	 */
	public const WP_AI_CLIENT_OPTION = 'wp_ai_client_provider_credentials';

	/**
	 * Maps wp-ai-client provider IDs to OpenClaw environment variable names.
	 *
	 * @var array<string, string>
	 */
	public const PROVIDER_MAP = array(
		'anthropic' => 'ANTHROPIC_API_KEY',
		'openai'    => 'OPENAI_API_KEY',
		'google'    => 'GOOGLE_API_KEY',
	);

	/**
	 * Gets stored credentials from wp-ai-client.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Credentials keyed by provider ID (e.g., ['anthropic' => 'sk-...']).
	 */
	public static function get_credentials(): array {
		$credentials = get_option( self::WP_AI_CLIENT_OPTION, array() );

		if ( ! is_array( $credentials ) ) {
			return array();
		}

		// Filter to only include non-empty string values.
		$valid_credentials = array();
		foreach ( $credentials as $provider_id => $api_key ) {
			if ( is_string( $api_key ) && '' !== trim( $api_key ) ) {
				$valid_credentials[ $provider_id ] = $api_key;
			}
		}

		return $valid_credentials;
	}

	/**
	 * Checks if at least one AI provider credential is configured.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if at least one credential is available.
	 */
	public static function has_any_credentials(): bool {
		$credentials = self::get_credentials();

		return count( $credentials ) > 0;
	}

	/**
	 * Gets credentials formatted as OpenClaw environment variables.
	 *
	 * Converts wp-ai-client provider IDs to OpenClaw environment variable names.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Environment variables (e.g., ['ANTHROPIC_API_KEY' => 'sk-...']).
	 */
	public static function get_openclaw_env(): array {
		$credentials = self::get_credentials();
		$env_vars    = array();

		foreach ( $credentials as $provider_id => $api_key ) {
			if ( isset( self::PROVIDER_MAP[ $provider_id ] ) ) {
				$env_vars[ self::PROVIDER_MAP[ $provider_id ] ] = $api_key;
			}
		}

		return $env_vars;
	}

	/**
	 * Checks if the wp-ai-client plugin is active.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if wp-ai-client plugin is active.
	 */
	public static function is_wp_ai_client_active(): bool {
		// Check if the main wp-ai-client class exists.
		if ( class_exists( 'WordPress\\AI_Client\\AI_Client' ) ) {
			return true;
		}

		// Alternative: check if the plugin is active via WordPress function.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Check common plugin file names.
		$possible_slugs = array(
			'wp-ai-client/plugin.php',
			'wp-ai-client/wp-ai-client.php',
			'wordpress-ai-client/plugin.php',
		);

		foreach ( $possible_slugs as $slug ) {
			if ( is_plugin_active( $slug ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets the URL to the wp-ai-client settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return string URL to credentials settings page, or empty string if not available.
	 */
	public static function get_credentials_page_url(): string {
		if ( ! self::is_wp_ai_client_active() ) {
			return '';
		}

		return admin_url( 'options-general.php?page=wp-ai-client' );
	}

	/**
	 * Gets a list of configured providers with their status.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{configured: bool, env_var: string}> Provider status information.
	 */
	public static function get_provider_status(): array {
		$credentials = self::get_credentials();
		$status      = array();

		foreach ( self::PROVIDER_MAP as $provider_id => $env_var ) {
			$status[ $provider_id ] = array(
				'configured' => isset( $credentials[ $provider_id ] ),
				'env_var'    => $env_var,
			);
		}

		return $status;
	}
}
