<?php
/**
 * Get Customer Credits ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Database;
use WP_Error;

/**
 * Returns credit balance and usage summary for a customer.
 */
class Ability_Get_Customer_Credits {

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		global $wpdb;

		$customer_id = isset( $input['customer_id'] ) ? absint( $input['customer_id'] ) : 0;

		if ( ! $customer_id ) {
			return new WP_Error( 'missing_customer_id', __( 'customer_id is required', 'spawn' ) );
		}

		// Get customer.
		$customer = Database::get_customer( $customer_id );
		if ( ! $customer ) {
			return new WP_Error( 'not_found', __( 'Customer not found', 'spawn' ) );
		}

		// Get usage summary from wp_spawn_usage table.
		$usage_table = Database::get_usage_table_name();
		$user_id     = $customer['user_id'];

		$total_used = 0.0;

		if ( $user_id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted source.
			$total_used = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(credits_used), 0) FROM {$usage_table} WHERE user_id = %d",
					$user_id
				)
			);
		}

		// Calculate total purchased: current balance + total used.
		// Note: This is an approximation since we don't track purchases separately.
		$current_balance = (float) $customer['credit_balance'];
		$total_purchased = $current_balance + (float) $total_used;

		return [
			'customer_id'     => $customer_id,
			'current_credits' => $current_balance,
			'total_purchased' => $total_purchased,
			'total_used'      => (float) $total_used,
		];
	}
}
