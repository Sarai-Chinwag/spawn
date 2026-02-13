<?php
/**
 * Credits REST API Controller.
 *
 * @package Spawn
 */

namespace Spawn\Controllers;

use Spawn\Abilities\Ability_Get_Customer_Credits;
use Spawn\Abilities\Ability_Set_Auto_Refill;
use Spawn\Database;
use Spawn\Payment_Helpers;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Credits controller for REST API.
 */
class Credits_Controller {

	/**
	 * Register credits routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'spawn/v1',
			'/credits/balance',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_balance' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		register_rest_route(
			'spawn/v1',
			'/credits/purchase',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'purchase' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'amount' => array(
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 10,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'spawn/v1',
			'/credits/deduct',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'deduct' ),
				'permission_callback' => array( __CLASS__, 'verify_internal_request' ),
				'args'                => array(
					'customer_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
					'amount'      => array(
						'required' => true,
						'type'     => 'number',
					),
					'reason'      => array(
						'type'    => 'string',
						'default' => 'api_call',
					),
				),
			)
		);

		register_rest_route(
			'spawn/v1',
			'/credits/packages',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_packages' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'spawn/v1',
			'/account/auto-refill',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_auto_refill' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		register_rest_route(
			'spawn/v1',
			'/account/auto-refill',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'update_auto_refill' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'enabled'   => array(
						'required' => true,
						'type'     => 'boolean',
					),
					'threshold' => array(
						'type'    => 'number',
						'default' => 5.00,
						'minimum' => 1.00,
						'maximum' => 100.00,
					),
					'amount'    => array(
						'type'    => 'number',
						'default' => 10.00,
						'minimum' => 10.00,
						'maximum' => 100.00,
					),
				),
			)
		);
	}

	/**
	 * Get current customer's credit balance.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function get_balance(): WP_REST_Response|WP_Error {
		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer ) {
			return new WP_Error(
				'no_customer',
				__( 'No customer account found.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		$auto_refill = Database::get_auto_refill_settings( (int) $customer['id'] );

		return new WP_REST_Response( array(
			'balance'     => (float) $customer['credit_balance'],
			'auto_refill' => $auto_refill,
		) );
	}

	/**
	 * Purchase credits (create Stripe checkout session).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function purchase( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );
		$amount   = (int) $request->get_param( 'amount' );

		if ( ! $customer ) {
			return new WP_Error(
				'no_customer',
				__( 'No customer account found.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		if ( $amount < 10 ) {
			return new WP_Error(
				'amount_too_low',
				__( 'Minimum purchase is $10.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		$credits = $amount * 100;

		$session = Payment_Helpers::create_credit_checkout_session( array(
			'customer_id'       => $customer['stripe_customer'] ?? null,
			'customer_email'    => $customer['email'],
			'amount'            => $amount * 100,
			'credits'           => $credits,
			'spawn_customer_id' => $customer['id'],
		) );

		if ( is_wp_error( $session ) ) {
			return $session;
		}

		return new WP_REST_Response( array(
			'session_id'   => $session['id'],
			'checkout_url' => $session['url'],
		) );
	}

	/**
	 * Deduct credits from a customer (internal endpoint).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function deduct( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$customer_id = (int) $request->get_param( 'customer_id' );
		$amount      = (float) $request->get_param( 'amount' );
		$reason      = sanitize_text_field( $request->get_param( 'reason' ) );

		$customer = Database::get_customer( $customer_id );
		if ( ! $customer ) {
			return new WP_Error(
				'customer_not_found',
				__( 'Customer not found.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		$current_balance = (float) $customer['credit_balance'];

		if ( $current_balance < $amount ) {
			return new WP_Error(
				'insufficient_credits',
				__( 'Insufficient credits.', 'spawn' ),
				array(
					'status'   => 402,
					'balance'  => $current_balance,
					'required' => $amount,
				)
			);
		}

		$success = Database::deduct_credits( $customer_id, $amount );

		if ( ! $success ) {
			return new WP_Error(
				'deduction_failed',
				__( 'Failed to deduct credits.', 'spawn' ),
				array( 'status' => 500 )
			);
		}

		$new_balance = Database::get_credit_balance( $customer_id );

		$auto_refill      = Database::get_auto_refill_settings( $customer_id );
		$refill_triggered = false;
		if ( $auto_refill && $auto_refill['enabled'] && $new_balance < $auto_refill['threshold'] ) {
			do_action( 'spawn_credits_auto_refill_needed', $customer_id, $auto_refill );
			$refill_triggered = true;
		}

		return new WP_REST_Response( array(
			'success'          => true,
			'previous_balance' => $current_balance,
			'deducted'         => $amount,
			'new_balance'      => $new_balance,
			'reason'           => $reason,
			'refill_triggered' => $refill_triggered,
		) );
	}

	/**
	 * Get available credit packages.
	 *
	 * @return WP_REST_Response Response.
	 */
	public static function get_packages(): WP_REST_Response {
		return new WP_REST_Response( self::get_credit_packages_config() );
	}

	/**
	 * Get auto-refill settings.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function get_auto_refill(): WP_REST_Response|WP_Error {
		$result = Ability_Set_Auto_Refill::execute( array() );

		if ( is_wp_error( $result ) ) {
			return self::error_response( $result );
		}

		return new WP_REST_Response( array(
			'enabled'   => $result['enabled'],
			'threshold' => $result['threshold'],
			'amount'    => $result['amount'],
		) );
	}

	/**
	 * Update auto-refill settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function update_auto_refill( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = Ability_Set_Auto_Refill::execute( array(
			'enabled'   => (bool) $request->get_param( 'enabled' ),
			'threshold' => (float) $request->get_param( 'threshold' ),
			'amount'    => (float) $request->get_param( 'amount' ),
		) );

		if ( is_wp_error( $result ) ) {
			return self::error_response( $result );
		}

		return new WP_REST_Response( array(
			'success'  => true,
			'settings' => array(
				'enabled'   => $result['enabled'],
				'threshold' => $result['threshold'],
				'amount'    => $result['amount'],
			),
		) );
	}

	/**
	 * Verify internal request (for deduct endpoint).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if valid, error otherwise.
	 */
	public static function verify_internal_request( WP_REST_Request $request ): bool|WP_Error {
		$api_key      = $request->get_header( 'X-Spawn-Internal-Key' );
		$expected_key = get_option( 'spawn_internal_api_key', '' );

		if ( empty( $expected_key ) ) {
			return new WP_Error(
				'not_configured',
				__( 'Internal API key not configured.', 'spawn' ),
				array( 'status' => 500 )
			);
		}

		if ( ! hash_equals( $expected_key, $api_key ?? '' ) ) {
			return new WP_Error(
				'unauthorized',
				__( 'Invalid internal API key.', 'spawn' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Get credit packages configuration.
	 *
	 * @return array Credit packages.
	 */
	public static function get_credit_packages_config(): array {
		return array(
			'small'  => array(
				'name'        => __( 'Small', 'spawn' ),
				'credits'     => 1000,
				'price'       => 10,
				'description' => __( '1,000 credits for $10', 'spawn' ),
				'per_credit'  => 0.01,
			),
			'medium' => array(
				'name'        => __( 'Medium', 'spawn' ),
				'credits'     => 3000,
				'price'       => 25,
				'description' => __( '3,000 credits for $25 (17% bonus)', 'spawn' ),
				'per_credit'  => 0.0083,
				'bonus'       => '17%',
			),
			'large'  => array(
				'name'        => __( 'Large', 'spawn' ),
				'credits'     => 7500,
				'price'       => 50,
				'description' => __( '7,500 credits for $50 (50% bonus)', 'spawn' ),
				'per_credit'  => 0.0067,
				'bonus'       => '50%',
			),
		);
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
