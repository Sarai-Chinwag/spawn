<?php
/**
 * List Customers ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Database;
use WP_Error;

/**
 * Returns a list of customers with filtering and pagination.
 */
class Ability_List_Customers {

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		global $wpdb;

		$status = isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : null;
		$limit  = isset( $input['limit'] ) ? absint( $input['limit'] ) : 50;
		$offset = isset( $input['offset'] ) ? absint( $input['offset'] ) : 0;

		// Cap limit to prevent abuse.
		$limit = min( $limit, 100 );

		$table = Database::get_table_name();
		$where = '';
		$args  = [];

		if ( $status ) {
			$where = 'WHERE status = %s';
			$args[] = $status;
		}

		$args[] = $limit;
		$args[] = $offset;

		// Build query with prepared values.
		if ( $status ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted source.
			$query = $wpdb->prepare(
				"SELECT id, email, domain, status, tier, credit_balance, created_at, server_ip FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				...$args
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted source.
			$query = $wpdb->prepare(
				"SELECT id, email, domain, status, tier, credit_balance, created_at, server_ip FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query already prepared above.
		$customers = $wpdb->get_results( $query, ARRAY_A );

		if ( null === $customers ) {
			return new WP_Error( 'db_error', __( 'Database query failed', 'spawn' ) );
		}

		// Get total count.
		if ( $status ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted source.
			$total = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE status = %s",
					$status
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted source.
			$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		// Format output.
		$formatted = array_map(
			function ( $customer ) {
				return [
					'id'             => (int) $customer['id'],
					'email'          => $customer['email'],
					'domain'         => $customer['domain'],
					'status'         => $customer['status'],
					'tier'           => $customer['tier'],
					'credit_balance' => (float) $customer['credit_balance'],
					'created_at'     => $customer['created_at'],
					'server_ip'      => $customer['server_ip'],
				];
			},
			$customers
		);

		return [
			'customers' => $formatted,
			'total'     => (int) $total,
			'limit'     => $limit,
			'offset'    => $offset,
		];
	}
}
