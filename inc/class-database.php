<?php
/**
 * Database operations.
 *
 * @package Spawn
 */

namespace Spawn;

/**
 * Handles database operations for Spawn.
 */
class Database {

	/**
	 * Get customers table name.
	 *
	 * @return string Table name.
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'spawn_customers';
	}

	/**
	 * Create database tables.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(255) NOT NULL,
			domain varchar(255) NOT NULL,
			tier varchar(50) NOT NULL DEFAULT 'starter',
			subdomain tinyint(1) NOT NULL DEFAULT 0,
			stripe_customer varchar(255) DEFAULT NULL,
			stripe_subscription varchar(255) DEFAULT NULL,
			server_id varchar(255) DEFAULT NULL,
			server_ip varchar(45) DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			renewed_at datetime DEFAULT NULL,
			cancelled_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY email (email),
			KEY domain (domain),
			KEY stripe_subscription (stripe_subscription),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create a customer record.
	 *
	 * @param array $data Customer data.
	 * @return int|false Customer ID or false on failure.
	 */
	public static function create_customer( array $data ): int|false {
		global $wpdb;

		$result = $wpdb->insert(
			self::get_table_name(),
			[
				'email'               => $data['email'],
				'domain'              => $data['domain'],
				'tier'                => $data['tier'] ?? 'starter',
				'subdomain'           => $data['subdomain'] ? 1 : 0,
				'stripe_customer'     => $data['stripe_customer'] ?? null,
				'stripe_subscription' => $data['stripe_subscription'] ?? null,
				'status'              => $data['status'] ?? 'pending',
				'created_at'          => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ]
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update a customer record.
	 *
	 * @param int   $id   Customer ID.
	 * @param array $data Data to update.
	 * @return bool Success.
	 */
	public static function update_customer( int $id, array $data ): bool {
		global $wpdb;

		$result = $wpdb->update(
			self::get_table_name(),
			$data,
			[ 'id' => $id ]
		);

		return $result !== false;
	}

	/**
	 * Get customer by ID.
	 *
	 * @param int $id Customer ID.
	 * @return array|null Customer data or null.
	 */
	public static function get_customer( int $id ): ?array {
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id = %d",
				self::get_table_name(),
				$id
			),
			ARRAY_A
		);

		return $result ?: null;
	}

	/**
	 * Get customer by Stripe subscription ID.
	 *
	 * @param string $subscription_id Stripe subscription ID.
	 * @return array|null Customer data or null.
	 */
	public static function get_customer_by_subscription( string $subscription_id ): ?array {
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE stripe_subscription = %s",
				self::get_table_name(),
				$subscription_id
			),
			ARRAY_A
		);

		return $result ?: null;
	}

	/**
	 * Get customer by domain.
	 *
	 * @param string $domain Domain name.
	 * @return array|null Customer data or null.
	 */
	public static function get_customer_by_domain( string $domain ): ?array {
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE domain = %s",
				self::get_table_name(),
				$domain
			),
			ARRAY_A
		);

		return $result ?: null;
	}
}
