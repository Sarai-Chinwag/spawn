<?php
/**
 * Get Status ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Database;
use WP_Error;

/**
 * Returns customer subscription status.
 */
class Ability_Get_Status {

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		$customer = self::get_customer( $input );

		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		return array(
			'customer_id'    => (int) $customer['id'],
			'email'          => $customer['email'],
			'domain'         => $customer['domain'],
			'server_type'       => $customer['server_type'],
			'status'         => $customer['status'],
			'server_ip'      => $customer['server_ip'],
			'credit_balance' => (float) $customer['credit_balance'],
			'created_at'     => $customer['created_at'],
		);
	}

	/**
	 * Get customer from input or current user.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Customer data or error.
	 */
	private static function get_customer( array $input ): array|WP_Error {
		if ( ! empty( $input['customer_id'] ) ) {
			$customer = Database::get_customer( (int) $input['customer_id'] );
		} else {
			$user = wp_get_current_user();
			if ( ! $user->ID ) {
				return new WP_Error( 'not_logged_in', __( 'You must be logged in', 'spawn' ) );
			}
			$customer = Database::get_customer_by_email( $user->user_email );
		}

		if ( ! $customer ) {
			return new WP_Error( 'not_found', __( 'Customer not found', 'spawn' ) );
		}

		return $customer;
	}
}
