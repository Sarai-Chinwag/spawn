<?php
/**
 * Webhook handlers.
 *
 * Uses stripe-integration plugin for Stripe webhooks.
 * Keeps provisioner webhook for Sweatpants callbacks.
 *
 * @package Spawn
 */

declare(strict_types=1);

namespace Spawn;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Handles incoming webhooks.
 */
class Webhook {

	/**
	 * Initialize webhooks.
	 */
	public static function init(): void {
		// Register provisioner webhook route.
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );

		// Hook into stripe-integration webhook events.
		add_action( 'stripe_integration_webhook_checkout_session_completed', array( __CLASS__, 'handle_checkout_completed' ), 10, 2 );
		add_action( 'stripe_integration_webhook_invoice_paid', array( __CLASS__, 'handle_invoice_paid' ), 10, 2 );
		add_action( 'stripe_integration_webhook_invoice_payment_failed', array( __CLASS__, 'handle_payment_failed' ), 10, 2 );
		add_action( 'stripe_integration_webhook_customer_subscription_deleted', array( __CLASS__, 'handle_subscription_cancelled' ), 10, 2 );
	}

	/**
	 * Register webhook routes (provisioner only, Stripe is handled by stripe-integration).
	 */
	public static function register_routes(): void {
		register_rest_route(
			'spawn/v1',
			'/webhook/provisioner',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_provisioner_webhook' ),
				'permission_callback' => array( __CLASS__, 'verify_provisioner_webhook' ),
			)
		);
	}

	/**
	 * Verify provisioner webhook request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if valid, error otherwise.
	 */
	public static function verify_provisioner_webhook( WP_REST_Request $request ): bool|WP_Error {
		$api_key      = $request->get_header( 'X-Spawn-Internal-Key' );
		$expected_key = get_option( 'spawn_internal_api_key', '' );

		$provisioner_token     = $request->get_header( 'Authorization' );
		$expected_prov_token   = get_option( 'spawn_provisioner_token', '' );

		if ( ! empty( $expected_key ) && hash_equals( $expected_key, $api_key ?? '' ) ) {
			return true;
		}

		if ( ! empty( $expected_prov_token ) && 'Bearer ' . $expected_prov_token === $provisioner_token ) {
			return true;
		}

		if ( empty( $expected_key ) && empty( $expected_prov_token ) ) {
			return true;
		}

		return new WP_Error(
			'unauthorized',
			__( 'Invalid webhook authorization', 'spawn' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Handle provisioner completion webhook from Sweatpants.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public static function handle_provisioner_webhook( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data  = $request->get_json_params();
		$event = $data['event'] ?? '';

		if ( 'provisioning_complete' !== $event ) {
			error_log( sprintf( '[Spawn] Unknown provisioner event: %s', $event ) );
			return new WP_REST_Response( array( 'received' => true ) );
		}

		$success = Provisioner::handle_completion( array(
			'domain'               => $data['domain'] ?? '',
			'server_ip'            => $data['server_ip'] ?? '',
			'server_id'            => $data['server_id'] ?? '',
			'opencode_password'    => $data['opencode_password'] ?? '',
			'cloudflare_record_id' => $data['cloudflare_record_id'] ?? '',
			'wp_admin_password'    => $data['wp_admin_password'] ?? '',
			'success'              => true,
		) );

		if ( ! $success ) {
			error_log( '[Spawn] Failed to process provisioner completion webhook' );
			return new WP_Error(
				'processing_failed',
				__( 'Failed to process webhook', 'spawn' ),
				array( 'status' => 500 )
			);
		}

		error_log( sprintf( '[Spawn] Provisioning complete for domain: %s', $data['domain'] ?? 'unknown' ) );

		return new WP_REST_Response( array(
			'received'  => true,
			'processed' => true,
		) );
	}

	/**
	 * Handle successful checkout from stripe-integration webhook.
	 *
	 * @param object|array $session Checkout session data.
	 * @param object       $event   Full Stripe event.
	 */
	public static function handle_checkout_completed( $session, $event ): void {
		$session  = is_object( $session ) ? $session->toArray() : (array) $session;
		$metadata = $session['metadata'] ?? array();

		// Only handle Spawn events.
		$source = $metadata['source'] ?? '';
		if ( 'spawn' !== $source && '' !== $source ) {
			// Check for legacy metadata without source.
			if ( ! isset( $metadata['domain'] ) && ! isset( $metadata['type'] ) ) {
				return; // Not ours.
			}
		}

		// Check if this is a credit purchase.
		if ( ( $metadata['type'] ?? '' ) === 'credit_purchase' ) {
			$result = Payment_Helpers::handle_credit_purchase( $session );
			if ( is_wp_error( $result ) ) {
				error_log( sprintf( '[Spawn] Credit purchase failed: %s', $result->get_error_message() ) );
			}
			return;
		}

		// Check if this is a domain renewal.
		if ( ( $metadata['type'] ?? '' ) === 'domain_renewal' ) {
			$customer_id = (int) ( $metadata['spawn_customer_id'] ?? 0 );
			$domain      = $metadata['domain'] ?? '';

			if ( $customer_id && $domain ) {
				$result = Controllers\Domain_Controller::process_domain_renewal_payment( $customer_id, $domain );
				if ( is_wp_error( $result ) ) {
					error_log( sprintf( '[Spawn] Domain renewal processing failed: %s', $result->get_error_message() ) );
				}
			} else {
				error_log( '[Spawn] Domain renewal webhook missing customer_id or domain' );
			}
			return;
		}

		// Check if this is a domain registration.
		if ( ( $metadata['type'] ?? '' ) === 'domain_registration' ) {
			self::process_domain_registration( $session );
			return;
		}

		// Handle subscription checkout.
		self::process_subscription_checkout( $session, $metadata );
	}

	/**
	 * Process domain registration after successful payment.
	 *
	 * @param array $session Checkout session data.
	 */
	private static function process_domain_registration( array $session ): void {
		$metadata    = $session['metadata'] ?? array();
		$domain      = strtolower( trim( $metadata['domain'] ?? '' ) );
		$domain      = preg_replace( '/^(https?:\/\/)?(www\.)?/', '', $domain );
		$domain      = rtrim( $domain, '/' );
		$domain      = sanitize_text_field( $domain );
		$server_id   = isset( $metadata['server_id'] ) ? (int) $metadata['server_id'] : null;
		$base_price  = isset( $metadata['base_price'] ) ? (float) $metadata['base_price'] : 0.0;
		$customer_id = (int) ( $metadata['customer_id'] ?? 0 );

		if ( ! $customer_id || empty( $domain ) ) {
			error_log( '[Spawn] Domain registration webhook missing customer_id or domain' );
			return;
		}

		$customer = Database::get_customer( $customer_id );
		if ( ! $customer ) {
			error_log( sprintf( '[Spawn] Domain registration webhook customer not found: %d', $customer_id ) );
			return;
		}

		$registration = Name_Com::register( $domain, 1 );
		if ( is_wp_error( $registration ) ) {
			error_log( sprintf( '[Spawn] Domain registration API failed for %s (customer #%d): %s', $domain, $customer_id, $registration->get_error_message() ) );
			self::send_domain_purchase_notification( $customer, $domain, $base_price, $registration->get_error_message() );

			// Refund the customer — registration failed after payment.
			self::refund_domain_purchase( $session, $domain, $customer_id, $registration->get_error_message() );
			return;
		}

		$domain_id = Database::create_domain( array(
			'user_id'        => (int) ( $customer['user_id'] ?? 0 ),
			'server_id'      => $server_id,
			'domain'         => $domain,
			'registrar'      => 'namecom',
			'registered_at'  => current_time( 'mysql' ),
			'expires_at'     => $registration['expires_at'] ?? null,
			'auto_renew'     => true,
			'dns_configured' => false,
			'ssl_configured' => false,
		) );

		if ( ! $domain_id ) {
			error_log( sprintf( '[Spawn] Failed to insert domain record for %s (customer #%d)', $domain, $customer_id ) );
		}

		self::send_domain_registration_email( $customer, $domain, $registration['expires_at'] ?? null );
		self::send_domain_purchase_notification( $customer, $domain, $base_price );

		// Trigger domain migration — point DNS, configure SSL, update WordPress.
		$old_domain = $customer['domain'] ?? '';
		if ( ! empty( $customer['server_ip'] ) && $domain !== $old_domain ) {
			self::trigger_domain_migration( $customer, $domain, $old_domain, $domain_id );
		}

		error_log( sprintf( '[Spawn] Domain registered: %s for customer #%d', $domain, $customer_id ) );
	}

	/**
	 * Send customer confirmation after domain registration.
	 *
	 * @param array       $customer   Customer data.
	 * @param string      $domain     Domain name.
	 * @param string|null $expires_at Expiration date.
	 */
	private static function send_domain_registration_email( array $customer, string $domain, ?string $expires_at ): void {
		$email   = $customer['email'] ?? '';
		$subject = sprintf(
			/* translators: %s: domain name */
			__( 'Your domain %s has been registered', 'spawn' ),
			$domain
		);

		$expires_formatted = $expires_at
			? wp_date( 'F j, Y', strtotime( $expires_at ) )
			: __( 'in approximately one year', 'spawn' );

		$message = sprintf(
			/* translators: 1: domain name, 2: expiration date */
			__(
				"Hello,\n\n" .
				"Your domain %1\$s has been successfully registered.\n\n" .
				"Expiration date: %2\$s\n\n" .
				"We'll let you know when it's time to renew.\n\n" .
				'—The Spawn Team',
				'spawn'
			),
			$domain,
			$expires_formatted
		);

		if ( $email ) {
			wp_mail( $email, $subject, $message );
		}
	}

	/**
	 * Send admin notification for domain purchase.
	 *
	 * @param array       $customer      Customer data.
	 * @param string      $domain        Domain name.
	 * @param float       $base_price    Price paid.
	 * @param string|null $error_message Optional error message.
	 */
	private static function send_domain_purchase_notification( array $customer, string $domain, float $base_price, ?string $error_message = null ): void {
		$admin_email = get_option( 'admin_email' );
		$email       = $customer['email'] ?? '';
		$customer_id = (int) ( $customer['id'] ?? 0 );

		$subject = sprintf(
			/* translators: %s: domain name */
			__( 'New Domain Purchase: %s', 'spawn' ),
			$domain
		);

		$message = sprintf(
			__(
				"A customer purchased a domain.\n\n" .
				"Customer email: %1\$s\n" .
				"Domain: %2\$s\n" .
				"Price paid: $%3\$.2f\n" .
				"Customer ID: %4\$d\n",
				'spawn'
			),
			$email,
			$domain,
			$base_price,
			$customer_id
		);

		if ( $error_message ) {
			$message .= sprintf(
				/* translators: %s: error message */
				__( "\nRegistration error: %s", 'spawn' ),
				$error_message
			);
		}

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Refund a domain purchase after registration failure.
	 *
	 * @param array  $session       Stripe checkout session data.
	 * @param string $domain        Domain that failed to register.
	 * @param int    $customer_id   Spawn customer ID.
	 * @param string $error_message Registration error message.
	 */
	private static function refund_domain_purchase( array $session, string $domain, int $customer_id, string $error_message ): void {
		if ( ! class_exists( '\\StripeIntegration\\StripeClient' ) ) {
			error_log( '[Spawn] Cannot refund domain purchase — Stripe Integration not available' );
			return;
		}

		$payment_intent = $session['payment_intent'] ?? '';
		if ( empty( $payment_intent ) ) {
			error_log( sprintf( '[Spawn] Cannot refund domain %s — no payment_intent in session', $domain ) );
			return;
		}

		$result = \StripeIntegration\StripeClient::create_refund(
			$payment_intent,
			null, // Full refund.
			'requested_by_customer'
		);

		if ( is_wp_error( $result ) ) {
			error_log( sprintf(
				'[Spawn] Refund failed for domain %s (customer #%d): %s',
				$domain,
				$customer_id,
				$result->get_error_message()
			) );
		} else {
			error_log( sprintf(
				'[Spawn] Refunded domain purchase for %s (customer #%d) due to registration failure: %s',
				$domain,
				$customer_id,
				$error_message
			) );
		}
	}

	/**
	 * Trigger domain migration via Sweatpants.
	 *
	 * Points DNS at the customer's VPS, configures SSL, updates nginx,
	 * and updates WordPress siteurl for the new domain.
	 *
	 * @param array    $customer   Customer data.
	 * @param string   $domain     New domain name.
	 * @param string   $old_domain Previous domain/subdomain.
	 * @param int|null $domain_id  Domain record ID.
	 */
	private static function trigger_domain_migration( array $customer, string $domain, string $old_domain, ?int $domain_id ): void {
		$config = array(
			'url'   => get_option( 'spawn_provisioner_url', 'http://127.0.0.1:8420' ),
			'token' => get_option( 'spawn_provisioner_token', '' ),
		);

		if ( empty( $config['url'] ) ) {
			error_log( '[Spawn] Cannot trigger domain migration — provisioner URL not configured' );
			return;
		}

		$job_data = array(
			'module_id' => 'domain-migrator',
			'inputs'    => array(
				'customer_id' => (int) $customer['id'],
				'new_domain'  => $domain,
				'old_domain'  => $old_domain,
				'server_ip'   => $customer['server_ip'],
				'domain_id'   => $domain_id,
			),
		);

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode( $job_data ),
			'timeout' => 30,
		);

		if ( ! empty( $config['token'] ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $config['token'];
		}

		$response = wp_remote_request( $config['url'] . '/jobs', $args );

		if ( is_wp_error( $response ) ) {
			error_log( sprintf(
				'[Spawn] Domain migration trigger failed for %s (customer #%d): %s',
				$domain,
				(int) $customer['id'],
				$response->get_error_message()
			) );
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			error_log( sprintf(
				'[Spawn] Domain migration API error for %s: %s',
				$domain,
				$body['error'] ?? "HTTP $code"
			) );
			return;
		}

		$job_id = $body['job_id'] ?? $body['id'] ?? 'unknown';
		error_log( sprintf(
			'[Spawn] Domain migration triggered for %s → %s (customer #%d, job: %s)',
			$old_domain,
			$domain,
			(int) $customer['id'],
			$job_id
		) );
	}

	/**
	 * Process subscription checkout completion.
	 *
	 * @param array $session  Checkout session data.
	 * @param array $metadata Session metadata.
	 */
	private static function process_subscription_checkout( array $session, array $metadata ): void {
		$domain          = $metadata['domain'] ?? '';
		$domain_type     = $metadata['domain_type'] ?? 'subdomain';
		$domain_price    = (float) ( $metadata['domain_price'] ?? 0 );
		$tier            = $metadata['tier'] ?? 'starter';
		$wants_website   = filter_var( $metadata['wants_website'] ?? true, FILTER_VALIDATE_BOOLEAN );
		$customer_region = sanitize_text_field( $metadata['customer_region'] ?? 'us' );
		$email           = $session['customer_email'] ?? '';

		// Email is always required.
		if ( empty( $email ) ) {
			error_log( '[Spawn] Checkout completed but missing email' );
			error_log( sprintf( '[Spawn] Session data: %s', wp_json_encode( $session ) ) );
			return;
		}

		// Domain is only required if customer wants a website AND is not using a subdomain.
		// Subdomain customers get their domain auto-generated after customer creation.
		if ( $wants_website && empty( $domain ) && 'subdomain' !== $domain_type ) {
			error_log( '[Spawn] Checkout completed with wants_website=true but missing domain (non-subdomain)' );
			return;
		}

		$is_subdomain = 'subdomain' === $domain_type;

		// Check for existing active customer with same email (prevents double-charge).
		$existing_by_email = Database::get_customer_by_email( $email );
		if ( $existing_by_email ) {
			$existing_status = $existing_by_email['status'] ?? '';
			// Only block if customer has an active/provisioning subscription.
			// Allow if previous subscription was cancelled/deleted.
			if ( in_array( $existing_status, array( 'active', 'provisioning', 'pending', 'payment_failed' ), true ) ) {
				error_log( sprintf( '[Spawn] Customer already exists with email %s (status: %s). Skipping duplicate.', $email, $existing_status ) );
				return;
			}
		}

		// Check for existing customer with same domain (if domain provided).
		if ( ! empty( $domain ) ) {
			$existing = Database::get_customer_by_domain( $domain );
			if ( $existing ) {
				error_log( sprintf( '[Spawn] Domain already exists in database: %s', $domain ) );
				return;
			}
		}

		$customer_id = Database::create_customer( array(
			'email'               => $email,
			'domain'              => $domain ? $domain : null,
			'domain_type'         => $domain_type,
			'domain_price'        => $domain_price > 0 ? $domain_price : null,
			'subdomain'           => $is_subdomain,
			'tier'                => $tier,
			'wants_website'       => $wants_website,
			'customer_region'     => $customer_region,
			'stripe_customer'     => $session['customer'] ?? '',
			'stripe_subscription' => $session['subscription'] ?? '',
			'status'              => 'provisioning',
		) );

		if ( ! $customer_id ) {
			error_log( '[Spawn] Failed to create customer record' );
			error_log( sprintf( '[Spawn] Attempted data: email=%s, domain=%s, tier=%s, wants_website=%s', $email, $domain, $tier, $wants_website ? 'yes' : 'no' ) );
			return;
		}

		error_log( sprintf( '[Spawn] Created customer #%d for %s (tier: %s, wants_website: %s, region: %s)', $customer_id, $email, $tier, $wants_website ? 'yes' : 'no', $customer_region ) );

		$result = Provisioner::trigger( array(
			'customer_id'    => $customer_id,
			'customer_email' => $email,
			'domain'         => $domain,
			'domain_type'    => $domain_type,
			'tier'           => $tier,
			'wants_website'  => $wants_website,
			'subdomain'      => $is_subdomain,
		) );

		if ( is_wp_error( $result ) ) {
			error_log( sprintf( '[Spawn] Provisioning trigger failed: %s', $result->get_error_message() ) );
			Database::update_customer( $customer_id, array( 'status' => 'failed' ) );
			return;
		}

		error_log( sprintf( '[Spawn] Provisioning triggered for customer #%d, job: %s', $customer_id, $result['job_id'] ?? 'unknown' ) );
	}

	/**
	 * Handle successful invoice payment (subscription renewal).
	 *
	 * Renews the subscription. Credits are pay-as-you-go (not included in tiers).
	 *
	 * @param object|array $invoice Invoice data.
	 * @param object       $event   Full Stripe event.
	 */
	public static function handle_invoice_paid( $invoice, $event ): void {
		$invoice         = is_object( $invoice ) ? $invoice->toArray() : (array) $invoice;
		$subscription_id = $invoice['subscription'] ?? '';

		if ( empty( $subscription_id ) ) {
			return;
		}

		$customer = Database::get_customer_by_subscription( $subscription_id );
		if ( ! $customer ) {
			return;
		}

		// Skip for comped customers - they don't have subscriptions.
		if ( 'comped' === ( $customer['billing_type'] ?? 'paid' ) ) {
			return;
		}

		// Mark subscription as active and update renewal date.
		// No monthly credits — AI credits are pay-as-you-go.
		Database::update_customer( $customer['id'], array(
			'status'     => 'active',
			'renewed_at' => current_time( 'mysql' ),
		) );

		error_log( sprintf(
			'[Spawn] Subscription renewed for customer %d (tier: %s).',
			$customer['id'],
			$customer['tier'] ?? 'starter'
		) );
	}

	/**
	 * Handle failed payment.
	 *
	 * @param object|array $invoice Invoice data.
	 * @param object       $event   Full Stripe event.
	 */
	public static function handle_payment_failed( $invoice, $event ): void {
		$invoice         = is_object( $invoice ) ? $invoice->toArray() : (array) $invoice;
		$subscription_id = $invoice['subscription'] ?? '';

		if ( empty( $subscription_id ) ) {
			return;
		}

		$customer = Database::get_customer_by_subscription( $subscription_id );
		if ( $customer ) {
			// Skip for comped customers - they don't have subscriptions.
			if ( 'comped' === ( $customer['billing_type'] ?? 'paid' ) ) {
				return;
			}

			Database::update_customer( $customer['id'], array(
				'status' => 'payment_failed',
			) );
		}
	}

	/**
	 * Handle subscription cancellation.
	 *
	 * Initiates the grace period - does NOT immediately delete resources.
	 * Actual deletion happens after grace period via Cleanup cron.
	 *
	 * @param object|array $subscription Subscription data.
	 * @param object       $event        Full Stripe event.
	 */
	public static function handle_subscription_cancelled( $subscription, $event ): void {
		$subscription    = is_object( $subscription ) ? $subscription->toArray() : (array) $subscription;
		$subscription_id = $subscription['id'] ?? '';

		if ( empty( $subscription_id ) ) {
			return;
		}

		$customer = Database::get_customer_by_subscription( $subscription_id );
		if ( ! $customer ) {
			return;
		}

		// Skip for comped customers - they don't have subscriptions to cancel.
		if ( 'comped' === ( $customer['billing_type'] ?? 'paid' ) ) {
			return;
		}

		// Skip if already cancelling or deleted.
		if ( in_array( $customer['status'], array( 'cancelling', 'deleted' ), true ) ) {
			return;
		}

		// Schedule deletion with grace period.
		$result = Database::schedule_deletion( $customer['id'], Cleanup::GRACE_PERIOD_DAYS );

		if ( $result ) {
			// Refresh customer data to get the scheduled deletion date.
			$customer = Database::get_customer( $customer['id'] );

			// Send cancellation confirmation email with export instructions.
			Cleanup::send_cancellation_email( $customer );

			error_log( sprintf(
				'[Spawn] Cancellation initiated for customer #%d (%s). Deletion scheduled for %s',
				$customer['id'],
				$customer['domain'],
				$customer['scheduled_deletion_at']
			) );
		}
	}
}
