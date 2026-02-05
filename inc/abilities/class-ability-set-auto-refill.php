<?php
/**
 * Set Auto-Refill ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Database;
use WP_Error;

/**
 * Allows agents to manage auto-refill settings for customers.
 */
class Ability_Set_Auto_Refill {

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

		$customer_id = (int) $customer['id'];
		$enabled     = isset( $input['enabled'] ) ? (bool) $input['enabled'] : null;
		$threshold   = isset( $input['threshold'] ) ? (float) $input['threshold'] : null;
		$amount      = isset( $input['amount'] ) ? (float) $input['amount'] : null;

		// Get current settings.
		$current = Database::get_auto_refill_settings( $customer_id );

		// If no changes specified, just return current settings.
		if ( null === $enabled && null === $threshold && null === $amount ) {
			return [
				'customer_id' => $customer_id,
				'enabled'     => $current['enabled'] ?? false,
				'threshold'   => (float) ( $current['threshold'] ?? 5.00 ),
				'amount'      => (float) ( $current['amount'] ?? 10.00 ),
				'message'     => 'Current auto-refill settings retrieved.',
			];
		}

		// Merge with current settings.
		$new_enabled   = $enabled ?? ( $current['enabled'] ?? false );
		$new_threshold = $threshold ?? ( (float) ( $current['threshold'] ?? 5.00 ) );
		$new_amount    = $amount ?? ( (float) ( $current['amount'] ?? 10.00 ) );

		// Validate threshold (in dollars).
		if ( $new_threshold < 1.00 || $new_threshold > 100.00 ) {
			return new WP_Error(
				'invalid_threshold',
				__( 'Threshold must be between $1 and $100.', 'spawn' )
			);
		}

		// Validate amount (in dollars, minimum $10).
		if ( $new_amount < 10.00 || $new_amount > 100.00 ) {
			return new WP_Error(
				'invalid_amount',
				__( 'Refill amount must be between $10 and $100.', 'spawn' )
			);
		}

		// Update settings.
		$success = Database::update_auto_refill_settings(
			$customer_id,
			$new_enabled,
			$new_threshold,
			$new_amount
		);

		if ( ! $success ) {
			return new WP_Error(
				'update_failed',
				__( 'Failed to update auto-refill settings.', 'spawn' )
			);
		}

		$action = $new_enabled ? 'enabled' : 'disabled';

		return [
			'customer_id' => $customer_id,
			'enabled'     => $new_enabled,
			'threshold'   => $new_threshold,
			'amount'      => $new_amount,
			'message'     => sprintf(
				/* translators: 1: action (enabled/disabled), 2: threshold, 3: amount */
				__( 'Auto-refill %1$s. Will refill $%3$.2f when balance falls below $%2$.2f.', 'spawn' ),
				$action,
				$new_threshold,
				$new_amount
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
			$customer = Database::get_customer_by_email( $user->user_email );
		}

		if ( ! $customer ) {
			return new WP_Error( 'not_found', __( 'Customer not found', 'spawn' ) );
		}

		return $customer;
	}
}
