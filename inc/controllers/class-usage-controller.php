<?php
/**
 * Usage REST API Controller.
 *
 * @package Spawn
 */

namespace Spawn\Controllers;

use Spawn\Database;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Usage controller for REST API.
 */
class Usage_Controller {

	/**
	 * Register usage routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'spawn/v1',
			'/usage',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_usage' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'months' => array(
						'type'              => 'integer',
						'default'           => 3,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'spawn/v1',
			'/provisioning/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'provisioning_status' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);
	}

	/**
	 * Get usage summary for current user.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public static function get_usage( WP_REST_Request $request ): WP_REST_Response {
		$months = (int) $request->get_param( 'months' );
		$months = $months > 0 ? $months : 3;
		$usage  = Database::get_user_usage( get_current_user_id(), $months );

		return new WP_REST_Response( array(
			'usage' => array_map( array( __CLASS__, 'format_usage_period' ), $usage ),
		) );
	}

	/**
	 * Get provisioning status for current customer.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function provisioning_status(): WP_REST_Response|WP_Error {
		$customer = Database::get_customer_by_user_id( get_current_user_id() );

		if ( ! $customer ) {
			return new WP_REST_Response( array(
				'status'  => 'not_found',
				'message' => __( 'No customer account found. Your account may still be processing.', 'spawn' ),
			) );
		}

		$status = $customer['status'] ?? 'pending';

		$progress = match ( $status ) {
			'pending'      => array(
				'percent' => 10,
				'step'    => 'payment',
				'message' => __( 'Payment confirmed, starting setup...', 'spawn' ),
			),
			'provisioning' => array(
				'percent' => 50,
				'step'    => 'provisioning',
				'message' => __( 'Setting up your server...', 'spawn' ),
			),
			'active'       => array(
				'percent' => 100,
				'step'    => 'complete',
				'message' => __( 'Your AI is ready!', 'spawn' ),
			),
			'failed'       => array(
				'percent' => 0,
				'step'    => 'failed',
				'message' => __( 'Setup failed. We\'ve been notified and will contact you.', 'spawn' ),
			),
			default        => array(
				'percent' => 0,
				'step'    => 'unknown',
				'message' => __( 'Checking status...', 'spawn' ),
			),
		};

		return new WP_REST_Response( array(
			'status'        => $status,
			'progress'      => $progress,
			'domain'        => $customer['domain'] ?? '',
			'server_ip'     => $customer['server_ip'] ?? '',
			'chat_url'      => home_url( '/spawn/chat/' ),
			'dashboard_url' => home_url( '/spawn/dashboard/' ),
		) );
	}

	/**
	 * Format usage period response for frontend.
	 *
	 * @param array $usage Usage data.
	 * @return array Formatted usage.
	 */
	private static function format_usage_period( array $usage ): array {
		return array(
			'period_start'   => $usage['period_start'] ?? null,
			'period_end'     => $usage['period_end'] ?? null,
			'credits_used'   => (float) ( $usage['credits_used'] ?? 0 ),
			'requests_count' => (int) ( $usage['requests_count'] ?? 0 ),
			'tokens_input'   => (int) ( $usage['tokens_input'] ?? 0 ),
			'tokens_output'  => (int) ( $usage['tokens_output'] ?? 0 ),
		);
	}
}
