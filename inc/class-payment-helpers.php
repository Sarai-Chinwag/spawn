<?php
/**
 * Payment helper functions for Spawn.
 *
 * Uses the shared stripe-integration plugin for Stripe API calls.
 *
 * @package Spawn
 */

declare(strict_types=1);

namespace Spawn;

use StripeIntegration\StripeClient;
use WP_Error;

/**
 * Spawn-specific payment helpers.
 */
class Payment_Helpers {

	/**
	 * Create checkout session for credit purchase.
	 *
	 * @param array $params Parameters including customer_email, amount, credits, spawn_customer_id.
	 * @return array|WP_Error Session data or error.
	 */
	public static function create_credit_checkout_session( array $params ): array|WP_Error {
		$amount_dollars = (int) $params['amount'] / 100;

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
								/* translators: %d: dollar amount */
								__( '$%d credit purchase', 'spawn' ),
								$amount_dollars
							),
						],
					],
					'quantity' => 1,
				],
			],
			'metadata'     => [
				'type'              => 'credit_purchase',
				'credits'           => $params['credits'],
				'spawn_customer_id' => $params['spawn_customer_id'],
				'source'            => 'spawn',
			],
		];

		// Use existing Stripe customer if available.
		if ( ! empty( $params['customer_id'] ) ) {
			$session_params['customer'] = $params['customer_id'];
		} else {
			$session_params['customer_email'] = $params['customer_email'];
		}

		return StripeClient::create_checkout_session( $session_params );
	}

	/**
	 * Handle credit purchase from webhook.
	 *
	 * @param array $session Checkout session data.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function handle_credit_purchase( array $session ): bool|WP_Error {
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
		$success = Database::add_credits( $spawn_customer_id, $credits );

		if ( ! $success ) {
			return new WP_Error(
				'credit_add_failed',
				__( 'Failed to add credits to customer.', 'spawn' )
			);
		}

		do_action( 'spawn_credits_purchased', $spawn_customer_id, $credits, $session );

		return true;
	}

	/**
	 * Get default payment method for a customer.
	 *
	 * @param string $customer_id Stripe customer ID.
	 * @return string|WP_Error Payment method ID or error.
	 */
	public static function get_default_payment_method( string $customer_id ): string|WP_Error {
		$customer = StripeClient::retrieve_customer( $customer_id );

		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		$pm = $customer['invoice_settings']['default_payment_method'] ?? null;
		if ( ! $pm ) {
			return new WP_Error(
				'no_payment_method',
				__( 'No default payment method found.', 'spawn' ),
				[ 'status' => 400 ]
			);
		}

		return $pm;
	}

	/**
	 * Charge a saved payment method for auto-refill.
	 *
	 * @param string $customer_id       Stripe customer ID.
	 * @param string $payment_method_id Stripe payment method ID.
	 * @param int    $amount_cents      Amount in cents.
	 * @param int    $credits           Number of credits being purchased.
	 * @param int    $spawn_customer_id Spawn customer ID.
	 * @return array|WP_Error PaymentIntent or error.
	 */
	public static function charge_for_auto_refill(
		string $customer_id,
		string $payment_method_id,
		int $amount_cents,
		int $credits,
		int $spawn_customer_id
	): array|WP_Error {
		return StripeClient::create_payment_intent( [
			'amount'         => $amount_cents,
			'currency'       => 'usd',
			'customer'       => $customer_id,
			'payment_method' => $payment_method_id,
			'off_session'    => true,
			'confirm'        => true,
			'description'    => sprintf(
				/* translators: %d: number of credits */
				__( 'Auto-refill: %d Spawn Credits', 'spawn' ),
				$credits
			),
			'metadata'       => [
				'type'              => 'auto_refill',
				'credits'           => $credits,
				'spawn_customer_id' => $spawn_customer_id,
				'source'            => 'spawn',
			],
		] );
	}

	/**
	 * Process auto-refill for a customer.
	 *
	 * @param int   $spawn_customer_id Spawn customer ID.
	 * @param array $settings          Auto-refill settings (amount in credits).
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function process_auto_refill( int $spawn_customer_id, array $settings ): bool|WP_Error {
		$customer = Database::get_customer( $spawn_customer_id );
		if ( ! $customer ) {
			return new WP_Error( 'customer_not_found', 'Customer not found.' );
		}

		$stripe_customer_id = $customer['stripe_customer'] ?? null;
		$payment_method_id  = $customer['stripe_payment_method'] ?? null;

		if ( ! $stripe_customer_id ) {
			return new WP_Error( 'no_stripe_customer', 'No Stripe customer ID.' );
		}

		// Get payment method from customer if not stored locally.
		if ( ! $payment_method_id ) {
			$payment_method_id = self::get_default_payment_method( $stripe_customer_id );
			if ( is_wp_error( $payment_method_id ) ) {
				return $payment_method_id;
			}
			// Save it for next time.
			Database::update_payment_method( $spawn_customer_id, $payment_method_id );
		}

		$credits      = (int) $settings['amount'];
		$amount_cents = $credits; // 1 credit = $0.01 = 1 cent.

		$result = self::charge_for_auto_refill(
			$stripe_customer_id,
			$payment_method_id,
			$amount_cents,
			$credits,
			$spawn_customer_id
		);

		if ( is_wp_error( $result ) ) {
			do_action( 'spawn_auto_refill_failed', $spawn_customer_id, $result );
			return $result;
		}

		// Add credits to customer.
		$success = Database::add_credits( $spawn_customer_id, $credits );
		if ( ! $success ) {
			return new WP_Error( 'credit_add_failed', 'Failed to add credits after charge.' );
		}

		do_action( 'spawn_auto_refill_success', $spawn_customer_id, $credits, $result );
		return true;
	}

	/**
	 * Update subscription to a new price with proration.
	 *
	 * @param string $subscription_id Stripe subscription ID.
	 * @param string $new_price_id    New Stripe price ID.
	 * @return array|WP_Error Updated subscription or error.
	 */
	public static function update_subscription_price( string $subscription_id, string $new_price_id ): array|WP_Error {
		// First get the current subscription to find the item ID.
		$subscription = StripeClient::retrieve_subscription( $subscription_id );

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
		return StripeClient::update_subscription( $subscription_id, [
			'items' => [
				[
					'id'    => $item_id,
					'price' => $new_price_id,
				],
			],
			'proration_behavior' => 'create_prorations',
		] );
	}
}
