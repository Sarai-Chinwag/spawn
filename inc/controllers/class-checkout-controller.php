<?php
/**
 * Checkout REST API Controller.
 *
 * @package Spawn
 */

namespace Spawn\Controllers;

use Spawn\Abilities\Ability_Search_Domain;
use Spawn\Config;
use Spawn\Database;
use Spawn\Name_Com;
use StripeIntegration\StripeClient;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Checkout controller for REST API.
 */
class Checkout_Controller {

	/**
	 * European country codes for region detection.
	 */
	private const EU_COUNTRIES = array(
		'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
		'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
		'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
		'GB', 'CH', 'NO', 'IS', 'LI', 'UA', 'BY', 'MD', 'RS', 'BA',
		'ME', 'MK', 'AL', 'XK',
	);

	/**
	 * Register checkout routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'spawn/v1',
			'/domain/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'search_domain' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'domain' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'spawn/v1',
			'/checkout/session',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_session' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'email'         => array(
						'required' => true,
						'type'     => 'string',
						'format'   => 'email',
					),
					'tier'          => array(
						'type'    => 'string',
						'enum'    => array( 'starter', 'pro', 'business' ),
						'default' => 'starter',
					),
					'wants_website' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'domain'        => array(
						'required' => false,
						'type'     => 'string',
					),
					'domain_type'   => array(
						'type'    => 'string',
						'enum'    => array( 'subdomain', 'register', 'byod' ),
						'default' => 'subdomain',
					),
					'domain_price'  => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
			)
		);

		register_rest_route(
			'spawn/v1',
			'/tiers',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_tiers' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'spawn/v1',
			'/checkout/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_status' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'session_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Search for domain availability.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function search_domain( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = Ability_Search_Domain::execute( array(
			'domain' => $request->get_param( 'domain' ),
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result );
	}

	/**
	 * Create Stripe checkout session.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function create_session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! class_exists( '\\StripeIntegration\\StripeClient' ) ) {
			return new WP_Error(
				'stripe_not_available',
				__( 'Payment processing is not configured on this site.', 'spawn' ),
				array( 'status' => 503 )
			);
		}

		$email           = sanitize_email( $request->get_param( 'email' ) );
		$tier            = sanitize_text_field( $request->get_param( 'tier' ) ?? 'starter' );
		$wants_website   = (bool) $request->get_param( 'wants_website' );
		$domain          = sanitize_text_field( $request->get_param( 'domain' ) ?? '' );
		$domain_type     = sanitize_text_field( $request->get_param( 'domain_type' ) ?? 'subdomain' );
		$domain_price    = (float) $request->get_param( 'domain_price' );
		$customer_region = self::detect_customer_region();

		$tier_config = Config::get_tier( $tier );
		if ( ! $tier_config ) {
			return new WP_Error(
				'invalid_tier',
				__( 'Invalid tier selected', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		$line_items = array(
			array(
				'price'    => $tier_config['stripe_price_id'],
				'quantity' => 1,
			),
		);

		if ( $wants_website && 'register' === $domain_type && $domain_price > 0 ) {
			$line_items[] = array(
				'price_data' => array(
					'currency'     => 'usd',
					'unit_amount'  => (int) ( $domain_price * 100 ),
					'product_data' => array(
						'name'        => sprintf(
							/* translators: %s: domain name */
							__( 'Domain Registration: %s', 'spawn' ),
							$domain
						),
						'description' => __( 'One-time domain registration fee (1 year)', 'spawn' ),
					),
				),
				'quantity'   => 1,
			);
		}

		$session = StripeClient::create_checkout_session( array(
			'customer_email'            => $email,
			'metadata'                  => array(
				'tier'            => $tier,
				'wants_website'   => $wants_website ? 'true' : 'false',
				'domain'          => $domain,
				'domain_type'     => $domain_type,
				'domain_price'    => $domain_price,
				'customer_region' => $customer_region,
				'source'          => 'spawn',
			),
			'line_items'                => $line_items,
			'mode'                      => 'subscription',
			'payment_method_collection' => 'always',
			'subscription_data'         => array(
				'metadata' => array(
					'tier'   => $tier,
					'source' => 'spawn',
				),
			),
			'success_url'               => home_url( '/spawn/success?session_id={CHECKOUT_SESSION_ID}' ),
			'cancel_url'                => home_url( '/spawn/' ),
		) );

		if ( is_wp_error( $session ) ) {
			return $session;
		}

		return new WP_REST_Response( array(
			'url' => $session['url'],
		) );
	}

	/**
	 * Get available tiers.
	 *
	 * @return WP_REST_Response Tiers response.
	 */
	public static function get_tiers(): WP_REST_Response {
		return new WP_REST_Response( Config::get_public_tiers() );
	}

	/**
	 * Get checkout/provisioning status by Stripe session ID.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function get_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! class_exists( '\\StripeIntegration\\StripeClient' ) ) {
			return new WP_Error(
				'stripe_not_available',
				__( 'Payment processing is not configured on this site.', 'spawn' ),
				array( 'status' => 503 )
			);
		}

		$session_id = $request->get_param( 'session_id' );

		if ( empty( $session_id ) ) {
			return new WP_Error(
				'missing_session_id',
				__( 'Missing session ID.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		$session = StripeClient::retrieve_checkout_session( $session_id );

		if ( is_wp_error( $session ) ) {
			return new WP_REST_Response( array(
				'success' => false,
				'status'  => 'not_found',
				'error'   => __( 'Session not found or expired.', 'spawn' ),
			) );
		}

		$customer_email = $session['customer_email'] ?? $session['customer_details']['email'] ?? '';

		if ( empty( $customer_email ) && ! empty( $session['customer'] ) ) {
			$stripe_customer = StripeClient::retrieve_customer( $session['customer'] );
			if ( ! is_wp_error( $stripe_customer ) ) {
				$customer_email = $stripe_customer['email'] ?? '';
			}
		}

		if ( empty( $customer_email ) ) {
			return new WP_REST_Response( array(
				'success' => false,
				'status'  => 'not_found',
				'error'   => __( 'Could not determine customer from session.', 'spawn' ),
			) );
		}

		$customer = Database::get_customer_by_email( $customer_email );

		if ( ! $customer ) {
			return new WP_REST_Response( array(
				'success'  => true,
				'status'   => 'pending',
				'message'  => __( 'Payment received. Setting up your account...', 'spawn' ),
				'progress' => array(
					'payment'   => true,
					'server'    => false,
					'wordpress' => false,
					'ai'        => false,
					'percent'   => 10,
				),
			) );
		}

		$status   = $customer['status'] ?? 'pending';
		$progress = self::get_provisioning_progress( $status, $customer );

		switch ( $status ) {
			case 'active':
			case 'ready':
				return new WP_REST_Response( array(
					'success'      => true,
					'status'       => 'active',
					'customer'     => array(
						'id'        => (int) $customer['id'],
						'domain'    => $customer['domain'] ?? '',
						'status'    => $status,
						'server_ip' => $customer['server_ip'] ?? null,
						'tier'      => $customer['tier'] ?? 'starter',
					),
					'progress'     => $progress,
					'redirect_url' => home_url( '/spawn/dashboard/' ),
				) );

			case 'failed':
			case 'error':
				return new WP_REST_Response( array(
					'success'  => true,
					'status'   => 'failed',
					'error'    => __( 'Server setup failed. Our team has been notified.', 'spawn' ),
					'customer' => array(
						'id'     => (int) $customer['id'],
						'domain' => $customer['domain'] ?? '',
						'status' => $status,
						'tier'   => $customer['tier'] ?? 'starter',
					),
				) );

			case 'pending':
			case 'provisioning':
			case 'creating':
			case 'configuring':
			default:
				return new WP_REST_Response( array(
					'success'  => true,
					'status'   => 'provisioning',
					'customer' => array(
						'id'        => (int) $customer['id'],
						'domain'    => $customer['domain'] ?? '',
						'status'    => $status,
						'server_ip' => $customer['server_ip'] ?? null,
						'tier'      => $customer['tier'] ?? 'starter',
					),
					'progress' => $progress,
				) );
		}
	}

	/**
	 * Calculate provisioning progress based on customer status.
	 *
	 * @param string $status   Customer status.
	 * @param array  $customer Customer data.
	 * @return array Progress data.
	 */
	public static function get_provisioning_progress( string $status, array $customer ): array {
		$has_server_id = ! empty( $customer['provider_server_id'] ) || ! empty( $customer['server_id'] );
		$has_server_ip = ! empty( $customer['server_ip'] );
		$has_wordpress = ! empty( $customer['agent_password'] );
		$is_active     = in_array( $status, array( 'active', 'ready' ), true );

		$payment_done   = true;
		$server_done    = $has_server_id || $has_server_ip || $is_active;
		$wordpress_done = $has_wordpress || $is_active;
		$ai_done        = $is_active;

		$steps_done = (int) $payment_done + (int) $server_done + (int) $wordpress_done + (int) $ai_done;
		$percent    = (int) ( ( $steps_done / 4 ) * 100 );

		if ( ! $is_active && $percent < 100 ) {
			switch ( $status ) {
				case 'creating':
					$percent = max( $percent, 25 );
					break;
				case 'provisioning':
					$percent = max( $percent, 50 );
					break;
				case 'configuring':
					$percent = max( $percent, 75 );
					break;
			}
		}

		return array(
			'payment'   => $payment_done,
			'server'    => $server_done,
			'wordpress' => $wordpress_done,
			'ai'        => $ai_done,
			'percent'   => $percent,
		);
	}

	/**
	 * Detect customer region from IP/headers.
	 *
	 * @return string Region code ('us' or 'eu').
	 */
	public static function detect_customer_region(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$country_code = isset( $_SERVER['HTTP_CF_IPCOUNTRY'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) )
			: '';

		if ( empty( $country_code ) || 'XX' === $country_code ) {
			return 'us';
		}

		if ( in_array( $country_code, self::EU_COUNTRIES, true ) ) {
			return 'eu';
		}

		return 'us';
	}
}
