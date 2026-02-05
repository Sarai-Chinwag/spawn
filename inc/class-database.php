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
			domain varchar(255) DEFAULT NULL,
			subdomain tinyint(1) NOT NULL DEFAULT 0,
			domain_type varchar(20) NOT NULL DEFAULT 'subdomain',
			domain_price decimal(10,2) DEFAULT NULL,
			domain_expires_at datetime DEFAULT NULL,
			tier varchar(50) NOT NULL DEFAULT 'starter',
			wants_website tinyint(1) NOT NULL DEFAULT 1,
			hetzner_type varchar(50) NOT NULL DEFAULT 'cpx21',
			hetzner_location varchar(50) NOT NULL DEFAULT 'ash',
			stripe_customer varchar(255) DEFAULT NULL,
			stripe_subscription varchar(255) DEFAULT NULL,
			stripe_payment_method varchar(255) DEFAULT NULL,
			server_id varchar(255) DEFAULT NULL,
			server_ip varchar(45) DEFAULT NULL,
			openclaw_token varchar(255) DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			renewed_at datetime DEFAULT NULL,
			cancelled_at datetime DEFAULT NULL,
			scheduled_deletion_at datetime DEFAULT NULL,
			cloudflare_record_id varchar(255) DEFAULT NULL,
			hetzner_server_id varchar(255) DEFAULT NULL,
			credit_balance decimal(10,2) NOT NULL DEFAULT 10.00,
			auto_refill_enabled tinyint(1) NOT NULL DEFAULT 0,
			auto_refill_threshold decimal(10,2) NOT NULL DEFAULT 5.00,
			auto_refill_amount decimal(10,2) NOT NULL DEFAULT 10.00,
			renewal_warnings_sent text DEFAULT NULL,
			domain_auto_renew tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY email (email),
			KEY domain (domain),
			KEY stripe_subscription (stripe_subscription),
			KEY status (status),
			KEY domain_expires_at (domain_expires_at),
			KEY tier (tier)
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

		$tier          = $data['tier'] ?? 'starter';
		$wants_website = isset( $data['wants_website'] ) ? (bool) $data['wants_website'] : true;
		$server_config = Config::get_server_config( $tier, $wants_website );

		if ( ! $server_config ) {
			// Invalid tier, fall back to starter.
			$tier          = 'starter';
			$server_config = Config::get_server_config( $tier, $wants_website );
		}

		$domain_type = $data['domain_type'] ?? 'subdomain';

		// Set domain expiration to 1 year from now if registering domain.
		$domain_expires = null;
		if ( 'register' === $domain_type && ! empty( $data['domain_price'] ) ) {
			$domain_expires = gmdate( 'Y-m-d H:i:s', strtotime( '+1 year' ) );
		}

		$result = $wpdb->insert(
			self::get_table_name(),
			[
				'user_id'             => $data['user_id'] ?? null,
				'email'               => $data['email'],
				'domain'              => $data['domain'] ?? null,
				'subdomain'           => 'subdomain' === $domain_type ? 1 : 0,
				'domain_type'         => $domain_type,
				'domain_price'        => $data['domain_price'] ?? null,
				'domain_expires_at'   => $domain_expires,
				'tier'                => $tier,
				'wants_website'       => $wants_website ? 1 : 0,
				'hetzner_type'        => $server_config['hetzner_type'],
				'hetzner_location'    => $server_config['location'],
				'stripe_customer'     => $data['stripe_customer'] ?? null,
				'stripe_subscription' => $data['stripe_subscription'] ?? null,
				'status'              => $data['status'] ?? 'pending',
				'credit_balance'      => $data['credit_balance'] ?? Config::get_included_credits( $tier ),
				'created_at'          => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%d', '%s', '%f', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s' ]
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
	 * Get all customers.
	 *
	 * @param string $order_by Column to order by.
	 * @param string $order    Order direction (ASC/DESC).
	 * @return array List of customers.
	 */
	public static function get_all_customers( string $order_by = 'created_at', string $order = 'DESC' ): array {
		global $wpdb;

		$allowed_columns = [ 'id', 'email', 'domain', 'status', 'created_at', 'credit_balance' ];
		$order_by        = in_array( $order_by, $allowed_columns, true ) ? $order_by : 'created_at';
		$order           = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column name validated above.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i ORDER BY {$order_by} {$order}",
				self::get_table_name()
			),
			ARRAY_A
		);

		return $results ?: [];
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
	 * Get customer by server IP.
	 *
	 * @param string $ip Server IP address.
	 * @return array|null Customer data or null.
	 */
	public static function get_customer_by_server_ip( string $ip ): ?array {
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE server_ip = %s",
				self::get_table_name(),
				$ip
			),
			ARRAY_A
		);

		return $result ?: null;
	}

	/**
	 * Update tier for customer.
	 *
	 * Note: This updates the tier record but does NOT automatically
	 * resize the server. Server resizing requires separate action.
	 *
	 * @param int    $id   Customer ID.
	 * @param string $tier New tier ID.
	 * @return bool Success.
	 */
	public static function update_tier( int $id, string $tier ): bool {
		if ( ! in_array( $tier, Config::get_tier_ids(), true ) ) {
			return false;
		}
		return self::update_customer( $id, [ 'tier' => $tier ] );
	}

	/**
	 * Update website preference for customer.
	 *
	 * Note: Changing this after provisioning has no effect on the server.
	 * Server type is determined at creation time.
	 *
	 * @param int  $id            Customer ID.
	 * @param bool $wants_website Whether customer wants a website.
	 * @return bool Success.
	 */
	public static function update_wants_website( int $id, bool $wants_website ): bool {
		return self::update_customer( $id, [ 'wants_website' => $wants_website ? 1 : 0 ] );
	}

	/**
	 * Deduct credits from customer balance.
	 *
	 * @param int   $id     Customer ID.
	 * @param float $amount Amount to deduct.
	 * @return bool Success.
	 */
	public static function deduct_credits( int $id, float $amount ): bool {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET credit_balance = credit_balance - %f WHERE id = %d AND credit_balance >= %f",
				self::get_table_name(),
				$amount,
				$id,
				$amount
			)
		);

		return $result > 0;
	}

	/**
	 * Get credit balance for a customer.
	 *
	 * @param int $id Customer ID.
	 * @return float|null Credit balance or null if customer not found.
	 */
	public static function get_credit_balance( int $id ): ?float {
		$customer = self::get_customer( $id );
		return $customer ? (float) $customer['credit_balance'] : null;
	}

	/**
	 * Add credits to a customer's balance.
	 *
	 * @param int   $id     Customer ID.
	 * @param float $amount Amount of credits to add.
	 * @return bool Success.
	 */
	public static function add_credits( int $id, float $amount ): bool {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET credit_balance = credit_balance + %f WHERE id = %d",
				self::get_table_name(),
				$amount,
				$id
			)
		);

		return $result !== false;
	}

	/**
	 * Update auto-refill settings for a customer (legacy, credit-based).
	 *
	 * @param int  $id        Customer ID.
	 * @param bool $enabled   Whether auto-refill is enabled.
	 * @param int  $threshold Refill when balance falls below this (in credits).
	 * @param int  $amount    Number of credits to add when refilling.
	 * @return bool Success.
	 */
	public static function update_auto_refill( int $id, bool $enabled, int $threshold = 100, int $amount = 1000 ): bool {
		return self::update_customer( $id, [
			'auto_refill_enabled'   => $enabled ? 1 : 0,
			'auto_refill_threshold' => $threshold,
			'auto_refill_amount'    => $amount,
		] );
	}

	/**
	 * Update auto-refill settings for a customer (dollar-based).
	 *
	 * @param int   $id        Customer ID.
	 * @param bool  $enabled   Whether auto-refill is enabled.
	 * @param float $threshold Refill when balance falls below this (in dollars).
	 * @param float $amount    Amount in dollars to refill.
	 * @return bool Success.
	 */
	public static function update_auto_refill_settings( int $id, bool $enabled, float $threshold = 5.00, float $amount = 10.00 ): bool {
		return self::update_customer( $id, [
			'auto_refill_enabled'   => $enabled ? 1 : 0,
			'auto_refill_threshold' => $threshold,
			'auto_refill_amount'    => $amount,
		] );
	}

	/**
	 * Get auto-refill settings for a customer.
	 *
	 * @param int $id Customer ID.
	 * @return array|null Auto-refill settings or null if customer not found.
	 */
	public static function get_auto_refill_settings( int $id ): ?array {
		$customer = self::get_customer( $id );
		if ( ! $customer ) {
			return null;
		}

		return [
			'enabled'   => (bool) $customer['auto_refill_enabled'],
			'threshold' => (int) $customer['auto_refill_threshold'],
			'amount'    => (int) $customer['auto_refill_amount'],
		];
	}

	/**
	 * Get customers needing auto-refill.
	 *
	 * @return array Customers with balance below threshold and auto-refill enabled.
	 */
	public static function get_customers_needing_refill(): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE auto_refill_enabled = 1 AND credit_balance < auto_refill_threshold AND status = 'active'",
				self::get_table_name()
			),
			ARRAY_A
		);

		return $results ?: [];
	}

	/**
	 * Get customers with domains expiring within given days.
	 *
	 * @param int $days Number of days to look ahead.
	 * @return array Customers with expiring domains.
	 */
	public static function get_expiring_domains( int $days = 30 ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE domain_type = 'register' AND domain_expires_at IS NOT NULL AND domain_expires_at <= DATE_ADD(NOW(), INTERVAL %d DAY) AND domain_expires_at > NOW() AND status = 'active' ORDER BY domain_expires_at ASC",
				self::get_table_name(),
				$days
			),
			ARRAY_A
		);

		return $results ?: [];
	}

	/**
	 * Renew domain for a customer (extends expiration by 1 year).
	 *
	 * @param int $id Customer ID.
	 * @return bool Success.
	 */
	public static function renew_domain( int $id ): bool {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET domain_expires_at = DATE_ADD(domain_expires_at, INTERVAL 1 YEAR), renewed_at = NOW() WHERE id = %d AND domain_type = 'register'",
				self::get_table_name(),
				$id
			)
		);

		return $result !== false;
	}

	/**
	 * Update Stripe payment method for a customer.
	 *
	 * @param int    $id               Customer ID.
	 * @param string $payment_method_id Stripe payment method ID.
	 * @return bool Success.
	 */
	public static function update_payment_method( int $id, string $payment_method_id ): bool {
		return self::update_customer( $id, [
			'stripe_payment_method' => $payment_method_id,
		] );
	}

	/**
	 * Get Stripe payment method for a customer.
	 *
	 * @param int $id Customer ID.
	 * @return string|null Payment method ID or null.
	 */
	public static function get_payment_method( int $id ): ?string {
		$customer = self::get_customer( $id );
		return $customer['stripe_payment_method'] ?? null;
	}

	/**
	 * Schedule customer for deletion after grace period.
	 *
	 * @param int $id         Customer ID.
	 * @param int $grace_days Number of days until deletion (default 7).
	 * @return bool Success.
	 */
	public static function schedule_deletion( int $id, int $grace_days = 7 ): bool {
		$deletion_date = gmdate( 'Y-m-d H:i:s', strtotime( "+{$grace_days} days" ) );

		return self::update_customer( $id, [
			'status'                => 'cancelling',
			'cancelled_at'          => current_time( 'mysql' ),
			'scheduled_deletion_at' => $deletion_date,
		] );
	}

	/**
	 * Get customers scheduled for deletion that have passed their grace period.
	 *
	 * @return array Customers ready for deletion.
	 */
	public static function get_customers_pending_deletion(): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE status = 'cancelling' AND scheduled_deletion_at IS NOT NULL AND scheduled_deletion_at <= NOW()",
				self::get_table_name()
			),
			ARRAY_A
		);

		return $results ?: [];
	}

	/**
	 * Get customers in cancelling status (for admin view).
	 *
	 * @return array Customers in cancellation grace period.
	 */
	public static function get_cancelling_customers(): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE status = 'cancelling' ORDER BY scheduled_deletion_at ASC",
				self::get_table_name()
			),
			ARRAY_A
		);

		return $results ?: [];
	}

	/**
	 * Mark customer as deleted after cleanup.
	 *
	 * @param int $id Customer ID.
	 * @return bool Success.
	 */
	public static function mark_deleted( int $id ): bool {
		return self::update_customer( $id, [
			'status'    => 'deleted',
			'server_id' => null,
			'server_ip' => null,
		] );
	}

	/**
	 * Reactivate a cancelling customer (cancel the cancellation).
	 *
	 * @param int $id Customer ID.
	 * @return bool Success.
	 */
	public static function reactivate_customer( int $id ): bool {
		return self::update_customer( $id, [
			'status'                => 'active',
			'cancelled_at'          => null,
			'scheduled_deletion_at' => null,
		] );
	}

	/**
	 * Store Cloudflare record ID for a customer.
	 *
	 * @param int    $id        Customer ID.
	 * @param string $record_id Cloudflare DNS record ID.
	 * @return bool Success.
	 */
	public static function set_cloudflare_record_id( int $id, string $record_id ): bool {
		return self::update_customer( $id, [
			'cloudflare_record_id' => $record_id,
		] );
	}

	/**
	 * Store Hetzner server ID for a customer.
	 *
	 * @param int    $id        Customer ID.
	 * @param string $server_id Hetzner server ID.
	 * @return bool Success.
	 */
	public static function set_hetzner_server_id( int $id, string $server_id ): bool {
		return self::update_customer( $id, [
			'hetzner_server_id' => $server_id,
		] );
	}
}
