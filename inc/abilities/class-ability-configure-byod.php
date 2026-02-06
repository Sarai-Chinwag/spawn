<?php
/**
 * Configure BYOD (Bring Your Own Domain) ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Database;
use WP_Error;

/**
 * Provides instructions for connecting a customer's own domain.
 */
class Ability_Configure_BYOD {

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		$domain    = $input['domain'] ?? '';
		$server_id = $input['server_id'] ?? null;

		if ( empty( $domain ) ) {
			return new WP_Error( 'missing_domain', __( 'Domain name is required', 'spawn' ) );
		}

		// Get customer.
		$customer = self::get_customer( $input );
		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		// Clean up domain.
		$domain = strtolower( trim( $domain ) );
		$domain = preg_replace( '/^(https?:\/\/)?(www\.)?/', '', $domain );
		$domain = rtrim( $domain, '/' );

		// Get server IP.
		$server_ip = null;
		if ( $server_id ) {
			$server = Database::get_server( (int) $server_id );
			if ( $server && ! empty( $server['server_ip'] ) ) {
				$server_ip = $server['server_ip'];
			}
		}
		
		// Fall back to customer's main server IP.
		if ( ! $server_ip && ! empty( $customer['server_ip'] ) ) {
			$server_ip = $customer['server_ip'];
		}

		if ( ! $server_ip ) {
			return new WP_Error(
				'no_server',
				__( 'No active server found. Server must be provisioned first.', 'spawn' )
			);
		}

		// Generate DNS instructions.
		$instructions = self::get_dns_instructions( $domain, $server_ip );

		// Store the pending BYOD configuration.
		$byod_data = [
			'domain'      => $domain,
			'server_id'   => $server_id,
			'server_ip'   => $server_ip,
			'customer_id' => $customer['id'],
			'status'      => 'pending_dns',
			'created_at'  => current_time( 'mysql' ),
		];

		// Save to domains table as pending.
		$domain_id = Database::add_domain( [
			'user_id'        => $customer['user_id'],
			'server_id'      => $server_id,
			'domain'         => $domain,
			'registrar'      => 'byod',
			'dns_configured' => false,
			'ssl_configured' => false,
		] );

		return [
			'success'      => true,
			'domain'       => $domain,
			'server_ip'    => $server_ip,
			'domain_id'    => $domain_id,
			'instructions' => $instructions,
			'message'      => sprintf(
				/* translators: %s: domain name */
				__( 'To connect %s, update your DNS settings as shown below.', 'spawn' ),
				$domain
			),
			'next_steps'   => [
				__( '1. Log into your domain registrar (GoDaddy, Namecheap, Cloudflare, etc.)', 'spawn' ),
				__( '2. Find DNS settings or DNS management', 'spawn' ),
				__( '3. Add the DNS records shown above', 'spawn' ),
				__( '4. Wait for DNS propagation (usually 5-30 minutes, can take up to 48 hours)', 'spawn' ),
				__( '5. SSL certificate will be automatically provisioned once DNS is verified', 'spawn' ),
			],
			'verification' => [
				'check_command' => sprintf( 'dig +short %s', $domain ),
				'expected'      => $server_ip,
				'note'          => __( 'Once this returns your server IP, DNS is ready.', 'spawn' ),
			],
		];
	}

	/**
	 * Get DNS configuration instructions.
	 *
	 * @param string $domain    Domain name.
	 * @param string $server_ip Server IP address.
	 * @return array DNS instructions.
	 */
	private static function get_dns_instructions( string $domain, string $server_ip ): array {
		return [
			'summary' => sprintf(
				/* translators: 1: domain, 2: IP address */
				__( 'Point %1$s to %2$s', 'spawn' ),
				$domain,
				$server_ip
			),
			'records' => [
				[
					'type'  => 'A',
					'name'  => '@',
					'value' => $server_ip,
					'ttl'   => 300,
					'note'  => __( 'Points the root domain to your server', 'spawn' ),
				],
				[
					'type'  => 'A',
					'name'  => 'www',
					'value' => $server_ip,
					'ttl'   => 300,
					'note'  => __( 'Points www subdomain to your server', 'spawn' ),
				],
			],
			'optional' => [
				[
					'type'  => 'CAA',
					'name'  => '@',
					'value' => '0 issue "letsencrypt.org"',
					'note'  => __( 'Allows Let\'s Encrypt to issue SSL certificates', 'spawn' ),
				],
			],
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
