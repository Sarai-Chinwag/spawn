<?php
/**
 * Auth REST API Controller.
 *
 * Handles login, registration, password reset, and OAuth.
 *
 * @package Spawn
 */

namespace Spawn\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Spawn\Google_OAuth;

/**
 * Auth controller for REST API.
 */
class Auth_Controller {

	/**
	 * Register auth routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'spawn/v1',
			'/auth/login',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'login' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'email'    => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					],
					'password' => [
						'required' => true,
						'type'     => 'string',
					],
				],
			]
		);

		register_rest_route(
			'spawn/v1',
			'/auth/register',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'register' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'email'    => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					],
					'password' => [
						'required'  => true,
						'type'      => 'string',
						'minLength' => 8,
					],
				],
			]
		);

		register_rest_route(
			'spawn/v1',
			'/auth/me',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'me' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'spawn/v1',
			'/auth/logout',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'logout' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		register_rest_route(
			'spawn/v1',
			'/auth/forgot-password',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'forgot_password' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'email' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					],
				],
			]
		);

		register_rest_route(
			'spawn/v1',
			'/auth/reset-password',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'reset_password' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'email'    => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					],
					'token'    => [
						'required' => true,
						'type'     => 'string',
					],
					'password' => [
						'required'  => true,
						'type'      => 'string',
						'minLength' => 8,
					],
				],
			]
		);

		register_rest_route(
			'spawn/v1',
			'/auth/google/configured',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'google_configured' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'spawn/v1',
			'/auth/google',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'google_start' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'spawn/v1',
			'/auth/google/callback',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'google_callback' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Login user.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function login( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$email    = $request->get_param( 'email' );
		$password = $request->get_param( 'password' );

		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			return new WP_Error(
				'invalid_credentials',
				__( 'Invalid email or password.', 'spawn' ),
				[ 'status' => 401 ]
			);
		}

		if ( ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
			return new WP_Error(
				'invalid_credentials',
				__( 'Invalid email or password.', 'spawn' ),
				[ 'status' => 401 ]
			);
		}

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		return new WP_REST_Response( [
			'success' => true,
			'user'    => [
				'id'    => $user->ID,
				'email' => $user->user_email,
				'name'  => $user->display_name,
			],
		] );
	}

	/**
	 * Register new user.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function register( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$email    = $request->get_param( 'email' );
		$password = $request->get_param( 'password' );

		if ( email_exists( $email ) ) {
			return new WP_Error(
				'email_exists',
				__( 'An account with this email already exists.', 'spawn' ),
				[ 'status' => 400 ]
			);
		}

		$user_id = wp_create_user( $email, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			return new WP_Error(
				'registration_failed',
				$user_id->get_error_message(),
				[ 'status' => 400 ]
			);
		}

		$user = get_user_by( 'id', $user_id );
		$user->set_role( 'spawn_customer' );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		return new WP_REST_Response( [
			'success' => true,
			'user'    => [
				'id'    => $user->ID,
				'email' => $user->user_email,
				'name'  => $user->display_name,
			],
		] );
	}

	/**
	 * Get current user info.
	 *
	 * @return WP_REST_Response Response.
	 */
	public static function me(): WP_REST_Response {
		if ( ! is_user_logged_in() ) {
			return new WP_REST_Response( [
				'logged_in' => false,
			] );
		}

		$user = wp_get_current_user();

		return new WP_REST_Response( [
			'logged_in' => true,
			'user'      => [
				'id'    => $user->ID,
				'email' => $user->user_email,
				'name'  => $user->display_name,
			],
		] );
	}

	/**
	 * Logout user.
	 *
	 * @return WP_REST_Response Response.
	 */
	public static function logout(): WP_REST_Response {
		wp_logout();

		return new WP_REST_Response( [
			'success' => true,
		] );
	}

	/**
	 * Request password reset.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public static function forgot_password( WP_REST_Request $request ): WP_REST_Response {
		$email = $request->get_param( 'email' );
		$user  = get_user_by( 'email', $email );

		// Always return success to prevent email enumeration.
		if ( ! $user ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'If an account exists with this email, a reset link has been sent.', 'spawn' ),
			] );
		}

		$reset_key = get_password_reset_key( $user );

		if ( is_wp_error( $reset_key ) ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'If an account exists with this email, a reset link has been sent.', 'spawn' ),
			] );
		}

		$reset_url = add_query_arg(
			[
				'action' => 'reset',
				'key'    => $reset_key,
				'login'  => rawurlencode( $user->user_login ),
			],
			home_url( '/spawn/login/' )
		);

		$site_name = get_bloginfo( 'name' );
		$subject   = sprintf( __( '[%s] Password Reset Request', 'spawn' ), $site_name );
		$message   = sprintf(
			__(
				"Hi %s,\n\n" .
				"Someone requested a password reset for your account.\n\n" .
				"To reset your password, click the link below:\n%s\n\n" .
				"This link will expire in 24 hours.\n\n" .
				"If you didn't request this, you can safely ignore this email.\n\n" .
				"— %s",
				'spawn'
			),
			$user->display_name ?: $user->user_login,
			$reset_url,
			$site_name
		);

		wp_mail( $email, $subject, $message );

		return new WP_REST_Response( [
			'success' => true,
			'message' => __( 'If an account exists with this email, a reset link has been sent.', 'spawn' ),
		] );
	}

	/**
	 * Reset password with token.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function reset_password( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$email    = $request->get_param( 'email' );
		$token    = $request->get_param( 'token' );
		$password = $request->get_param( 'password' );

		// Try to find user by email first, then by login.
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			$user = get_user_by( 'login', $email );
		}

		if ( ! $user ) {
			return new WP_Error(
				'invalid_reset',
				__( 'Invalid password reset request.', 'spawn' ),
				[ 'status' => 400 ]
			);
		}

		$check = check_password_reset_key( $token, $user->user_login );

		if ( is_wp_error( $check ) ) {
			return new WP_Error(
				'invalid_token',
				__( 'This reset link has expired or is invalid. Please request a new one.', 'spawn' ),
				[ 'status' => 400 ]
			);
		}

		reset_password( $user, $password );

		return new WP_REST_Response( [
			'success' => true,
			'message' => __( 'Password has been reset. You can now log in.', 'spawn' ),
		] );
	}

	/**
	 * Check if Google OAuth is configured.
	 *
	 * @return WP_REST_Response Response.
	 */
	public static function google_configured(): WP_REST_Response {
		return new WP_REST_Response( [
			'configured' => Google_OAuth::is_configured(),
		] );
	}

	/**
	 * Start Google OAuth flow.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function google_start(): WP_REST_Response|WP_Error {
		if ( ! Google_OAuth::is_configured() ) {
			return new WP_Error(
				'google_oauth_not_configured',
				__( 'Google OAuth is not configured.', 'spawn' ),
				[ 'status' => 500 ]
			);
		}

		return new WP_REST_Response( [
			'auth_url' => Google_OAuth::get_auth_url(),
		] );
	}

	/**
	 * Handle Google OAuth callback.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function google_callback( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$code  = $request->get_param( 'code' );
		$error = $request->get_param( 'error' );

		if ( $error ) {
			wp_safe_redirect( home_url( '/spawn/login/?error=oauth_denied' ) );
			exit;
		}

		if ( empty( $code ) ) {
			return new WP_Error(
				'missing_code',
				__( 'Missing authorization code.', 'spawn' ),
				[ 'status' => 400 ]
			);
		}

		$result = Google_OAuth::handle_callback( $code );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( home_url( '/spawn/login/?error=oauth_failed' ) );
			exit;
		}

		wp_safe_redirect( home_url( '/spawn/dashboard/' ) );
		exit;
	}
}
