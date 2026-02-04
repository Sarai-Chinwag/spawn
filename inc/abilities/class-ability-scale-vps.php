<?php
/**
 * Scale VPS ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Database;
use WP_Error;

/**
 * Resizes Hetzner VPS.
 */
class Ability_Scale_VPS {

	/**
	 * Valid VPS tiers.
	 */
	private const VALID_TIERS = [ 'cx22', 'cx32', 'cx42' ];

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		$new_tier = $input['new_tier'] ?? '';

		if ( ! in_array( $new_tier, self::VALID_TIERS, true ) ) {
			return new WP_Error( 'invalid_tier', __( 'Invalid VPS tier', 'spawn' ) );
		}

		// Get customer.
		$customer = self::get_customer( $input );
		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		// Check if already on this tier.
		if ( $customer['vps_tier'] === $new_tier ) {
			return new WP_Error( 'same_tier', __( 'Already on this tier', 'spawn' ) );
		}

		// Must have a server to resize.
		if ( empty( $customer['server_id'] ) ) {
			return new WP_Error( 'no_server', __( 'No server to resize', 'spawn' ) );
		}

		// Call Hetzner to resize.
		$result = self::resize_hetzner_server( $customer['server_id'], $new_tier );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update database.
		$success = Database::update_vps_tier( (int) $customer['id'], $new_tier );

		if ( ! $success ) {
			return new WP_Error( 'update_failed', __( 'Failed to update tier in database', 'spawn' ) );
		}

		return [
			'success'        => true,
			'new_tier'       => $new_tier,
			'server_id'      => $customer['server_id'],
			'effective_date' => current_time( 'mysql' ),
			'note'           => __( 'Server resize initiated. May require reboot.', 'spawn' ),
		];
	}

	/**
	 * Resize Hetzner server using hcloud CLI.
	 *
	 * @param string $server_id Server ID.
	 * @param string $new_tier  New server type.
	 * @return true|WP_Error True on success or error.
	 */
	private static function resize_hetzner_server( string $server_id, string $new_tier ): true|WP_Error {
		// Use hcloud CLI for resize.
		$command = sprintf(
			'HCLOUD_TOKEN=%s /usr/local/bin/hcloud server change-type %s %s --keep-disk 2>&1',
			escapeshellarg( getenv( 'HETZNER_API_TOKEN' ) ?: '' ),
			escapeshellarg( $server_id ),
			escapeshellarg( $new_tier )
		);

		$output = [];
		$return_code = 0;
		exec( $command, $output, $return_code );

		if ( $return_code !== 0 ) {
			$error_msg = implode( "\n", $output );
			return new WP_Error( 
				'hetzner_error', 
				sprintf( __( 'Hetzner resize failed: %s', 'spawn' ), $error_msg )
			);
		}

		return true;
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
