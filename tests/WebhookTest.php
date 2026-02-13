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
				'source'          => 'spawn',
			'domain'          => 'newsite.example.com',
				'domain_type'     => 'subdomain',
				'tier'            => 'starter',
				'wants_website'   => 'true',
				'customer_region' => 'us',
			],
		];

		// Mock the event object.
		$event = (object) [ 'type' => 'checkout.session.completed' ];

		// Process the webhook (this would normally be called by stripe-integration).
		Webhook::handle_checkout_completed( $session, $event );

		// Verify customer was created.
		$customer = Database::get_customer_by_domain( 'newsite.example.com' );

		$this->assertNotNull( $customer, 'Customer should be created after checkout' );
		$this->assertEquals( 'checkout@example.com', $customer['email'] );
		$this->assertEquals( 'cus_test123', $customer['stripe_customer'] );
		$this->assertEquals( 'sub_test123', $customer['stripe_subscription'] );
		$this->assertEquals( 'provisioning', $customer['status'] );
	}

	/**
	 * Test checkout creates customers with zero credits (pay-as-you-go).
	 */
	public function test_checkout_starts_with_zero_credits(): void {
		$test_cases = [
			'starter'  => 0,
			'pro'      => 0,
			'business' => 0,
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
					'source'          => 'spawn',
				'domain'          => "{$tier}.example.com",
					'domain_type'     => 'subdomain',
					'tier'            => $tier,
					'wants_website'   => 'true',
					'customer_region' => 'us',
				],
			];

			$event = (object) [ 'type' => 'checkout.session.completed' ];
			Webhook::handle_checkout_completed( $session, $event );

			$customer = Database::get_customer_by_domain( "{$tier}.example.com" );

			$this->assertNotNull( $customer, "Customer for $tier should exist" );
			$this->assertEquals(
				$expected_credits,
				(float) $customer['credit_balance'],
				"Tier $tier should have \${$expected_credits} credits"
			);
		}
	}

	/**
	 * Test checkout with wants_website=true creates customer with website flag.
	 */
	public function test_checkout_with_wants_website_true(): void {
		$session = [
			'customer_email' => 'website@example.com',
			'customer'       => 'cus_website',
			'subscription'   => 'sub_website',
			'metadata'       => [
				'source'          => 'spawn',
			'domain'          => 'withsite.example.com',
				'domain_type'     => 'subdomain',
				'tier'            => 'starter',
				'wants_website'   => 'true',
				'customer_region' => 'us',
			],
		];

		$event = (object) [ 'type' => 'checkout.session.completed' ];
		Webhook::handle_checkout_completed( $session, $event );

		$customer = Database::get_customer_by_domain( 'withsite.example.com' );

		$this->assertNotNull( $customer );
		$this->assertEquals( 1, (int) $customer['wants_website'] );
	}

	/**
	 * Test checkout with wants_website=false creates AI-only customer.
	 */
	public function test_checkout_with_wants_website_false(): void {
		$session = [
			'customer_email' => 'aionly@example.com',
			'customer'       => 'cus_aionly',
			'subscription'   => 'sub_aionly',
			'metadata'       => [
				'source'          => 'spawn',
				'domain'          => '',  // No domain for AI-only.
				'domain_type'     => 'subdomain',
				'tier'            => 'starter',
				'wants_website'   => 'false',
				'customer_region' => 'us',
			],
		];

		$event = (object) [ 'type' => 'checkout.session.completed' ];
		Webhook::handle_checkout_completed( $session, $event );

		// AI-only customers don't have a domain, look up by email.
		global $wpdb;
		$customer = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}spawn_customers WHERE email = %s",
				'aionly@example.com'
			),
			ARRAY_A
		);

		$this->assertNotNull( $customer );
		$this->assertEquals( 0, (int) $customer['wants_website'] );
		$this->assertEmpty( $customer['domain'] );
	}

	/**
	 * Test checkout respects customer_region for server selection.
	 */
	public function test_checkout_customer_region(): void {
		$session = [
			'customer_email' => 'eu@example.com',
			'customer'       => 'cus_eu',
			'subscription'   => 'sub_eu',
			'metadata'       => [
				'source'          => 'spawn',
			'domain'          => 'eusite.example.com',
				'domain_type'     => 'subdomain',
				'tier'            => 'starter',
				'wants_website'   => 'true',
				'customer_region' => 'eu',
			],
		];

		$event = (object) [ 'type' => 'checkout.session.completed' ];
		Webhook::handle_checkout_completed( $session, $event );

		$customer = Database::get_customer_by_domain( 'eusite.example.com' );

		$this->assertNotNull( $customer );
		$this->assertEquals( 'eu', $customer['customer_region'] );
		// EU customers get EU server types (cpx22 instead of cpx21).
		$this->assertEquals( 'cpx22', $customer['server_type'] );
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
				'source'          => 'spawn',
				'domain'          => 'mycustomsite.com',
				'domain_type'     => 'register',
				'domain_price'    => '15.99',
				'tier'            => 'pro',
				'wants_website'   => 'true',
				'customer_region' => 'us',
			],
		];

		$event = (object) [ 'type' => 'checkout.session.completed' ];
		Webhook::handle_checkout_completed( $session, $event );

		$customer = Database::get_customer_by_domain( 'mycustomsite.com' );

		$this->assertNotNull( $customer );
		$this->assertEquals( 'register', $customer['domain_type'] );
		$this->assertEquals( 15.99, (float) $customer['domain_price'] );
	}

	/**
	 * Test duplicate domain checkout is rejected.
	 */
	public function test_duplicate_domain_rejected(): void {
		// Create existing customer.
		Database::create_customer( [
			'email'  => 'existing@example.com',
			'domain' => 'taken.example.com',
			'status' => 'active',
		] );

		// Try checkout with same domain.
		$session = [
			'customer_email' => 'new@example.com',
			'customer'       => 'cus_new',
			'subscription'   => 'sub_new',
			'metadata'       => [
				'source'          => 'spawn',
				'domain'          => 'taken.example.com',
				'domain_type'     => 'subdomain',
				'tier'            => 'starter',
				'wants_website'   => 'true',
				'customer_region' => 'us',
			],
		];

		$event = (object) [ 'type' => 'checkout.session.completed' ];
		Webhook::handle_checkout_completed( $session, $event );

		// Should still only have the original customer.
		$customer = Database::get_customer_by_domain( 'taken.example.com' );
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
			'domain'          => 'buyer.example.com',
			'stripe_customer' => 'cus_buyer',
			'credit_balance'  => 5.00,
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
				'credits'           => '20.00', // $20 in credits.
			],
		];

		$event = (object) [ 'type' => 'checkout.session.completed' ];
		Webhook::handle_checkout_completed( $session, $event );

		$customer = Database::get_customer( $customer_id );
		$this->assertEquals( 25.00, (float) $customer['credit_balance'] );
	}

	/**
	 * Test subscription cancelled initiates grace period.
	 */
	public function test_subscription_cancelled(): void {
		Database::create_customer( [
			'email'               => 'cancel@example.com',
			'domain'              => 'cancel.example.com',
			'stripe_subscription' => 'sub_tocancel',
			'status'              => 'active',
		] );

		$subscription = [
			'id' => 'sub_tocancel',
		];

		$event = (object) [ 'type' => 'customer.subscription.deleted' ];
		Webhook::handle_subscription_cancelled( $subscription, $event );

		$customer = Database::get_customer_by_domain( 'cancel.example.com' );
		$this->assertEquals( 'cancelling', $customer['status'] );
		$this->assertNotEmpty( $customer['scheduled_deletion_at'] );
	}

	/**
	 * Test payment failed updates status.
	 */
	public function test_payment_failed(): void {
		Database::create_customer( [
			'email'               => 'fail@example.com',
			'domain'              => 'fail.example.com',
			'stripe_subscription' => 'sub_failing',
			'status'              => 'active',
		] );

		$invoice = [
			'subscription' => 'sub_failing',
		];

		$event = (object) [ 'type' => 'invoice.payment_failed' ];
		Webhook::handle_payment_failed( $invoice, $event );

		$customer = Database::get_customer_by_domain( 'fail.example.com' );
		$this->assertEquals( 'payment_failed', $customer['status'] );
	}

	/**
	 * Test invoice paid updates status to active.
	 */
	public function test_invoice_paid_activates_customer(): void {
		Database::create_customer( [
			'email'               => 'renew@example.com',
			'domain'              => 'renew.example.com',
			'stripe_subscription' => 'sub_renewing',
			'status'              => 'payment_failed',
		] );

		$invoice = [
			'subscription' => 'sub_renewing',
		];

		$event = (object) [ 'type' => 'invoice.paid' ];
		Webhook::handle_invoice_paid( $invoice, $event );

		$customer = Database::get_customer_by_domain( 'renew.example.com' );
		$this->assertEquals( 'active', $customer['status'] );
	}

	/**
	 * Test wants_website default is true when not specified.
	 */
	public function test_wants_website_defaults_to_true(): void {
		$session = [
			'customer_email' => 'default@example.com',
			'customer'       => 'cus_default',
			'subscription'   => 'sub_default',
			'metadata'       => [
				'source'      => 'spawn',
			'domain'      => 'default.example.com',
				'domain_type' => 'subdomain',
				'tier'        => 'starter',
				// Note: wants_website NOT specified.
			],
		];

		$event = (object) [ 'type' => 'checkout.session.completed' ];
		Webhook::handle_checkout_completed( $session, $event );

		$customer = Database::get_customer_by_domain( 'default.example.com' );

		$this->assertNotNull( $customer );
		$this->assertEquals( 1, (int) $customer['wants_website'] );
	}

	/**
	 * Test tier defaults to starter when not specified.
	 */
	public function test_tier_defaults_to_starter(): void {
		$session = [
			'customer_email' => 'notier@example.com',
			'customer'       => 'cus_notier',
			'subscription'   => 'sub_notier',
			'metadata'       => [
				'source'        => 'spawn',
			'domain'        => 'notier.example.com',
				'domain_type'   => 'subdomain',
				'wants_website' => 'true',
				// Note: tier NOT specified.
			],
		];

		$event = (object) [ 'type' => 'checkout.session.completed' ];
		Webhook::handle_checkout_completed( $session, $event );

		$customer = Database::get_customer_by_domain( 'notier.example.com' );

		$this->assertNotNull( $customer );
		$this->assertEquals( 'starter', $customer['tier'] );
		$this->assertEquals( 5.00, (float) $customer['credit_balance'] );
	}
}
