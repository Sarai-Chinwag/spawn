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
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );

		// Hook into stripe-integration webhook events.
		add_action( 'stripe_integration_webhook_checkout_session_completed', [ __CLASS__, 'handle_checkout_completed' ], 10, 2 );
		add_action( 'stripe_integration_webhook_invoice_paid', [ __CLASS__, 'handle_invoice_paid' ], 10, 2 );
		add_action( 'stripe_integration_webhook_invoice_payment_failed', [ __CLASS__, 'handle_payment_failed' ], 10, 2 );
		add_action( 'stripe_integration_webhook_customer_subscription_deleted', [ __CLASS__, 'handle_subscription_cancelled' ], 10, 2 );
	}

	/**
	 * Register webhook routes (provisioner only, Stripe is handled by stripe-integration).
	 */
	public static function register_routes(): void {
		register_rest_route(
			'spawn/v1',
			'/webhook/provisioner',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'handle_provisioner_webhook' ],
				'permission_callback' => [ __CLASS__, 'verify_provisioner_webhook' ],
			]
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

		$sweatpants_token  = $request->get_header( 'Authorization' );
		$expected_sp_token = get_option( 'spawn_sweatpants_token', '' );

		if ( ! empty( $expected_key ) && hash_equals( $expected_key, $api_key ?? '' ) ) {
			return true;
		}

		if ( ! empty( $expected_sp_token ) && 'Bearer ' . $expected_sp_token === $sweatpants_token ) {
			return true;
		}

		if ( empty( $expected_key ) && empty( $expected_sp_token ) ) {
			return true;
		}

		return new WP_Error(
			'unauthorized',
			__( 'Invalid webhook authorization', 'spawn' ),
			[ 'status' => 401 ]
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
			return new WP_REST_Response( [ 'received' => true ] );
		}

		$success = Provisioner::handle_completion( [
			'domain'         => $data['domain'] ?? '',
			'server_ip'      => $data['server_ip'] ?? '',
			'openclaw_token' => $data['openclaw_token'] ?? '',
			'success'        => true,
		] );

		if ( ! $success ) {
			error_log( '[Spawn] Failed to process provisioner completion webhook' );
			return new WP_Error(
				'processing_failed',
				__( 'Failed to process webhook', 'spawn' ),
				[ 'status' => 500 ]
			);
		}

		error_log( sprintf( '[Spawn] Provisioning complete for domain: %s', $data['domain'] ?? 'unknown' ) );

		return new WP_REST_Response( [ 'received' => true, 'processed' => true ] );
	}

	/**
	 * Handle successful checkout from stripe-integration webhook.
	 *
	 * @param object|array $session Checkout session data.
	 * @param object       $event   Full Stripe event.
	 */
	public static function handle_checkout_completed( $session, $event ): void {
		$session  = is_object( $session ) ? $session->toArray() : (array) $session;
		$metadata = $session['metadata'] ?? [];

		// Only handle Spawn events.
		$source = $metadata['source'] ?? '';
		if ( $source !== 'spawn' && $source !== '' ) {
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
				$result = REST_API::process_domain_renewal_payment( $customer_id, $domain );
				if ( is_wp_error( $result ) ) {
					error_log( sprintf( '[Spawn] Domain renewal processing failed: %s', $result->get_error_message() ) );
				}
			} else {
				error_log( '[Spawn] Domain renewal webhook missing customer_id or domain' );
			}
			return;
		}

		// Handle subscription checkout.
		self::process_subscription_checkout( $session, $metadata );
	}

	/**
	 * Process subscription checkout completion.
	 *
	 * @param array $session  Checkout session data.
	 * @param array $metadata Session metadata.
	 */
	private static function process_subscription_checkout( array $session, array $metadata ): void {
		$domain       = $metadata['domain'] ?? '';
		$domain_type  = $metadata['domain_type'] ?? 'subdomain';
		$domain_price = (float) ( $metadata['domain_price'] ?? 0 );
		$tier         = $metadata['tier'] ?? 'starter';
		$email        = $session['customer_email'] ?? '';

		if ( empty( $domain ) || empty( $email ) ) {
			error_log( '[Spawn] Checkout completed but missing domain or email' );
			error_log( sprintf( '[Spawn] Session data: %s', wp_json_encode( $session ) ) );
			return;
		}

		$tier_config  = Config::get_tier( $tier ) ?? Config::get_tier( 'starter' );
		$is_subdomain = 'subdomain' === $domain_type;

		$existing = Database::get_customer_by_domain( $domain );
		if ( $existing ) {
			error_log( sprintf( '[Spawn] Domain already exists in database: %s', $domain ) );
			return;
		}

		$customer_id = Database::create_customer( [
			'email'               => $email,
			'domain'              => $domain,
			'domain_type'         => $domain_type,
			'domain_price'        => $domain_price > 0 ? $domain_price : null,
			'subdomain'           => $is_subdomain,
			'vps_tier'            => $tier_config['hetzner_type'],
			'stripe_customer'     => $session['customer'] ?? '',
			'stripe_subscription' => $session['subscription'] ?? '',
			'status'              => 'provisioning',
			'credit_balance'      => $tier_config['included_credits'],
		] );

		if ( ! $customer_id ) {
			error_log( '[Spawn] Failed to create customer record' );
			error_log( sprintf( '[Spawn] Attempted data: email=%s, domain=%s, tier=%s', $email, $domain, $tier ) );
			return;
		}

		error_log( sprintf( '[Spawn] Created customer #%d for %s (%s)', $customer_id, $domain, $email ) );

		$result = Provisioner::trigger( [
			'customer_id'    => $customer_id,
			'customer_email' => $email,
			'domain'         => $domain,
			'tier'           => $tier,
			'subdomain'      => $is_subdomain,
		] );

		if ( is_wp_error( $result ) ) {
			error_log( sprintf( '[Spawn] Provisioning trigger failed: %s', $result->get_error_message() ) );
			Database::update_customer( $customer_id, [ 'status' => 'failed' ] );
			return;
		}

		error_log( sprintf( '[Spawn] Provisioning triggered for customer #%d, job: %s', $customer_id, $result['job_id'] ?? 'unknown' ) );
	}

	/**
	 * Handle successful invoice payment (subscription renewal).
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
		if ( $customer ) {
			Database::update_customer( $customer['id'], [
				'status'     => 'active',
				'renewed_at' => current_time( 'mysql' ),
			] );
		}
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
			Database::update_customer( $customer['id'], [
				'status' => 'payment_failed',
			] );
		}
	}

	/**
	 * Handle subscription cancellation.
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
		if ( $customer ) {
			Database::update_customer( $customer['id'], [
				'status'       => 'cancelled',
				'cancelled_at' => current_time( 'mysql' ),
			] );
		}
	}
}
