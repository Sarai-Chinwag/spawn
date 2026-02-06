<?php
/**
 * Get Metrics ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Database;
use WP_Error;

/**
 * Returns aggregate metrics for the Spawn service.
 */
class Ability_Get_Metrics {

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		global $wpdb;

		$days = isset( $input['days'] ) ? absint( $input['days'] ) : 30;
		$days = min( max( $days, 1 ), 365 ); // Cap between 1 and 365 days.

		$customers_table = Database::get_table_name();
		$domains_table   = Database::get_domains_table_name();
		$servers_table   = Database::get_servers_table_name();

		$date_limit = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// Total customers (all time).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted source.
		$total_customers = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$customers_table}" );

		// Active customers.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted source.
		$active_customers = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$customers_table} WHERE status = %s",
				'active'
			)
		);

		// Customers in period.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted source.
		$new_customers_period = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$customers_table} WHERE created_at >= %s",
				$date_limit
			)
		);

		// Total credit balance (sum of all active customer credits).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted source.
		$total_credits_balance = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(credit_balance), 0) FROM {$customers_table} WHERE status = %s",
				'active'
			)
		);

		// Count domains registered (from main customers table where domain_type = 'register').
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted source.
		$domains_registered_customers = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$customers_table} WHERE domain_type = %s AND domain IS NOT NULL",
				'register'
			)
		);

		// Count domains from domains table.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted source.
		$domains_registered_table = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$domains_table}" );

		$domains_registered = $domains_registered_customers + $domains_registered_table;

		// Provisioning success rate.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted source.
		$total_provisioned = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$customers_table} WHERE status IN (%s, %s)",
				'active',
				'failed'
			)
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted source.
		$successful_provisions = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$customers_table} WHERE status = %s",
				'active'
			)
		);

		$provisioning_success_rate = $total_provisioned > 0
			? round( ( $successful_provisions / $total_provisioned ) * 100, 2 )
			: 100.0;

		// Revenue estimation (domain purchases in period).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted source.
		$domain_revenue = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(domain_price), 0) FROM {$customers_table} WHERE domain_type = %s AND created_at >= %s",
				'register',
				$date_limit
			)
		);

		// Status breakdown.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted source.
		$status_breakdown = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$customers_table} GROUP BY status",
			ARRAY_A
		);

		$by_status = [];
		foreach ( $status_breakdown as $row ) {
			$by_status[ $row['status'] ] = (int) $row['count'];
		}

		// Tier breakdown.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted source.
		$tier_breakdown = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tier, COUNT(*) as count FROM {$customers_table} WHERE status = %s GROUP BY tier",
				'active'
			),
			ARRAY_A
		);

		$by_tier = [];
		foreach ( $tier_breakdown as $row ) {
			$by_tier[ $row['tier'] ] = (int) $row['count'];
		}

		return [
			'period_days'              => $days,
			'total_customers'          => $total_customers,
			'active_customers'         => $active_customers,
			'new_customers_period'     => $new_customers_period,
			'total_credits_balance'    => $total_credits_balance,
			'domain_revenue_period'    => $domain_revenue,
			'provisioning_success_rate' => $provisioning_success_rate,
			'domains_registered'       => $domains_registered,
			'by_status'                => $by_status,
			'by_tier'                  => $by_tier,
		];
	}
}
