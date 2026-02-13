<?php
/**
 * BYOK (Bring Your Own Key) REST API Controller.
 *
 * Allows customers to provide their own Anthropic API key
 * instead of using the shared LiteLLM proxy.
 *
 * @package Spawn
 */

namespace Spawn\Controllers;

use Spawn\Crypto;
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
			'/customer/api-key',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_key_status' ),
					'permission_callback' => 'is_user_logged_in',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'save_key' ),
					'permission_callback' => 'is_user_logged_in',
					'args'                => array(
						'api_key' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'remove_key' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);
	}

	/**
	 * Get API key status for the current customer.
	 *
	 * Never returns the actual key — only whether one exists and a masked hint.
	 *
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public static function get_key_status(): WP_REST_Response|WP_Error {
		$customer = self::get_current_customer();

		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		$has_key    = ! empty( $customer['api_key_encrypted'] );
		$masked_key = null;

		if ( $has_key ) {
			$decrypted = Crypto::decrypt( $customer['api_key_encrypted'] );
			if ( false !== $decrypted ) {
				$masked_key = Crypto::mask_key( $decrypted );
			}
		}

		return new WP_REST_Response( array(
			'has_key'      => $has_key,
			'masked_key'   => $masked_key,
			'billing_mode' => $customer['billing_mode'] ?? 'managed',
		) );
	}

	/**
	 * Save a customer's Anthropic API key.
	 *
	 * Validates the key with a test API call before saving.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public static function save_key( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$customer = self::get_current_customer();

		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		$api_key = $request->get_param( 'api_key' );

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'missing_key',
				__( 'API key is required.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		// Validate the key with a test API call.
		$valid = self::validate_anthropic_key( $api_key );

		if ( ! $valid ) {
			return new WP_Error(
				'invalid_key',
				__( 'The API key could not be validated. Please check that it is a valid Anthropic API key with active billing.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		// Encrypt and save.
		$encrypted = Crypto::encrypt( $api_key );

		$success = Database::update_customer( (int) $customer['id'], array(
			'api_key_encrypted' => $encrypted,
			'billing_mode'      => 'byok',
		) );

		if ( ! $success ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to save API key.', 'spawn' ),
				array( 'status' => 500 )
			);
		}

		// TODO: Push key to customer's VPS via SSH (same mechanism as provisioner).
		// The OpenClaw gateway binds to loopback so we can't reach it remotely via HTTP.
		// For now, the key is saved in our DB and will be used during re-provisioning.
		// Follow-up: SSH to VPS, update OpenClaw config, restart gateway.

		return new WP_REST_Response( array(
			'success'    => true,
			'masked_key' => Crypto::mask_key( $api_key ),
			'message'    => __( 'API key validated and saved. You are now using your own Anthropic key.', 'spawn' ),
		) );
	}

	/**
	 * Remove the customer's API key and revert to managed billing.
	 *
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public static function remove_key(): WP_REST_Response|WP_Error {
		$customer = self::get_current_customer();

		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		$success = Database::update_customer( (int) $customer['id'], array(
			'api_key_encrypted' => null,
			'billing_mode'      => 'managed',
		) );

		if ( ! $success ) {
			return new WP_Error(
				'remove_failed',
				__( 'Failed to remove API key.', 'spawn' ),
				array( 'status' => 500 )
			);
		}

		// TODO: Push config change to VPS via SSH (revert to LiteLLM proxy).
		// Same constraint as save_key — gateway is loopback, needs SSH.

		return new WP_REST_Response( array(
			'success' => true,
			'message' => __( 'API key removed. Reverted to managed billing.', 'spawn' ),
		) );
	}

	/**
	 * Validate an Anthropic API key with a test API call.
	 *
	 * Sends a minimal request to verify the key works.
	 *
	 * @param string $key Anthropic API key.
	 * @return bool True if the key is valid.
	 */
	private static function validate_anthropic_key( string $key ): bool {
		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'headers' => array(
					'x-api-key'         => $key,
					'anthropic-version'  => '2023-06-01',
					'content-type'       => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'model'      => 'claude-haiku-4-20250514',
					'max_tokens' => 1,
					'messages'   => array(
						array(
							'role'    => 'user',
							'content' => 'hi',
						),
					),
				) ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[Spawn BYOK] Key validation request failed: %s', $response->get_error_message() ) );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		return $code >= 200 && $code < 300;
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
