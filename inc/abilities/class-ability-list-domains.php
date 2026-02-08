<?php
/**
 * List Domains ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Database;
use WP_Error;

/**
 * Returns a list of domains with optional customer filtering.
 */
class Ability_List_Domains {

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		global $wpdb;

		$customer_id = isset( $input['customer_id'] ) ? absint( $input['customer_id'] ) : 0;
		$limit       = isset( $input['limit'] ) ? absint( $input['limit'] ) : 50;
		$limit       = min( $limit, 100 ); // Cap limit.

		$domains = array();

		// Get domains from wp_spawn_domains table.
		$domains_table = Database::get_domains_table_name();

		if ( $customer_id ) {
			// Get customer to find user_id.
			$customer = Database::get_customer( $customer_id );
			if ( ! $customer ) {
				return new WP_Error( 'not_found', __( 'Customer not found', 'spawn' ) );
			}

			if ( $customer['user_id'] ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted source.
				$domain_rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$domains_table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
						$customer['user_id'],
						$limit
					),
					ARRAY_A
				);
			} else {
				$domain_rows = array();
			}

			// Also include domain from customer record if it's a registered domain.
			if ( 'register' === $customer['domain_type'] && ! empty( $customer['domain'] ) ) {
				$domains[] = array(
					'domain'        => $customer['domain'],
					'customer_id'   => $customer_id,
					'user_id'       => $customer['user_id'] ? (int) $customer['user_id'] : null,
					'registered_at' => $customer['created_at'],
					'expires_at'    => $customer['domain_expires_at'],
					'auto_renew'    => (bool) $customer['domain_auto_renew'],
					'source'        => 'customer_record',
				);
			}
		} else {
			// Get all domains.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted source.
			$domain_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$domains_table} ORDER BY created_at DESC LIMIT %d",
					$limit
				),
				ARRAY_A
			);
		}

		// Format domains from domains table.
		foreach ( $domain_rows as $row ) {
			$domains[] = array(
				'domain'         => $row['domain'],
				'customer_id'    => null, // Domains table links via user_id, not customer_id.
				'user_id'        => (int) $row['user_id'],
				'server_id'      => $row['server_id'] ? (int) $row['server_id'] : null,
				'registrar'      => $row['registrar'],
				'registered_at'  => $row['registered_at'],
				'expires_at'     => $row['expires_at'],
				'auto_renew'     => (bool) $row['auto_renew'],
				'dns_configured' => (bool) $row['dns_configured'],
				'ssl_configured' => (bool) $row['ssl_configured'],
				'source'         => 'domains_table',
			);
		}

		// If no customer_id filter, also include domains from customer records.
		if ( ! $customer_id ) {
			$customers_table = Database::get_table_name();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted source.
			$customer_domains = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, user_id, domain, created_at, domain_expires_at, domain_auto_renew FROM {$customers_table} WHERE domain_type = %s AND domain IS NOT NULL ORDER BY created_at DESC LIMIT %d",
					'register',
					$limit
				),
				ARRAY_A
			);

			foreach ( $customer_domains as $cd ) {
				$domains[] = array(
					'domain'        => $cd['domain'],
					'customer_id'   => (int) $cd['id'],
					'user_id'       => $cd['user_id'] ? (int) $cd['user_id'] : null,
					'registered_at' => $cd['created_at'],
					'expires_at'    => $cd['domain_expires_at'],
					'auto_renew'    => (bool) $cd['domain_auto_renew'],
					'source'        => 'customer_record',
				);
			}
		}

		// De-duplicate by domain name (prefer domains_table entries).
		$seen   = array();
		$unique = array();
		foreach ( $domains as $d ) {
			if ( ! isset( $seen[ $d['domain'] ] ) ) {
				$seen[ $d['domain'] ] = true;
				$unique[]             = $d;
			}
		}

		return array(
			'domains' => array_slice( $unique, 0, $limit ),
			'count'   => count( $unique ),
		);
	}
}
