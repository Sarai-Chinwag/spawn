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
	 * Get servers table name.
	 *
	 * @return string Table name.
	 */
	public static function get_servers_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'spawn_servers';
	}

	/**
	 * Get domains table name.
	 *
	 * @return string Table name.
	 */
	public static function get_domains_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'spawn_domains';
	}

	/**
	 * Get usage table name.
	 *
	 * @return string Table name.
	 */
	public static function get_usage_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'spawn_usage';
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
			billing_mode varchar(20) NOT NULL DEFAULT 'managed',
			wants_website tinyint(1) NOT NULL DEFAULT 1,
			server_type varchar(50) NOT NULL DEFAULT 'cpx21',
			server_location varchar(50) NOT NULL DEFAULT 'ash',
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
			provider_server_id varchar(255) DEFAULT NULL,
			credit_balance decimal(10,2) NOT NULL DEFAULT 0.00,
			auto_refill_enabled tinyint(1) NOT NULL DEFAULT 0,
			auto_refill_threshold decimal(10,2) NOT NULL DEFAULT 5.00,
			auto_refill_amount decimal(10,2) NOT NULL DEFAULT 10.00,
			renewal_warnings_sent text DEFAULT NULL,
			domain_auto_renew tinyint(1) NOT NULL DEFAULT 0,
			customer_region varchar(10) NOT NULL DEFAULT 'us',
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
	 * Create servers table for multi-server support.
	 */
	public static function create_servers_table(): void {
		global $wpdb;

		$table_name      = self::get_servers_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			name varchar(255) DEFAULT '',
			tier varchar(50) NOT NULL DEFAULT 'starter',
			provider_server_id varchar(100) DEFAULT NULL,
			server_type varchar(50) DEFAULT NULL,
			server_ip varchar(45) DEFAULT NULL,
			server_location varchar(10) DEFAULT 'ash',
			openclaw_token varchar(255) DEFAULT NULL,
			has_wordpress tinyint(1) DEFAULT 0,
			status varchar(50) DEFAULT 'pending',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_user_id (user_id),
			KEY idx_status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create domains table for multi-domain support.
	 */
	public static function create_domains_table(): void {
		global $wpdb;

		$table_name      = self::get_domains_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			server_id bigint(20) unsigned DEFAULT NULL,
			domain varchar(255) NOT NULL,
			registrar varchar(50) DEFAULT 'namecom',
			registered_at datetime DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			auto_renew tinyint(1) DEFAULT 0,
			dns_configured tinyint(1) DEFAULT 0,
			ssl_configured tinyint(1) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY idx_domain (domain),
			KEY idx_user_id (user_id),
			KEY idx_server_id (server_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Migrate old column names to provider-agnostic names.
	 *
	 * Renames hetzner_type → server_type, hetzner_server_id → provider_server_id,
	 * hetzner_location → server_location for existing installations.
	 */
	public static function migrate_column_names(): void {
		global $wpdb;

		$customers_table = self::get_table_name();
		$servers_table   = self::get_servers_table_name();

		// Customers table migrations.
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$customers_table}" );
		if ( in_array( 'hetzner_type', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$customers_table} CHANGE `hetzner_type` `server_type` varchar(50) NOT NULL DEFAULT 'cpx21'" );
		}
		if ( in_array( 'hetzner_server_id', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$customers_table} CHANGE `hetzner_server_id` `provider_server_id` varchar(255) DEFAULT NULL" );
		}
		if ( in_array( 'hetzner_location', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$customers_table} CHANGE `hetzner_location` `server_location` varchar(50) NOT NULL DEFAULT 'ash'" );
		}

		// Servers table migrations.
		$server_columns = $wpdb->get_col( "SHOW COLUMNS FROM {$servers_table}" );
		if ( in_array( 'hetzner_type', $server_columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$servers_table} CHANGE `hetzner_type` `server_type` varchar(50) DEFAULT NULL" );
		}
		if ( in_array( 'hetzner_server_id', $server_columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$servers_table} CHANGE `hetzner_server_id` `provider_server_id` varchar(255) DEFAULT NULL" );
		}
	}

	/**
	 * Remove api_key_encrypted column if it exists from v0.8.0.
	 *
	 * We no longer store customer API keys on our server — keys are
	 * managed entirely on the customer's VPS for security.
	 */
	public static function migrate_remove_api_key_column(): void {
		global $wpdb;

		$table   = self::get_table_name();
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( in_array( 'api_key_encrypted', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} DROP COLUMN api_key_encrypted" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Create usage table for per-server usage tracking.
	 */
	public static function create_usage_table(): void {
		global $wpdb;

		$table_name      = self::get_usage_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			server_id bigint(20) unsigned NOT NULL,
			credits_used decimal(10,4) DEFAULT 0,
			requests_count int(10) unsigned DEFAULT 0,
			tokens_input bigint(20) unsigned DEFAULT 0,
			tokens_output bigint(20) unsigned DEFAULT 0,
			period_start date NOT NULL,
			period_end date NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY idx_server_period (server_id, period_start),
			KEY idx_user_id (user_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create all multi-server tables.
	 */
	public static function create_multi_server_tables(): void {
		self::create_servers_table();
		self::create_domains_table();
		self::create_usage_table();
	}

	/**
	 * Create a customer record.
	 *
	 * @param array $data Customer data.
	 * @return int|false Customer ID or false on failure.
	 */
	public static function create_customer( array $data ): int|false {
		global $wpdb;

		$tier            = $data['tier'] ?? 'starter';
		$wants_website   = isset( $data['wants_website'] ) ? (bool) $data['wants_website'] : true;
		$customer_region = $data['customer_region'] ?? 'us';
		$server_config   = Config::get_server_config( $tier, $wants_website, $customer_region );

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
			array(
				'user_id'             => $data['user_id'] ?? null,
				'email'               => $data['email'],
				'domain'              => $data['domain'] ?? null,
				'subdomain'           => 'subdomain' === $domain_type ? 1 : 0,
				'domain_type'         => $domain_type,
				'domain_price'        => $data['domain_price'] ?? null,
				'domain_expires_at'   => $domain_expires,
				'tier'                => $tier,
				'wants_website'       => $wants_website ? 1 : 0,
				'server_type'        => $server_config['server_type'],
				'server_location'    => $server_config['location'],
				'stripe_customer'     => $data['stripe_customer'] ?? null,
				'stripe_subscription' => $data['stripe_subscription'] ?? null,
				'status'              => $data['status'] ?? 'pending',
				'credit_balance'      => $data['credit_balance'] ?? Config::get_included_credits( $tier ),
				'customer_region'     => $customer_region,
				'created_at'          => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%f', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s' )
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
			array( 'id' => $id )
		);

		return false !== $result;
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
				'SELECT * FROM %i WHERE id = %d',
				self::get_table_name(),
				$id
			),
			ARRAY_A
		);

		return $result ? $result : null;
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

		$allowed_columns = array( 'id', 'email', 'domain', 'status', 'created_at', 'credit_balance' );
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

		return $results ? $results : array();
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
				'SELECT * FROM %i WHERE stripe_subscription = %s',
				self::get_table_name(),
				$subscription_id
			),
			ARRAY_A
		);

		return $result ? $result : null;
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
				'SELECT * FROM %i WHERE domain = %s',
				self::get_table_name(),
				$domain
			),
			ARRAY_A
		);

		return $result ? $result : null;
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
				'SELECT * FROM %i WHERE email = %s',
				self::get_table_name(),
				$email
			),
			ARRAY_A
		);

		return $result ? $result : null;
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
				'SELECT * FROM %i WHERE user_id = %d',
				self::get_table_name(),
				$user_id
			),
			ARRAY_A
		);

		return $result ? $result : null;
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
				'SELECT * FROM %i WHERE server_ip = %s',
				self::get_table_name(),
				$ip
			),
			ARRAY_A
		);

		return $result ? $result : null;
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
		return self::update_customer( $id, array( 'tier' => $tier ) );
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
		return self::update_customer( $id, array( 'wants_website' => $wants_website ? 1 : 0 ) );
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
				'UPDATE %i SET credit_balance = credit_balance - %f WHERE id = %d AND credit_balance >= %f',
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
				'UPDATE %i SET credit_balance = credit_balance + %f WHERE id = %d',
				self::get_table_name(),
				$amount,
				$id
			)
		);

		return false !== $result;
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
		return self::update_customer( $id, array(
			'auto_refill_enabled'   => $enabled ? 1 : 0,
			'auto_refill_threshold' => $threshold,
			'auto_refill_amount'    => $amount,
		) );
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
		return self::update_customer( $id, array(
			'auto_refill_enabled'   => $enabled ? 1 : 0,
			'auto_refill_threshold' => $threshold,
			'auto_refill_amount'    => $amount,
		) );
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

		return array(
			'enabled'   => (bool) $customer['auto_refill_enabled'],
			'threshold' => (int) $customer['auto_refill_threshold'],
			'amount'    => (int) $customer['auto_refill_amount'],
		);
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

		return $results ? $results : array();
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

		return $results ? $results : array();
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

		return false !== $result;
	}

	/**
	 * Update Stripe payment method for a customer.
	 *
	 * @param int    $id               Customer ID.
	 * @param string $payment_method_id Stripe payment method ID.
	 * @return bool Success.
	 */
	public static function update_payment_method( int $id, string $payment_method_id ): bool {
		return self::update_customer( $id, array(
			'stripe_payment_method' => $payment_method_id,
		) );
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

		return self::update_customer( $id, array(
			'status'                => 'cancelling',
			'cancelled_at'          => current_time( 'mysql' ),
			'scheduled_deletion_at' => $deletion_date,
		) );
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

		return $results ? $results : array();
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

		return $results ? $results : array();
	}

	/**
	 * Mark customer as deleted after cleanup.
	 *
	 * @param int $id Customer ID.
	 * @return bool Success.
	 */
	public static function mark_deleted( int $id ): bool {
		return self::update_customer( $id, array(
			'status'    => 'deleted',
			'server_id' => null,
			'server_ip' => null,
		) );
	}

	/**
	 * Reactivate a cancelling customer (cancel the cancellation).
	 *
	 * @param int $id Customer ID.
	 * @return bool Success.
	 */
	public static function reactivate_customer( int $id ): bool {
		return self::update_customer( $id, array(
			'status'                => 'active',
			'cancelled_at'          => null,
			'scheduled_deletion_at' => null,
		) );
	}

	/**
	 * Store Cloudflare record ID for a customer.
	 *
	 * @param int    $id        Customer ID.
	 * @param string $record_id Cloudflare DNS record ID.
	 * @return bool Success.
	 */
	public static function set_cloudflare_record_id( int $id, string $record_id ): bool {
		return self::update_customer( $id, array(
			'cloudflare_record_id' => $record_id,
		) );
	}

	/**
	 * Store provider server ID for a customer.
	 *
	 * @param int    $id        Customer ID.
	 * @param string $server_id Provider server ID.
	 * @return bool Success.
	 */
	public static function set_provider_server_id( int $id, string $server_id ): bool {
		return self::update_customer( $id, array(
			'provider_server_id' => $server_id,
		) );
	}

	// =========================================================================
	// Server CRUD (multi-server support)
	// =========================================================================

	/**
	 * Create a server record.
	 *
	 * @param array $data Server data.
	 * @return int|false Server ID or false on failure.
	 */
	public static function create_server( array $data ): int|false {
		global $wpdb;

		$result = $wpdb->insert(
			self::get_servers_table_name(),
			array(
				'user_id'           => $data['user_id'],
				'name'              => $data['name'] ?? '',
				'tier'              => $data['tier'] ?? 'starter',
				'provider_server_id' => $data['provider_server_id'] ?? null,
				'server_type'      => $data['server_type'] ?? null,
				'server_ip'         => $data['server_ip'] ?? null,
				'server_location'   => $data['server_location'] ?? 'ash',
				'openclaw_token'    => $data['openclaw_token'] ?? null,
				'has_wordpress'     => ! empty( $data['has_wordpress'] ) ? 1 : 0,
				'status'            => $data['status'] ?? 'pending',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get a server by ID.
	 *
	 * @param int $id Server ID.
	 * @return array|null Server data or null if not found.
	 */
	public static function get_server( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				self::get_servers_table_name(),
				$id
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Get all servers for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array Servers.
	 */
	public static function get_servers_by_user( int $user_id ): array {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE user_id = %d ORDER BY created_at DESC',
				self::get_servers_table_name(),
				$user_id
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Update a server record.
	 *
	 * @param int   $id   Server ID.
	 * @param array $data Data to update.
	 * @return bool Success.
	 */
	public static function update_server( int $id, array $data ): bool {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		return $wpdb->update(
			self::get_servers_table_name(),
			$data,
			array( 'id' => $id )
		) !== false;
	}

	/**
	 * Delete a server record.
	 *
	 * @param int $id Server ID.
	 * @return bool Success.
	 */
	public static function delete_server( int $id ): bool {
		global $wpdb;

		return $wpdb->delete(
			self::get_servers_table_name(),
			array( 'id' => $id ),
			array( '%d' )
		) !== false;
	}

	// =========================================================================
	// Domain CRUD (multi-domain support)
	// =========================================================================

	/**
	 * Create a domain record.
	 *
	 * @param array $data Domain data.
	 * @return int|false Domain ID or false on failure.
	 */
	public static function create_domain( array $data ): int|false {
		global $wpdb;

		$result = $wpdb->insert(
			self::get_domains_table_name(),
			array(
				'user_id'        => $data['user_id'],
				'server_id'      => $data['server_id'] ?? null,
				'domain'         => $data['domain'],
				'registrar'      => $data['registrar'] ?? 'namecom',
				'registered_at'  => $data['registered_at'] ?? current_time( 'mysql' ),
				'expires_at'     => $data['expires_at'] ?? null,
				'auto_renew'     => ! empty( $data['auto_renew'] ) ? 1 : 0,
				'dns_configured' => ! empty( $data['dns_configured'] ) ? 1 : 0,
				'ssl_configured' => ! empty( $data['ssl_configured'] ) ? 1 : 0,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get a domain by ID.
	 *
	 * @param int $id Domain ID.
	 * @return array|null Domain data or null if not found.
	 */
	public static function get_domain( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				self::get_domains_table_name(),
				$id
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Get all domains for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array Domains.
	 */
	public static function get_domains_by_user( int $user_id ): array {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE user_id = %d ORDER BY created_at DESC',
				self::get_domains_table_name(),
				$user_id
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Update a domain record.
	 *
	 * @param int   $id   Domain ID.
	 * @param array $data Data to update.
	 * @return bool Success.
	 */
	public static function update_domain( int $id, array $data ): bool {
		global $wpdb;

		return $wpdb->update(
			self::get_domains_table_name(),
			$data,
			array( 'id' => $id )
		) !== false;
	}

	/**
	 * Delete a domain record.
	 *
	 * @param int $id Domain ID.
	 * @return bool Success.
	 */
	public static function delete_domain( int $id ): bool {
		global $wpdb;

		return $wpdb->delete(
			self::get_domains_table_name(),
			array( 'id' => $id ),
			array( '%d' )
		) !== false;
	}

	/**
	 * Assign a domain to a server.
	 *
	 * @param int      $domain_id Domain ID.
	 * @param int|null $server_id Server ID (null to unassign).
	 * @return bool Success.
	 */
	public static function assign_domain_to_server( int $domain_id, ?int $server_id ): bool {
		return self::update_domain( $domain_id, array( 'server_id' => $server_id ) );
	}

	// =========================================================================
	// Usage tracking (per-server)
	// =========================================================================

	/**
	 * Record usage for a server.
	 *
	 * @param int   $user_id      User ID.
	 * @param int   $server_id    Server ID.
	 * @param float $credits_used Credits used.
	 * @param int   $tokens_in    Input tokens.
	 * @param int   $tokens_out   Output tokens.
	 * @return bool Success.
	 */
	public static function record_usage( int $user_id, int $server_id, float $credits_used, int $tokens_in = 0, int $tokens_out = 0 ): bool {
		global $wpdb;

		$today        = current_time( 'Y-m-d' );
		$period_start = gmdate( 'Y-m-01', strtotime( $today ) );
		$period_end   = gmdate( 'Y-m-t', strtotime( $today ) );

		// Try to update existing record for this period.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE server_id = %d AND period_start = %s',
				self::get_usage_table_name(),
				$server_id,
				$period_start
			)
		);

		if ( $existing ) {
			return $wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET credits_used = credits_used + %f, requests_count = requests_count + 1, tokens_input = tokens_input + %d, tokens_output = tokens_output + %d WHERE id = %d',
					self::get_usage_table_name(),
					$credits_used,
					$tokens_in,
					$tokens_out,
					$existing
				)
			) !== false;
		}

		// Insert new record.
		return $wpdb->insert(
			self::get_usage_table_name(),
			array(
				'user_id'        => $user_id,
				'server_id'      => $server_id,
				'credits_used'   => $credits_used,
				'requests_count' => 1,
				'tokens_input'   => $tokens_in,
				'tokens_output'  => $tokens_out,
				'period_start'   => $period_start,
				'period_end'     => $period_end,
			),
			array( '%d', '%d', '%f', '%d', '%d', '%d', '%s', '%s' )
		) !== false;
	}

	/**
	 * Get usage for a server.
	 *
	 * @param int $server_id Server ID.
	 * @param int $months    Number of months to retrieve.
	 * @return array Usage records.
	 */
	public static function get_server_usage( int $server_id, int $months = 3 ): array {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d', strtotime( "-{$months} months" ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE server_id = %d AND period_start >= %s ORDER BY period_start DESC',
				self::get_usage_table_name(),
				$server_id,
				$cutoff
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Get total usage for a user across all servers.
	 *
	 * @param int $user_id User ID.
	 * @param int $months  Number of months to retrieve.
	 * @return array Usage records grouped by period.
	 */
	public static function get_user_usage( int $user_id, int $months = 3 ): array {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d', strtotime( "-{$months} months" ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT period_start, period_end, SUM(credits_used) as credits_used, SUM(requests_count) as requests_count, SUM(tokens_input) as tokens_input, SUM(tokens_output) as tokens_output FROM %i WHERE user_id = %d AND period_start >= %s GROUP BY period_start, period_end ORDER BY period_start DESC',
				self::get_usage_table_name(),
				$user_id,
				$cutoff
			),
			ARRAY_A
		) ?: array();
	}
}
