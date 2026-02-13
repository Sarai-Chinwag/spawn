<?php
/**
 * Domain REST API Controller.
 *
 * @package Spawn
 */

namespace Spawn\Controllers;

use Spawn\Abilities\Ability_Get_Domain_Renewal_Info;
use Spawn\Abilities\Ability_List_Domains;
use Spawn\Cron;
use Spawn\Database;
use Spawn\Name_Com;
use Spawn\Payment_Helpers;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Domain controller for REST API.
 */
class Domain_Controller {

	/**
	 * Register domain routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'spawn/v1',
			'/domains',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_domains' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		register_rest_route(
			'spawn/v1',
			'/domains/(?P<id>\d+)/assign',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'assign' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'id'        => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'server_id' => array(
						'required' => false,
						'type'     => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'spawn/v1',
			'/domains/(?P<id>\d+)/auto-renew',
			array(
				'methods'             => 'PUT',
				'callback'            => array( __CLASS__, 'update_auto_renew_for_domain' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'id'      => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'enabled' => array(
						'required' => true,
						'type'     => 'boolean',
					),
				),
			)
		);

		register_rest_route(
			'spawn/v1',
			'/account/domain-auto-renew',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_domain_auto_renew' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		register_rest_route(
			'spawn/v1',
			'/account/domain-auto-renew',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'update_domain_auto_renew' ),
				'permission_callback' => array( __CLASS__, 'can_set_domain_auto_renew' ),
				'args'                => array(
					'enabled' => array(
						'required' => true,
						'type'     => 'boolean',
					),
				),
			)
		);
	}

	/**
	 * List domains for current user.
	 *
	 * @return WP_REST_Response Response.
	 */
	public static function list_domains(): WP_REST_Response {
		$domains = Database::get_domains_by_user( get_current_user_id() );

		return new WP_REST_Response( array(
			'domains' => array_map( array( __CLASS__, 'format_domain' ), $domains ),
		) );
	}

	/**
	 * Assign a domain to a server.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function assign( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$domain_id = (int) $request->get_param( 'id' );
		$server_id = $request->get_param( 'server_id' );
		if ( null === $server_id || '' === $server_id || 0 === (int) $server_id ) {
			$server_id = null;
		} else {
			$server_id = (int) $server_id;
		}

		$domain = Database::get_domain( $domain_id );

		if ( ! $domain ) {
			return new WP_Error( 'domain_not_found', __( 'Domain not found.', 'spawn' ), array( 'status' => 404 ) );
		}

		if ( (int) $domain['user_id'] !== get_current_user_id() ) {
			return new WP_Error( 'forbidden', __( 'You do not have access to this domain.', 'spawn' ), array( 'status' => 403 ) );
		}

		if ( null !== $server_id ) {
			$server = Database::get_server( $server_id );
			if ( ! $server ) {
				return new WP_Error( 'server_not_found', __( 'Server not found.', 'spawn' ), array( 'status' => 404 ) );
			}

			if ( (int) $server['user_id'] !== get_current_user_id() ) {
				return new WP_Error( 'forbidden', __( 'You do not have access to this server.', 'spawn' ), array( 'status' => 403 ) );
			}
		}

		$updated = Database::assign_domain_to_server( $domain_id, $server_id );

		if ( ! $updated ) {
			return new WP_Error( 'update_failed', __( 'Failed to assign domain.', 'spawn' ), array( 'status' => 500 ) );
		}

		$domain = Database::get_domain( $domain_id );

		return new WP_REST_Response( array(
			'success' => true,
			'domain'  => self::format_domain( $domain ?? array() ),
		) );
	}

	/**
	 * Update domain auto-renew for a specific domain.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function update_auto_renew_for_domain( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$domain_id = (int) $request->get_param( 'id' );
		$enabled   = (bool) $request->get_param( 'enabled' );
		$domain    = Database::get_domain( $domain_id );

		if ( ! $domain ) {
			return new WP_Error( 'domain_not_found', __( 'Domain not found.', 'spawn' ), array( 'status' => 404 ) );
		}

		if ( (int) $domain['user_id'] !== get_current_user_id() ) {
			return new WP_Error( 'forbidden', __( 'You do not have access to this domain.', 'spawn' ), array( 'status' => 403 ) );
		}

		$updated = Database::update_domain( $domain_id, array( 'auto_renew' => $enabled ? 1 : 0 ) );

		if ( ! $updated ) {
			return new WP_Error( 'update_failed', __( 'Failed to update domain.', 'spawn' ), array( 'status' => 500 ) );
		}

		$domain = Database::get_domain( $domain_id );

		return new WP_REST_Response( array(
			'success' => true,
			'domain'  => self::format_domain( $domain ?? array() ),
		) );
	}

	/**
	 * Get domain auto-renew setting for current customer.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function get_domain_auto_renew(): WP_REST_Response|WP_Error {
		$result = Ability_Get_Domain_Renewal_Info::execute( array() );

		if ( is_wp_error( $result ) ) {
			return self::error_response( $result );
		}

		return new WP_REST_Response( array(
			'enabled'     => $result['auto_renew_enabled'] ?? false,
			'domain'      => $result['domain'],
			'domain_type' => $result['domain_type'],
			'renewable'   => $result['renewable'],
		) );
	}

	/**
	 * Update domain auto-renew setting for current customer.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function update_domain_auto_renew( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer ) {
			return new WP_Error( 'no_customer', __( 'No customer account found.', 'spawn' ), array( 'status' => 404 ) );
		}

		if ( 'register' !== ( $customer['domain_type'] ?? '' ) ) {
			return new WP_Error( 'not_renewable', __( 'Auto-renewal is only available for registered domains.', 'spawn' ), array( 'status' => 400 ) );
		}

		$enabled = (bool) $request->get_param( 'enabled' );

		if ( $enabled ) {
			$stripe_customer = $customer['stripe_customer'] ?? '';
			$payment_method  = $customer['stripe_payment_method'] ?? '';

			if ( empty( $stripe_customer ) ) {
				return new WP_Error( 'no_payment_method', __( 'A payment method is required for auto-renewal. Please add a payment method first.', 'spawn' ), array( 'status' => 400 ) );
			}

			if ( empty( $payment_method ) ) {
				$payment_method = Payment_Helpers::get_default_payment_method( $stripe_customer );
				if ( is_wp_error( $payment_method ) ) {
					return new WP_Error( 'no_payment_method', __( 'No valid payment method found. Please add a payment method in the billing portal.', 'spawn' ), array( 'status' => 400 ) );
				}
			}
		}

		$success = Database::update_customer( (int) $customer['id'], array(
			'domain_auto_renew' => $enabled ? 1 : 0,
		) );

		if ( ! $success ) {
			return new WP_Error( 'update_failed', __( 'Failed to update auto-renewal setting.', 'spawn' ), array( 'status' => 500 ) );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf(
			'[Spawn] Domain auto-renew %s for customer #%d (%s)',
			$enabled ? 'enabled' : 'disabled',
			$customer['id'],
			$customer['domain']
		) );

		return new WP_REST_Response( array(
			'success' => true,
			'enabled' => $enabled,
			'domain'  => $customer['domain'],
			'message' => $enabled
				? __( 'Auto-renewal enabled. Your domain will be automatically renewed 7 days before expiry.', 'spawn' )
				: __( 'Auto-renewal disabled. You will receive warning emails before your domain expires.', 'spawn' ),
		) );
	}

	/**
	 * Process successful domain renewal after Stripe payment.
	 *
	 * Called from webhook when domain_renewal payment succeeds.
	 *
	 * @param int    $customer_id Spawn customer ID.
	 * @param string $domain      Domain name.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function process_domain_renewal_payment( int $customer_id, string $domain ): bool|WP_Error {
		$renewal_result = Name_Com::renew( $domain, 1 );

		if ( is_wp_error( $renewal_result ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf(
				'[Spawn] Domain renewal API failed after payment for %s (customer #%d): %s',
				$domain,
				$customer_id,
				$renewal_result->get_error_message()
			) );

			$admin_email = get_option( 'admin_email' );
			wp_mail(
				$admin_email,
				sprintf( '[Spawn] URGENT: Domain renewal failed after payment: %s', $domain ),
				sprintf(
					"Domain renewal API call failed after successful payment.\n\n" .
					"Customer ID: %d\n" .
					"Domain: %s\n" .
					"Error: %s\n\n" .
					'MANUAL INTERVENTION REQUIRED: Renew the domain manually via Name.com dashboard.',
					$customer_id,
					$domain,
					$renewal_result->get_error_message()
				)
			);

			return $renewal_result;
		}

		$db_updated = Database::renew_domain( $customer_id );

		if ( ! $db_updated ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[Spawn] Failed to update database after domain renewal for customer #%d', $customer_id ) );
		}

		Cron::clear_warnings_sent( $customer_id );

		$customer = Database::get_customer( $customer_id );
		if ( $customer ) {
			$new_expires = $renewal_result['expires_at'] ?? null;
			self::send_renewal_success_email( $customer, $new_expires );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf(
			'[Spawn] Successfully renewed domain %s for customer #%d, new expiration: %s',
			$domain,
			$customer_id,
			$renewal_result['expires_at'] ?? 'unknown'
		) );

		/**
		 * Fires after a domain is successfully renewed.
		 *
		 * @param int    $customer_id   Spawn customer ID.
		 * @param string $domain        Domain name.
		 * @param array  $renewal_result Result from Name.com API.
		 */
		do_action( 'spawn_domain_renewed', $customer_id, $domain, $renewal_result );

		return true;
	}

	/**
	 * Send domain renewal success email.
	 *
	 * @param array       $customer    Customer data.
	 * @param string|null $new_expires New expiration date.
	 */
	private static function send_renewal_success_email( array $customer, ?string $new_expires ): void {
		$domain  = $customer['domain'];
		$subject = sprintf(
			/* translators: %s: domain name */
			__( 'Your domain %s has been renewed', 'spawn' ),
			$domain
		);

		$expires_formatted = $new_expires
			? wp_date( 'F j, Y', strtotime( $new_expires ) )
			: __( 'in approximately one year', 'spawn' );

		$message = sprintf(
			/* translators: 1: domain name, 2: expiration date */
			__(
				"Hello,\n\n" .
				"Great news! Your domain %1\$s has been successfully renewed.\n\n" .
				"New expiration date: %2\$s\n\n" .
				"Thank you for using Spawn!\n\n" .
				'—The Spawn Team',
				'spawn'
			),
			$domain,
			$expires_formatted
		);

		wp_mail( $customer['email'], $subject, $message );
	}

	/**
	 * Check if current user can set domain auto-renew.
	 *
	 * @return bool|WP_Error True if allowed, error otherwise.
	 */
	public static function can_set_domain_auto_renew(): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'not_logged_in', __( 'You must be logged in.', 'spawn' ), array( 'status' => 401 ) );
		}

		if ( ! current_user_can( 'spawn_set_domain_auto_renew' ) && ! current_user_can( 'manage_options' ) ) {
			$customer = Database::get_customer_by_user_id( get_current_user_id() );
			if ( ! $customer ) {
				return new WP_Error( 'no_customer', __( 'No customer account found.', 'spawn' ), array( 'status' => 404 ) );
			}
		}

		return true;
	}

	/**
	 * Format domain response for frontend.
	 *
	 * @param array $domain Domain data.
	 * @return array Formatted domain.
	 */
	private static function format_domain( array $domain ): array {
		$server_id = $domain['server_id'] ?? null;
		return array(
			'id'         => (int) ( $domain['id'] ?? 0 ),
			'domain'     => $domain['domain'] ?? '',
			'server_id'  => is_null( $server_id ) ? null : (int) $server_id,
			'expires_at' => $domain['expires_at'] ?? null,
			'auto_renew' => ! empty( $domain['auto_renew'] ),
		);
	}

	/**
	 * Convert WP_Error to REST response with proper status code.
	 *
	 * @param WP_Error $error Error object.
	 * @return WP_REST_Response Response.
	 */
	private static function error_response( WP_Error $error ): WP_REST_Response {
		$data   = $error->get_error_data();
		$status = is_array( $data ) ? ( $data['status'] ?? 400 ) : 400;

		return new WP_REST_Response(
			array(
				'success' => false,
				'code'    => $error->get_error_code(),
				'message' => $error->get_error_message(),
			),
			$status
		);
	}
}
