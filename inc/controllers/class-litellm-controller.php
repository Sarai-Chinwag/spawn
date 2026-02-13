<?php
/**
 * LiteLLM REST API Controller.
 *
 * Handles LiteLLM billing callbacks and agent endpoints.
 *
 * @package Spawn
 */

namespace Spawn\Controllers;

use Spawn\Config;
use Spawn\Database;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * LiteLLM controller for REST API.
 */
class LiteLLM_Controller {

	/**
	 * Anthropic model pricing per MTok (pass-through, no markup).
	 */
	private const ANTHROPIC_PRICING = array(
		'claude-opus-4-20250514'           => array(
			'input'  => 5.0,
			'output' => 25.0,
		),
		'claude-opus-4.5'                  => array(
			'input'  => 5.0,
			'output' => 25.0,
		),
		'anthropic/claude-opus-4-20250514' => array(
			'input'  => 5.0,
			'output' => 25.0,
		),
		'default'                          => array(
			'input'  => 5.0,
			'output' => 25.0,
		),
	);

	/**
	 * Register LiteLLM routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'spawn/v1',
			'/litellm/callback',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'callback' ),
				'permission_callback' => array( __CLASS__, 'verify_callback' ),
			)
		);

		register_rest_route(
			'spawn/v1',
			'/balance/check',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'balance_check' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'ip' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'spawn/v1',
			'/agent/billing-mode',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'set_billing_mode' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'ip'           => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'billing_mode' => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'managed', 'byok' ),
					),
				),
			)
		);

		register_rest_route(
			'spawn/v1',
			'/agent/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'agent_status' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'ip' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Verify LiteLLM callback request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if valid, error otherwise.
	 */
	public static function verify_callback( WP_REST_Request $request ): bool|WP_Error {
		$auth_header = $request->get_header( 'Authorization' );
		$expected    = get_option( 'spawn_litellm_callback_secret', '' );

		if ( empty( $expected ) ) {
			return true;
		}

		$token = str_replace( 'Bearer ', '', $auth_header ?? '' );
		if ( $token !== $expected ) {
			return new WP_Error( 'unauthorized', __( 'Invalid callback secret.', 'spawn' ), array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Handle LiteLLM usage callback.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public static function callback( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();

		$model             = $body['model'] ?? '';
		$prompt_tokens     = (int) ( $body['prompt_tokens'] ?? 0 );
		$completion_tokens = (int) ( $body['completion_tokens'] ?? 0 );
		$response_cost     = (float) ( $body['response_cost'] ?? 0.0 );
		$metadata          = $body['metadata'] ?? array();
		$spawn_customer_id = (int) ( $metadata['spawn_customer_id'] ?? 0 );

		if ( ! $spawn_customer_id && ! empty( $body['user'] ) ) {
			$spawn_customer_id = (int) $body['user'];
		}

		if ( ! $spawn_customer_id ) {
			$api_key = $metadata['user_api_key_alias'] ?? '';
			if ( preg_match( '/^spawn-customer-(\d+)$/', $api_key, $matches ) ) {
				$spawn_customer_id = (int) $matches[1];
			}
		}

		if ( ! $spawn_customer_id ) {
			$client_ip = $body['requester_ip_address'] ?? $metadata['requester_ip_address'] ?? '';
			if ( $client_ip ) {
				$customer = Database::get_customer_by_server_ip( $client_ip );
				if ( $customer ) {
					$spawn_customer_id = (int) $customer['id'];
				}
			}
		}

		if ( ! $spawn_customer_id ) {
			return new WP_REST_Response( array(
				'status'  => 'skipped',
				'message' => 'No customer ID in metadata or by IP.',
			) );
		}

		// Skip billing for BYOK customers — they pay Anthropic directly.
		$byok_customer = Database::get_customer( $spawn_customer_id );
		if ( $byok_customer && 'byok' === ( $byok_customer['billing_mode'] ?? 'managed' ) ) {
			return new WP_REST_Response( array(
				'status'  => 'skipped',
				'message' => 'Customer is BYOK — no credits deducted.',
			) );
		}

		if ( 0 === $prompt_tokens && 0 === $completion_tokens ) {
			return new WP_REST_Response( array(
				'status'  => 'skipped',
				'message' => 'No tokens to charge.',
			) );
		}

		if ( $response_cost > 0 ) {
			$total_cost = $response_cost;
		} else {
			$pricing     = self::ANTHROPIC_PRICING[ $model ] ?? self::ANTHROPIC_PRICING['default'];
			$input_cost  = ( $prompt_tokens / 1_000_000 ) * $pricing['input'];
			$output_cost = ( $completion_tokens / 1_000_000 ) * $pricing['output'];
			$total_cost  = $input_cost + $output_cost;
		}

		$amount_to_deduct = max( 0.01, round( $total_cost, 2 ) );

		$customer = Database::get_customer( $spawn_customer_id );
		if ( ! $customer ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "LiteLLM callback: Customer $spawn_customer_id not found" );
			return new WP_REST_Response( array(
				'status'  => 'error',
				'message' => 'Customer not found.',
			), 404 );
		}

		$current_balance = (float) $customer['credit_balance'];

		$success = Database::deduct_credits( $spawn_customer_id, $amount_to_deduct );

		if ( ! $success ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "LiteLLM callback: Failed to deduct $amount_to_deduct from customer $spawn_customer_id" );
			return new WP_REST_Response( array(
				'status'  => 'error',
				'message' => 'Failed to deduct credits.',
			), 500 );
		}

		$user_id   = (int) ( $customer['user_id'] ?? 0 );
		$server_id = (int) ( $customer['id'] ?? 0 );
		Database::record_usage( $user_id, $server_id, $total_cost, $prompt_tokens, $completion_tokens );

		$new_balance = Database::get_credit_balance( $spawn_customer_id );

		$auto_refill = Database::get_auto_refill_settings( $spawn_customer_id );
		if ( $auto_refill && $auto_refill['enabled'] && $new_balance < $auto_refill['threshold'] ) {
			do_action( 'spawn_credits_auto_refill_needed', $spawn_customer_id, $auto_refill );
		}

		return new WP_REST_Response( array(
			'status'            => 'success',
			'model'             => $model,
			'prompt_tokens'     => $prompt_tokens,
			'completion_tokens' => $completion_tokens,
			'cost_usd'          => round( $total_cost, 6 ),
			'amount_deducted'   => $amount_to_deduct,
			'previous_balance'  => $current_balance,
			'new_balance'       => $new_balance,
		) );
	}

	/**
	 * Pre-request balance check for LiteLLM.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public static function balance_check( WP_REST_Request $request ): WP_REST_Response {
		$ip       = $request->get_param( 'ip' );
		$customer = Database::get_customer_by_server_ip( $ip );

		if ( ! $customer ) {
			return new WP_REST_Response( array( 'allow' => true ) );
		}

		$balance     = (float) $customer['credit_balance'];
		$auto_refill = (bool) $customer['auto_refill_enabled'];

		if ( $auto_refill || $balance > 0 ) {
			return new WP_REST_Response( array( 'allow' => true ) );
		}

		$dashboard = home_url( '/spawn/dashboard/' );

		return new WP_REST_Response( array(
			'allow'         => false,
			'message'       => "Your AI credits have been depleted. Add credits or enable auto-refill at: {$dashboard}",
			'balance'       => $balance,
			'dashboard_url' => $dashboard,
		) );
	}

	/**
	 * Set billing mode from customer's agent.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public static function set_billing_mode( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$ip           = $request->get_param( 'ip' );
		$billing_mode = $request->get_param( 'billing_mode' );

		$customer = Database::get_customer_by_server_ip( $ip );

		if ( ! $customer ) {
			return new WP_Error( 'customer_not_found', __( 'No customer found for this server IP.', 'spawn' ), array( 'status' => 404 ) );
		}

		$success = Database::update_customer( (int) $customer['id'], array( 'billing_mode' => $billing_mode ) );

		if ( ! $success ) {
			return new WP_Error( 'update_failed', __( 'Failed to update billing mode.', 'spawn' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array(
			'success'      => true,
			'billing_mode' => $billing_mode,
			'message'      => 'byok' === $billing_mode
				? __( 'Switched to Bring Your Own Key mode. You will be billed directly by your AI provider.', 'spawn' )
				: __( 'Switched to managed credits. Usage will be deducted from your Spawn credit balance.', 'spawn' ),
		) );
	}

	/**
	 * Get customer status from customer's agent.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public static function agent_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$ip       = $request->get_param( 'ip' );
		$customer = Database::get_customer_by_server_ip( $ip );

		if ( ! $customer ) {
			return new WP_Error( 'customer_not_found', __( 'No customer found for this server IP.', 'spawn' ), array( 'status' => 404 ) );
		}

		$tier_config = Config::get_tier( $customer['tier'] ?? 'starter' );
		$model_info  = Config::get_ai_model_info();

		$usage_data    = Database::get_server_usage( (int) $customer['id'], 1 );
		$current_usage = $usage_data[0] ?? null;

		return new WP_REST_Response( array(
			'customer_id'      => (int) $customer['id'],
			'tier'             => $customer['tier'] ?? 'starter',
			'billing_mode'     => $customer['billing_mode'] ?? 'managed',
			'credit_balance'   => (float) ( $customer['credit_balance'] ?? 0 ),
			'included_credits' => $tier_config['included_credits'] ?? 5.0,
			'model'            => $model_info,
			'usage'            => array(
				'credits_used'   => (float) ( $current_usage['credits_used'] ?? 0 ),
				'requests_count' => (int) ( $current_usage['requests_count'] ?? 0 ),
				'tokens_input'   => (int) ( $current_usage['tokens_input'] ?? 0 ),
				'tokens_output'  => (int) ( $current_usage['tokens_output'] ?? 0 ),
			),
			'dashboard_url'    => home_url( '/spawn/dashboard/' ),
		) );
	}
}
