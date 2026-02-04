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
	 * Handle successful checkout.
	 *
	 * @param array $session Checkout session data.
	 */
	private static function handle_checkout_completed( array $session ): void {
		$metadata = $session['metadata'] ?? [];
		$domain   = $metadata['domain'] ?? '';
		$tier     = $metadata['tier'] ?? 'starter';
		$subdomain = ( $metadata['subdomain'] ?? '0' ) === '1';
		$email    = $session['customer_email'] ?? '';

		if ( empty( $domain ) || empty( $email ) ) {
			error_log( '[Spawn] Checkout completed but missing domain or email' );
			return;
		}

		// Create customer record.
		$customer_id = Database::create_customer( [
			'email'           => $email,
			'domain'          => $domain,
			'tier'            => $tier,
			'subdomain'       => $subdomain,
			'stripe_customer' => $session['customer'] ?? '',
			'stripe_subscription' => $session['subscription'] ?? '',
			'status'          => 'provisioning',
		] );

		if ( ! $customer_id ) {
			error_log( '[Spawn] Failed to create customer record' );
			return;
		}

		// Trigger VPS provisioning via Sweatpants.
		$result = Provisioner::trigger( [
			'customer_id'    => $customer_id,
			'customer_email' => $email,
			'domain'         => $domain,
			'tier'           => $tier,
			'subdomain'      => $subdomain,
		] );

		if ( is_wp_error( $result ) ) {
			error_log( sprintf( '[Spawn] Provisioning trigger failed: %s', $result->get_error_message() ) );
			Database::update_customer( $customer_id, [ 'status' => 'failed' ] );
		}
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
