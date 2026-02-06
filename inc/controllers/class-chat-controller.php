<?php
/**
 * Chat REST API Controller.
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
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'send' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'message'    => [
						'required' => true,
						'type'     => 'string',
					],
					'sessionKey' => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'context'    => [
						'type'    => 'object',
						'default' => [],
					],
				],
			]
		);

		register_rest_route(
			'spawn/v1',
			'/chat/sessions',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'list_sessions' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		register_rest_route(
			'spawn/v1',
			'/chat/sessions/(?P<sessionKey>[a-zA-Z0-9_%:-]+)/history',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'session_history' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'sessionKey' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'limit'      => [
						'type'    => 'integer',
						'default' => 50,
					],
				],
			]
		);

		register_rest_route(
			'spawn/v1',
			'/chat/generate-title',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'generate_title' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'username' => [
						'type'              => 'string',
						'default'           => 'friend',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'wordBank' => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Send a chat message.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function send( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id     = get_current_user_id();
		$message     = sanitize_textarea_field( $request->get_param( 'message' ) );
		$session_key = sanitize_text_field( $request->get_param( 'sessionKey' ) );
		$context     = $request->get_param( 'context' );

		// Self-spawn mode: local OpenClaw installation (highest priority).
		if ( \Spawn\Self_Spawn::is_openclaw_running() ) {
			return self::chat_with_local_openclaw( $message, $session_key );
		}

		// Admin users chat with the control plane OpenClaw (if configured).
		if ( current_user_can( 'manage_options' ) ) {
			$gateway_url = get_option( 'spawn_openclaw_gateway_url', '' );
			if ( ! empty( $gateway_url ) ) {
				return self::chat_with_control_plane( $message, $session_key );
			}
		}

		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer ) {
			return new WP_Error(
				'no_customer',
				__( 'No customer account found.', 'spawn' ),
				[ 'status' => 404 ]
			);
		}

		if ( empty( $message ) ) {
			return new WP_Error(
				'empty_message',
				__( 'Message cannot be empty.', 'spawn' ),
				[ 'status' => 400 ]
			);
		}

		// If server not ready, return placeholder.
		if ( empty( $customer['server_ip'] ) || 'provisioning' === $customer['status'] ) {
			return new WP_REST_Response( [
				'reply' => "Your website is still being set up! This usually takes a few minutes. I'll be fully operational once it's ready. In the meantime, is there anything you'd like to plan for your site?",
			] );
		}

		// Build system context for the AI.
		$system_prompt = sprintf(
			"[Spawn Web Chat - Bootstrap Interface]\n" .
			"Platform: WordPress\n" .
			"Customer: %s\n" .
			"Site: %s\n" .
			"Status: %s\n" .
			"Mobile channel configured: %s\n\n" .
			"This is the Spawn web chat. Help the user with their WordPress site.",
			$customer['email'],
			$customer['domain'],
			$customer['status'],
			! empty( $context['has_mobile'] ) ? 'yes' : 'no'
		);

		$gateway_url   = 'http://' . $customer['server_ip'] . ':18789/v1/chat/completions';
		$gateway_token = $customer['openclaw_token'] ?? '';

		if ( empty( $gateway_token ) ) {
			return new WP_REST_Response( [
				'reply' => "I'm still getting configured. Try again in a moment!",
			] );
		}

		$payload = [
			'model'    => 'openclaw:main',
			'messages' => [
				[ 'role' => 'system', 'content' => $system_prompt ],
				[ 'role' => 'user', 'content' => $message ],
			],
		];

		$headers = [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $gateway_token,
		];

		if ( ! empty( $session_key ) ) {
			$headers['x-openclaw-session-key'] = $session_key;
		}

		$response = wp_remote_post( $gateway_url, [
			'headers' => $headers,
			'body'    => wp_json_encode( $payload ),
			'timeout' => 120,
		] );

		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response( [
				'reply' => "I'm having trouble connecting right now. Your site might be restarting. Try again in a moment!",
			] );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 ) {
			$error_msg = $body['error']['message'] ?? "HTTP $code";
			return new WP_REST_Response( [
				'reply' => "Something went wrong: $error_msg. Try again in a moment!",
			] );
		}

		$reply = $body['choices'][0]['message']['content'] ?? null;

		if ( empty( $reply ) ) {
			return new WP_REST_Response( [
				'reply' => "I didn't get a response. Could you try again?",
			] );
		}

		return new WP_REST_Response( [
			'reply' => $reply,
		] );
	}

	/**
	 * Chat with local OpenClaw (self-spawn mode).
	 *
	 * @param string $message     User message.
	 * @param string $session_key Optional session key.
	 * @return WP_REST_Response Response.
	 */
	private static function chat_with_local_openclaw( string $message, string $session_key = '' ): WP_REST_Response {
		$gateway_url = \Spawn\Self_Spawn::get_gateway_url() . '/v1/chat/completions';

		// Self-spawn mode typically uses wp-ai-client credentials configured in OpenClaw.
		// No separate token needed - OpenClaw handles auth internally.
		$current_user = wp_get_current_user();
		$site_name    = get_bloginfo( 'name' );
		$site_url     = home_url();

		$system_prompt = sprintf(
			"[Spawn Web Chat - Self-Spawn Mode]\n" .
			"Platform: WordPress (self-hosted OpenClaw)\n" .
			"Site: %s (%s)\n" .
			"User: %s <%s>\n" .
			"Interface: Web chat block\n\n" .
			"This is a self-spawned OpenClaw installation running on the same server as WordPress.",
			$site_name,
			$site_url,
			$current_user->display_name ?: $current_user->user_login,
			$current_user->user_email
		);

		$payload = [
			'model'    => 'openclaw:main',
			'messages' => [
				[ 'role' => 'system', 'content' => $system_prompt ],
				[ 'role' => 'user', 'content' => $message ],
			],
		];

		$headers = [
			'Content-Type' => 'application/json',
		];

		if ( ! empty( $session_key ) ) {
			$headers['x-openclaw-session-key'] = $session_key;
		}

		$response = wp_remote_post( $gateway_url, [
			'headers' => $headers,
			'body'    => wp_json_encode( $payload ),
			'timeout' => 120,
		] );

		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response( [
				'reply' => 'Connection to local OpenClaw failed: ' . $response->get_error_message(),
			] );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$error_msg = $body['error']['message'] ?? "HTTP $code";
			return new WP_REST_Response( [
				'reply' => "Local OpenClaw error: $error_msg",
			] );
		}

		$reply = $body['choices'][0]['message']['content'] ?? null;

		if ( empty( $reply ) ) {
			return new WP_REST_Response( [
				'reply' => 'No response received from local agent.',
			] );
		}

		return new WP_REST_Response( [
			'reply' => $reply,
		] );
	}

	/**
	 * Chat with control plane OpenClaw (for admins).
	 *
	 * @param string $message     User message.
	 * @param string $session_key Optional session key.
	 * @return WP_REST_Response Response.
	 */
	private static function chat_with_control_plane( string $message, string $session_key = '' ): WP_REST_Response {
		$gateway_base  = rtrim( get_option( 'spawn_openclaw_gateway_url', '' ), '/' );
		$gateway_token = get_option( 'spawn_openclaw_token', '' );

		if ( empty( $gateway_token ) ) {
			return new WP_REST_Response( [
				'reply' => 'Control plane chat not configured. Set spawn_openclaw_token in Settings → Spawn.',
			] );
		}

		$chat_url = $gateway_base . '/v1/chat/completions';

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

		$payload = [
			'model'    => 'openclaw:main',
			'messages' => [
				[ 'role' => 'system', 'content' => $system_prompt ],
				[ 'role' => 'user', 'content' => $message ],
			],
		];

		$headers = [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $gateway_token,
		];

		if ( ! empty( $session_key ) ) {
			$headers['x-openclaw-session-key'] = $session_key;
		}

		$response = wp_remote_post( $chat_url, [
			'headers' => $headers,
			'body'    => wp_json_encode( $payload ),
			'timeout' => 120,
		] );

		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response( [
				'reply' => 'Connection failed: ' . $response->get_error_message(),
			] );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $code ) {
			return new WP_REST_Response( [
				'reply' => 'Authentication failed. Check spawn_openclaw_token matches your gateway auth token.',
			] );
		}

		if ( $code >= 400 ) {
			$error_msg = $body['error']['message'] ?? "HTTP $code";
			return new WP_REST_Response( [
				'reply' => "Gateway error: $error_msg",
			] );
		}

		$reply = $body['choices'][0]['message']['content'] ?? null;

		if ( empty( $reply ) ) {
			return new WP_REST_Response( [
				'reply' => 'No response received from agent.',
			] );
		}

		return new WP_REST_Response( [
			'reply' => $reply,
		] );
	}

	/**
	 * List chat sessions.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function list_sessions(): WP_REST_Response|WP_Error {
		$user_id = get_current_user_id();

		// Self-spawn mode: local OpenClaw.
		if ( \Spawn\Self_Spawn::is_openclaw_running() ) {
			return self::invoke_openclaw_tool(
				\Spawn\Self_Spawn::get_gateway_url(),
				'', // No token needed for local.
				'sessions_list',
				[]
			);
		}

		// Admin uses control plane.
		if ( current_user_can( 'manage_options' ) ) {
			$gateway_url   = get_option( 'spawn_openclaw_gateway_url', '' );
			$gateway_token = get_option( 'spawn_openclaw_token', '' );

			if ( empty( $gateway_url ) || empty( $gateway_token ) ) {
				return new WP_REST_Response( [ 'sessions' => [] ] );
			}

			return self::invoke_openclaw_tool( $gateway_url, $gateway_token, 'sessions_list', [] );
		}

		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer || empty( $customer['server_ip'] ) || empty( $customer['openclaw_token'] ) ) {
			return new WP_REST_Response( [ 'sessions' => [] ] );
		}

		return self::invoke_openclaw_tool(
			'http://' . $customer['server_ip'] . ':18789',
			$customer['openclaw_token'],
			'sessions_list',
			[]
		);
	}

	/**
	 * Get session history.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function session_history( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id     = get_current_user_id();
		$session_key = sanitize_text_field( $request->get_param( 'sessionKey' ) );
		$limit       = (int) $request->get_param( 'limit' );

		// Self-spawn mode: local OpenClaw.
		if ( \Spawn\Self_Spawn::is_openclaw_running() ) {
			return self::invoke_openclaw_tool(
				\Spawn\Self_Spawn::get_gateway_url(),
				'', // No token needed for local.
				'sessions_history',
				[
					'sessionKey' => $session_key,
					'limit'      => $limit,
				]
			);
		}

		// Admin uses control plane.
		if ( current_user_can( 'manage_options' ) ) {
			$gateway_url   = get_option( 'spawn_openclaw_gateway_url', '' );
			$gateway_token = get_option( 'spawn_openclaw_token', '' );

			if ( empty( $gateway_url ) || empty( $gateway_token ) ) {
				return new WP_REST_Response( [ 'messages' => [] ] );
			}

			return self::invoke_openclaw_tool(
				$gateway_url,
				$gateway_token,
				'sessions_history',
				[
					'sessionKey' => $session_key,
					'limit'      => $limit,
				]
			);
		}

		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer || empty( $customer['server_ip'] ) || empty( $customer['openclaw_token'] ) ) {
			return new WP_REST_Response( [ 'messages' => [] ] );
		}

		return self::invoke_openclaw_tool(
			'http://' . $customer['server_ip'] . ':18789',
			$customer['openclaw_token'],
			'sessions_history',
			[
				'sessionKey' => $session_key,
				'limit'      => $limit,
			]
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

		return new WP_REST_Response( [
			'title' => $title,
		] );
	}

	/**
	 * Invoke an OpenClaw tool via the /tools/invoke endpoint.
	 *
	 * @param string $gateway_url   Base URL of the OpenClaw gateway.
	 * @param string $gateway_token Auth token.
	 * @param string $tool          Tool name.
	 * @param array  $args          Tool arguments.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	private static function invoke_openclaw_tool(
		string $gateway_url,
		string $gateway_token,
		string $tool,
		array $args
	): WP_REST_Response|WP_Error {
		$url = rtrim( $gateway_url, '/' ) . '/tools/invoke';

		$response = wp_remote_post( $url, [
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $gateway_token,
			],
			'body'    => wp_json_encode( [
				'tool' => $tool,
				'args' => $args,
			] ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'openclaw_error',
				__( 'Failed to connect to OpenClaw', 'spawn' ),
				[ 'status' => 502 ]
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			return new WP_Error(
				'openclaw_error',
				$body['error'] ?? __( 'OpenClaw request failed', 'spawn' ),
				[ 'status' => $code ]
			);
		}

		return new WP_REST_Response( $body );
	}
}
