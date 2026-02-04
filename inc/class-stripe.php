<?php
/**
 * Stripe API integration.
 *
 * @package Spawn
 */

namespace Spawn;

use WP_Error;

/**
 * Handles Stripe API operations.
 */
class Stripe {

	/**
	 * Stripe API base URL.
	 */
	private const API_BASE = 'https://api.stripe.com/v1';

	/**
	 * Get secret key.
	 *
	 * @return string Secret key.
	 */
	private static function get_secret_key(): string {
		// First check for shared key from sell-my-images.
		$key = get_option( 'spawn_stripe_secret_key', '' );
		if ( empty( $key ) ) {
			$key = get_option( 'smi_stripe_secret_key', '' );
		}
		return $key;
	}

	/**
	 * Get webhook secret.
	 *
	 * @return string Webhook secret.
	 */
	private static function get_webhook_secret(): string {
		return get_option( 'spawn_stripe_webhook_secret', '' );
	}

	/**
	 * Make API request.
	 *
	 * @param string $endpoint API endpoint.
	 * @param string $method   HTTP method.
	 * @param array  $data     Request data.
	 * @return array|WP_Error Response or error.
	 */
	private static function request( string $endpoint, string $method = 'GET', array $data = [] ): array|WP_Error {
		$secret_key = self::get_secret_key();

		if ( empty( $secret_key ) ) {
			return new WP_Error(
				'stripe_not_configured',
				__( 'Stripe is not configured', 'spawn' ),
				[ 'status' => 500 ]
			);
		}

		$args = [
			'method'  => $method,
			'headers' => [
				'Authorization' => 'Bearer ' . $secret_key,
				'Content-Type'  => 'application/x-www-form-urlencoded',
			],
			'timeout' => 30,
		];

		if ( ! empty( $data ) && in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) ) {
			$args['body'] = http_build_query( self::flatten_array( $data ) );
		}

		$response = wp_remote_request( self::API_BASE . $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 ) {
			$message = $body['error']['message'] ?? __( 'Stripe API error', 'spawn' );
			return new WP_Error(
				'stripe_api_error',
				$message,
				[ 'status' => $code ]
			);
		}

		return $body;
	}

	/**
	 * Flatten nested array for Stripe API.
	 *
	 * @param array  $array  Array to flatten.
	 * @param string $prefix Key prefix.
	 * @return array Flattened array.
	 */
	private static function flatten_array( array $array, string $prefix = '' ): array {
		$result = [];

		foreach ( $array as $key => $value ) {
			$new_key = $prefix ? "{$prefix}[{$key}]" : $key;

			if ( is_array( $value ) ) {
				$result = array_merge( $result, self::flatten_array( $value, $new_key ) );
			} else {
				$result[ $new_key ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Create checkout session.
	 *
	 * @param array $params Session parameters.
	 * @return array|WP_Error Session data or error.
	 */
	public static function create_checkout_session( array $params ): array|WP_Error {
		return self::request( '/checkout/sessions', 'POST', $params );
	}

	/**
	 * Create checkout session for credit purchase (one-time payment).
	 *
	 * @param array $params Parameters including customer_email, amount, credits, package, spawn_customer_id.
	 * @return array|WP_Error Session data or error.
	 */
	public static function create_credit_checkout_session( array $params ): array|WP_Error {
		$session_params = [
			'mode'         => 'payment',
			'success_url'  => home_url( '/spawn/dashboard/?credits_purchased=1&session_id={CHECKOUT_SESSION_ID}' ),
			'cancel_url'   => home_url( '/spawn/dashboard/' ),
			'line_items'   => [
				[
					'price_data' => [
						'currency'     => 'usd',
						'unit_amount'  => (int) $params['amount'],
						'product_data' => [
							'name'        => sprintf(
								/* translators: %d: number of credits */
								__( '%d Spawn Credits', 'spawn' ),
								$params['credits']
							),
							'description' => sprintf(
								/* translators: %s: package name */
								__( '%s credit package', 'spawn' ),
								ucfirst( $params['package'] )
							),
						],
					],
					'quantity' => 1,
				],
			],
			'metadata'     => [
				'type'              => 'credit_purchase',
				'credits'           => $params['credits'],
				'package'           => $params['package'],
				'spawn_customer_id' => $params['spawn_customer_id'],
			],
		];

		// Use existing Stripe customer if available.
		if ( ! empty( $params['customer_id'] ) ) {
			$session_params['customer'] = $params['customer_id'];
		} else {
			$session_params['customer_email'] = $params['customer_email'];
		}

		return self::request( '/checkout/sessions', 'POST', $session_params );
	}

	/**
	 * Handle credit purchase webhook event.
	 *
	 * @param array $event Stripe event data.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function handle_credit_purchase( array $event ): bool|WP_Error {
		$session = $event['data']['object'] ?? [];

		// Verify this is a credit purchase.
		$metadata = $session['metadata'] ?? [];
		if ( ( $metadata['type'] ?? '' ) !== 'credit_purchase' ) {
			return true; // Not a credit purchase, skip.
		}

		$spawn_customer_id = (int) ( $metadata['spawn_customer_id'] ?? 0 );
		$credits           = (int) ( $metadata['credits'] ?? 0 );

		if ( ! $spawn_customer_id || ! $credits ) {
			return new WP_Error(
				'invalid_metadata',
				__( 'Invalid credit purchase metadata.', 'spawn' )
			);
		}

		// Add credits to customer.
		$success = \Spawn\Database::add_credits( $spawn_customer_id, $credits );

		if ( ! $success ) {
			return new WP_Error(
				'credit_add_failed',
				__( 'Failed to add credits to customer.', 'spawn' )
			);
		}

		// Log the purchase.
		do_action( 'spawn_credits_purchased', $spawn_customer_id, $credits, $session );

		return true;
	}

	/**
	 * Verify webhook signature.
	 *
	 * @param string $payload   Raw webhook payload.
	 * @param string $sig_header Stripe signature header.
	 * @return array|WP_Error Event data or error.
	 */
	public static function verify_webhook( string $payload, string $sig_header ): array|WP_Error {
		$webhook_secret = self::get_webhook_secret();

		if ( empty( $webhook_secret ) ) {
			return new WP_Error(
				'webhook_not_configured',
				__( 'Webhook secret not configured', 'spawn' ),
				[ 'status' => 500 ]
			);
		}

		// Parse signature header.
		$sig_parts = [];
		foreach ( explode( ',', $sig_header ) as $part ) {
			$pair = explode( '=', $part, 2 );
			if ( count( $pair ) === 2 ) {
				$sig_parts[ $pair[0] ] = $pair[1];
			}
		}

		$timestamp = $sig_parts['t'] ?? '';
		$signature = $sig_parts['v1'] ?? '';

		if ( empty( $timestamp ) || empty( $signature ) ) {
			return new WP_Error(
				'invalid_signature_header',
				__( 'Invalid signature header', 'spawn' ),
				[ 'status' => 400 ]
			);
		}

		// Check timestamp (reject if older than 5 minutes).
		if ( abs( time() - (int) $timestamp ) > 300 ) {
			return new WP_Error(
				'webhook_timestamp_expired',
				__( 'Webhook timestamp expired', 'spawn' ),
				[ 'status' => 400 ]
			);
		}

		// Verify signature.
		$signed_payload  = $timestamp . '.' . $payload;
		$expected_sig    = hash_hmac( 'sha256', $signed_payload, $webhook_secret );

		if ( ! hash_equals( $expected_sig, $signature ) ) {
			return new WP_Error(
				'invalid_signature',
				__( 'Invalid webhook signature', 'spawn' ),
				[ 'status' => 400 ]
			);
		}

		return json_decode( $payload, true );
	}

	/**
	 * Get customer portal URL.
	 *
	 * @param string $customer_id Stripe customer ID.
	 * @param string $return_url  URL to return to after portal.
	 * @return string|WP_Error Portal URL or error.
	 */
	public static function create_portal_session( string $customer_id, string $return_url ): string|WP_Error {
		$result = self::request( '/billing_portal/sessions', 'POST', [
			'customer'   => $customer_id,
			'return_url' => $return_url,
		] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result['url'] ?? '';
	}

	/**
	 * Create billing portal session (alias for REST API).
	 *
	 * @param string $customer_id Stripe customer ID.
	 * @param string $return_url  URL to return to after portal.
	 * @return array|WP_Error Portal data or error.
	 */
	public static function create_billing_portal_session( string $customer_id, string $return_url ): array|WP_Error {
		$result = self::request( '/billing_portal/sessions', 'POST', [
			'customer'   => $customer_id,
			'return_url' => $return_url,
		] );

		return $result;
	}

	/**
	 * Cancel a subscription.
	 *
	 * @param string $subscription_id Stripe subscription ID.
	 * @return array|WP_Error Cancellation result or error.
	 */
	public static function cancel_subscription( string $subscription_id ): array|WP_Error {
		// Cancel at period end rather than immediately.
		return self::request( '/subscriptions/' . $subscription_id, 'POST', [
			'cancel_at_period_end' => true,
		] );
	}

	/**
	 * Update subscription line items.
	 *
	 * @param string $subscription_id Stripe subscription ID.
	 * @param array  $items           New line items.
	 * @return array|WP_Error Updated subscription or error.
	 */
	public static function update_subscription( string $subscription_id, array $items ): array|WP_Error {
		return self::request( '/subscriptions/' . $subscription_id, 'POST', [
			'items' => $items,
		] );
	}

	/**
	 * Update subscription to a new price.
	 *
	 * @param string $subscription_id Stripe subscription ID.
	 * @param string $new_price_id    New Stripe price ID.
	 * @return array|WP_Error Updated subscription or error.
	 */
	public static function update_subscription_price( string $subscription_id, string $new_price_id ): array|WP_Error {
		// First get the current subscription to find the item ID.
		$subscription = self::request( '/subscriptions/' . $subscription_id, 'GET' );

		if ( is_wp_error( $subscription ) ) {
			return $subscription;
		}

		$item_id = $subscription['items']['data'][0]['id'] ?? '';

		if ( empty( $item_id ) ) {
			return new WP_Error(
				'no_subscription_item',
				__( 'No subscription item found', 'spawn' ),
				[ 'status' => 400 ]
			);
		}

		// Update the subscription item with the new price.
		return self::request( '/subscriptions/' . $subscription_id, 'POST', [
			'items' => [
				[
					'id'    => $item_id,
					'price' => $new_price_id,
				],
			],
			'proration_behavior' => 'create_prorations',
		] );
	}

	/**
	 * Get invoices for a customer.
	 *
	 * @param string $customer_id Stripe customer ID.
	 * @param int    $limit       Number of invoices to return.
	 * @return array|WP_Error Invoices or error.
	 */
	public static function get_invoices( string $customer_id, int $limit = 10 ): array|WP_Error {
		$result = self::request( '/invoices?customer=' . urlencode( $customer_id ) . '&limit=' . $limit, 'GET' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result['data'] ?? [];
	}
}
