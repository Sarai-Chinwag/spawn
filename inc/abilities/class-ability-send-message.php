<?php
/**
 * Send Message ability - core ability to message customer's AI agent.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Database;
use WP_Error;

/**
 * Sends a message to a customer's AI agent via their OpenClaw instance.
 */
class Ability_Send_Message {

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		$message     = $input['message'] ?? '';
		$customer_id = $input['customer_id'] ?? null;
		$session_key = $input['session_key'] ?? '';
		$system_note = $input['system_note'] ?? '';

		if ( empty( $message ) ) {
			return new WP_Error( 'empty_message', __( 'Message cannot be empty.', 'spawn' ) );
		}

		// Get customer - either by ID or current user.
		$customer = self::get_customer( $customer_id );
		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		// Check if server is ready.
		if ( empty( $customer['server_ip'] ) || 'provisioning' === $customer['status'] ) {
			return new WP_Error( 
				'server_not_ready', 
				__( 'Customer server is not ready yet.', 'spawn' ) 
			);
		}

		$gateway_url   = 'http://' . $customer['server_ip'] . ':18789/v1/chat/completions';
		$gateway_token = $customer['openclaw_token'] ?? '';

		if ( empty( $gateway_token ) ) {
			return new WP_Error( 
				'no_token', 
				__( 'Customer OpenClaw token not configured.', 'spawn' ) 
			);
		}

		// Build system context.
		$system_prompt = sprintf(
			"[Spawn System Message]\n" .
			"Customer: %s\n" .
			"Site: %s\n" .
			"%s",
			$customer['email'],
			$customer['domain'] ?? 'N/A',
			! empty( $system_note ) ? "\nContext: $system_note" : ''
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

		$response = wp_remote_post( $gateway_url, [
			'headers' => $headers,
			'body'    => wp_json_encode( $payload ),
			'timeout' => 120,
		] );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 
				'connection_failed', 
				__( 'Failed to connect to customer agent: ', 'spawn' ) . $response->get_error_message() 
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$error_msg = $body['error']['message'] ?? "HTTP $code";
			return new WP_Error( 'agent_error', "Agent error: $error_msg" );
		}

		$reply = $body['choices'][0]['message']['content'] ?? null;

		return [
			'success'     => true,
			'customer_id' => (int) $customer['id'],
			'reply'       => $reply,
		];
	}

	/**
	 * Get customer from input or current user.
	 *
	 * @param int|null $customer_id Customer ID or null for current user.
	 * @return array|WP_Error Customer data or error.
	 */
	private static function get_customer( ?int $customer_id ): array|WP_Error {
		if ( ! empty( $customer_id ) ) {
			$customer = Database::get_customer( $customer_id );
		} else {
			$user = wp_get_current_user();
			if ( ! $user->ID ) {
				return new WP_Error( 'not_logged_in', __( 'You must be logged in.', 'spawn' ) );
			}
			$customer = Database::get_customer_by_user_id( $user->ID );
			
			if ( ! $customer ) {
				$customer = Database::get_customer_by_email( $user->user_email );
			}
		}

		if ( ! $customer ) {
			return new WP_Error( 'not_found', __( 'Customer not found.', 'spawn' ) );
		}

		return $customer;
	}
}
