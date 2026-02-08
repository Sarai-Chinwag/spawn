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
			$period_start = gmdate( 'Y-m-01' );
			$period_end   = gmdate( 'Y-m-t' );
		} else {
			$period_start = gmdate( 'Y-m-01', strtotime( 'first day of last month' ) );
			$period_end   = gmdate( 'Y-m-t', strtotime( 'last day of last month' ) );
		}

		// Get AI usage from usage table (server_id = customer_id as proxy).
		$usage         = Database::get_server_usage( (int) $customer['id'], 1 );
		$current_usage = $usage[0] ?? null;

		// Get tier info for included credits.
		$tier             = $customer['tier'] ?? 'starter';
		$tier_config      = \Spawn\Config::get_tier( $tier );
		$included_credits = $tier_config['included_credits'] ?? 5.0;

		return array(
			'customer_id'      => (int) $customer['id'],
			'tier'             => $tier,
			'credit_balance'   => (float) $customer['credit_balance'],
			'included_credits' => $included_credits,
			'auto_refill'      => (bool) $customer['auto_refill_enabled'],
			'ai_usage'         => array(
				'credits_used'   => (float) ( $current_usage['credits_used'] ?? 0 ),
				'requests_count' => (int) ( $current_usage['requests_count'] ?? 0 ),
				'tokens_input'   => (int) ( $current_usage['tokens_input'] ?? 0 ),
				'tokens_output'  => (int) ( $current_usage['tokens_output'] ?? 0 ),
			),
			'period_start'     => $period_start,
			'period_end'       => $period_end,
			'period'           => $period,
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
