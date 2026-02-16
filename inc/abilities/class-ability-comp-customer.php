<?php
/**
 * Comp Customer ability.
 *
 * Creates a comped (free) customer with VPS but no Stripe subscription.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Config;
use Spawn\Database;
use Spawn\Provisioner;
use WP_Error;

/**
 * Creates a comped customer - free VPS without Stripe payment.
 */
class Ability_Comp_Customer {

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|\WP_Error {
		$email        = \sanitize_email( $input['email'] ?? '' );
		$tier         = \sanitize_text_field( $input['tier'] ?? 'starter' );
		$wants_website = isset( $input['wants_website'] ) ? (bool) $input['wants_website'] : true;
		$domain       = ! empty( $input['domain'] ) ? \sanitize_text_field( $input['domain'] ) : '';
		$domain_type  = \sanitize_text_field( $input['domain_type'] ?? 'subdomain' );

		if ( empty( $email ) ) {
			return new \WP_Error( 'missing_email', \__( 'Email is required', 'spawn' ) );
		}

		if ( ! \is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', \__( 'Invalid email address', 'spawn' ) );
		}

		if ( ! in_array( $tier, Config::get_tier_ids(), true ) ) {
			return new \WP_Error( 'invalid_tier', \__( 'Invalid tier. Must be starter, pro, or business', 'spawn' ) );
		}

		$valid_domain_types = array( 'subdomain', 'register', 'byod' );
		if ( ! in_array( $domain_type, $valid_domain_types, true ) ) {
			return new \WP_Error( 'invalid_domain_type', \__( 'Invalid domain type. Must be subdomain, register, or byod', 'spawn' ) );
		}

		$existing_customer = Database::get_customer_by_email( $email );
		if ( $existing_customer ) {
			return new \WP_Error(
				'customer_exists',
				\sprintf(
					\__( 'Customer with email %s already exists', 'spawn' ),
					$email
				)
			);
		}

		if ( ! empty( $domain ) ) {
			$existing_domain = Database::get_customer_by_domain( $domain );
			if ( $existing_domain ) {
				return new \WP_Error(
					'domain_exists',
					\sprintf(
						\__( 'Domain %s already exists', 'spawn' ),
						$domain
					)
				);
			}
		}

		$user_id = self::get_or_create_user( $email );
		if ( \is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$customer_id = Database::create_customer( array(
			'user_id'         => $user_id,
			'email'           => $email,
			'domain'          => $domain ?: null,
			'domain_type'     => $domain_type,
			'tier'            => $tier,
			'wants_website'  => $wants_website,
			'billing_type'    => 'comped',
			'status'          => 'provisioning',
			'customer_region' => $input['customer_region'] ?? 'us',
		) );

		if ( ! $customer_id ) {
			return new \WP_Error( 'creation_failed', \__( 'Failed to create customer record', 'spawn' ) );
		}

		$is_subdomain = 'subdomain' === $domain_type;

		$result = Provisioner::trigger( array(
			'customer_id'    => $customer_id,
			'customer_email' => $email,
			'domain'         => $domain,
			'domain_type'    => $domain_type,
			'tier'           => $tier,
			'wants_website'  => $wants_website,
			'subdomain'      => $is_subdomain,
		) );

		if ( \is_wp_error( $result ) ) {
			Database::update_customer( $customer_id, array( 'status' => 'failed' ) );
			return new \WP_Error(
				'provisioning_failed',
				$result->get_error_message()
			);
		}

		return array(
			'success'              => true,
			'customer_id'          => $customer_id,
			'email'                => $email,
			'tier'                 => $tier,
			'billing_type'        => 'comped',
			'status'               => 'provisioning',
			'domain'               => $domain ?: null,
			'domain_type'         => $domain_type,
			'provisioning_job_id' => $result['job_id'] ?? null,
		);
	}

	/**
	 * Get existing user or create new one.
	 *
	 * @param string $email User email.
	 * @return int|\WP_Error User ID or error.
	 */
	private static function get_or_create_user( string $email ): int|\WP_Error {
		$user = \get_user_by( 'email', $email );

		if ( $user ) {
			return $user->ID;
		}

		$username = self::generate_username( $email );
		$password = \wp_generate_password( 12, true );

		$user_id = \wp_create_user( $username, $password, $email );

		if ( \is_wp_error( $user_id ) ) {
			return new \WP_Error(
				'user_creation_failed',
				$user_id->get_error_message()
			);
		}

		\wp_new_user_notification( $user_id, null, 'both' );

		return $user_id;
	}

	/**
	 * Generate a unique username from email.
	 *
	 * @param string $email User email.
	 * @return string Unique username.
	 */
	private static function generate_username( string $email ): string {
		$username = \sanitize_user( explode( '@', $email )[0], true );
		$username = str_replace( array( '-', '_' ), '', $username );

		if ( \username_exists( $username ) ) {
			$suffix = 1;
			while ( \username_exists( $username . $suffix ) ) {
				++$suffix;
			}
			$username .= $suffix;
		}

		return $username;
	}
}
