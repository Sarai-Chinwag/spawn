<?php
/**
 * Manage Billing ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Database;
use Spawn\Stripe;
use WP_Error;

/**
 * Returns Stripe customer portal URL.
 */
class Ability_Manage_Billing {

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		// Get customer.
		$customer = self::get_customer( $input );
		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		// Must have Stripe customer ID.
		if ( empty( $customer['stripe_customer'] ) ) {
			return new WP_Error( 'no_stripe', __( 'No billing account found', 'spawn' ) );
		}

		// Get portal URL.
		$return_url = home_url( '/spawn/dashboard/' );
		$portal_url = Stripe::create_portal_session( $customer['stripe_customer'], $return_url );

		if ( is_wp_error( $portal_url ) ) {
			return $portal_url;
		}

		return [
			'success'    => true,
			'portal_url' => $portal_url,
		];
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
