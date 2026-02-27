<?php
/**
 * Chat REST API Controller.
 *
 * NOTE: This controller is only used for proxy mode:
 * - Admin chat (control plane agent server)
 * - Self-spawn mode (local agent on the same server)
 *
 * Customer chat now uses direct mode: the browser talks directly to the
 * customer's agent server over HTTPS. See blocks/chat/view.ts for the
 * client-side direct mode implementation.
 *
 * Agent-agnostic: all agent communication goes through Agent_Factory / Agent_Adapter.
 *
 * @package Spawn
 */

namespace Spawn\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Spawn\Agent_Factory;
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
	 * Resolve the appropriate agent adapter for the current request.
	 *
	 * Priority: local agent > admin control plane > customer agent.
	 *
	 * @param array|null $customer Customer record (null for admin/local).
	 * @return \Spawn\Agent_Adapter|null Adapter or null.
	 */
	private static function resolve_adapter( ?array $customer = null ): ?\Spawn\Agent_Adapter {
		// Self-spawn mode: local agent installation (highest priority).
		$local = Agent_Factory::for_local();
		if ( $local ) {
			return $local;
		}

		// Admin users chat with the control plane agent (if configured).
		if ( current_user_can( 'manage_options' ) ) {
			$control_plane = Agent_Factory::for_control_plane();
			if ( $control_plane ) {
				return $control_plane;
			}
		}

		// Customer agent.
		if ( $customer ) {
			return Agent_Factory::for_customer( $customer );
		}

		return null;
	}

	/**
	 * Build a system prompt based on context.
	 *
	 * @param string     $mode     Chat mode ('local', 'control_plane', 'customer').
	 * @param array|null $customer Customer record (for customer mode).
	 * @param array      $context  Additional context from request.
	 * @return string System prompt.
	 */
	private static function build_system_prompt( string $mode, ?array $customer = null, array $context = array() ): string {
		$current_user = wp_get_current_user();
		$site_name    = get_bloginfo( 'name' );
		$site_url     = home_url();

		switch ( $mode ) {
			case 'local':
				return sprintf(
					"[Spawn Web Chat - Self-Spawn Mode]\n" .
					"Platform: WordPress (self-hosted agent)\n" .
					"Site: %s (%s)\n" .
					"User: %s <%s>\n" .
					"Interface: Web chat block\n\n" .
					'This is a local agent installation running on this server.',
					$site_name,
					$site_url,
					$current_user->display_name ?: $current_user->user_login,
					$current_user->user_email
				);

			case 'control_plane':
				return sprintf(
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

			case 'customer':
			default:
				return sprintf(
					"[Spawn Web Chat - Bootstrap Interface]\n" .
					"Platform: WordPress\n" .
					"Customer: %s\n" .
					"Site: %s\n" .
					"Status: %s\n" .
					"Mobile channel configured: %s\n\n" .
					'This is the Spawn web chat. Help the user with their WordPress site.',
					$customer['email'] ?? '',
					$customer['domain'] ?? '',
					$customer['status'] ?? '',
					! empty( $context['has_mobile'] ) ? 'yes' : 'no'
				);
		}
	}

	/**
	 * Determine chat mode and get adapter.
	 *
	 * @param array|null $customer Customer record.
	 * @return string Chat mode ('local', 'control_plane', 'customer').
	 */
	private static function detect_mode( ?array $customer = null ): string {
		if ( Agent_Factory::is_local_running() ) {
			return 'local';
		}

		if ( current_user_can( 'manage_options' ) && Agent_Factory::for_control_plane() ) {
			return 'control_plane';
		}

		return 'customer';
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

		if ( empty( $message ) ) {
			return new WP_Error(
				'empty_message',
				__( 'Message cannot be empty.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		// Try local or control plane first (no customer needed).
		$mode    = self::detect_mode();
		$adapter = null;

		if ( 'local' === $mode || 'control_plane' === $mode ) {
			$adapter = self::resolve_adapter();

			if ( $adapter ) {
				$system_prompt = self::build_system_prompt( $mode );
				return self::send_with_adapter( $adapter, $session_id, $message, $system_prompt );
			}
		}

		// Customer mode — need customer record.
		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer ) {
			return new WP_Error(
				'no_customer',
				__( 'No customer account found.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		$is_admin     = current_user_can( 'manage_options' );
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

		// If server not ready, return placeholder.
		if ( empty( $customer['server_ip'] ) || 'provisioning' === $customer['status'] ) {
			return new WP_REST_Response( array(
				'reply' => "Your website is still being set up! This usually takes a few minutes. I'll be fully operational once it's ready. In the meantime, is there anything you'd like to plan for your site?",
			) );
		}

		$adapter = Agent_Factory::for_customer( $customer );

		if ( ! $adapter ) {
			return new WP_REST_Response( array(
				'reply' => "I'm still getting configured. Try again in a moment!",
			) );
		}

		$system_prompt = self::build_system_prompt( 'customer', $customer, $context ?? array() );
		return self::send_with_adapter( $adapter, $session_id, $message, $system_prompt );
	}

	/**
	 * Send a message using an adapter, handling session creation.
	 *
	 * @param \Spawn\Agent_Adapter $adapter       Agent adapter.
	 * @param string               $session_id    Session ID (may be empty).
	 * @param string               $message       User message.
	 * @param string               $system_prompt System prompt.
	 * @return WP_REST_Response Response.
	 */
	private static function send_with_adapter(
		\Spawn\Agent_Adapter $adapter,
		string $session_id,
		string $message,
		string $system_prompt
	): WP_REST_Response {
		// Create session if none provided.
		if ( empty( $session_id ) ) {
			$result = $adapter->create_session();
			if ( is_wp_error( $result ) ) {
				return new WP_REST_Response( array(
					'reply' => "I'm having trouble connecting right now. Try again in a moment!",
				) );
			}
			$session_id = $result;
		}

		$result = $adapter->send_message( $session_id, $message, $system_prompt );

		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();

			if ( 'agent_auth_failed' === $code ) {
				return new WP_REST_Response( array(
					'reply' => 'Authentication failed. Check agent password in Settings → Spawn.',
				) );
			}

			return new WP_REST_Response( array(
				'reply' => "Something went wrong: " . $result->get_error_message() . ". Try again in a moment!",
			) );
		}

		$reply = $result['reply'] ?? null;

		if ( empty( $reply ) ) {
			return new WP_REST_Response( array(
				'reply' => "I didn't get a response. Could you try again?",
			) );
		}

		return new WP_REST_Response( array(
			'reply'     => $reply,
			'sessionId' => $result['session_id'] ?? $session_id,
		) );
	}

	/**
	 * List chat sessions.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function list_sessions(): WP_REST_Response|WP_Error {
		$user_id = get_current_user_id();
		$adapter = self::resolve_adapter();

		if ( ! $adapter ) {
			// Try customer adapter.
			$customer = Database::get_customer_by_user_id( $user_id );

			if ( ! $customer || empty( $customer['server_ip'] ) ) {
				return new WP_REST_Response( array( 'sessions' => array() ) );
			}

			$adapter = Agent_Factory::for_customer( $customer );
		}

		if ( ! $adapter ) {
			return new WP_REST_Response( array( 'sessions' => array() ) );
		}

		$result = $adapter->list_sessions();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'sessions' => $result ) );
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

		$adapter = self::resolve_adapter();

		if ( ! $adapter ) {
			$customer = Database::get_customer_by_user_id( $user_id );

			if ( ! $customer || empty( $customer['server_ip'] ) ) {
				return new WP_REST_Response( array( 'messages' => array() ) );
			}

			$adapter = Agent_Factory::for_customer( $customer );
		}

		if ( ! $adapter ) {
			return new WP_REST_Response( array( 'messages' => array() ) );
		}

		$result = $adapter->get_messages( $session_id, $limit );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'messages' => $result ) );
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
}
