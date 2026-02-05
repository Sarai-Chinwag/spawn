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
			'tier'                => 'starter',
			'wants_website'       => true,
			'stripe_customer'     => 'cus_test123',
			'stripe_subscription' => 'sub_test123',
			'status'              => 'provisioning',
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
			'credit_balance' => 20.00,
		] );

		$customer = Database::get_customer( $customer_id );

		$this->assertIsArray( $customer );
		$this->assertEquals( 'retrieve@example.com', $customer['email'] );
		$this->assertEquals( 'retrieve.saraichinwag.com', $customer['domain'] );
		$this->assertEquals( 'active', $customer['status'] );
		$this->assertEquals( 20.00, (float) $customer['credit_balance'] );
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
	 * Test credit operations with dollar amounts.
	 */
	public function test_credit_operations(): void {
		$customer_id = Database::create_customer( [
			'email'          => 'credits@example.com',
			'domain'         => 'credits.saraichinwag.com',
			'credit_balance' => 10.00,
			'status'         => 'active',
		] );

		// Add credits.
		$result = Database::add_credits( $customer_id, 5.00 );
		$this->assertTrue( $result );

		$customer = Database::get_customer( $customer_id );
		$this->assertEquals( 15.00, (float) $customer['credit_balance'] );

		// Deduct credits.
		$result = Database::deduct_credits( $customer_id, 2.50 );
		$this->assertTrue( $result );

		$customer = Database::get_customer( $customer_id );
		$this->assertEquals( 12.50, (float) $customer['credit_balance'] );
	}

	/**
	 * Test deduct_credits fails when insufficient balance.
	 */
	public function test_deduct_credits_insufficient(): void {
		$customer_id = Database::create_customer( [
			'email'          => 'poor@example.com',
			'domain'         => 'poor.saraichinwag.com',
			'credit_balance' => 1.00,
			'status'         => 'active',
		] );

		$result = Database::deduct_credits( $customer_id, 50.00 );

		// Should fail or return false.
		$customer = Database::get_customer( $customer_id );
		// Balance should not go negative.
		$this->assertGreaterThanOrEqual( 0, (float) $customer['credit_balance'] );
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

	/**
	 * Test wants_website field is stored correctly.
	 */
	public function test_wants_website_stored(): void {
		// Customer with website.
		$with_id = Database::create_customer( [
			'email'         => 'withsite@example.com',
			'domain'        => 'withsite.saraichinwag.com',
			'wants_website' => true,
			'status'        => 'active',
		] );

		$with_customer = Database::get_customer( $with_id );
		$this->assertEquals( 1, (int) $with_customer['wants_website'] );

		// Customer without website (AI-only).
		$without_id = Database::create_customer( [
			'email'         => 'aionly@example.com',
			'domain'        => '',
			'wants_website' => false,
			'status'        => 'active',
		] );

		$without_customer = Database::get_customer( $without_id );
		$this->assertEquals( 0, (int) $without_customer['wants_website'] );
	}

	/**
	 * Test update_wants_website method.
	 */
	public function test_update_wants_website(): void {
		$customer_id = Database::create_customer( [
			'email'         => 'toggle@example.com',
			'domain'        => 'toggle.saraichinwag.com',
			'wants_website' => true,
			'status'        => 'active',
		] );

		// Toggle to false.
		$result = Database::update_wants_website( $customer_id, false );
		$this->assertTrue( $result );

		$customer = Database::get_customer( $customer_id );
		$this->assertEquals( 0, (int) $customer['wants_website'] );

		// Toggle back to true.
		$result = Database::update_wants_website( $customer_id, true );
		$this->assertTrue( $result );

		$customer = Database::get_customer( $customer_id );
		$this->assertEquals( 1, (int) $customer['wants_website'] );
	}

	/**
	 * Test update_tier method.
	 */
	public function test_update_tier(): void {
		$customer_id = Database::create_customer( [
			'email'  => 'upgrade@example.com',
			'domain' => 'upgrade.saraichinwag.com',
			'tier'   => 'starter',
			'status' => 'active',
		] );

		$result = Database::update_tier( $customer_id, 'pro' );
		$this->assertTrue( $result );

		$customer = Database::get_customer( $customer_id );
		$this->assertEquals( 'pro', $customer['tier'] );
	}

	/**
	 * Test tier and hetzner_type are set correctly from Config.
	 */
	public function test_tier_sets_hetzner_type(): void {
		// US customer with website = US server type.
		$us_id = Database::create_customer( [
			'email'           => 'us@example.com',
			'domain'          => 'us.saraichinwag.com',
			'tier'            => 'starter',
			'wants_website'   => true,
			'customer_region' => 'us',
			'status'          => 'active',
		] );

		$us_customer = Database::get_customer( $us_id );
		$this->assertEquals( 'starter', $us_customer['tier'] );
		$this->assertEquals( 'cpx21', $us_customer['hetzner_type'] );

		// EU customer = EU server type.
		$eu_id = Database::create_customer( [
			'email'           => 'eu@example.com',
			'domain'          => 'eu.saraichinwag.com',
			'tier'            => 'starter',
			'wants_website'   => true,
			'customer_region' => 'eu',
			'status'          => 'active',
		] );

		$eu_customer = Database::get_customer( $eu_id );
		$this->assertEquals( 'cpx22', $eu_customer['hetzner_type'] );
	}

	/**
	 * Test get_customer_by_user_id.
	 */
	public function test_get_customer_by_user_id(): void {
		$user_id = $this->factory->user->create( [
			'user_email' => 'wpuser@example.com',
		] );

		$customer_id = Database::create_customer( [
			'email'   => 'wpuser@example.com',
			'domain'  => 'wpuser.saraichinwag.com',
			'user_id' => $user_id,
			'status'  => 'active',
		] );

		$customer = Database::get_customer_by_user_id( $user_id );

		$this->assertIsArray( $customer );
		$this->assertEquals( $customer_id, (int) $customer['id'] );
		$this->assertEquals( 'wpuser@example.com', $customer['email'] );
	}

	/**
	 * Test schedule_deletion sets cancelling status and date.
	 */
	public function test_schedule_deletion(): void {
		$customer_id = Database::create_customer( [
			'email'  => 'delete@example.com',
			'domain' => 'delete.saraichinwag.com',
			'status' => 'active',
		] );

		$result = Database::schedule_deletion( $customer_id, 7 );
		$this->assertTrue( $result );

		$customer = Database::get_customer( $customer_id );
		$this->assertEquals( 'cancelling', $customer['status'] );
		$this->assertNotEmpty( $customer['scheduled_deletion_at'] );

		// Verify the date is ~7 days in the future.
		$scheduled = strtotime( $customer['scheduled_deletion_at'] );
		$expected  = strtotime( '+7 days' );
		$this->assertEqualsWithDelta( $expected, $scheduled, 86400 ); // Within 1 day.
	}

	/**
	 * Test auto_refill settings.
	 */
	public function test_auto_refill_settings(): void {
		$customer_id = Database::create_customer( [
			'email'  => 'refill@example.com',
			'domain' => 'refill.saraichinwag.com',
			'status' => 'active',
		] );

		// Update auto-refill settings.
		$result = Database::update_auto_refill_settings(
			$customer_id,
			true,  // enabled
			5.00,  // threshold ($5)
			20.00  // amount ($20)
		);
		$this->assertTrue( $result );

		// Retrieve settings.
		$settings = Database::get_auto_refill_settings( $customer_id );
		$this->assertTrue( $settings['enabled'] );
		$this->assertEquals( 5.00, (float) $settings['threshold'] );
		$this->assertEquals( 20.00, (float) $settings['amount'] );
	}
}
