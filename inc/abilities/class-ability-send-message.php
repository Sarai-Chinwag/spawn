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
 * Sends a message to a customer's AI agent via their OpenCode instance.
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

		$server_url = 'http://' . $customer['server_ip'] . ':4096';
		$password   = $customer['opencode_password'] ?? '';

		if ( empty( $password ) ) {
			return new WP_Error(
				'no_password',
				__( 'Customer OpenCode password not configured.', 'spawn' )
			);
		}

		// Build system context.
		$system_prompt = sprintf(
			"[Spawn System Message]\n" .
			"Customer: %s\n" .
			"Site: %s\n" .
			'%s',
			$customer['email'],
			$customer['domain'] ?? 'N/A',
			! empty( $system_note ) ? "\nContext: $system_note" : ''
		);

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Basic ' . base64_encode( 'opencode:' . $password ),
		);

		// Create or reuse session.
		$session_id = $session_key;
		if ( empty( $session_id ) ) {
			$session_response = wp_remote_post( $server_url . '/session', array(
				'headers' => $headers,
				'body'    => wp_json_encode( array() ),
				'timeout' => 15,
			) );

			if ( is_wp_error( $session_response ) ) {
				return new WP_Error(
					'connection_failed',
					__( 'Failed to connect to customer agent: ', 'spawn' ) . $session_response->get_error_message()
				);
			}

			$session_body = json_decode( wp_remote_retrieve_body( $session_response ), true );
			$session_id   = $session_body['id'] ?? '';

			if ( empty( $session_id ) ) {
				return new WP_Error( 'session_failed', __( 'Failed to create OpenCode session.', 'spawn' ) );
			}
		}

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

		$response = wp_remote_post( $server_url . '/session/' . $session_id . '/message', array(
			'headers' => $headers,
			'body'    => wp_json_encode( $payload ),
			'timeout' => 120,
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'connection_failed',
				__( 'Failed to connect to customer agent: ', 'spawn' ) . $response->get_error_message()
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$error_msg = $body['error']['message'] ?? $body['error'] ?? "HTTP $code";
			return new WP_Error( 'agent_error', "Agent error: $error_msg" );
		}

		// Extract text reply from OpenCode response parts.
		$reply = null;
		$parts = $body['parts'] ?? array();
		foreach ( $parts as $part ) {
			if ( isset( $part['type'] ) && 'text' === $part['type'] && ! empty( $part['content'] ) ) {
				$reply = $part['content'];
				break;
			}
		}

		return array(
			'success'     => true,
			'customer_id' => (int) $customer['id'],
			'reply'       => $reply,
		);
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
