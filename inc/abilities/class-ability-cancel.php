<?php
/**
 * Cancel ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Database;
use Spawn\Stripe;
use WP_Error;

/**
 * Cancels subscription.
 */
class Ability_Cancel {

	/**
	 * Grace period in days before VPS deletion.
	 */
	private const GRACE_PERIOD_DAYS = 7;

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		$reason = $input['reason'] ?? '';

		// Get customer.
		$customer = self::get_customer( $input );
		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		// Check if already cancelled.
		if ( 'cancelled' === $customer['status'] ) {
			return new WP_Error( 'already_cancelled', __( 'Subscription already cancelled', 'spawn' ) );
		}

		// Cancel Stripe subscription.
		if ( ! empty( $customer['stripe_subscription'] ) ) {
			$result = Stripe::cancel_subscription( $customer['stripe_subscription'] );
			if ( is_wp_error( $result ) ) {
				// Log but don't fail - customer may have cancelled via Stripe portal.
				error_log( sprintf( '[Spawn] Stripe cancel failed: %s', $result->get_error_message() ) );
			}
		}

		// Calculate grace period end.
		$cancelled_at     = current_time( 'mysql' );
		$grace_period_end = gmdate( 'Y-m-d H:i:s', strtotime( "+{self::GRACE_PERIOD_DAYS} days" ) );

		// Update customer status.
		Database::update_customer( (int) $customer['id'], [
			'status'       => 'cancelled',
			'cancelled_at' => $cancelled_at,
		] );

		// TODO: Schedule VPS deletion after grace period.
		// TODO: Send cancellation confirmation email.

		return [
			'success'          => true,
			'cancellation_date' => $cancelled_at,
			'grace_period_end' => $grace_period_end,
			'message'          => sprintf(
				__( 'Your subscription has been cancelled. Your site will remain active until %s.', 'spawn' ),
				$grace_period_end
			),
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
