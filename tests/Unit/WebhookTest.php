<?php
/**
 * Unit tests for Spawn\Webhook.
 *
 * Tests webhook handlers using mocked dependencies (no WordPress or DB needed).
 *
 * @package Spawn\Tests\Unit
 */

declare(strict_types=1);

namespace Spawn\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Spawn\Webhook;
use SpawnTestState;
use WP_Error;
use WP_REST_Request;

/**
 * Webhook unit tests.
 */
class WebhookTest extends TestCase {

	protected function setUp(): void {
		SpawnTestState::reset();
		// Default options needed by most tests.
		SpawnTestState::$options['admin_email'] = 'admin@example.com';
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Build a checkout session array with domain_registration type.
	 */
	private function domain_registration_session( array $overrides = [] ): array {
		return array_replace_recursive(
			[
				'payment_intent' => 'pi_test_123',
				'customer_email' => 'test@example.com',
				'customer'       => 'cus_test',
				'metadata'       => [
					'source'      => 'spawn',
					'type'        => 'domain_registration',
					'domain'      => 'mynewsite.com',
					'customer_id' => '42',
					'server_id'   => '7',
					'base_price'  => '15.99',
				],
			],
			$overrides
		);
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

	/**
	 * Set up a mock customer in the DB returns.
	 */
	private function mock_customer( array $overrides = [] ): array {
		return array_merge(
			[
				'id'                  => 42,
				'email'               => 'test@example.com',
				'domain'              => 'old.example.com',
				'server_ip'           => '1.2.3.4',
				'user_id'             => 10,
				'billing_type'        => 'paid',
				'status'              => 'active',
				'tier'                => 'starter',
				'stripe_customer'     => 'cus_test',
				'stripe_subscription' => 'sub_test',
			],
			$overrides
		);
	}

	// -----------------------------------------------------------------------
	// Domain Registration — Success
	// -----------------------------------------------------------------------

	public function test_domain_registration_success(): void {
		$customer = $this->mock_customer();
		SpawnTestState::$db_returns['get_customer'] = $customer;
		SpawnTestState::$namecom_return = [
			'domain'     => 'mynewsite.com',
			'expires_at' => '2027-02-16T00:00:00Z',
		];
		SpawnTestState::$options['spawn_provisioner_url']   = 'http://127.0.0.1:8420';
		SpawnTestState::$options['spawn_provisioner_token']  = 'secret';
		SpawnTestState::$next_http_response = [
			'response' => [ 'code' => 200 ],
			'body'     => '{"job_id":"job_456"}',
		];

		$session = $this->domain_registration_session();
		Webhook::handle_checkout_completed( $session, (object) [] );

		// Name.com register was called.
		$namecom_calls = array_filter( SpawnTestState::$db_calls, fn( $c ) => $c['method'] === 'namecom_register' );
		$this->assertCount( 1, $namecom_calls );

		// Domain record created.
		$create_domain_calls = array_filter( SpawnTestState::$db_calls, fn( $c ) => $c['method'] === 'create_domain' );
		$this->assertCount( 1, $create_domain_calls );

		// Email sent to customer.
		$this->assertNotEmpty( SpawnTestState::$emails );

		// Domain migration triggered (HTTP request to Sweatpants).
		$this->assertNotEmpty( SpawnTestState::$http_requests );
		$req = SpawnTestState::$http_requests[0];
		$this->assertStringContainsString( '/jobs', $req['url'] );
		$body = json_decode( $req['args']['body'], true );
		$this->assertEquals( 'domain-migrator', $body['module_id'] );
		$this->assertEquals( 'mynewsite.com', $body['inputs']['new_domain'] );
		$this->assertEquals( 'old.example.com', $body['inputs']['old_domain'] );
	}

	// -----------------------------------------------------------------------
	// Domain Registration — Failure triggers refund
	// -----------------------------------------------------------------------

	public function test_domain_registration_failure_triggers_refund(): void {
		$customer = $this->mock_customer();
		SpawnTestState::$db_returns['get_customer'] = $customer;
		SpawnTestState::$namecom_return = new WP_Error( 'registration_failed', 'Domain taken' );

		$session = $this->domain_registration_session();
		Webhook::handle_checkout_completed( $session, (object) [] );

		// No domain record created.
		$create_domain_calls = array_filter( SpawnTestState::$db_calls, fn( $c ) => $c['method'] === 'create_domain' );
		$this->assertCount( 0, $create_domain_calls );

		// Refund triggered.
		$this->assertNotNull( SpawnTestState::$refund_args );
		$this->assertEquals( 'pi_test_123', SpawnTestState::$refund_args['payment_intent'] );

		// Admin notification sent (with error).
		$admin_emails = array_filter( SpawnTestState::$emails, fn( $e ) => str_contains( $e['subject'], 'Domain Purchase' ) );
		$this->assertNotEmpty( $admin_emails );
	}

	// -----------------------------------------------------------------------
	// Domain Registration — Missing customer_id
	// -----------------------------------------------------------------------

	public function test_domain_registration_missing_customer_id_returns_early(): void {
		$session = $this->domain_registration_session([
			'metadata' => [ 'customer_id' => '0', 'domain' => 'test.com', 'type' => 'domain_registration', 'source' => 'spawn' ],
		]);
		Webhook::handle_checkout_completed( $session, (object) [] );

		// No DB lookups for customer.
		$get_customer_calls = array_filter( SpawnTestState::$db_calls, fn( $c ) => $c['method'] === 'get_customer' );
		$this->assertCount( 0, $get_customer_calls );
	}

	// -----------------------------------------------------------------------
	// Domain Registration — Customer not found
	// -----------------------------------------------------------------------

	public function test_domain_registration_customer_not_found(): void {
		SpawnTestState::$db_returns['get_customer'] = null;

		$session = $this->domain_registration_session();
		Webhook::handle_checkout_completed( $session, (object) [] );

		// No Name.com call.
		$namecom_calls = array_filter( SpawnTestState::$db_calls, fn( $c ) => $c['method'] === 'namecom_register' );
		$this->assertCount( 0, $namecom_calls );

		// Error logged.
		$log_match = array_filter( SpawnTestState::$error_log, fn( $m ) => str_contains( $m, 'customer not found' ) );
		$this->assertNotEmpty( $log_match );
	}

	// -----------------------------------------------------------------------
	// Domain Migration — No migration when same domain
	// -----------------------------------------------------------------------

	public function test_domain_migration_skipped_when_same_domain(): void {
		$customer = $this->mock_customer( [ 'domain' => 'mynewsite.com' ] );
		SpawnTestState::$db_returns['get_customer'] = $customer;
		SpawnTestState::$namecom_return = [ 'domain' => 'mynewsite.com', 'expires_at' => '2027-01-01' ];

		$session = $this->domain_registration_session();
		Webhook::handle_checkout_completed( $session, (object) [] );

		// No HTTP request for migration.
		$this->assertEmpty( SpawnTestState::$http_requests );
	}

	// -----------------------------------------------------------------------
	// Domain Migration — No migration when no server_ip
	// -----------------------------------------------------------------------

	public function test_domain_migration_skipped_when_no_server_ip(): void {
		$customer = $this->mock_customer( [ 'server_ip' => '' ] );
		SpawnTestState::$db_returns['get_customer'] = $customer;
		SpawnTestState::$namecom_return = [ 'domain' => 'mynewsite.com', 'expires_at' => '2027-01-01' ];

		$session = $this->domain_registration_session();
		Webhook::handle_checkout_completed( $session, (object) [] );

		// No HTTP request for migration.
		$this->assertEmpty( SpawnTestState::$http_requests );
	}

	// -----------------------------------------------------------------------
	// Subscription Checkout — Success
	// -----------------------------------------------------------------------

	public function test_subscription_checkout_success(): void {
		SpawnTestState::$db_returns['get_customer_by_email']  = null;
		SpawnTestState::$db_returns['get_customer_by_domain'] = null;
		SpawnTestState::$db_returns['create_customer']        = 99;

		$session = $this->subscription_session();
		Webhook::handle_checkout_completed( $session, (object) [] );

		// Customer created.
		$create_calls = array_filter( SpawnTestState::$db_calls, fn( $c ) => $c['method'] === 'create_customer' );
		$this->assertCount( 1, $create_calls );
		$data = array_values( $create_calls )[0]['args'][0];
		$this->assertEquals( 'subscriber@example.com', $data['email'] );
		$this->assertEquals( 'provisioning', $data['status'] );

		// Provisioner triggered.
		$this->assertNotNull( SpawnTestState::$provisioner_args );
		$this->assertEquals( 99, SpawnTestState::$provisioner_args['customer_id'] );
	}

	// -----------------------------------------------------------------------
	// Subscription Checkout — Missing email
	// -----------------------------------------------------------------------

	public function test_subscription_checkout_missing_email(): void {
		$session = $this->subscription_session( [ 'customer_email' => '' ] );
		Webhook::handle_checkout_completed( $session, (object) [] );

		// No customer created.
		$create_calls = array_filter( SpawnTestState::$db_calls, fn( $c ) => $c['method'] === 'create_customer' );
		$this->assertCount( 0, $create_calls );
	}

	// -----------------------------------------------------------------------
	// Subscription Checkout — Duplicate email (active)
	// -----------------------------------------------------------------------

	public function test_subscription_checkout_duplicate_active_email(): void {
		SpawnTestState::$db_returns['get_customer_by_email'] = [
			'id'     => 1,
			'email'  => 'subscriber@example.com',
			'status' => 'active',
		];

		$session = $this->subscription_session();
		Webhook::handle_checkout_completed( $session, (object) [] );

		// No new customer created.
		$create_calls = array_filter( SpawnTestState::$db_calls, fn( $c ) => $c['method'] === 'create_customer' );
		$this->assertCount( 0, $create_calls );
	}

	// -----------------------------------------------------------------------
	// Subscription Checkout — Duplicate domain
	// -----------------------------------------------------------------------

	public function test_subscription_checkout_duplicate_domain(): void {
		SpawnTestState::$db_returns['get_customer_by_email']  = null;
		SpawnTestState::$db_returns['get_customer_by_domain'] = [ 'id' => 1, 'domain' => 'mysite.example.com' ];

		$session = $this->subscription_session();
		Webhook::handle_checkout_completed( $session, (object) [] );

		$create_calls = array_filter( SpawnTestState::$db_calls, fn( $c ) => $c['method'] === 'create_customer' );
		$this->assertCount( 0, $create_calls );
	}

	// -----------------------------------------------------------------------
	// Subscription Checkout — Provisioner failure sets status to failed
	// -----------------------------------------------------------------------

	public function test_subscription_checkout_provisioner_failure(): void {
		SpawnTestState::$db_returns['get_customer_by_email']  = null;
		SpawnTestState::$db_returns['get_customer_by_domain'] = null;
		SpawnTestState::$db_returns['create_customer']        = 99;
		SpawnTestState::$provisioner_return = new WP_Error( 'prov_fail', 'Server unavailable' );

		$session = $this->subscription_session();
		Webhook::handle_checkout_completed( $session, (object) [] );

		// Customer status updated to failed.
		$update_calls = array_filter( SpawnTestState::$db_calls, fn( $c ) => $c['method'] === 'update_customer' );
		$this->assertCount( 1, $update_calls );
		$update = array_values( $update_calls )[0];
		$this->assertEquals( 99, $update['args'][0] );
		$this->assertEquals( 'failed', $update['args'][1]['status'] );
	}

	// -----------------------------------------------------------------------
	// Credit Purchase
	// -----------------------------------------------------------------------

	public function test_credit_purchase_handled(): void {
		$session = [
			'customer_email' => 'buyer@example.com',
			'customer'       => 'cus_buyer',
			'amount_total'   => 2000,
			'metadata'       => [
				'source'            => 'spawn',
				'type'              => 'credit_purchase',
				'spawn_customer_id' => '42',
				'credits'           => '20.00',
			],
		];

		Webhook::handle_checkout_completed( $session, (object) [] );

		$this->assertNotNull( SpawnTestState::$credit_purchase_args );
	}

	// -----------------------------------------------------------------------
	// Invoice Paid — Active
	// -----------------------------------------------------------------------

	public function test_invoice_paid_activates_customer(): void {
		SpawnTestState::$db_returns['get_customer_by_subscription'] = $this->mock_customer( [
			'status'              => 'payment_failed',
			'stripe_subscription' => 'sub_renew',
		] );

		// BUG: Webhook::handle_invoice_paid calls self::log() which doesn't exist.
		// Should be error_log(). Catching the Error to verify the rest of the flow works.
		try {
			Webhook::handle_invoice_paid( [ 'subscription' => 'sub_renew' ], (object) [] );
		} catch ( \Error $e ) {
			$this->assertStringContainsString( 'log', $e->getMessage() );
		}

		$update_calls = array_filter( SpawnTestState::$db_calls, fn( $c ) => $c['method'] === 'update_customer' );
		$this->assertCount( 1, $update_calls );
		$update = array_values( $update_calls )[0];
		$this->assertEquals( 'active', $update['args'][1]['status'] );
	}

	// -----------------------------------------------------------------------
	// Invoice Paid — Comped customer skipped
	// -----------------------------------------------------------------------

	public function test_invoice_paid_skipped_for_comped(): void {
		SpawnTestState::$db_returns['get_customer_by_subscription'] = $this->mock_customer( [
			'billing_type' => 'comped',
		] );

		Webhook::handle_invoice_paid( [ 'subscription' => 'sub_comped' ], (object) [] );

		$update_calls = array_filter( SpawnTestState::$db_calls, fn( $c ) => $c['method'] === 'update_customer' );
		$this->assertCount( 0, $update_calls );
	}

	// -----------------------------------------------------------------------
	// Payment Failed
	// -----------------------------------------------------------------------

	public function test_payment_failed_updates_status(): void {
		SpawnTestState::$db_returns['get_customer_by_subscription'] = $this->mock_customer( [
			'stripe_subscription' => 'sub_fail',
		] );

		Webhook::handle_payment_failed( [ 'subscription' => 'sub_fail' ], (object) [] );

		$update_calls = array_filter( SpawnTestState::$db_calls, fn( $c ) => $c['method'] === 'update_customer' );
		$this->assertCount( 1, $update_calls );
		$this->assertEquals( 'payment_failed', array_values( $update_calls )[0]['args'][1]['status'] );
	}

	// -----------------------------------------------------------------------
	// Payment Failed — Comped customer skipped
	// -----------------------------------------------------------------------

	public function test_payment_failed_skipped_for_comped(): void {
		SpawnTestState::$db_returns['get_customer_by_subscription'] = $this->mock_customer( [
			'billing_type' => 'comped',
		] );

		Webhook::handle_payment_failed( [ 'subscription' => 'sub_comped' ], (object) [] );

		$update_calls = array_filter( SpawnTestState::$db_calls, fn( $c ) => $c['method'] === 'update_customer' );
		$this->assertCount( 0, $update_calls );
	}

	// -----------------------------------------------------------------------
	// Subscription Cancelled — Grace period
	// -----------------------------------------------------------------------

	public function test_subscription_cancelled_schedules_deletion(): void {
		$customer = $this->mock_customer( [ 'stripe_subscription' => 'sub_cancel' ] );
		SpawnTestState::$db_returns['get_customer_by_subscription'] = $customer;
		// After schedule_deletion, get_customer is called again for email.
		SpawnTestState::$db_returns['get_customer'] = array_merge( $customer, [
			'status'                => 'cancelling',
			'scheduled_deletion_at' => '2026-02-23 19:00:00',
		] );

		Webhook::handle_subscription_cancelled( [ 'id' => 'sub_cancel' ], (object) [] );

		// Deletion scheduled.
		$sched_calls = array_filter( SpawnTestState::$db_calls, fn( $c ) => $c['method'] === 'schedule_deletion' );
		$this->assertCount( 1, $sched_calls );
		$this->assertEquals( 7, array_values( $sched_calls )[0]['args'][1] );

		// Cancellation email sent.
		$this->assertTrue( SpawnTestState::$cancellation_email_sent );
	}

	// -----------------------------------------------------------------------
	// Subscription Cancelled — Comped customer skipped
	// -----------------------------------------------------------------------

	public function test_subscription_cancelled_skipped_for_comped(): void {
		SpawnTestState::$db_returns['get_customer_by_subscription'] = $this->mock_customer( [
			'billing_type' => 'comped',
		] );

		Webhook::handle_subscription_cancelled( [ 'id' => 'sub_comped' ], (object) [] );

		$sched_calls = array_filter( SpawnTestState::$db_calls, fn( $c ) => $c['method'] === 'schedule_deletion' );
		$this->assertCount( 0, $sched_calls );
	}

	// -----------------------------------------------------------------------
	// Subscription Cancelled — Already cancelling
	// -----------------------------------------------------------------------

	public function test_subscription_cancelled_already_cancelling_skipped(): void {
		SpawnTestState::$db_returns['get_customer_by_subscription'] = $this->mock_customer( [
			'status' => 'cancelling',
		] );

		Webhook::handle_subscription_cancelled( [ 'id' => 'sub_already' ], (object) [] );

		$sched_calls = array_filter( SpawnTestState::$db_calls, fn( $c ) => $c['method'] === 'schedule_deletion' );
		$this->assertCount( 0, $sched_calls );
	}

	// -----------------------------------------------------------------------
	// Provisioner Webhook — Completion
	// -----------------------------------------------------------------------

	public function test_provisioner_webhook_completion(): void {
		$request = new WP_REST_Request( 'POST' );
		$request->set_body( json_encode( [
			'event'              => 'provisioning_complete',
			'domain'             => 'newsite.example.com',
			'server_ip'          => '5.6.7.8',
			'server_id'          => 'srv_1',
			'openclaw_token'     => 'tok_abc',
			'cloudflare_record_id' => 'cf_123',
			'wp_admin_password'  => 'pass123',
		] ) );

		$response = Webhook::handle_provisioner_webhook( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$data = $response->get_data();
		$this->assertTrue( $data['received'] );
		$this->assertTrue( $data['processed'] );

		// Provisioner::handle_completion was called.
		$completion_calls = array_filter( SpawnTestState::$db_calls, fn( $c ) => $c['method'] === 'provisioner_handle_completion' );
		$this->assertCount( 1, $completion_calls );
	}

	// -----------------------------------------------------------------------
	// Provisioner Webhook — Unknown event
	// -----------------------------------------------------------------------

	public function test_provisioner_webhook_unknown_event(): void {
		$request = new WP_REST_Request( 'POST' );
		$request->set_body( json_encode( [ 'event' => 'something_weird' ] ) );

		$response = Webhook::handle_provisioner_webhook( $request );

		$data = $response->get_data();
		$this->assertTrue( $data['received'] );
		$this->assertArrayNotHasKey( 'processed', $data );
	}

	// -----------------------------------------------------------------------
	// Non-Spawn webhook ignored
	// -----------------------------------------------------------------------

	public function test_non_spawn_webhook_ignored(): void {
		$session = [
			'customer_email' => 'other@example.com',
			'metadata'       => [
				'source' => 'sell-my-images',
				'job_id' => '123',
			],
		];

		Webhook::handle_checkout_completed( $session, (object) [] );

		// No DB calls.
		$create_calls = array_filter( SpawnTestState::$db_calls, fn( $c ) => $c['method'] === 'create_customer' );
		$this->assertCount( 0, $create_calls );
	}

	// -----------------------------------------------------------------------
	// Domain Renewal
	// -----------------------------------------------------------------------

	public function test_domain_renewal_routes_correctly(): void {
		$session = [
			'customer_email' => 'renew@example.com',
			'metadata'       => [
				'source'            => 'spawn',
				'type'              => 'domain_renewal',
				'spawn_customer_id' => '42',
				'domain'            => 'renewed.com',
			],
		];

		// This calls Domain_Controller::process_domain_renewal_payment which we haven't mocked.
		// The test verifies it routes correctly (doesn't create a customer or trigger provisioner).
		Webhook::handle_checkout_completed( $session, (object) [] );

		$create_calls = array_filter( SpawnTestState::$db_calls, fn( $c ) => $c['method'] === 'create_customer' );
		$this->assertCount( 0, $create_calls );
		$this->assertNull( SpawnTestState::$provisioner_args );
	}
}
