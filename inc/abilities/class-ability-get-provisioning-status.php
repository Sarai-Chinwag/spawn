<?php
/**
 * Get Provisioning Status ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Database;
use Spawn\Provisioner;
use WP_Error;

/**
 * Returns provisioning status for a customer.
 */
class Ability_Get_Provisioning_Status {

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		$customer_id = isset( $input['customer_id'] ) ? absint( $input['customer_id'] ) : 0;

		if ( ! $customer_id ) {
			return new WP_Error( 'missing_customer_id', __( 'customer_id is required', 'spawn' ) );
		}

		$customer = Database::get_customer( $customer_id );
		if ( ! $customer ) {
			return new WP_Error( 'not_found', __( 'Customer not found', 'spawn' ) );
		}

		$result = array(
			'customer_id'       => $customer_id,
			'status'            => $customer['status'],
			'domain'            => $customer['domain'],
			'server_ip'         => $customer['server_ip'],
			'hetzner_server_id' => $customer['hetzner_server_id'],
			'tier'              => $customer['tier'],
			'wants_website'     => (bool) $customer['wants_website'],
			'hetzner_type'      => $customer['hetzner_type'],
			'hetzner_location'  => $customer['hetzner_location'],
			'created_at'        => $customer['created_at'],
			'last_error'        => null,
			'job_status'        => null,
		);

		// If there's a job ID in server_id, try to get job status.
		$server_id = $customer['server_id'];
		if ( $server_id && strpos( $server_id, 'job:' ) === 0 ) {
			$job_id     = substr( $server_id, 4 );
			$job_status = Provisioner::get_status( $job_id );

			if ( is_wp_error( $job_status ) ) {
				$result['last_error'] = $job_status->get_error_message();
			} else {
				$result['job_status'] = array(
					'job_id' => $job_id,
					'status' => $job_status['status'] ?? 'unknown',
					'error'  => $job_status['error'] ?? null,
				);

				// Extract error if job failed.
				if ( isset( $job_status['error'] ) ) {
					$result['last_error'] = $job_status['error'];
				}
			}
		}

		// Add provisioning state interpretation.
		$result['provisioning_state'] = self::interpret_state( $customer, $result );

		return $result;
	}

	/**
	 * Interpret the provisioning state into a human-readable status.
	 *
	 * @param array $customer Customer data.
	 * @param array $result   Result data with job status.
	 * @return string Human-readable state.
	 */
	private static function interpret_state( array $customer, array $result ): string {
		$status    = $customer['status'];
		$server_id = $customer['server_id'];
		$server_ip = $customer['server_ip'];

		switch ( $status ) {
			case 'active':
				if ( $server_ip ) {
					return 'complete';
				}
				return 'active_no_server';

			case 'pending':
				if ( ! $server_id ) {
					return 'awaiting_payment';
				}
				if ( strpos( $server_id, 'job:' ) === 0 ) {
					$job_status = $result['job_status']['status'] ?? 'unknown';
					switch ( $job_status ) {
						case 'queued':
							return 'job_queued';
						case 'running':
							return 'provisioning';
						case 'completed':
							return 'job_complete_updating';
						case 'failed':
							return 'job_failed';
						default:
							return 'job_status_unknown';
					}
				}
				return 'pending_unknown';

			case 'failed':
				return 'provisioning_failed';

			case 'cancelling':
				return 'cancellation_pending';

			case 'deleted':
				return 'deleted';

			default:
				return 'unknown';
		}
	}
}
