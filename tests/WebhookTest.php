<?php
/**
 * Tests for Webhook handling.
 *
 * @package Spawn
 */

declare(strict_types=1);

namespace Spawn\Tests;

use WP_UnitTestCase;
use Spawn\Config;
use Spawn\Database;
use Spawn\Webhook;

/**
 * Webhook handler tests.
 */
class WebhookTest extends WP_UnitTestCase {

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		Database::create_tables();
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down(): void {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}spawn_customers" );
		parent::tear_down();
	}

	/**
	 * Test checkout completed creates customer record.
	 */
	public function test_checkout_completed_creates_customer(): void {
		// Simulate checkout session data.
		$session = [
			'customer_email' => 'checkout@example.com',
			'customer'       => 'cus_test123',
			'subscription'   => 'sub_test123',
			'metadata'       => [
				'source'      => 'spawn',
				'domain'      => 'newsite.saraichinwag.com',
				'domain_type' => 'subdomain',
				'tier'        => 'starter',
			],
		];

		// Mock the event object.
		$event = (object) [ 'type' => 'checkout.session.completed' ];

		// Process the webhook (this would normally be called by stripe-integration).
		Webhook::handle_checkout_completed( $session, $event );

		// Verify customer was created.
		$customer = Database::get_customer_by_domain( 'newsite.saraichinwag.com' );

		$this->assertNotNull( $customer, 'Customer should be created after checkout' );
		$this->assertEquals( 'checkout@example.com', $customer['email'] );
		$this->assertEquals( 'cus_test123', $customer['stripe_customer'] );
		$this->assertEquals( 'sub_test123', $customer['stripe_subscription'] );
		$this->assertEquals( 'provisioning', $customer['status'] );
	}

	/**
	 * Test checkout with correct tier sets correct credits.
	 */
	public function test_checkout_sets_correct_credits(): void {
		$test_cases = [
			'starter'  => 1000,
			'pro'      => 2000,
			'business' => 4000,
		];

		foreach ( $test_cases as $tier => $expected_credits ) {
			// Clean up for each iteration.
			global $wpdb;
			$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}spawn_customers" );

			$session = [
				'customer_email' => "test-{$tier}@example.com",
				'customer'       => "cus_{$tier}",
				'subscription'   => "sub_{$tier}",
				'metadata'       => [
					'source'      => 'spawn',
					'domain'      => "{$tier}.saraichinwag.com",
					'domain_type' => 'subdomain',
					'tier'        => $tier,
				],
			];

			$event = (object) [ 'type' => 'checkout.session.completed' ];
			Webhook::handle_checkout_completed( $session, $event );

			$customer = Database::get_customer_by_domain( "{$tier}.saraichinwag.com" );

			$this->assertNotNull( $customer, "Customer for $tier should exist" );
			$this->assertEquals(
				$expected_credits,
				(int) $customer['credit_balance'],
				"Tier $tier should have $expected_credits credits"
			);
		}
	}

	/**
	 * Test checkout with custom domain includes domain price.
	 */
	public function test_checkout_with_custom_domain(): void {
		$session = [
			'customer_email' => 'custom@example.com',
			'customer'       => 'cus_custom',
			'subscription'   => 'sub_custom',
			'metadata'       => [
				'source'       => 'spawn',
				'domain'       => 'mycustomsite.com',
				'domain_type'  => 'custom',
				'domain_price' => '15.99',
				'tier'         => 'pro',
			],
		];

		$event = (object) [ 'type' => 'checkout.session.completed' ];
		Webhook::handle_checkout_completed( $session, $event );

		$customer = Database::get_customer_by_domain( 'mycustomsite.com' );

		$this->assertNotNull( $customer );
		$this->assertEquals( 'custom', $customer['domain_type'] );
		$this->assertEquals( 15.99, (float) $customer['domain_price'] );
	}

	/**
	 * Test duplicate domain checkout is rejected.
	 */
	public function test_duplicate_domain_rejected(): void {
		// Create existing customer.
		Database::create_customer( [
			'email'  => 'existing@example.com',
			'domain' => 'taken.saraichinwag.com',
			'status' => 'active',
		] );

		// Try checkout with same domain.
		$session = [
			'customer_email' => 'new@example.com',
			'customer'       => 'cus_new',
			'subscription'   => 'sub_new',
			'metadata'       => [
				'source'      => 'spawn',
				'domain'      => 'taken.saraichinwag.com',
				'domain_type' => 'subdomain',
				'tier'        => 'starter',
			],
		];

		$event = (object) [ 'type' => 'checkout.session.completed' ];
		Webhook::handle_checkout_completed( $session, $event );

		// Should still only have the original customer.
		$customer = Database::get_customer_by_domain( 'taken.saraichinwag.com' );
		$this->assertEquals( 'existing@example.com', $customer['email'] );
	}

	/**
	 * Test non-spawn webhooks are ignored.
	 */
	public function test_non_spawn_webhooks_ignored(): void {
		$session = [
			'customer_email' => 'other@example.com',
			'metadata'       => [
				'source' => 'sell-my-images',
				'job_id' => '123',
			],
		];

		$event = (object) [ 'type' => 'checkout.session.completed' ];
		Webhook::handle_checkout_completed( $session, $event );

		// No customer should be created.
		global $wpdb;
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}spawn_customers" );
		$this->assertEquals( 0, (int) $count );
	}

	/**
	 * Test credit purchase webhook adds credits.
	 */
	public function test_credit_purchase_adds_credits(): void {
		// Create existing customer.
		$customer_id = Database::create_customer( [
			'email'           => 'buyer@example.com',
			'domain'          => 'buyer.saraichinwag.com',
			'stripe_customer' => 'cus_buyer',
			'credit_balance'  => 500,
			'status'          => 'active',
		] );

		$session = [
			'customer'       => 'cus_buyer',
			'customer_email' => 'buyer@example.com',
			'amount_total'   => 2000, // $20.00 in cents.
			'metadata'       => [
				'source'            => 'spawn',
				'type'              => 'credit_purchase',
				'spawn_customer_id' => (string) $customer_id,
				'credits'           => '2000',
			],
		];

		$event = (object) [ 'type' => 'checkout.session.completed' ];
		Webhook::handle_checkout_completed( $session, $event );

		$customer = Database::get_customer( $customer_id );
		$this->assertEquals( 2500, (int) $customer['credit_balance'] );
	}

	/**
	 * Test subscription cancelled updates status.
	 */
	public function test_subscription_cancelled(): void {
		Database::create_customer( [
			'email'               => 'cancel@example.com',
			'domain'              => 'cancel.saraichinwag.com',
			'stripe_subscription' => 'sub_tocancel',
			'status'              => 'active',
		] );

		$subscription = [
			'id' => 'sub_tocancel',
		];

		$event = (object) [ 'type' => 'customer.subscription.deleted' ];
		Webhook::handle_subscription_cancelled( $subscription, $event );

		$customer = Database::get_customer_by_domain( 'cancel.saraichinwag.com' );
		$this->assertEquals( 'cancelled', $customer['status'] );
	}

	/**
	 * Test payment failed updates status.
	 */
	public function test_payment_failed(): void {
		Database::create_customer( [
			'email'               => 'fail@example.com',
			'domain'              => 'fail.saraichinwag.com',
			'stripe_subscription' => 'sub_failing',
			'status'              => 'active',
		] );

		$invoice = [
			'subscription' => 'sub_failing',
		];

		$event = (object) [ 'type' => 'invoice.payment_failed' ];
		Webhook::handle_payment_failed( $invoice, $event );

		$customer = Database::get_customer_by_domain( 'fail.saraichinwag.com' );
		$this->assertEquals( 'payment_failed', $customer['status'] );
	}
}
