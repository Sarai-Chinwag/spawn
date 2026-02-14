<?php
/**
 * BYOK (Bring Your Own Key) REST API Controller.
 *
 * Manages billing mode toggling for BYOK customers. The actual API key
 * is managed entirely on the customer's VPS — our server never sees,
 * stores, or transmits customer API keys.
 *
 * Flow:
 * 1. Customer tells their own AI agent to configure their API key locally
 * 2. Customer's agent updates OpenClaw config on their VPS (loopback)
 * 3. Customer's agent calls our billing-mode endpoint to switch to BYOK
 * 4. We stop billing them through LiteLLM — they pay their provider directly
 *
 * @package Spawn
 */

namespace Spawn\Controllers;

use Spawn\Database;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * BYOK controller for REST API.
 */
class BYOK_Controller {

	/**
	 * Register BYOK routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'spawn/v1',
			'/customer/billing-mode',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_billing_mode' ),
					'permission_callback' => 'is_user_logged_in',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'set_billing_mode' ),
					'permission_callback' => 'is_user_logged_in',
					'args'                => array(
						'mode' => array(
							'required'          => true,
							'type'              => 'string',
							'enum'              => array( 'managed', 'byok' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			'spawn/v1',
			'/customer/byok-instructions',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_instructions' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);
	}

	/**
	 * Get current billing mode.
	 *
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public static function get_billing_mode(): WP_REST_Response|WP_Error {
		$customer = self::get_current_customer();

		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		return new WP_REST_Response( array(
			'billing_mode' => $customer['billing_mode'] ?? 'managed',
		) );
	}

	/**
	 * Toggle billing mode between managed and BYOK.
	 *
	 * This only updates the billing mode flag. The actual API key
	 * configuration happens on the customer's VPS — we never touch it.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public static function set_billing_mode( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$customer = self::get_current_customer();

		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		$mode = $request->get_param( 'mode' );

		$success = Database::update_customer( (int) $customer['id'], array(
			'billing_mode' => $mode,
		) );

		if ( ! $success ) {
			return new WP_Error(
				'update_failed',
				__( 'Failed to update billing mode.', 'spawn' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( array(
			'success'      => true,
			'billing_mode' => $mode,
			'message'      => 'byok' === $mode
				? __( 'Switched to Bring Your Own Key mode. Make sure your AI agent has your API key configured locally.', 'spawn' )
				: __( 'Switched to managed billing. Usage will be deducted from your Spawn credit balance.', 'spawn' ),
		) );
	}

	/**
	 * Get BYOK setup instructions for the customer.
	 *
	 * Returns instructions the customer can give to their AI agent
	 * to configure their own API key locally on their VPS.
	 *
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public static function get_instructions(): WP_REST_Response|WP_Error {
		$customer = self::get_current_customer();

		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		$dashboard_url = home_url( '/spawn/dashboard/' );

		return new WP_REST_Response( array(
			'instructions' => array(
				'title' => __( 'Set up your own API key', 'spawn' ),
				'steps' => array(
					__( 'Get an API key from your AI provider (e.g. console.anthropic.com)', 'spawn' ),
					__( 'Tell your AI agent: "Configure my Anthropic API key: sk-ant-..."', 'spawn' ),
					__( 'Your agent will update the config locally on your server', 'spawn' ),
					sprintf(
						/* translators: %s: dashboard URL */
						__( 'Switch to BYOK billing mode in your dashboard at %s', 'spawn' ),
						$dashboard_url
					),
				),
				'note'  => __( 'Your API key never leaves your server. We only track your billing mode — not your key.', 'spawn' ),
			),
		) );
	}

	/**
	 * Get the current logged-in user's customer record.
	 *
	 * @return array|WP_Error Customer data or error.
	 */
	private static function get_current_customer(): array|WP_Error {
		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer ) {
			return new WP_Error(
				'no_customer',
				__( 'No Spawn subscription found for your account.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		return $customer;
	}
}
