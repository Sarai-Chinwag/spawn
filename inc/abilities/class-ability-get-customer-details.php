<?php
/**
 * Get Customer Details ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Database;
use WP_Error;

/**
 * Returns full customer record by ID or email.
 */
class Ability_Get_Customer_Details {

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		$customer_id = isset( $input['customer_id'] ) ? absint( $input['customer_id'] ) : 0;
		$email       = isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '';

		if ( ! $customer_id && ! $email ) {
			return new WP_Error(
				'missing_identifier',
				__( 'Either customer_id or email is required', 'spawn' )
			);
		}

		// Fetch customer.
		if ( $customer_id ) {
			$customer = Database::get_customer( $customer_id );
		} else {
			$customer = Database::get_customer_by_email( $email );
		}

		if ( ! $customer ) {
			return new WP_Error( 'not_found', __( 'Customer not found', 'spawn' ) );
		}

		// Return all fields with proper type casting.
		return array(
			'id'                    => (int) $customer['id'],
			'user_id'               => $customer['user_id'] ? (int) $customer['user_id'] : null,
			'email'                 => $customer['email'],
			'domain'                => $customer['domain'],
			'subdomain'             => (bool) $customer['subdomain'],
			'domain_type'           => $customer['domain_type'],
			'domain_price'          => $customer['domain_price'] ? (float) $customer['domain_price'] : null,
			'domain_expires_at'     => $customer['domain_expires_at'],
			'domain_auto_renew'     => (bool) $customer['domain_auto_renew'],
			'tier'                  => $customer['tier'],
			'wants_website'         => (bool) $customer['wants_website'],
			'server_type'          => $customer['server_type'],
			'server_location'      => $customer['server_location'],
			'provider_server_id'     => $customer['provider_server_id'],
			'stripe_customer'       => $customer['stripe_customer'],
			'stripe_subscription'   => $customer['stripe_subscription'],
			'stripe_payment_method' => $customer['stripe_payment_method'],
			'server_id'             => $customer['server_id'],
			'server_ip'             => $customer['server_ip'],
			'agent_password'        => $customer['agent_password'] ? '***' : null, // Redact password.
			'status'                => $customer['status'],
			'created_at'            => $customer['created_at'],
			'renewed_at'            => $customer['renewed_at'],
			'cancelled_at'          => $customer['cancelled_at'],
			'scheduled_deletion_at' => $customer['scheduled_deletion_at'],
			'credit_balance'        => (float) $customer['credit_balance'],
			'auto_refill_enabled'   => (bool) $customer['auto_refill_enabled'],
			'auto_refill_threshold' => (float) $customer['auto_refill_threshold'],
			'auto_refill_amount'    => (float) $customer['auto_refill_amount'],
			'customer_region'       => $customer['customer_region'],
		);
	}
}
