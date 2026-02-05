<?php
/**
 * Database migrations for Spawn.
 *
 * @package Spawn
 */

namespace Spawn;

/**
 * Handles database schema migrations.
 */
class Migration {

	/**
	 * Current database schema version.
	 */
	private const DB_VERSION = 2;

	/**
	 * Option key for database version.
	 */
	private const DB_VERSION_OPTION = 'spawn_db_version';

	/**
	 * Run migrations if needed.
	 */
	public static function run(): void {
		$installed_version = (int) get_option( self::DB_VERSION_OPTION, 1 );
		if ( $installed_version >= self::DB_VERSION ) {
			return;
		}

		self::create_tables();
		self::migrate_customers();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Create all new tables.
	 */
	public static function create_tables(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		self::create_servers_table();
		self::create_domains_table();
		self::create_usage_table();
	}

	/**
	 * Create spawn_servers table.
	 */
	public static function create_servers_table(): void {
		global $wpdb;

		$table_name      = Database::get_servers_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			name varchar(255) DEFAULT '',
			tier varchar(50) NOT NULL DEFAULT 'starter',
			hetZner_server_id varchar(100) DEFAULT NULL,
			hetZner_type varchar(50) DEFAULT NULL,
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

		dbDelta( $sql );
	}

	/**
	 * Create spawn_domains table.
	 */
	public static function create_domains_table(): void {
		global $wpdb;

		$table_name      = Database::get_domains_table_name();
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

		dbDelta( $sql );
	}

	/**
	 * Create spawn_usage table.
	 */
	public static function create_usage_table(): void {
		global $wpdb;

		$table_name      = Database::get_usage_table_name();
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

		dbDelta( $sql );
	}

	/**
	 * Migrate existing customers into new tables.
	 */
	public static function migrate_customers(): void {
		global $wpdb;

		$customer_table = Database::get_table_name();
		$servers_table  = Database::get_servers_table_name();
		$domains_table  = Database::get_domains_table_name();

		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$customer_table
			)
		);

		if ( $table_exists !== $customer_table ) {
			return;
		}

		$customers = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i',
				$customer_table
			),
			ARRAY_A
		);

		if ( empty( $customers ) ) {
			return;
		}

		foreach ( $customers as $customer ) {
			$server_id = self::ensure_server_for_customer( $customer, $servers_table );
			self::ensure_domain_for_customer( $customer, $domains_table, $server_id );
		}
	}

	/**
	 * Drop new tables and reset version.
	 */
	public static function rollback(): void {
		global $wpdb;

		$tables = [
			Database::get_usage_table_name(),
			Database::get_domains_table_name(),
			Database::get_servers_table_name(),
		];

		foreach ( $tables as $table ) {
			$wpdb->query(
				$wpdb->prepare(
					'DROP TABLE IF EXISTS %i',
					$table
				)
			);
		}

		update_option( self::DB_VERSION_OPTION, 1 );
	}

	/**
	 * Ensure a server record exists for a customer row.
	 *
	 * @param array  $customer Customer data.
	 * @param string $servers_table Servers table name.
	 * @return int|null Server ID.
	 */
	private static function ensure_server_for_customer( array $customer, string $servers_table ): ?int {
		global $wpdb;

		$name = self::build_server_name( $customer );
		$existing_id = self::find_existing_server_id( $customer, $servers_table, $name );

		if ( $existing_id ) {
			return (int) $existing_id;
		}

		$server_data = [
			'user_id'          => (int) ( $customer['user_id'] ?? 0 ),
			'name'             => $name,
			'tier'             => $customer['tier'] ?? 'starter',
			'hetzner_server_id'=> $customer['hetzner_server_id'] ?? $customer['server_id'] ?? null,
			'hetzner_type'     => $customer['hetzner_type'] ?? null,
			'server_ip'        => $customer['server_ip'] ?? null,
			'server_location'  => $customer['hetzner_location'] ?? 'ash',
			'openclaw_token'   => $customer['openclaw_token'] ?? null,
			'has_wordpress'    => ! empty( $customer['wants_website'] ) ? 1 : 0,
			'status'           => $customer['status'] ?? 'pending',
			'created_at'       => $customer['created_at'] ?? current_time( 'mysql' ),
			'updated_at'       => current_time( 'mysql' ),
		];

		$prepared = self::prepare_insert( $servers_table, $server_data, self::get_server_formats() );
		if ( ! $prepared ) {
			return null;
		}

		$inserted = $wpdb->query( $prepared );
		if ( $inserted === false ) {
			return null;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Ensure a domain record exists for a customer row.
	 *
	 * @param array    $customer Customer data.
	 * @param string   $domains_table Domains table name.
	 * @param int|null $server_id Server ID.
	 */
	private static function ensure_domain_for_customer( array $customer, string $domains_table, ?int $server_id ): void {
		global $wpdb;

		$domain = $customer['domain'] ?? '';
		if ( empty( $domain ) ) {
			return;
		}

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE domain = %s LIMIT 1',
				$domains_table,
				$domain
			)
		);

		if ( $existing_id ) {
			return;
		}

		$domain_data = [
			'user_id'         => (int) ( $customer['user_id'] ?? 0 ),
			'server_id'       => $server_id,
			'domain'          => $domain,
			'registrar'       => 'namecom',
			'registered_at'   => $customer['created_at'] ?? null,
			'expires_at'      => $customer['domain_expires_at'] ?? null,
			'auto_renew'      => ! empty( $customer['domain_auto_renew'] ) ? 1 : 0,
			'dns_configured'  => ! empty( $customer['cloudflare_record_id'] ) ? 1 : 0,
			'ssl_configured'  => 0,
			'created_at'      => $customer['created_at'] ?? current_time( 'mysql' ),
		];

		$prepared = self::prepare_insert( $domains_table, $domain_data, self::get_domain_formats() );
		if ( $prepared ) {
			$wpdb->query( $prepared );
		}
	}

	/**
	 * Find an existing server record for a customer.
	 *
	 * @param array  $customer Customer data.
	 * @param string $servers_table Servers table name.
	 * @param string $name Computed server name.
	 * @return int|null Server ID.
	 */
	private static function find_existing_server_id( array $customer, string $servers_table, string $name ): ?int {
		global $wpdb;

		if ( ! empty( $customer['hetzner_server_id'] ) ) {
			$existing_id = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM %i WHERE hetzner_server_id = %s LIMIT 1',
					$servers_table,
					$customer['hetzner_server_id']
				)
			);
			if ( $existing_id ) {
				return (int) $existing_id;
			}
		}

		if ( ! empty( $customer['server_ip'] ) ) {
			$existing_id = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM %i WHERE server_ip = %s LIMIT 1',
					$servers_table,
					$customer['server_ip']
				)
			);
			if ( $existing_id ) {
				return (int) $existing_id;
			}
		}

		if ( ! empty( $customer['openclaw_token'] ) ) {
			$existing_id = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM %i WHERE openclaw_token = %s LIMIT 1',
					$servers_table,
					$customer['openclaw_token']
				)
			);
			if ( $existing_id ) {
				return (int) $existing_id;
			}
		}

		if ( ! empty( $customer['user_id'] ) ) {
			$existing_id = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM %i WHERE user_id = %d AND name = %s LIMIT 1',
					$servers_table,
					(int) $customer['user_id'],
					$name
				)
			);
			if ( $existing_id ) {
				return (int) $existing_id;
			}
		}

		return null;
	}

	/**
	 * Build a server name from customer data.
	 *
	 * @param array $customer Customer data.
	 * @return string Server name.
	 */
	private static function build_server_name( array $customer ): string {
		if ( ! empty( $customer['domain'] ) ) {
			return $customer['domain'];
		}

		if ( ! empty( $customer['email'] ) ) {
			return $customer['email'];
		}

		return 'Spawn Server';
	}

	/**
	 * Prepare an insert statement with NULL support.
	 *
	 * @param string $table Table name.
	 * @param array  $data Data to insert.
	 * @param array  $formats Column formats.
	 * @return string|null Prepared SQL or null.
	 */
	private static function prepare_insert( string $table, array $data, array $formats ): ?string {
		global $wpdb;

		$columns      = [];
		$placeholders = [];
		$values       = [ $table ];

		foreach ( $formats as $column => $format ) {
			if ( ! array_key_exists( $column, $data ) ) {
				continue;
			}

			$columns[] = $column;
			if ( null === $data[ $column ] ) {
				$placeholders[] = 'NULL';
				continue;
			}

			$placeholders[] = $format;
			$values[]       = $data[ $column ];
		}

		if ( empty( $columns ) ) {
			return null;
		}

		$sql = 'INSERT INTO %i (' . implode( ', ', $columns ) . ') VALUES (' . implode( ', ', $placeholders ) . ')';
		return $wpdb->prepare( $sql, $values );
	}

	/**
	 * Formats for server table columns.
	 *
	 * @return array
	 */
	private static function get_server_formats(): array {
		return [
			'user_id'           => '%d',
			'name'              => '%s',
			'tier'              => '%s',
			'hetzner_server_id' => '%s',
			'hetzner_type'      => '%s',
			'server_ip'         => '%s',
			'server_location'   => '%s',
			'openclaw_token'    => '%s',
			'has_wordpress'     => '%d',
			'status'            => '%s',
			'created_at'        => '%s',
			'updated_at'        => '%s',
		];
	}

	/**
	 * Formats for domain table columns.
	 *
	 * @return array
	 */
	private static function get_domain_formats(): array {
		return [
			'user_id'        => '%d',
			'server_id'      => '%d',
			'domain'         => '%s',
			'registrar'      => '%s',
			'registered_at'  => '%s',
			'expires_at'     => '%s',
			'auto_renew'     => '%d',
			'dns_configured' => '%d',
			'ssl_configured' => '%d',
			'created_at'     => '%s',
		];
	}
}
