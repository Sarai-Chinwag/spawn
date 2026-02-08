<?php
/**
 * Get Domain Renewal Info ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Database;
use Spawn\Name_Com;
use WP_Error;

/**
 * Returns domain expiration info and renewal pricing.
 */
class Ability_Get_Domain_Renewal_Info {

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		$customer = self::get_customer( $input );

		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		// Check if this is a registered domain (not subdomain/BYOD).
		if ( 'register' !== ( $customer['domain_type'] ?? '' ) ) {
			return array(
				'renewable'   => false,
				'domain'      => $customer['domain'],
				'domain_type' => $customer['domain_type'] ?? 'subdomain',
				'message'     => __( 'This domain type does not require renewal through Spawn.', 'spawn' ),
			);
		}

		$domain     = $customer['domain'];
		$expires_at = $customer['domain_expires_at'];

		// Calculate days until expiry.
		$expires_timestamp = strtotime( $expires_at );
		$now_timestamp     = time();
		$days_until_expiry = (int) ceil( ( $expires_timestamp - $now_timestamp ) / DAY_IN_SECONDS );

		// Get renewal price from Name.com.
		$renewal_price = Name_Com::get_renewal_price( $domain );
		$price_error   = null;

		if ( is_wp_error( $renewal_price ) ) {
			$price_error   = $renewal_price->get_error_message();
			$renewal_price = null;
		} else {
			// Apply markup.
			$markup        = (float) get_option( 'spawn_domain_markup', 1.5 );
			$renewal_price = round( $renewal_price * $markup, 2 );
		}

		return array(
			'renewable'           => true,
			'domain'              => $domain,
			'domain_type'         => 'register',
			'expires_at'          => $expires_at,
			'expires_formatted'   => wp_date( 'F j, Y', $expires_timestamp ),
			'days_until_expiry'   => $days_until_expiry,
			'renewal_price'       => $renewal_price,
			'renewal_price_error' => $price_error,
			'is_expired'          => $days_until_expiry < 0,
			'is_expiring_soon'    => $days_until_expiry <= 30 && $days_until_expiry >= 0,
			'auto_renew_enabled'  => (bool) ( $customer['domain_auto_renew'] ?? false ),
		);
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
