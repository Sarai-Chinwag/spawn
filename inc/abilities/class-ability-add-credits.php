<?php
/**
 * Add Credits ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Database;
use WP_Error;

/**
 * Adds credits to customer balance.
 */
class Ability_Add_Credits {

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		$amount = (float) ( $input['amount'] ?? 0 );

		if ( $amount <= 0 ) {
			return new WP_Error( 'invalid_amount', __( 'Amount must be positive', 'spawn' ) );
		}

		// Get customer.
		$customer = self::get_customer( $input );
		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		// Add credits.
		$success = Database::add_credits( (int) $customer['id'], $amount );

		if ( ! $success ) {
			return new WP_Error( 'update_failed', __( 'Failed to add credits', 'spawn' ) );
		}

		// Get updated balance.
		$new_balance = Database::get_credit_balance( (int) $customer['id'] );

		return array(
			'success'     => true,
			'added'       => $amount,
			'new_balance' => $new_balance,
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
			$customer = Database::get_customer_by_user_id( $user->ID );
		}

		if ( ! $customer ) {
			return new WP_Error( 'not_found', __( 'Customer not found', 'spawn' ) );
		}

		return $customer;
	}
}
