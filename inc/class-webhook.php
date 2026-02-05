<?php
/**
 * Webhook handlers.
 *
 * @package Spawn
 */

namespace Spawn;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Handles incoming webhooks from Stripe.
 */
class Webhook {

	/**
	 * Initialize webhooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	/**
	 * Register webhook routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'spawn/v1',
			'/webhook/stripe',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'handle_stripe_webhook' ],
				'permission_callback' => '__return_true', // Verified via signature.
			]
		);

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
		// Check for internal key (same as used for credits deduction).
		$api_key      = $request->get_header( 'X-Spawn-Internal-Key' );
		$expected_key = get_option( 'spawn_internal_api_key', '' );

		// Also accept sweatpants token for backward compat.
		$sweatpants_token   = $request->get_header( 'Authorization' );
		$expected_sp_token  = get_option( 'spawn_sweatpants_token', '' );

		if ( ! empty( $expected_key ) && hash_equals( $expected_key, $api_key ?? '' ) ) {
			return true;
		}

		if ( ! empty( $expected_sp_token ) && 'Bearer ' . $expected_sp_token === $sweatpants_token ) {
			return true;
		}

		// Allow if no keys configured (for initial setup).
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
		$data = $request->get_json_params();

		$event = $data['event'] ?? '';

		if ( 'provisioning_complete' !== $event ) {
			error_log( sprintf( '[Spawn] Unknown provisioner event: %s', $event ) );
			return new WP_REST_Response( [ 'received' => true ] );
		}

		$success = Provisioner::handle_completion( [
			'domain'    => $data['domain'] ?? '',
			'server_ip' => $data['server_ip'] ?? '',
			'success'   => true,
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
	 * Handle Stripe webhook.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public static function handle_stripe_webhook( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$payload   = $request->get_body();
		$sig_header = $request->get_header( 'stripe-signature' );

		// Verify webhook signature.
		$event = Stripe::verify_webhook( $payload, $sig_header );

		if ( is_wp_error( $event ) ) {
			return $event;
		}

		// Handle the event.
		switch ( $event['type'] ) {
			case 'checkout.session.completed':
				self::handle_checkout_completed( $event['data']['object'] );
				break;

			case 'invoice.paid':
				self::handle_invoice_paid( $event['data']['object'] );
				break;

			case 'invoice.payment_failed':
				self::handle_payment_failed( $event['data']['object'] );
				break;

			case 'customer.subscription.deleted':
				self::handle_subscription_cancelled( $event['data']['object'] );
				break;

			default:
				// Log unhandled event types.
				error_log( sprintf( '[Spawn] Unhandled Stripe event: %s', $event['type'] ) );
		}

		return new WP_REST_Response( [ 'received' => true ] );
	}

	/**
	 * Tier to VPS and AI tier mapping.
	 */
	private const TIER_MAP = [
		'starter'  => [
			'vps' => 'cx22',
			'ai'  => '1k',
		],
		'pro'      => [
			'vps' => 'cx32',
			'ai'  => '5k',
		],
		'business' => [
			'vps' => 'cx42',
			'ai'  => '20k',
		],
	];

	/**
	 * Handle successful checkout.
	 *
	 * @param array $session Checkout session data.
	 */
	private static function handle_checkout_completed( array $session ): void {
		$metadata = $session['metadata'] ?? [];

		// Check if this is a credit purchase (handled separately).
		if ( ( $metadata['type'] ?? '' ) === 'credit_purchase' ) {
			$result = Stripe::handle_credit_purchase( [ 'data' => [ 'object' => $session ] ] );
			if ( is_wp_error( $result ) ) {
				error_log( sprintf( '[Spawn] Credit purchase failed: %s', $result->get_error_message() ) );
			}
			return;
		}

		// Extract checkout metadata.
		$domain       = $metadata['domain'] ?? '';
		$domain_type  = $metadata['domain_type'] ?? 'subdomain';
		$domain_price = (float) ( $metadata['domain_price'] ?? 0 );
		$tier         = $metadata['tier'] ?? 'starter';
		$email        = $session['customer_email'] ?? '';

		// Validate required fields.
		if ( empty( $domain ) || empty( $email ) ) {
			error_log( '[Spawn] Checkout completed but missing domain or email' );
			error_log( sprintf( '[Spawn] Session data: %s', wp_json_encode( $session ) ) );
			return;
		}

		// Map tier to VPS and AI tiers.
		$tier_config = self::TIER_MAP[ $tier ] ?? self::TIER_MAP['starter'];

		// Determine if this is a subdomain.
		$is_subdomain = 'subdomain' === $domain_type;

		// Check for duplicate domain.
		$existing = Database::get_customer_by_domain( $domain );
		if ( $existing ) {
			error_log( sprintf( '[Spawn] Domain already exists in database: %s', $domain ) );
			return;
		}

		// Create customer record.
		$customer_id = Database::create_customer( [
			'email'               => $email,
			'domain'              => $domain,
			'domain_type'         => $domain_type,
			'domain_price'        => $domain_price > 0 ? $domain_price : null,
			'subdomain'           => $is_subdomain,
			'vps_tier'            => $tier_config['vps'],
			'ai_tier'             => $tier_config['ai'],
			'stripe_customer'     => $session['customer'] ?? '',
			'stripe_subscription' => $session['subscription'] ?? '',
			'status'              => 'provisioning',
		] );

		if ( ! $customer_id ) {
			error_log( '[Spawn] Failed to create customer record' );
			error_log( sprintf( '[Spawn] Attempted data: email=%s, domain=%s, tier=%s', $email, $domain, $tier ) );
			return;
		}

		error_log( sprintf( '[Spawn] Created customer #%d for %s (%s)', $customer_id, $domain, $email ) );

		// Trigger VPS provisioning via Sweatpants.
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
	 * @param array $invoice Invoice data.
	 */
	private static function handle_invoice_paid( array $invoice ): void {
		$subscription_id = $invoice['subscription'] ?? '';
		
		if ( empty( $subscription_id ) ) {
			return;
		}

		// Update customer status.
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
	 * @param array $invoice Invoice data.
	 */
	private static function handle_payment_failed( array $invoice ): void {
		$subscription_id = $invoice['subscription'] ?? '';
		
		if ( empty( $subscription_id ) ) {
			return;
		}

		$customer = Database::get_customer_by_subscription( $subscription_id );
		if ( $customer ) {
			Database::update_customer( $customer['id'], [
				'status' => 'payment_failed',
			] );

			// TODO: Send notification email.
			// TODO: Start grace period countdown.
		}
	}

	/**
	 * Handle subscription cancellation.
	 *
	 * @param array $subscription Subscription data.
	 */
	private static function handle_subscription_cancelled( array $subscription ): void {
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

			// TODO: Schedule VPS deletion after grace period.
			// TODO: Send notification email.
		}
	}
}
