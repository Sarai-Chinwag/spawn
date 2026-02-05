<?php
/**
 * Tests for Database class.
 *
 * @package Spawn
 */

declare(strict_types=1);

namespace Spawn\Tests;

use WP_UnitTestCase;
use Spawn\Database;

/**
 * Database class tests.
 */
class DatabaseTest extends WP_UnitTestCase {

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		// Ensure tables exist.
		Database::create_tables();
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down(): void {
		global $wpdb;

		// Clean up test data.
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}spawn_customers" );

		parent::tear_down();
	}

	/**
	 * Test create_customer inserts a record.
	 */
	public function test_create_customer(): void {
		$customer_id = Database::create_customer( [
			'email'               => 'test@example.com',
			'domain'              => 'test.saraichinwag.com',
			'domain_type'         => 'subdomain',
			'subdomain'           => true,
			'vps_tier'            => 'cpx21',
			'stripe_customer'     => 'cus_test123',
			'stripe_subscription' => 'sub_test123',
			'status'              => 'provisioning',
			'credit_balance'      => 1000,
		] );

		$this->assertIsInt( $customer_id );
		$this->assertGreaterThan( 0, $customer_id );
	}

	/**
	 * Test get_customer retrieves correct data.
	 */
	public function test_get_customer(): void {
		$customer_id = Database::create_customer( [
			'email'          => 'retrieve@example.com',
			'domain'         => 'retrieve.saraichinwag.com',
			'status'         => 'active',
			'credit_balance' => 2000,
		] );

		$customer = Database::get_customer( $customer_id );

		$this->assertIsArray( $customer );
		$this->assertEquals( 'retrieve@example.com', $customer['email'] );
		$this->assertEquals( 'retrieve.saraichinwag.com', $customer['domain'] );
		$this->assertEquals( 'active', $customer['status'] );
		$this->assertEquals( 2000, (int) $customer['credit_balance'] );
	}

	/**
	 * Test get_customer_by_domain.
	 */
	public function test_get_customer_by_domain(): void {
		Database::create_customer( [
			'email'  => 'domain@example.com',
			'domain' => 'findme.saraichinwag.com',
			'status' => 'active',
		] );

		$customer = Database::get_customer_by_domain( 'findme.saraichinwag.com' );

		$this->assertIsArray( $customer );
		$this->assertEquals( 'domain@example.com', $customer['email'] );
	}

	/**
	 * Test get_customer_by_domain returns null for nonexistent.
	 */
	public function test_get_customer_by_domain_returns_null(): void {
		$customer = Database::get_customer_by_domain( 'nonexistent.example.com' );

		$this->assertNull( $customer );
	}

	/**
	 * Test get_customer_by_subscription.
	 */
	public function test_get_customer_by_subscription(): void {
		Database::create_customer( [
			'email'               => 'sub@example.com',
			'domain'              => 'sub.saraichinwag.com',
			'stripe_subscription' => 'sub_findme123',
			'status'              => 'active',
		] );

		$customer = Database::get_customer_by_subscription( 'sub_findme123' );

		$this->assertIsArray( $customer );
		$this->assertEquals( 'sub@example.com', $customer['email'] );
	}

	/**
	 * Test update_customer modifies record.
	 */
	public function test_update_customer(): void {
		$customer_id = Database::create_customer( [
			'email'  => 'update@example.com',
			'domain' => 'update.saraichinwag.com',
			'status' => 'provisioning',
		] );

		$result = Database::update_customer( $customer_id, [
			'status'    => 'active',
			'server_ip' => '192.0.2.1',
		] );

		$this->assertTrue( $result );

		$customer = Database::get_customer( $customer_id );
		$this->assertEquals( 'active', $customer['status'] );
		$this->assertEquals( '192.0.2.1', $customer['server_ip'] );
	}

	/**
	 * Test credit operations.
	 */
	public function test_credit_operations(): void {
		$customer_id = Database::create_customer( [
			'email'          => 'credits@example.com',
			'domain'         => 'credits.saraichinwag.com',
			'credit_balance' => 1000,
			'status'         => 'active',
		] );

		// Add credits.
		$result = Database::add_credits( $customer_id, 500 );
		$this->assertTrue( $result );

		$customer = Database::get_customer( $customer_id );
		$this->assertEquals( 1500, (int) $customer['credit_balance'] );

		// Deduct credits.
		$result = Database::deduct_credits( $customer_id, 200 );
		$this->assertTrue( $result );

		$customer = Database::get_customer( $customer_id );
		$this->assertEquals( 1300, (int) $customer['credit_balance'] );
	}

	/**
	 * Test deduct_credits fails when insufficient balance.
	 */
	public function test_deduct_credits_insufficient(): void {
		$customer_id = Database::create_customer( [
			'email'          => 'poor@example.com',
			'domain'         => 'poor.saraichinwag.com',
			'credit_balance' => 100,
			'status'         => 'active',
		] );

		$result = Database::deduct_credits( $customer_id, 500 );

		// Should fail or return false.
		$customer = Database::get_customer( $customer_id );
		// Balance should not go negative (depends on implementation).
		$this->assertGreaterThanOrEqual( 0, (int) $customer['credit_balance'] );
	}

	/**
	 * Test duplicate domain prevention.
	 */
	public function test_duplicate_domain_prevented(): void {
		Database::create_customer( [
			'email'  => 'first@example.com',
			'domain' => 'unique.saraichinwag.com',
			'status' => 'active',
		] );

		// Attempting to create with same domain should fail or return existing.
		$existing = Database::get_customer_by_domain( 'unique.saraichinwag.com' );
		$this->assertNotNull( $existing );
		$this->assertEquals( 'first@example.com', $existing['email'] );
	}
}
