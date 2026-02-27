<?php
/**
 * Chat REST API Controller.
 *
 * NOTE: This controller is only used for proxy mode:
 * - Admin chat (control plane OpenCode server)
 * - Self-spawn mode (local OpenCode on the same server)
 *
 * Customer chat now uses direct mode: the browser talks directly to the
 * customer's VPS OpenCode server over HTTPS. See blocks/chat/view.ts for the
 * client-side direct mode implementation.
 *
 * Handles chat sessions, messages, and history.
 *
 * @package Spawn
 */

namespace Spawn\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Spawn\Database;

/**
 * Chat controller for REST API.
 */
class Chat_Controller {

	/**
	 * Register chat routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'spawn/v1',
			'/chat/send',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'send' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'message'    => array(
						'required' => true,
						'type'     => 'string',
					),
					'sessionId'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'context'    => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);

		register_rest_route(
			'spawn/v1',
			'/chat/sessions',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_sessions' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		register_rest_route(
			'spawn/v1',
			'/chat/sessions/(?P<sessionId>[a-zA-Z0-9_%:-]+)/history',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'session_history' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'sessionId' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit'     => array(
						'type'    => 'integer',
						'default' => 50,
					),
				),
			)
		);

		register_rest_route(
			'spawn/v1',
			'/chat/generate-title',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'generate_title' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'username' => array(
						'type'              => 'string',
						'default'           => 'friend',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'wordBank' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Build HTTP Basic auth headers for OpenCode server.
	 *
	 * @param string $password OpenCode server password.
	 * @return array HTTP headers.
	 */
	private static function get_opencode_auth_headers( string $password ): array {
		$headers = array(
			'Content-Type' => 'application/json',
		);

		if ( ! empty( $password ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			$headers['Authorization'] = 'Basic ' . base64_encode( 'opencode:' . $password );
		}

		return $headers;
	}

	/**
	 * Extract text reply from OpenCode response parts.
	 *
	 * OpenCode returns { info: {...}, parts: [{type: "text", content: "..."}] }.
	 *
	 * @param array $body Decoded response body.
	 * @return string|null Extracted text or null.
	 */
	private static function extract_reply( array $body ): ?string {
		// Direct parts array in response.
		$parts = $body['parts'] ?? array();

		foreach ( $parts as $part ) {
			if ( isset( $part['type'] ) && 'text' === $part['type'] && ! empty( $part['content'] ) ) {
				return $part['content'];
			}
		}

		// Fallback: try nested under result.
		if ( isset( $body['result']['parts'] ) ) {
			foreach ( $body['result']['parts'] as $part ) {
				if ( isset( $part['type'] ) && 'text' === $part['type'] && ! empty( $part['content'] ) ) {
					return $part['content'];
				}
			}
		}

		return null;
	}

	/**
	 * Create a new session on an OpenCode server.
	 *
	 * @param string $server_url OpenCode server base URL.
	 * @param string $password   OpenCode server password.
	 * @return string|null Session ID or null on failure.
	 */
	private static function create_session( string $server_url, string $password ): ?string {
		$response = wp_remote_post(
			rtrim( $server_url, '/' ) . '/session',
			array(
				'headers' => self::get_opencode_auth_headers( $password ),
				'body'    => wp_json_encode( array() ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return $body['id'] ?? null;
	}

	/**
	 * Send a message to an OpenCode server session.
	 *
	 * @param string $server_url    OpenCode server base URL.
	 * @param string $password      OpenCode server password.
	 * @param string $session_id    Session ID.
	 * @param string $message       User message.
	 * @param string $system_prompt Optional system prompt.
	 * @return WP_REST_Response Response.
	 */
	private static function send_to_opencode(
		string $server_url,
		string $password,
		string $session_id,
		string $message,
		string $system_prompt = ''
	): WP_REST_Response {
		$payload = array(
			'parts' => array(
				array(
					'type' => 'text',
					'text' => $message,
				),
			),
		);

		if ( ! empty( $system_prompt ) ) {
			$payload['system'] = $system_prompt;
		}

		$response = wp_remote_post(
			rtrim( $server_url, '/' ) . '/session/' . $session_id . '/message',
			array(
				'headers' => self::get_opencode_auth_headers( $password ),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response( array(
				'reply' => "I'm having trouble connecting right now. Try again in a moment!",
			) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $code ) {
			return new WP_REST_Response( array(
				'reply' => 'Authentication failed. Check OpenCode server password in Settings → Spawn.',
			) );
		}

		if ( $code >= 400 ) {
			$error_msg = $body['error']['message'] ?? $body['error'] ?? "HTTP $code";
			return new WP_REST_Response( array(
				'reply' => "Something went wrong: $error_msg. Try again in a moment!",
			) );
		}

		$reply = self::extract_reply( $body );

		if ( empty( $reply ) ) {
			return new WP_REST_Response( array(
				'reply' => "I didn't get a response. Could you try again?",
			) );
		}

		return new WP_REST_Response( array(
			'reply'     => $reply,
			'sessionId' => $session_id,
		) );
	}

	/**
	 * Send a chat message.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function send( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id    = get_current_user_id();
		$message    = sanitize_textarea_field( $request->get_param( 'message' ) );
		$session_id = sanitize_text_field( $request->get_param( 'sessionId' ) );
		$context    = $request->get_param( 'context' );

		// Self-spawn mode: local OpenCode installation (highest priority).
		if ( \Spawn\Local_OpenCode::is_running() ) {
			return self::chat_with_local_opencode( $message, $session_id );
		}

		// Admin users chat with the control plane OpenCode (if configured).
		if ( current_user_can( 'manage_options' ) ) {
			$server_url = get_option( 'spawn_opencode_server_url', '' );
			if ( ! empty( $server_url ) ) {
				return self::chat_with_control_plane( $message, $session_id );
			}
		}

		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer ) {
			return new WP_Error(
				'no_customer',
				__( 'No customer account found.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		$is_admin = current_user_can( 'manage_options' );

		$billing_mode = $customer['billing_mode'] ?? 'managed';
		$billing_type = $customer['billing_type'] ?? 'paid';

		if ( ! $is_admin && 'managed' === $billing_mode && 'comped' !== $billing_type ) {
			$credit_balance = (float) ( $customer['credit_balance'] ?? 0 );
			if ( $credit_balance <= 0 ) {
				return new WP_Error(
					'insufficient_credits',
					__( 'Insufficient credits.', 'spawn' ),
					array(
						'status'  => 402,
						'code'    => 'insufficient_credits',
						'balance' => $credit_balance,
					)
				);
			}
		}

		if ( empty( $message ) ) {
			return new WP_Error(
				'empty_message',
				__( 'Message cannot be empty.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		// If server not ready, return placeholder.
		if ( empty( $customer['server_ip'] ) || 'provisioning' === $customer['status'] ) {
			return new WP_REST_Response( array(
				'reply' => "Your website is still being set up! This usually takes a few minutes. I'll be fully operational once it's ready. In the meantime, is there anything you'd like to plan for your site?",
			) );
		}

		// Build system context for the AI.
		$system_prompt = sprintf(
			"[Spawn Web Chat - Bootstrap Interface]\n" .
			"Platform: WordPress\n" .
			"Customer: %s\n" .
			"Site: %s\n" .
			"Status: %s\n" .
			"Mobile channel configured: %s\n\n" .
			'This is the Spawn web chat. Help the user with their WordPress site.',
			$customer['email'],
			$customer['domain'],
			$customer['status'],
			! empty( $context['has_mobile'] ) ? 'yes' : 'no'
		);

		$server_url = 'http://' . $customer['server_ip'] . ':4096';
		$password   = $customer['opencode_password'] ?? '';

		// Create session if none provided.
		if ( empty( $session_id ) ) {
			$session_id = self::create_session( $server_url, $password );
			if ( ! $session_id ) {
				return new WP_REST_Response( array(
					'reply' => "I'm still getting configured. Try again in a moment!",
				) );
			}
		}

		return self::send_to_opencode( $server_url, $password, $session_id, $message, $system_prompt );
	}

	/**
	 * Chat with local OpenCode (self-spawn mode).
	 *
	 * @param string $message    User message.
	 * @param string $session_id Optional session ID.
	 * @return WP_REST_Response Response.
	 */
	private static function chat_with_local_opencode( string $message, string $session_id = '' ): WP_REST_Response {
		$server_url = \Spawn\Local_OpenCode::get_server_url();
		$password   = get_option( 'spawn_local_opencode_password', '' );

		$current_user = wp_get_current_user();
		$site_name    = get_bloginfo( 'name' );
		$site_url     = home_url();

		$system_prompt = sprintf(
			"[Spawn Web Chat - Self-Spawn Mode]\n" .
			"Platform: WordPress (self-hosted OpenCode)\n" .
			"Site: %s (%s)\n" .
			"User: %s <%s>\n" .
			"Interface: Web chat block\n\n" .
			'This is a local OpenCode installation running on this server.',
			$site_name,
			$site_url,
			$current_user->display_name ?: $current_user->user_login,
			$current_user->user_email
		);

		// Create session if none provided.
		if ( empty( $session_id ) ) {
			$session_id = self::create_session( $server_url, $password );
			if ( ! $session_id ) {
				return new WP_REST_Response( array(
					'reply' => 'Connection to local OpenCode failed. Is the server running?',
				) );
			}
		}

		return self::send_to_opencode( $server_url, $password, $session_id, $message, $system_prompt );
	}

	/**
	 * Chat with control plane OpenCode (for admins).
	 *
	 * @param string $message    User message.
	 * @param string $session_id Optional session ID.
	 * @return WP_REST_Response Response.
	 */
	private static function chat_with_control_plane( string $message, string $session_id = '' ): WP_REST_Response {
		$server_url = rtrim( get_option( 'spawn_opencode_server_url', '' ), '/' );
		$password   = get_option( 'spawn_opencode_password', '' );

		if ( empty( $server_url ) ) {
			return new WP_REST_Response( array(
				'reply' => 'Control plane chat not configured. Set OpenCode server URL in Settings → Spawn.',
			) );
		}

		$current_user = wp_get_current_user();
		$site_name    = get_bloginfo( 'name' );
		$site_url     = home_url();

		$system_prompt = sprintf(
			"[Spawn Web Chat - Bootstrap Interface]\n" .
			"Platform: WordPress\n" .
			"Site: %s (%s)\n" .
			"User: %s <%s>\n" .
			"Interface: Web chat block (temporary)\n\n" .
			"This is the Spawn plugin's web-based chat interface.",
			$site_name,
			$site_url,
			$current_user->display_name ?: $current_user->user_login,
			$current_user->user_email
		);

		// Create session if none provided.
		if ( empty( $session_id ) ) {
			$session_id = self::create_session( $server_url, $password );
			if ( ! $session_id ) {
				return new WP_REST_Response( array(
					'reply' => 'Connection to control plane failed. Is the OpenCode server running?',
				) );
			}
		}

		return self::send_to_opencode( $server_url, $password, $session_id, $message, $system_prompt );
	}

	/**
	 * List chat sessions.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function list_sessions(): WP_REST_Response|WP_Error {
		$user_id = get_current_user_id();

		// Self-spawn mode: local OpenCode.
		if ( \Spawn\Local_OpenCode::is_running() ) {
			return self::fetch_sessions(
				\Spawn\Local_OpenCode::get_server_url(),
				get_option( 'spawn_local_opencode_password', '' )
			);
		}

		// Admin uses control plane.
		if ( current_user_can( 'manage_options' ) ) {
			$server_url = get_option( 'spawn_opencode_server_url', '' );
			$password   = get_option( 'spawn_opencode_password', '' );

			if ( empty( $server_url ) ) {
				return new WP_REST_Response( array( 'sessions' => array() ) );
			}

			return self::fetch_sessions( $server_url, $password );
		}

		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer || empty( $customer['server_ip'] ) ) {
			return new WP_REST_Response( array( 'sessions' => array() ) );
		}

		return self::fetch_sessions(
			'http://' . $customer['server_ip'] . ':4096',
			$customer['opencode_password'] ?? ''
		);
	}

	/**
	 * Get session history.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function session_history( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id    = get_current_user_id();
		$session_id = sanitize_text_field( $request->get_param( 'sessionId' ) );
		$limit      = (int) $request->get_param( 'limit' );

		// Self-spawn mode: local OpenCode.
		if ( \Spawn\Local_OpenCode::is_running() ) {
			return self::fetch_session_messages(
				\Spawn\Local_OpenCode::get_server_url(),
				get_option( 'spawn_local_opencode_password', '' ),
				$session_id,
				$limit
			);
		}

		// Admin uses control plane.
		if ( current_user_can( 'manage_options' ) ) {
			$server_url = get_option( 'spawn_opencode_server_url', '' );
			$password   = get_option( 'spawn_opencode_password', '' );

			if ( empty( $server_url ) ) {
				return new WP_REST_Response( array( 'messages' => array() ) );
			}

			return self::fetch_session_messages( $server_url, $password, $session_id, $limit );
		}

		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer || empty( $customer['server_ip'] ) ) {
			return new WP_REST_Response( array( 'messages' => array() ) );
		}

		return self::fetch_session_messages(
			'http://' . $customer['server_ip'] . ':4096',
			$customer['opencode_password'] ?? '',
			$session_id,
			$limit
		);
	}

	/**
	 * Generate a session title.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public static function generate_title( WP_REST_Request $request ): WP_REST_Response {
		$username  = sanitize_text_field( $request->get_param( 'username' ) ) ?: 'friend';
		$word_bank = sanitize_text_field( $request->get_param( 'wordBank' ) );

		if ( empty( $word_bank ) ) {
			$word_bank = 'curious, mystical, cosmic, enchanted, wandering, crow, phoenix, bloom, quest, star';
		}

		$words = array_map( 'trim', explode( ',', $word_bank ) );

		// Simple fallback: pick two random words.
		if ( count( $words ) >= 2 ) {
			shuffle( $words );
			$title = strtolower( $words[0] ) . '-' . strtolower( $words[1] );
		} else {
			$title = 'chat-' . time();
		}

		return new WP_REST_Response( array(
			'title' => $title,
		) );
	}

	/**
	 * Fetch sessions from an OpenCode server.
	 *
	 * @param string $server_url OpenCode server base URL.
	 * @param string $password   OpenCode server password.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	private static function fetch_sessions( string $server_url, string $password ): WP_REST_Response|WP_Error {
		$response = wp_remote_get(
			rtrim( $server_url, '/' ) . '/session',
			array(
				'headers' => self::get_opencode_auth_headers( $password ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'opencode_error',
				__( 'Failed to connect to OpenCode server', 'spawn' ),
				array( 'status' => 502 )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			return new WP_Error(
				'opencode_error',
				$body['error'] ?? __( 'OpenCode request failed', 'spawn' ),
				array( 'status' => $code )
			);
		}

		return new WP_REST_Response( array( 'sessions' => $body ) );
	}

	/**
	 * Fetch session messages from an OpenCode server.
	 *
	 * @param string $server_url OpenCode server base URL.
	 * @param string $password   OpenCode server password.
	 * @param string $session_id Session ID.
	 * @param int    $limit      Max messages to return.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	private static function fetch_session_messages(
		string $server_url,
		string $password,
		string $session_id,
		int $limit
	): WP_REST_Response|WP_Error {
		$url = rtrim( $server_url, '/' ) . '/session/' . urlencode( $session_id ) . '/message';

		if ( $limit > 0 ) {
			$url .= '?limit=' . $limit;
		}

		$response = wp_remote_get(
			$url,
			array(
				'headers' => self::get_opencode_auth_headers( $password ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'opencode_error',
				__( 'Failed to connect to OpenCode server', 'spawn' ),
				array( 'status' => 502 )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			return new WP_Error(
				'opencode_error',
				$body['error'] ?? __( 'OpenCode request failed', 'spawn' ),
				array( 'status' => $code )
			);
		}

		return new WP_REST_Response( array( 'messages' => $body ) );
	}
}
