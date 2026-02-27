<?php
/**
 * Send Message ability - core ability to message customer's AI agent.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Agent_Factory;
use Spawn\Database;
use WP_Error;

/**
 * Sends a message to a customer's AI agent via the configured agent adapter.
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

		$adapter = Agent_Factory::for_customer( $customer );

		if ( ! $adapter ) {
			return new WP_Error(
				'no_agent',
				__( 'Customer agent not configured.', 'spawn' )
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

		// Create or reuse session.
		$session_id = $session_key;
		if ( empty( $session_id ) ) {
			$result = $adapter->create_session();
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$session_id = $result;
		}

		$result = $adapter->send_message( $session_id, $message, $system_prompt );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'     => true,
			'customer_id' => (int) $customer['id'],
			'reply'       => $result['reply'] ?? null,
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
