<?php
/**
 * Google OAuth integration.
 *
 * @package Spawn
 */

declare(strict_types=1);

namespace Spawn;

use WP_Error;

/**
 * Handles Google OAuth authentication.
 */
class Google_OAuth {
	public const GOOGLE_AUTH_URL     = 'https://accounts.google.com/o/oauth2/v2/auth';
	public const GOOGLE_TOKEN_URL    = 'https://oauth2.googleapis.com/token';
	public const GOOGLE_USERINFO_URL = 'https://www.googleapis.com/oauth2/v2/userinfo';

	/**
	 * Initialize Google OAuth hooks.
	 */
	public static function init(): void {
		// No hooks needed yet.
	}

	/**
	 * Get Google client ID.
	 *
	 * @return string Client ID.
	 */
	public static function get_client_id(): string {
		$client_id = get_option( 'spawn_google_client_id', '' );
		$client_id = sanitize_text_field( wp_unslash( $client_id ) );
		return $client_id;
	}

	/**
	 * Get Google client secret.
	 *
	 * @return string Client secret.
	 */
	public static function get_client_secret(): string {
		$client_secret = get_option( 'spawn_google_client_secret', '' );
		$client_secret = sanitize_text_field( wp_unslash( $client_secret ) );
		return $client_secret;
	}

	/**
	 * Check if Google OAuth is configured.
	 *
	 * @return bool True if configured.
	 */
	public static function is_configured(): bool {
		return ! empty( self::get_client_id() ) && ! empty( self::get_client_secret() );
	}

	/**
	 * Build Google OAuth authorization URL.
	 *
	 * @return string Authorization URL.
	 */
	public static function get_auth_url(): string {
		$redirect_uri = home_url( '/wp-json/spawn/v1/auth/google/callback' );

		return add_query_arg(
			[
				'client_id'     => self::get_client_id(),
				'redirect_uri'  => $redirect_uri,
				'response_type' => 'code',
				'scope'         => 'openid email profile',
				'state'         => wp_create_nonce( 'spawn_google_oauth' ),
			],
			self::GOOGLE_AUTH_URL
		);
	}

	/**
	 * Exchange auth code for access token.
	 *
	 * @param string $code Authorization code.
	 * @return array|WP_Error Token data or error.
	 */
	public static function exchange_code_for_token( string $code ): array|WP_Error {
		$client_id     = self::get_client_id();
		$client_secret = self::get_client_secret();
		$redirect_uri  = home_url( '/wp-json/spawn/v1/auth/google/callback' );

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return new WP_Error(
				'google_oauth_not_configured',
				__( 'Google OAuth is not configured.', 'spawn' ),
				[ 'status' => 500 ]
			);
		}

		$response = wp_remote_post( self::GOOGLE_TOKEN_URL, [
			'timeout' => 30,
			'body'    => [
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'code'          => $code,
				'redirect_uri'  => $redirect_uri,
				'grant_type'    => 'authorization_code',
			],
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 400 ) {
			$message = $body['error_description'] ?? $body['error'] ?? __( 'Google token exchange failed.', 'spawn' );
			return new WP_Error(
				'google_oauth_token_error',
				$message,
				[ 'status' => $status_code ]
			);
		}

		$access_token = $body['access_token'] ?? '';
		if ( empty( $access_token ) ) {
			return new WP_Error(
				'google_oauth_missing_token',
				__( 'No access token returned from Google.', 'spawn' ),
				[ 'status' => 400 ]
			);
		}

		return [
			'access_token' => $access_token,
		];
	}

	/**
	 * Fetch Google user info.
	 *
	 * @param string $access_token Access token.
	 * @return array|WP_Error User info or error.
	 */
	public static function get_user_info( string $access_token ): array|WP_Error {
		$response = wp_remote_get( self::GOOGLE_USERINFO_URL, [
			'timeout' => 30,
			'headers' => [
				'Authorization' => 'Bearer ' . $access_token,
			],
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 400 ) {
			$message = $body['error']['message'] ?? __( 'Failed to fetch Google user info.', 'spawn' );
			return new WP_Error(
				'google_oauth_userinfo_error',
				$message,
				[ 'status' => $status_code ]
			);
		}

		$email = sanitize_email( $body['email'] ?? '' );
		if ( empty( $email ) ) {
			return new WP_Error(
				'google_oauth_missing_email',
				__( 'Google account did not return an email address.', 'spawn' ),
				[ 'status' => 400 ]
			);
		}

		return $body ?? [];
	}

	/**
	 * Find or create a spawn_customer user.
	 *
	 * @param array $google_user Google user info.
	 * @return int|WP_Error User ID or error.
	 */
	public static function find_or_create_user( array $google_user ): int|WP_Error {
		$email = sanitize_email( $google_user['email'] ?? '' );
		$name  = sanitize_text_field( $google_user['name'] ?? '' );

		if ( empty( $email ) ) {
			return new WP_Error(
				'google_oauth_missing_email',
				__( 'Google account did not return an email address.', 'spawn' ),
				[ 'status' => 400 ]
			);
		}

		$existing_user = get_user_by( 'email', $email );
		if ( $existing_user ) {
			if ( in_array( 'spawn_customer', (array) $existing_user->roles, true ) ) {
				return (int) $existing_user->ID;
			}

			return new WP_Error(
				'google_oauth_role_conflict',
				__( 'An account with this email already exists with a different role.', 'spawn' ),
				[ 'status' => 403 ]
			);
		}

		$password   = wp_generate_password( 20, true, true );
		$user_id    = wp_create_user( $email, $password, $email );
		$final_name = $name ?: $email;

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user = get_user_by( 'ID', $user_id );
		if ( $user ) {
			$user->set_role( 'spawn_customer' );
			wp_update_user( [
				'ID'           => $user_id,
				'display_name' => $final_name,
			] );
		}

		return (int) $user_id;
	}
}
