<?php
/**
 * Search Domain ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Name_Com;
use WP_Error;

/**
 * Searches for domain availability and pricing.
 */
class Ability_Search_Domain {

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		$domain = $input['domain'] ?? '';

		if ( empty( $domain ) ) {
			return new WP_Error( 'missing_domain', __( 'Domain name is required', 'spawn' ) );
		}

		// Clean up domain input.
		$domain = strtolower( trim( $domain ) );
		$domain = preg_replace( '/^(https?:\/\/)?(www\.)?/', '', $domain );
		$domain = rtrim( $domain, '/' );

		// Check availability via Name.com.
		$result = Name_Com::check_availability( $domain );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Apply markup to prices.
		$markup = (float) get_option( 'spawn_domain_markup', 1.5 );

		if ( ! empty( $result['price'] ) ) {
			$result['price'] = round( $result['price'] * $markup, 2 );
		}
		if ( ! empty( $result['renewal'] ) ) {
			$result['renewal'] = round( $result['renewal'] * $markup, 2 );
		}

		// Add helpful context.
		$result['markup_applied'] = true;

		if ( $result['available'] ) {
			$result['message'] = sprintf(
				/* translators: 1: domain name, 2: price */
				__( '%1$s is available for $%2$s/year', 'spawn' ),
				$result['domain'],
				$result['price']
			);
			$result['next_step'] = __( 'Use spawn_register_domain to purchase this domain', 'spawn' );
		} else {
			$result['message'] = sprintf(
				/* translators: %s: domain name */
				__( '%s is not available', 'spawn' ),
				$result['domain']
			);
			$result['next_step'] = __( 'Try a different domain name or extension', 'spawn' );
		}

		return $result;
	}
}
