<?php
/**
 * Mock Spawn classes for unit testing.
 *
 * These must be loaded BEFORE the Spawn autoloader so they take precedence.
 * Each class records calls to SpawnTestState for assertion.
 *
 * @package Spawn\Tests
 */

declare(strict_types=1);

namespace Spawn;

/**
 * Mock Database — no real DB, records calls and returns configured values.
 */
class Database {

	public static function get_customer( int $id ): ?array {
		\SpawnTestState::$db_calls[] = [ 'method' => 'get_customer', 'args' => [ $id ] ];
		return \SpawnTestState::$db_returns['get_customer'] ?? null;
	}

	public static function get_customer_by_email( string $email ): ?array {
		\SpawnTestState::$db_calls[] = [ 'method' => 'get_customer_by_email', 'args' => [ $email ] ];
		return \SpawnTestState::$db_returns['get_customer_by_email'] ?? null;
	}

	public static function get_customer_by_domain( string $domain ): ?array {
		\SpawnTestState::$db_calls[] = [ 'method' => 'get_customer_by_domain', 'args' => [ $domain ] ];
		return \SpawnTestState::$db_returns['get_customer_by_domain'] ?? null;
	}

	public static function get_customer_by_subscription( string $subscription_id ): ?array {
		\SpawnTestState::$db_calls[] = [ 'method' => 'get_customer_by_subscription', 'args' => [ $subscription_id ] ];
		return \SpawnTestState::$db_returns['get_customer_by_subscription'] ?? null;
	}

	public static function create_customer( array $data ): ?int {
		\SpawnTestState::$db_calls[] = [ 'method' => 'create_customer', 'args' => [ $data ] ];
		return \SpawnTestState::$db_returns['create_customer'] ?? 1;
	}

	public static function update_customer( int $id, array $data ): bool {
		\SpawnTestState::$db_calls[] = [ 'method' => 'update_customer', 'args' => [ $id, $data ] ];
		return \SpawnTestState::$db_returns['update_customer'] ?? true;
	}

	public static function create_domain( array $data ): ?int {
		\SpawnTestState::$db_calls[] = [ 'method' => 'create_domain', 'args' => [ $data ] ];
		return \SpawnTestState::$db_returns['create_domain'] ?? 1;
	}

	public static function renew_domain( int $customer_id ): bool {
		\SpawnTestState::$db_calls[] = [ 'method' => 'renew_domain', 'args' => [ $customer_id ] ];
		return true;
	}

	public static function schedule_deletion( int $customer_id, int $days ): bool {
		\SpawnTestState::$db_calls[] = [ 'method' => 'schedule_deletion', 'args' => [ $customer_id, $days ] ];
		return \SpawnTestState::$schedule_deletion_return ?? true;
	}
}

/**
 * Mock Provisioner — records trigger calls.
 */
class Provisioner {

	public static function trigger( array $params ): array|\WP_Error {
		\SpawnTestState::$provisioner_args = $params;
		if ( \SpawnTestState::$provisioner_return !== null ) {
			return \SpawnTestState::$provisioner_return;
		}
		return [ 'job_id' => 'test-job-123' ];
	}

	public static function handle_completion( array $data ): bool {
		\SpawnTestState::$db_calls[] = [ 'method' => 'provisioner_handle_completion', 'args' => [ $data ] ];
		return \SpawnTestState::$db_returns['provisioner_handle_completion'] ?? true;
	}
}

/**
 * Mock Name_Com — returns configured result.
 */
class Name_Com {

	public static function renew( string $domain, int $years = 1 ): array|\WP_Error {
		\SpawnTestState::$db_calls[] = [ 'method' => 'namecom_renew', 'args' => [ $domain, $years ] ];
		return [ 'domain' => $domain, 'expires_at' => '2028-02-16T00:00:00Z' ];
	}

	public static function register( string $domain, int $years = 1 ): array|\WP_Error {
		\SpawnTestState::$db_calls[] = [ 'method' => 'namecom_register', 'args' => [ $domain, $years ] ];
		if ( \SpawnTestState::$namecom_return !== null ) {
			return \SpawnTestState::$namecom_return;
		}
		return [
			'domain'     => $domain,
			'expires_at' => '2027-02-16T00:00:00Z',
		];
	}
}

/**
 * Mock Payment_Helpers — records credit purchase calls.
 */
class Payment_Helpers {

	public static function handle_credit_purchase( array $session ): true|\WP_Error {
		\SpawnTestState::$credit_purchase_args = $session;
		if ( \SpawnTestState::$credit_purchase_return !== null ) {
			return \SpawnTestState::$credit_purchase_return;
		}
		return true;
	}
}

/**
 * Mock Cleanup — records email calls.
 */
class Cleanup {

	public const GRACE_PERIOD_DAYS = 7;

	public static function send_cancellation_email( array $customer ): void {
		\SpawnTestState::$cancellation_email_sent = true;
	}
}

/**
 * Mock Domain_Controller (Controllers namespace).
 */
// Defined below after namespace switch.

/**
 * Mock Cron.
 */
class Cron {

	public static function clear_warnings_sent( int $customer_id ): void {
		\SpawnTestState::$db_calls[] = [ 'method' => 'cron_clear_warnings', 'args' => [ $customer_id ] ];
	}
}

// ---------------------------------------------------------------------------
// Mock StripeIntegration namespace
// ---------------------------------------------------------------------------

namespace StripeIntegration;

class StripeClient {

	public static function create_refund( string $payment_intent, ?int $amount = null, string $reason = '' ): array|\WP_Error {
		\SpawnTestState::$refund_args = compact( 'payment_intent', 'amount', 'reason' );
		if ( \SpawnTestState::$refund_return !== null ) {
			return \SpawnTestState::$refund_return;
		}
		return [ 'id' => 're_test_123', 'status' => 'succeeded' ];
	}
}

// ---------------------------------------------------------------------------
// Mock Spawn\Controllers namespace
// ---------------------------------------------------------------------------

namespace Spawn\Controllers;

use WP_Error;

class Domain_Controller {

	public static function process_domain_renewal_payment( int $customer_id, string $domain ): bool|WP_Error {
		// Use the real mock chain: Name_Com::renew → Database::renew_domain → Cron::clear_warnings.
		$renewal_result = \Spawn\Name_Com::renew( $domain, 1 );

		if ( \Spawn\is_wp_error( $renewal_result ) ) {
			return $renewal_result;
		}

		\Spawn\Database::renew_domain( $customer_id );
		\Spawn\Cron::clear_warnings_sent( $customer_id );

		return true;
	}
}
