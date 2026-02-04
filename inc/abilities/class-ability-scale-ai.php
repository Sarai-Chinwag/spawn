<?php
/**
 * Scale AI ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Database;
use Spawn\Stripe;
use WP_Error;

/**
 * Changes AI token allocation tier.
 */
class Ability_Scale_AI {

	/**
	 * AI tier to Stripe price mapping.
	 */
	private const TIER_PRICES = [
		'1k'  => 'spawn_stripe_price_ai_1k',
		'5k'  => 'spawn_stripe_price_ai_5k',
		'20k' => 'spawn_stripe_price_ai_20k',
	];

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		$new_tier = $input['new_tier'] ?? '';

		if ( ! isset( self::TIER_PRICES[ $new_tier ] ) ) {
			return new WP_Error( 'invalid_tier', __( 'Invalid AI tier', 'spawn' ) );
		}

		// Get customer.
		$customer = self::get_customer( $input );
		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		// Check if already on this tier.
		if ( $customer['ai_tier'] === $new_tier ) {
			return new WP_Error( 'same_tier', __( 'Already on this tier', 'spawn' ) );
		}

		// Update Stripe subscription (swap AI line item).
		$price_id = get_option( self::TIER_PRICES[ $new_tier ], '' );
		if ( ! empty( $price_id ) && ! empty( $customer['stripe_subscription'] ) ) {
			// TODO: Call Stripe to update subscription line item.
			// For now, just update the database.
		}

		// Update database.
		$success = Database::update_ai_tier( (int) $customer['id'], $new_tier );

		if ( ! $success ) {
			return new WP_Error( 'update_failed', __( 'Failed to update tier', 'spawn' ) );
		}

		// Get updated customer.
		$updated = Database::get_customer( (int) $customer['id'] );

		return [
			'success'        => true,
			'new_tier'       => $new_tier,
			'ai_calls_limit' => (int) $updated['ai_calls_limit'],
			'effective_date' => current_time( 'mysql' ),
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
