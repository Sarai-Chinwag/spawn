<?php
/**
 * Customer REST API Controller.
 *
 * @package Spawn
 */

namespace Spawn\Controllers;

use Spawn\Abilities\Ability_Cancel;
use Spawn\Abilities\Ability_Get_Status;
use Spawn\Abilities\Ability_Manage_Billing;
use Spawn\Abilities\Ability_Scale_VPS;
use Spawn\Database;
use Spawn\Payment_Helpers;
use StripeIntegration\StripeClient;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Customer controller for REST API.
 */
class Customer_Controller {

	/**
	 * Register customer routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'spawn/v1',
			'/customer/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_current' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		register_rest_route(
			'spawn/v1',
			'/customer/billing-portal',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'billing_portal' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		register_rest_route(
			'spawn/v1',
			'/customer/upgrade',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'upgrade' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'tier' => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'starter', 'pro', 'business' ),
					),
				),
			)
		);

		register_rest_route(
			'spawn/v1',
			'/customer/toggle-website',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'toggle_website' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'wants_website' => array(
						'required' => true,
						'type'     => 'boolean',
					),
				),
			)
		);

		register_rest_route(
			'spawn/v1',
			'/customer/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'cancel' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		register_rest_route(
			'spawn/v1',
			'/customer/invoices',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'invoices' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);
	}

	/**
	 * Get current customer data.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function get_current(): WP_REST_Response|WP_Error {
		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer ) {
			return new WP_REST_Response( array(
				'success'  => false,
				'customer' => null,
			) );
		}

		return new WP_REST_Response( array(
			'success'  => true,
			'customer' => array(
				'id'             => (int) $customer['id'],
				'domain'         => $customer['domain'],
				'subdomain'      => (bool) $customer['subdomain'],
				'tier'           => $customer['tier'] ?? 'starter',
				'wants_website'  => (bool) ( $customer['wants_website'] ?? true ),
				'server_type'    => $customer['server_type'] ?? 'cpx21',
				'credit_balance' => (float) $customer['credit_balance'],
				'server_ip'      => $customer['server_ip'],
				'status'         => $customer['status'],
				'created_at'     => $customer['created_at'],
			),
		) );
	}

	/**
	 * Get Stripe billing portal URL.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function billing_portal(): WP_REST_Response|WP_Error {
		$result = Ability_Manage_Billing::execute( array() );

		if ( is_wp_error( $result ) ) {
			return self::error_response( $result );
		}

		return new WP_REST_Response( array(
			'url' => $result['portal_url'],
		) );
	}

	/**
	 * Upgrade/change subscription plan.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function upgrade( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );
		$new_tier = $request->get_param( 'tier' );

		if ( ! $customer ) {
			return new WP_Error(
				'no_subscription',
				__( 'No active subscription found.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		// Get tier config from single source of truth.
		$tier_config = \Spawn\Config::get_tier( $new_tier );
		if ( ! $tier_config ) {
			return new WP_Error(
				'invalid_tier',
				__( 'Invalid tier selected.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		$new_price_id = $tier_config['stripe_price_id'] ?? '';

		// Update Stripe subscription if we have a subscription ID and price ID.
		if ( ! empty( $customer['stripe_subscription'] ) && ! empty( $new_price_id ) ) {
			$result = Payment_Helpers::update_subscription_price(
				$customer['stripe_subscription'],
				$new_price_id
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Pro-rate credits on upgrade.
		$old_tier       = $customer['tier'] ?? 'starter';
		$old_credits    = \Spawn\Config::get_included_credits( $old_tier );
		$new_credits    = \Spawn\Config::get_included_credits( $new_tier );
		$credits_to_add = 0;

		if ( $new_credits > $old_credits ) {
			$credits_to_add = $new_credits - $old_credits;
			Database::add_credits( (int) $customer['id'], $credits_to_add );

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf(
				'[Spawn] Pro-rated credits for customer #%d: %s → %s, added $%.2f',
				$customer['id'],
				$old_tier,
				$new_tier,
				$credits_to_add
			) );
		}

		// Update database.
		Database::update_tier( (int) $customer['id'], $new_tier );

		return new WP_REST_Response( array(
			'success'       => true,
			'tier'          => $new_tier,
			'credits_added' => $credits_to_add,
			'new_balance'   => Database::get_credit_balance( (int) $customer['id'] ),
		) );
	}

	/**
	 * Toggle website preference for customer.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function toggle_website( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id       = get_current_user_id();
		$customer      = Database::get_customer_by_user_id( $user_id );
		$wants_website = (bool) $request->get_param( 'wants_website' );

		if ( ! $customer ) {
			return new WP_Error(
				'no_subscription',
				__( 'No active subscription found.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		Database::update_wants_website( (int) $customer['id'], $wants_website );

		return new WP_REST_Response( array(
			'success'       => true,
			'wants_website' => $wants_website,
			'note'          => 'active' === $customer['status']
				? __( 'Preference updated. Server type cannot be changed after provisioning.', 'spawn' )
				: __( 'Preference updated.', 'spawn' ),
		) );
	}

	/**
	 * Cancel subscription.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function cancel(): WP_REST_Response|WP_Error {
		$result = Ability_Cancel::execute( array( 'confirm' => true ) );

		if ( is_wp_error( $result ) ) {
			return self::error_response( $result );
		}

		return new WP_REST_Response( array(
			'success' => true,
		) );
	}

	/**
	 * Get customer invoices from Stripe.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function invoices(): WP_REST_Response|WP_Error {
		if ( ! class_exists( '\\StripeIntegration\\StripeClient' ) ) {
			return new WP_Error(
				'stripe_not_available',
				__( 'Invoice history is not available on this site.', 'spawn' ),
				array( 'status' => 503 )
			);
		}

		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer || empty( $customer['stripe_customer'] ) ) {
			return new WP_Error(
				'no_subscription',
				__( 'No active subscription found.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		$invoices = StripeClient::get_invoices( $customer['stripe_customer'] );

		if ( is_wp_error( $invoices ) ) {
			return $invoices;
		}

		return new WP_REST_Response( array(
			'invoices' => $invoices,
		) );
	}

	/**
	 * Convert WP_Error to REST response with proper status code.
	 *
	 * @param WP_Error $error Error object.
	 * @return WP_REST_Response Response.
	 */
	private static function error_response( WP_Error $error ): WP_REST_Response {
		$data   = $error->get_error_data();
		$status = is_array( $data ) ? ( $data['status'] ?? 400 ) : 400;

		return new WP_REST_Response(
			array(
				'success' => false,
				'code'    => $error->get_error_code(),
				'message' => $error->get_error_message(),
			),
			$status
		);
	}
}
