<?php
/**
 * Admin REST API Controller.
 *
 * @package Spawn
 */

namespace Spawn\Controllers;

use Spawn\Config;
use Spawn\Database;
use Spawn\Provisioner;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Admin controller for REST API.
 */
class Admin_Controller {

	/**
	 * Register admin routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'spawn/v1',
			'/admin/comp',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_comp' ),
				'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
				'args'                => array(
					'email'           => array(
						'required'          => true,
						'type'              => 'string',
						'format'            => 'email',
						'sanitize_callback' => 'sanitize_email',
					),
					'domain'          => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'tier'            => array(
						'type'              => 'string',
						'enum'              => array( 'starter', 'pro', 'business' ),
						'default'           => 'starter',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'wants_website'   => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'site_title'      => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'domain_type'     => array(
						'type'              => 'string',
						'enum'              => array( 'subdomain', 'register', 'byod' ),
						'default'           => 'subdomain',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'initial_credits' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
			)
		);
	}

	/**
	 * Check if current user has admin permissions.
	 *
	 * @return bool|WP_Error True if admin, error otherwise.
	 */
	public static function check_admin_permission(): bool|WP_Error {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to perform this action.', 'spawn' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Create a comped (free) customer and trigger provisioning.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function create_comp( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$email           = sanitize_email( $request->get_param( 'email' ) );
		$domain          = sanitize_text_field( $request->get_param( 'domain' ) );
		$tier            = sanitize_text_field( $request->get_param( 'tier' ) ?? 'starter' );
		$wants_website   = (bool) $request->get_param( 'wants_website' );
		$site_title      = sanitize_text_field( $request->get_param( 'site_title' ) ?? '' );
		$domain_type     = sanitize_text_field( $request->get_param( 'domain_type' ) ?? 'subdomain' );
		$initial_credits = (float) $request->get_param( 'initial_credits' );

		if ( empty( $email ) || ! is_email( $email ) ) {
			return new WP_Error(
				'invalid_email',
				__( 'A valid email address is required.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $domain ) ) {
			return new WP_Error(
				'missing_domain',
				__( 'A domain or subdomain is required.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		// Verify tier is valid.
		$tier_config = Config::get_tier( $tier );
		if ( ! $tier_config ) {
			return new WP_Error(
				'invalid_tier',
				__( 'Invalid tier selected.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		// Check for existing customer with this email.
		$existing = Database::get_customer_by_email( $email );
		if ( $existing ) {
			return new WP_Error(
				'customer_exists',
				__( 'A customer with this email already exists.', 'spawn' ),
				array( 'status' => 409 )
			);
		}

		$is_subdomain = 'subdomain' === $domain_type;

		// Create customer record with no Stripe subscription.
		$customer_id = Database::create_customer( array(
			'email'               => $email,
			'domain'              => $domain,
			'domain_type'         => $domain_type,
			'subdomain'           => $is_subdomain,
			'tier'                => $tier,
			'wants_website'       => $wants_website,
			'customer_region'     => 'us',
			'stripe_customer'     => null,
			'stripe_subscription' => null,
			'billing_type'        => 'comped',
			'status'              => 'provisioning',
			'credit_balance'      => $initial_credits,
		) );

		if ( ! $customer_id ) {
			return new WP_Error(
				'customer_creation_failed',
				__( 'Failed to create customer record.', 'spawn' ),
				array( 'status' => 500 )
			);
		}

		error_log( sprintf( '[Spawn] Admin comp: created customer #%d for %s (tier: %s)', $customer_id, $email, $tier ) );

		// Trigger provisioning same as normal checkout.
		$result = Provisioner::trigger( array(
			'customer_id'    => $customer_id,
			'customer_email' => $email,
			'domain'         => $domain,
			'domain_type'    => $domain_type,
			'tier'           => $tier,
			'wants_website'  => $wants_website,
			'subdomain'      => $is_subdomain,
		) );

		if ( is_wp_error( $result ) ) {
			error_log( sprintf( '[Spawn] Admin comp: provisioning failed for customer #%d: %s', $customer_id, $result->get_error_message() ) );
			Database::update_customer( $customer_id, array( 'status' => 'failed' ) );

			return new WP_REST_Response(
				array(
					'customer_id'   => $customer_id,
					'status'        => 'failed',
					'error'         => $result->get_error_message(),
				),
				201
			);
		}

		error_log( sprintf( '[Spawn] Admin comp: provisioning triggered for customer #%d, job: %s', $customer_id, $result['job_id'] ?? 'unknown' ) );

		return new WP_REST_Response(
			array(
				'customer_id' => $customer_id,
				'status'      => 'provisioning',
				'job_id'      => $result['job_id'] ?? null,
				'message'     => sprintf(
					/* translators: %s: customer email */
					__( 'Comped customer %s created and provisioning started.', 'spawn' ),
					$email
				),
			),
			201
		);
	}
}
