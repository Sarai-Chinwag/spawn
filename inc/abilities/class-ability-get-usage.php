<?php
/**
 * Get Usage ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Database;
use WP_Error;

/**
 * Returns usage statistics.
 */
class Ability_Get_Usage {

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		$period = $input['period'] ?? 'current';

		// Get customer.
		$customer = self::get_customer( $input );
		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		// Calculate period bounds.
		if ( 'current' === $period ) {
			$period_start = gmdate( 'Y-m-01 00:00:00' );
			$period_end   = gmdate( 'Y-m-t 23:59:59' );
		} else {
			$period_start = gmdate( 'Y-m-01 00:00:00', strtotime( 'first day of last month' ) );
			$period_end   = gmdate( 'Y-m-t 23:59:59', strtotime( 'last day of last month' ) );
		}

		return [
			'customer_id'    => (int) $customer['id'],
			'ai_calls_used'  => (int) $customer['ai_calls_used'],
			'ai_calls_limit' => (int) $customer['ai_calls_limit'],
			'ai_calls_pct'   => $customer['ai_calls_limit'] > 0 
				? round( ( $customer['ai_calls_used'] / $customer['ai_calls_limit'] ) * 100, 1 )
				: 0,
			'bandwidth_mb'   => 0, // TODO: Track bandwidth.
			'storage_mb'     => 0, // TODO: Track storage.
			'period_start'   => $period_start,
			'period_end'     => $period_end,
			'period'         => $period,
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
