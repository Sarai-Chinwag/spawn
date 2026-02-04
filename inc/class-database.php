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
			user_id bigint(20) unsigned DEFAULT NULL,
			email varchar(255) NOT NULL,
			domain varchar(255) NOT NULL,
			subdomain tinyint(1) NOT NULL DEFAULT 0,
			vps_tier varchar(50) NOT NULL DEFAULT 'cx22',
			ai_tier varchar(50) NOT NULL DEFAULT '1k',
			ai_calls_used int(11) NOT NULL DEFAULT 0,
			ai_calls_limit int(11) NOT NULL DEFAULT 1000,
			stripe_customer varchar(255) DEFAULT NULL,
			stripe_subscription varchar(255) DEFAULT NULL,
			server_id varchar(255) DEFAULT NULL,
			server_ip varchar(45) DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			renewed_at datetime DEFAULT NULL,
			cancelled_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
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
	/**
	 * AI tier to calls limit mapping.
	 */
	private const AI_TIER_LIMITS = [
		'1k'  => 1000,
		'5k'  => 5000,
		'20k' => 20000,
	];

	public static function create_customer( array $data ): int|false {
		global $wpdb;

		$ai_tier = $data['ai_tier'] ?? '1k';
		$ai_limit = self::AI_TIER_LIMITS[ $ai_tier ] ?? 1000;

		$result = $wpdb->insert(
			self::get_table_name(),
			[
				'user_id'             => $data['user_id'] ?? null,
				'email'               => $data['email'],
				'domain'              => $data['domain'],
				'subdomain'           => $data['subdomain'] ? 1 : 0,
				'vps_tier'            => $data['vps_tier'] ?? 'cx22',
				'ai_tier'             => $ai_tier,
				'ai_calls_used'       => 0,
				'ai_calls_limit'      => $ai_limit,
				'stripe_customer'     => $data['stripe_customer'] ?? null,
				'stripe_subscription' => $data['stripe_subscription'] ?? null,
				'status'              => $data['status'] ?? 'pending',
				'created_at'          => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' ]
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

	/**
	 * Get customer by email.
	 *
	 * @param string $email Email address.
	 * @return array|null Customer data or null.
	 */
	public static function get_customer_by_email( string $email ): ?array {
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE email = %s",
				self::get_table_name(),
				$email
			),
			ARRAY_A
		);

		return $result ?: null;
	}

	/**
	 * Get customer by user ID.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array|null Customer data or null.
	 */
	public static function get_customer_by_user_id( int $user_id ): ?array {
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE user_id = %d",
				self::get_table_name(),
				$user_id
			),
			ARRAY_A
		);

		return $result ?: null;
	}

	/**
	 * Update AI tier for customer.
	 *
	 * @param int    $id       Customer ID.
	 * @param string $ai_tier  New AI tier.
	 * @return bool Success.
	 */
	public static function update_ai_tier( int $id, string $ai_tier ): bool {
		$limits = self::AI_TIER_LIMITS;
		$limit  = $limits[ $ai_tier ] ?? 1000;

		return self::update_customer( $id, [
			'ai_tier'        => $ai_tier,
			'ai_calls_limit' => $limit,
		] );
	}

	/**
	 * Update VPS tier for customer.
	 *
	 * @param int    $id       Customer ID.
	 * @param string $vps_tier New VPS tier.
	 * @return bool Success.
	 */
	public static function update_vps_tier( int $id, string $vps_tier ): bool {
		return self::update_customer( $id, [ 'vps_tier' => $vps_tier ] );
	}

	/**
	 * Increment AI calls used.
	 *
	 * @param int $id    Customer ID.
	 * @param int $count Number of calls to add.
	 * @return bool Success.
	 */
	public static function increment_ai_calls( int $id, int $count = 1 ): bool {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET ai_calls_used = ai_calls_used + %d WHERE id = %d",
				self::get_table_name(),
				$count,
				$id
			)
		);

		return $result !== false;
	}

	/**
	 * Reset AI calls used (for new billing period).
	 *
	 * @param int $id Customer ID.
	 * @return bool Success.
	 */
	public static function reset_ai_calls( int $id ): bool {
		return self::update_customer( $id, [ 'ai_calls_used' => 0 ] );
	}
}
