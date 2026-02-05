<?php
/**
 * Renew Domain ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Database;
use Spawn\Name_Com;
use StripeIntegration\StripeClient;
use WP_Error;

/**
 * Initiates domain renewal checkout via Stripe.
 */
class Ability_Renew_Domain {

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		$customer = self::get_customer( $input );

		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		// Validate domain type.
		if ( 'register' !== ( $customer['domain_type'] ?? '' ) ) {
			return new WP_Error(
				'not_renewable',
				__( 'This domain type cannot be renewed through Spawn.', 'spawn' )
			);
		}

		$domain      = $customer['domain'];
		$customer_id = (int) $customer['id'];

		// Get renewal price from Name.com.
		$renewal_price = Name_Com::get_renewal_price( $domain );

		if ( is_wp_error( $renewal_price ) ) {
			return new WP_Error(
				'price_unavailable',
				sprintf(
					/* translators: %s: error message */
					__( 'Could not get renewal price: %s', 'spawn' ),
					$renewal_price->get_error_message()
				)
			);
		}

		// Apply markup.
		$markup          = (float) get_option( 'spawn_domain_markup', 1.5 );
		$marked_up_price = round( $renewal_price * $markup, 2 );
		$amount_cents    = (int) round( $marked_up_price * 100 );

		// Create Stripe checkout session for domain renewal.
		$session = StripeClient::create_checkout_session( [
			'customer'       => $customer['stripe_customer'] ?? null,
			'customer_email' => $customer['email'],
			'metadata'       => [
				'type'              => 'domain_renewal',
				'domain'            => $domain,
				'spawn_customer_id' => $customer_id,
				'source'            => 'spawn',
			],
			'line_items'     => [
				[
					'price_data' => [
						'currency'     => 'usd',
						'unit_amount'  => $amount_cents,
						'product_data' => [
							'name'        => sprintf(
								/* translators: %s: domain name */
								__( 'Domain Renewal: %s', 'spawn' ),
								$domain
							),
							'description' => __( 'One-year domain renewal', 'spawn' ),
						],
					],
					'quantity'   => 1,
				],
			],
			'mode'           => 'payment',
			'success_url'    => add_query_arg(
				[
					'renewed' => '1',
					'domain'  => $domain,
				],
				home_url( '/spawn/dashboard/' )
			),
			'cancel_url'     => home_url( '/spawn/dashboard/' ),
		] );

		if ( is_wp_error( $session ) ) {
			return $session;
		}

		return [
			'success'       => true,
			'checkout_url'  => $session['url'],
			'session_id'    => $session['id'],
			'domain'        => $domain,
			'renewal_price' => $marked_up_price,
		];
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
