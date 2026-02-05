<?php
/**
 * VPS Provisioning via Sweatpants.
 *
 * @package Spawn
 */

namespace Spawn;

use WP_Error;

/**
 * Handles VPS provisioning by triggering Sweatpants jobs.
 */
class Provisioner {

	/**
	 * Get Sweatpants configuration.
	 *
	 * @return array{url: string, token: string} Configuration.
	 */
	private static function get_config(): array {
		return [
			'url'   => get_option( 'spawn_sweatpants_url', 'http://127.0.0.1:8420' ),
			'token' => get_option( 'spawn_sweatpants_token', '' ),
		];
	}

	/**
	 * Trigger VPS provisioning.
	 *
	 * @param array $params Provisioning parameters.
	 * @return array|WP_Error Job info or error.
	 */
	public static function trigger( array $params ): array|WP_Error {
		$config = self::get_config();

		if ( empty( $config['url'] ) ) {
			return new WP_Error(
				'sweatpants_not_configured',
				__( 'Sweatpants is not configured', 'spawn' ),
				[ 'status' => 500 ]
			);
		}

		// Build the job request.
		$job_data = [
			'module_id' => 'vps-provisioner',
			'inputs' => [
				'customer_email'           => $params['customer_email'],
				'domain'                   => $params['domain'],
				'subdomain'                => $params['subdomain'] ?? false,
				'tier'                     => $params['tier'] ?? 'starter',
				'site_title'               => $params['site_title'] ?? '',
				'skip_domain_registration' => $params['subdomain'] ?? false,
			],
		];

		// Make request to Sweatpants API.
		$args = [
			'method'  => 'POST',
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => wp_json_encode( $job_data ),
			'timeout' => 30,
		];

		// Add auth token if configured.
		if ( ! empty( $config['token'] ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $config['token'];
		}

		$response = wp_remote_request( $config['url'] . '/jobs', $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 ) {
			$message = $body['error'] ?? __( 'Failed to create provisioning job', 'spawn' );
			return new WP_Error(
				'provisioning_failed',
				$message,
				[ 'status' => $code ]
			);
		}

		// Store job ID with customer record.
		if ( ! empty( $params['customer_id'] ) && ! empty( $body['job_id'] ) ) {
			Database::update_customer( $params['customer_id'], [
				'server_id' => 'job:' . $body['job_id'],
			] );
		}

		return [
			'job_id' => $body['job_id'] ?? null,
			'status' => $body['status'] ?? 'queued',
		];
	}

	/**
	 * Check provisioning job status.
	 *
	 * @param string $job_id Sweatpants job ID.
	 * @return array|WP_Error Job status or error.
	 */
	public static function get_status( string $job_id ): array|WP_Error {
		$config = self::get_config();

		if ( empty( $config['url'] ) ) {
			return new WP_Error(
				'sweatpants_not_configured',
				__( 'Sweatpants is not configured', 'spawn' ),
				[ 'status' => 500 ]
			);
		}

		$args = [
			'method'  => 'GET',
			'headers' => [],
			'timeout' => 30,
		];

		if ( ! empty( $config['token'] ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $config['token'];
		}

		$response = wp_remote_request( $config['url'] . '/jobs/' . $job_id, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 ) {
			$message = $body['error'] ?? __( 'Failed to get job status', 'spawn' );
			return new WP_Error(
				'status_check_failed',
				$message,
				[ 'status' => $code ]
			);
		}

		return $body;
	}

	/**
	 * Handle provisioning completion webhook from Sweatpants.
	 *
	 * @param array $data Webhook data.
	 * @return bool Success.
	 */
	public static function handle_completion( array $data ): bool {
		$domain    = $data['domain'] ?? '';
		$server_ip = $data['server_ip'] ?? $data['vps_ip'] ?? '';
		$success   = $data['success'] ?? false;

		if ( empty( $domain ) ) {
			return false;
		}

		$customer = Database::get_customer_by_domain( $domain );
		if ( ! $customer ) {
			return false;
		}

		if ( $success ) {
			Database::update_customer( $customer['id'], [
				'server_ip' => $server_ip,
				'status'    => 'active',
			] );

			// TODO: Send welcome email to customer.
		} else {
			Database::update_customer( $customer['id'], [
				'status' => 'failed',
			] );

			// TODO: Send failure notification.
			// TODO: Trigger refund?
		}

		return true;
	}
}
