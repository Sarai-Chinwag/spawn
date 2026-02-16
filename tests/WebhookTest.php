<?php
/**
 * Tests for Spawn\Webhook.
 *
 * @package Spawn
 */

use Spawn\Database;
use Spawn\Webhook;

/**
 * Webhook handler tests.
 *
 * Uses pre_http_request filter to mock all external HTTP calls.
 * Tests run against SQLite via Homeboy WordPress module.
 */
class WebhookTest extends WP_UnitTestCase {

	/**
	 * HTTP requests captured during test.
	 *
	 * @var array{url: string, args: array}[]
	 */
	private array $http_requests = [];

	/**
	 * Next HTTP response to return.
	 *
	 * @var mixed
	 */
	private mixed $next_http_response = null;

	/**
	 * Emails captured during test.
	 *
	 * @var array[]
	 */
	private array $sent_emails = [];

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		// Create Spawn tables.
		Database::create_tables();

		// Set required options.
		update_option( 'admin_email', 'admin@example.com' );
		update_option( 'spawn_provisioner_url', 'http://127.0.0.1:8420' );
		update_option( 'spawn_provisioner_token', 'test-token' );

		// Intercept all outgoing HTTP requests.
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );

		// Capture emails.
		add_filter( 'wp_mail', [ $this, 'capture_email' ] );
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}spawn_customers" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}spawn_domains" );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10 );
		remove_filter( 'wp_mail', [ $this, 'capture_email' ] );

		$this->http_requests      = [];
		$this->next_http_response = null;
		$this->sent_emails        = [];

		parent::tear_down();
	}

	/**
	 * Mock HTTP request filter.
	 *
	 * @param false|array $response Response.
	 * @param array       $args     Request args.
	 * @param string      $url      Request URL.
	 * @return array|WP_Error Mocked response.
	 */
	public function mock_http_request( $response, $args, $url ) {
		$this->http_requests[] = [
			'url'  => $url,
			'args' => $args,
		];

		if ( $this->next_http_response !== null ) {
			$resp                     = $this->next_http_response;
			$this->next_http_response = null;
			return $resp;
		}

		return [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => wp_json_encode( [ 'job_id' => 'test-job-123' ] ),
			'headers'  => [],
		];
	}

	/**
	 * Capture outgoing email.
	 *
	 * @param array $args Email args.
	 * @return array Same args (passthrough).
	 */
	public function capture_email( array $args ): array {
		$this->sent_emails[] = $args;
		return $args;
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Create a test customer in the database.
	 *
	 * @param array $overrides Field overrides.
	 * @return int Customer ID.
	 */
	private function create_test_customer( array $overrides = [] ): int {
		$data = array_merge(
			[
				'email'               => 'test@example.com',
				'domain'              => 'old.example.com',
				'tier'                => 'starter',
				'status'              => 'active',
				'stripe_customer'     => 'cus_test',
				'stripe_subscription' => 'sub_test',
			],
			$overrides
		);

		return Database::create_customer( $data );
	}

	/**
	 * Build a subscription checkout session.
	 */
	private function subscription_session( array $overrides = [] ): array {
		return array_replace_recursive(
			[
				'customer_email' => 'subscriber@example.com',
				'customer'       => 'cus_sub',
				'subscription'   => 'sub_test_123',
				'metadata'       => [
					'source'          => 'spawn',
					'domain'          => 'mysite.example.com',
					'domain_type'     => 'subdomain',
					'tier'            => 'starter',
					'wants_website'   => 'true',
					'customer_region' => 'us',
				],
			],
			$overrides
		);
	}

	// -----------------------------------------------------------------------
	// Subscription Checkout
	// -----------------------------------------------------------------------

	/**
	 * Test successful subscription checkout creates customer and triggers provisioner.
	 */
	public function test_subscription_checkout_creates_customer(): void {
		$session = $this->subscription_session();
		Webhook::handle_checkout_completed( $session, (object) [] );

		$customer = Database::get_customer_by_domain( 'mysite.example.com' );
		$this->assertNotNull( $customer, 'Customer should be created' );
		$this->assertEquals( 'subscriber@example.com', $customer['email'] );
		$this->assertEquals( 'provisioning', $customer['status'] );
		$this->assertEquals( 'cus_sub', $customer['stripe_customer'] );

		// Provisioner should have been called.
		$prov_requests = array_filter( $this->http_requests, fn( $r ) => str_contains( $r['url'], '8420' ) );
		$this->assertNotEmpty( $prov_requests, 'Provisioner should be triggered' );
	}

	/**
	 * Test checkout with missing email does nothing.
	 */
	public function test_subscription_checkout_missing_email(): void {
		$session = $this->subscription_session( [ 'customer_email' => '' ] );
		Webhook::handle_checkout_completed( $session, (object) [] );

		global $wpdb;
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}spawn_customers" );
		$this->assertEquals( 0, (int) $count );
	}

	/**
	 * Test duplicate email with active status is rejected.
	 */
	public function test_subscription_checkout_duplicate_active_email(): void {
		$this->create_test_customer( [ 'email' => 'subscriber@example.com', 'domain' => 'existing.com' ] );

		$session = $this->subscription_session();
		Webhook::handle_checkout_completed( $session, (object) [] );

		// Should still only have the original customer.
		global $wpdb;
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}spawn_customers" );
		$this->assertEquals( 1, (int) $count );
	}

	/**
	 * Test duplicate domain is rejected.
	 */
	public function test_subscription_checkout_duplicate_domain(): void {
		$this->create_test_customer( [
			'email'  => 'first@example.com',
			'domain' => 'mysite.example.com',
		] );

		$session = $this->subscription_session();
		Webhook::handle_checkout_completed( $session, (object) [] );

		global $wpdb;
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}spawn_customers" );
		$this->assertEquals( 1, (int) $count );
	}

	/**
	 * Test provisioner failure sets customer status to failed.
	 */
	public function test_subscription_checkout_provisioner_failure(): void {
		$this->next_http_response = new WP_Error( 'http_failure', 'Connection refused' );

		$session = $this->subscription_session();
		Webhook::handle_checkout_completed( $session, (object) [] );

		$customer = Database::get_customer_by_domain( 'mysite.example.com' );
		$this->assertNotNull( $customer );
		$this->assertEquals( 'failed', $customer['status'] );
	}

	// -----------------------------------------------------------------------
	// Non-Spawn webhook
	// -----------------------------------------------------------------------

	/**
	 * Test non-spawn webhooks are ignored.
	 */
	public function test_non_spawn_webhook_ignored(): void {
		$session = [
			'customer_email' => 'other@example.com',
			'metadata'       => [
				'source' => 'sell-my-images',
				'job_id' => '123',
			],
		];

		Webhook::handle_checkout_completed( $session, (object) [] );

		global $wpdb;
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}spawn_customers" );
		$this->assertEquals( 0, (int) $count );
	}

	// -----------------------------------------------------------------------
	// Credit Purchase
	// -----------------------------------------------------------------------

	/**
	 * Test credit purchase webhook adds credits.
	 */
	public function test_credit_purchase_adds_credits(): void {
		$customer_id = $this->create_test_customer( [
			'email'           => 'buyer@example.com',
			'domain'          => 'buyer.example.com',
			'stripe_customer' => 'cus_buyer',
			'credit_balance'  => 5.00,
		] );

		$session = [
			'customer'       => 'cus_buyer',
			'customer_email' => 'buyer@example.com',
			'amount_total'   => 2000,
			'metadata'       => [
				'source'            => 'spawn',
				'type'              => 'credit_purchase',
				'spawn_customer_id' => (string) $customer_id,
				'credits'           => '20.00',
			],
		];

		Webhook::handle_checkout_completed( $session, (object) [] );

		$customer = Database::get_customer( $customer_id );
		$this->assertEquals( 25.00, (float) $customer['credit_balance'] );
	}

	// -----------------------------------------------------------------------
	// Invoice Lifecycle
	// -----------------------------------------------------------------------

	/**
	 * Test payment failed updates customer status.
	 */
	public function test_payment_failed_updates_status(): void {
		$this->create_test_customer( [
			'stripe_subscription' => 'sub_failing',
		] );

		Webhook::handle_payment_failed(
			[ 'subscription' => 'sub_failing' ],
			(object) []
		);

		$customer = Database::get_customer_by_subscription( 'sub_failing' );
		$this->assertEquals( 'payment_failed', $customer['status'] );
	}

	/**
	 * Test payment failed skipped for comped customer.
	 */
	public function test_payment_failed_skipped_for_comped(): void {
		$this->create_test_customer( [
			'stripe_subscription' => 'sub_comped',
			'billing_type'        => 'comped',
		] );

		Webhook::handle_payment_failed(
			[ 'subscription' => 'sub_comped' ],
			(object) []
		);

		$customer = Database::get_customer_by_subscription( 'sub_comped' );
		$this->assertEquals( 'active', $customer['status'] );
	}

	// -----------------------------------------------------------------------
	// Subscription Cancelled
	// -----------------------------------------------------------------------

	/**
	 * Test subscription cancelled schedules deletion.
	 */
	public function test_subscription_cancelled_schedules_deletion(): void {
		$this->create_test_customer( [
			'stripe_subscription' => 'sub_cancel',
		] );

		Webhook::handle_subscription_cancelled(
			[ 'id' => 'sub_cancel' ],
			(object) []
		);

		$customer = Database::get_customer_by_subscription( 'sub_cancel' );
		$this->assertEquals( 'cancelling', $customer['status'] );
		$this->assertNotEmpty( $customer['scheduled_deletion_at'] );
	}

	/**
	 * Test subscription cancelled skipped for comped customer.
	 */
	public function test_subscription_cancelled_skipped_for_comped(): void {
		$this->create_test_customer( [
			'stripe_subscription' => 'sub_comped_cancel',
			'billing_type'        => 'comped',
		] );

		Webhook::handle_subscription_cancelled(
			[ 'id' => 'sub_comped_cancel' ],
			(object) []
		);

		$customer = Database::get_customer_by_subscription( 'sub_comped_cancel' );
		$this->assertEquals( 'active', $customer['status'] );
	}

	/**
	 * Test already-cancelling customer is not re-processed.
	 */
	public function test_subscription_cancelled_already_cancelling(): void {
		$this->create_test_customer( [
			'stripe_subscription' => 'sub_already',
			'status'              => 'cancelling',
		] );

		Webhook::handle_subscription_cancelled(
			[ 'id' => 'sub_already' ],
			(object) []
		);

		// Status should remain cancelling (not re-processed).
		$customer = Database::get_customer_by_subscription( 'sub_already' );
		$this->assertEquals( 'cancelling', $customer['status'] );
	}

	// -----------------------------------------------------------------------
	// Provisioner Webhook
	// -----------------------------------------------------------------------

	/**
	 * Test provisioner completion webhook.
	 */
	public function test_provisioner_webhook_completion(): void {
		$this->create_test_customer( [
			'domain' => 'newsite.example.com',
			'status' => 'provisioning',
		] );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body( wp_json_encode( [
			'event'                => 'provisioning_complete',
			'domain'               => 'newsite.example.com',
			'server_ip'            => '5.6.7.8',
			'server_id'            => 'srv_1',
			'openclaw_token'       => 'tok_abc',
			'cloudflare_record_id' => 'cf_123',
			'wp_admin_password'    => 'pass123',
		] ) );

		$response = Webhook::handle_provisioner_webhook( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$data = $response->get_data();
		$this->assertTrue( $data['received'] );
		$this->assertTrue( $data['processed'] );
	}

	/**
	 * Test provisioner webhook with unknown event.
	 */
	public function test_provisioner_webhook_unknown_event(): void {
		$request = new WP_REST_Request( 'POST' );
		$request->set_body( wp_json_encode( [ 'event' => 'something_weird' ] ) );

		$response = Webhook::handle_provisioner_webhook( $request );

		$data = $response->get_data();
		$this->assertTrue( $data['received'] );
		$this->assertArrayNotHasKey( 'processed', $data );
	}
}
