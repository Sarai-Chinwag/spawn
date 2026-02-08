<?php
/**
 * Register Domain ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Database;
use Spawn\Name_Com;
use Stripe_Integration\StripeClient;
use WP_Error;

/**
 * Initiates domain registration via Stripe checkout.
 */
class Ability_Register_Domain {

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		$domain    = $input['domain'] ?? '';
		$server_id = $input['server_id'] ?? null;

		if ( empty( $domain ) ) {
			return new WP_Error( 'missing_domain', __( 'Domain name is required', 'spawn' ) );
		}

		// Get customer.
		$customer = self::get_customer( $input );
		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		// Clean up domain.
		$domain = strtolower( trim( $domain ) );
		$domain = preg_replace( '/^(https?:\/\/)?(www\.)?/', '', $domain );
		$domain = rtrim( $domain, '/' );

		// Verify domain is available.
		$availability = Name_Com::check_availability( $domain );
		if ( is_wp_error( $availability ) ) {
			return $availability;
		}

		if ( ! $availability['available'] ) {
			return new WP_Error(
				'domain_unavailable',
				sprintf( __( '%s is not available for registration', 'spawn' ), $domain )
			);
		}

		// Get price with markup.
		$base_price = $availability['price'] ?? 12.99;
		$markup     = (float) get_option( 'spawn_domain_markup', 1.5 );
		$price      = round( $base_price * $markup, 2 );

		// Create Stripe checkout session for domain purchase.
		$checkout_args = array(
			'mode'        => 'payment',
			'line_items'  => array(
				array(
					'price_data' => array(
						'currency'     => 'usd',
						'unit_amount'  => (int) ( $price * 100 ),
						'product_data' => array(
							'name'        => sprintf( __( 'Domain Registration: %s', 'spawn' ), $domain ),
							'description' => __( '1 year registration', 'spawn' ),
						),
					),
					'quantity'   => 1,
				),
			),
			'metadata'    => array(
				'type'        => 'domain_registration',
				'domain'      => $domain,
				'customer_id' => $customer['id'],
				'server_id'   => $server_id,
				'base_price'  => $base_price,
			),
			'success_url' => home_url( '/spawn/dashboard/?tab=domains&domain_registered=' . urlencode( $domain ) ),
			'cancel_url'  => home_url( '/spawn/dashboard/?tab=domains' ),
		);

		// Add customer if they have a Stripe ID.
		if ( ! empty( $customer['stripe_customer'] ) ) {
			$checkout_args['customer'] = $customer['stripe_customer'];
		} else {
			$checkout_args['customer_email'] = $customer['email'];
		}

		$session = StripeClient::create_checkout_session( $checkout_args );

		if ( is_wp_error( $session ) ) {
			return $session;
		}

		return array(
			'success'      => true,
			'domain'       => $domain,
			'price'        => $price,
			'checkout_url' => $session['url'],
			'message'      => sprintf(
				/* translators: 1: domain, 2: price */
				__( 'Ready to register %1$s for $%2$s/year. Complete checkout to confirm.', 'spawn' ),
				$domain,
				$price
			),
			'instructions' => __( 'Provide the checkout URL to your user to complete the purchase.', 'spawn' ),
		);
	}

	/**
	 * Get customer from input or current user.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Customer data or error.
	 */
	private static function get_customer( array $input ): array|WP_Error {
		if ( ! empty( $input['customer_id'] ) ) {
			$customer = Database::get_customer( (int) $input['customer_id'] );
		} else {
			$user = wp_get_current_user();
			if ( ! $user->ID ) {
				return new WP_Error( 'not_logged_in', __( 'You must be logged in', 'spawn' ) );
			}
			$customer = Database::get_customer_by_user_id( $user->ID );
		}

		if ( ! $customer ) {
			return new WP_Error( 'not_found', __( 'Customer not found', 'spawn' ) );
		}

		return $customer;
	}
}
