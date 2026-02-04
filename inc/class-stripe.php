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
	 * Cancel a subscription.
	 *
	 * @param string $subscription_id Stripe subscription ID.
	 * @return array|WP_Error Cancellation result or error.
	 */
	public static function cancel_subscription( string $subscription_id ): array|WP_Error {
		return self::request( '/subscriptions/' . $subscription_id, 'DELETE' );
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
}
