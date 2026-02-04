<?php
/**
 * REST API endpoints.
 *
 * @package Spawn
 */

namespace Spawn;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Handles REST API endpoints for Spawn.
 */
class REST_API {

	/**
	 * API namespace.
	 */
	private const NAMESPACE = 'spawn/v1';

	/**
	 * Initialize REST API.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes(): void {
		// Domain search.
		register_rest_route(
			self::NAMESPACE,
			'/domain/search',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'search_domain' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'domain' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		// Create checkout session.
		register_rest_route(
			self::NAMESPACE,
			'/checkout/session',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'create_checkout_session' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'domain'   => [
						'required' => true,
						'type'     => 'string',
					],
					'tier'     => [
						'required' => true,
						'type'     => 'string',
						'enum'     => [ 'starter', 'pro', 'business' ],
					],
					'email'    => [
						'required' => true,
						'type'     => 'string',
						'format'   => 'email',
					],
					'subdomain' => [
						'type'    => 'boolean',
						'default' => false,
					],
				],
			]
		);

		// Get tiers/pricing.
		register_rest_route(
			self::NAMESPACE,
			'/tiers',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_tiers' ],
				'permission_callback' => '__return_true',
			]
		);

		// Auth: Login.
		register_rest_route(
			self::NAMESPACE,
			'/auth/login',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'auth_login' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'email'    => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					],
					'password' => [
						'required' => true,
						'type'     => 'string',
					],
				],
			]
		);

		// Auth: Register.
		register_rest_route(
			self::NAMESPACE,
			'/auth/register',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'auth_register' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'email'    => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					],
					'password' => [
						'required'  => true,
						'type'      => 'string',
						'minLength' => 8,
					],
				],
			]
		);

		// Auth: Get current user.
		register_rest_route(
			self::NAMESPACE,
			'/auth/me',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'auth_me' ],
				'permission_callback' => '__return_true',
			]
		);

		// Auth: Logout.
		register_rest_route(
			self::NAMESPACE,
			'/auth/logout',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'auth_logout' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		// Customer: Get current customer.
		register_rest_route(
			self::NAMESPACE,
			'/customer/me',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_customer' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		// Customer: Billing portal.
		register_rest_route(
			self::NAMESPACE,
			'/customer/billing-portal',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_billing_portal' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		// Customer: Upgrade/change plan.
		register_rest_route(
			self::NAMESPACE,
			'/customer/upgrade',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'upgrade_plan' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'tier' => [
						'required' => true,
						'type'     => 'string',
						'enum'     => [ 'starter', 'pro', 'business' ],
					],
				],
			]
		);

		// Customer: Cancel subscription.
		register_rest_route(
			self::NAMESPACE,
			'/customer/cancel',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'cancel_subscription' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		// Customer: Get invoices.
		register_rest_route(
			self::NAMESPACE,
			'/customer/invoices',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_invoices' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);
	}

	/**
	 * Search for domain availability.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function search_domain( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$domain = $request->get_param( 'domain' );
		
		// Validate domain format.
		if ( ! preg_match( '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z]{2,})+$/i', $domain ) ) {
			return new WP_Error(
				'invalid_domain',
				__( 'Invalid domain format', 'spawn' ),
				[ 'status' => 400 ]
			);
		}

		// Check with Name.com API.
		$result = Name_Com::check_availability( $domain );

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
	public static function create_checkout_session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$domain    = sanitize_text_field( $request->get_param( 'domain' ) );
		$tier      = sanitize_text_field( $request->get_param( 'tier' ) );
		$email     = sanitize_email( $request->get_param( 'email' ) );
		$subdomain = (bool) $request->get_param( 'subdomain' );

		// Get tier pricing.
		$tiers = self::get_tier_config();
		if ( ! isset( $tiers[ $tier ] ) ) {
			return new WP_Error(
				'invalid_tier',
				__( 'Invalid tier selected', 'spawn' ),
				[ 'status' => 400 ]
			);
		}

		$tier_config = $tiers[ $tier ];

		// Create Stripe checkout session.
		$session = Stripe::create_checkout_session( [
			'customer_email' => $email,
			'metadata'       => [
				'domain'    => $domain,
				'tier'      => $tier,
				'subdomain' => $subdomain ? '1' : '0',
			],
			'line_items'     => [
				[
					'price'    => $tier_config['stripe_price_id'],
					'quantity' => 1,
				],
			],
			'mode'           => 'subscription',
			'success_url'    => home_url( '/spawn/success?session_id={CHECKOUT_SESSION_ID}' ),
			'cancel_url'     => home_url( '/spawn/pricing' ),
		] );

		if ( is_wp_error( $session ) ) {
			return $session;
		}

		return new WP_REST_Response( [
			'session_id'   => $session['id'],
			'checkout_url' => $session['url'],
		] );
	}

	/**
	 * Get available tiers.
	 *
	 * @return WP_REST_Response Tiers response.
	 */
	public static function get_tiers(): WP_REST_Response {
		$tiers = self::get_tier_config();
		
		// Remove internal fields for public response.
		$public_tiers = [];
		foreach ( $tiers as $id => $tier ) {
			$public_tiers[ $id ] = [
				'name'        => $tier['name'],
				'price'       => $tier['price'],
				'description' => $tier['description'],
				'features'    => $tier['features'],
			];
		}

		return new WP_REST_Response( $public_tiers );
	}

	/**
	 * Handle user login.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function auth_login( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$email    = $request->get_param( 'email' );
		$password = $request->get_param( 'password' );

		// Authenticate user.
		$user = wp_authenticate( $email, $password );

		if ( is_wp_error( $user ) ) {
			return new WP_Error(
				'invalid_credentials',
				__( 'Invalid email or password.', 'spawn' ),
				[ 'status' => 401 ]
			);
		}

		// Set auth cookie.
		wp_set_auth_cookie( $user->ID, true );
		wp_set_current_user( $user->ID );

		return new WP_REST_Response( [
			'success' => true,
			'user'    => [
				'id'    => $user->ID,
				'email' => $user->user_email,
				'name'  => $user->display_name,
			],
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		] );
	}

	/**
	 * Handle user registration.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function auth_register( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$email    = $request->get_param( 'email' );
		$password = $request->get_param( 'password' );

		// Check if email already exists.
		if ( email_exists( $email ) ) {
			return new WP_Error(
				'email_exists',
				__( 'An account with this email already exists.', 'spawn' ),
				[ 'status' => 400 ]
			);
		}

		// Create user.
		$user_id = wp_create_user( $email, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			return new WP_Error(
				'registration_failed',
				$user_id->get_error_message(),
				[ 'status' => 400 ]
			);
		}

		// Set user role.
		$user = get_user_by( 'ID', $user_id );
		$user->set_role( 'spawn_customer' );

		// Set auth cookie.
		wp_set_auth_cookie( $user_id, true );
		wp_set_current_user( $user_id );

		return new WP_REST_Response( [
			'success' => true,
			'user'    => [
				'id'    => $user_id,
				'email' => $email,
				'name'  => $email,
			],
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		] );
	}

	/**
	 * Get current user info.
	 *
	 * @return WP_REST_Response Response.
	 */
	public static function auth_me(): WP_REST_Response {
		if ( ! is_user_logged_in() ) {
			return new WP_REST_Response( [
				'logged_in' => false,
			] );
		}

		$user = wp_get_current_user();

		return new WP_REST_Response( [
			'logged_in' => true,
			'user'      => [
				'id'    => $user->ID,
				'email' => $user->user_email,
				'name'  => $user->display_name,
			],
		] );
	}

	/**
	 * Handle user logout.
	 *
	 * @return WP_REST_Response Response.
	 */
	public static function auth_logout(): WP_REST_Response {
		wp_logout();

		return new WP_REST_Response( [
			'success' => true,
		] );
	}

	/**
	 * Get current customer data.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function get_customer(): WP_REST_Response|WP_Error {
		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer ) {
			return new WP_REST_Response( [
				'success'  => false,
				'customer' => null,
			] );
		}

		return new WP_REST_Response( [
			'success'  => true,
			'customer' => [
				'id'             => (int) $customer['id'],
				'domain'         => $customer['domain'],
				'subdomain'      => (bool) $customer['subdomain'],
				'vps_tier'       => $customer['vps_tier'],
				'ai_tier'        => $customer['ai_tier'],
				'ai_calls_used'  => (int) $customer['ai_calls_used'],
				'ai_calls_limit' => (int) $customer['ai_calls_limit'],
				'server_ip'      => $customer['server_ip'],
				'status'         => $customer['status'],
				'created_at'     => $customer['created_at'],
			],
		] );
	}

	/**
	 * Get Stripe billing portal URL.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function get_billing_portal(): WP_REST_Response|WP_Error {
		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer || empty( $customer['stripe_customer'] ) ) {
			return new WP_Error(
				'no_subscription',
				__( 'No active subscription found.', 'spawn' ),
				[ 'status' => 404 ]
			);
		}

		$portal = Stripe::create_billing_portal_session(
			$customer['stripe_customer'],
			home_url( '/spawn/dashboard/' )
		);

		if ( is_wp_error( $portal ) ) {
			return $portal;
		}

		return new WP_REST_Response( [
			'url' => $portal['url'],
		] );
	}

	/**
	 * Upgrade/change subscription plan.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function upgrade_plan( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );
		$new_tier = $request->get_param( 'tier' );

		if ( ! $customer ) {
			return new WP_Error(
				'no_subscription',
				__( 'No active subscription found.', 'spawn' ),
				[ 'status' => 404 ]
			);
		}

		// Map tier to VPS and AI tiers.
		$tier_map = [
			'starter'  => [ 'vps' => 'cx22', 'ai' => '1k' ],
			'pro'      => [ 'vps' => 'cx32', 'ai' => '5k' ],
			'business' => [ 'vps' => 'cx42', 'ai' => '20k' ],
		];

		if ( ! isset( $tier_map[ $new_tier ] ) ) {
			return new WP_Error(
				'invalid_tier',
				__( 'Invalid tier selected.', 'spawn' ),
				[ 'status' => 400 ]
			);
		}

		$tier_config = self::get_tier_config();
		$new_price_id = $tier_config[ $new_tier ]['stripe_price_id'] ?? '';

		// Update Stripe subscription if we have a subscription ID and price ID.
		if ( ! empty( $customer['stripe_subscription'] ) && ! empty( $new_price_id ) ) {
			$result = Stripe::update_subscription_price(
				$customer['stripe_subscription'],
				$new_price_id
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Update database.
		Database::update_vps_tier( (int) $customer['id'], $tier_map[ $new_tier ]['vps'] );
		Database::update_ai_tier( (int) $customer['id'], $tier_map[ $new_tier ]['ai'] );

		return new WP_REST_Response( [
			'success' => true,
			'tier'    => $new_tier,
		] );
	}

	/**
	 * Cancel subscription.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function cancel_subscription(): WP_REST_Response|WP_Error {
		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer ) {
			return new WP_Error(
				'no_subscription',
				__( 'No active subscription found.', 'spawn' ),
				[ 'status' => 404 ]
			);
		}

		// Cancel at period end in Stripe.
		if ( ! empty( $customer['stripe_subscription'] ) ) {
			$result = Stripe::cancel_subscription( $customer['stripe_subscription'] );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Update status in database.
		Database::update_customer( (int) $customer['id'], [
			'status'       => 'cancelled',
			'cancelled_at' => current_time( 'mysql' ),
		] );

		return new WP_REST_Response( [
			'success' => true,
		] );
	}

	/**
	 * Get customer invoices from Stripe.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function get_invoices(): WP_REST_Response|WP_Error {
		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer || empty( $customer['stripe_customer'] ) ) {
			return new WP_Error(
				'no_subscription',
				__( 'No active subscription found.', 'spawn' ),
				[ 'status' => 404 ]
			);
		}

		$invoices = Stripe::get_invoices( $customer['stripe_customer'] );

		if ( is_wp_error( $invoices ) ) {
			return $invoices;
		}

		return new WP_REST_Response( [
			'invoices' => $invoices,
		] );
	}

	/**
	 * Get tier configuration.
	 *
	 * @return array Tier configuration.
	 */
	private static function get_tier_config(): array {
		return [
			'starter'  => [
				'name'            => __( 'Starter', 'spawn' ),
				'price'           => 9,
				'description'     => __( 'Perfect for personal sites and blogs', 'spawn' ),
				'stripe_price_id' => get_option( 'spawn_stripe_price_starter', '' ),
				'features'        => [
					__( '2GB RAM, 1 vCPU', 'spawn' ),
					__( '1,000 AI calls/month', 'spawn' ),
					__( 'Custom domain', 'spawn' ),
					__( 'SSL included', 'spawn' ),
				],
			],
			'pro'      => [
				'name'            => __( 'Pro', 'spawn' ),
				'price'           => 9,
				'description'     => __( 'For growing businesses', 'spawn' ),
				'stripe_price_id' => get_option( 'spawn_stripe_price_pro', '' ),
				'features'        => [
					__( '4GB RAM, 2 vCPU', 'spawn' ),
					__( '5,000 AI calls/month', 'spawn' ),
					__( 'Custom domain', 'spawn' ),
					__( 'SSL included', 'spawn' ),
					__( 'Priority support', 'spawn' ),
				],
			],
			'business' => [
				'name'            => __( 'Business', 'spawn' ),
				'price'           => 99,
				'description'     => __( 'For high-traffic sites', 'spawn' ),
				'stripe_price_id' => get_option( 'spawn_stripe_price_business', '' ),
				'features'        => [
					__( '8GB RAM, 4 vCPU', 'spawn' ),
					__( '20,000 AI calls/month', 'spawn' ),
					__( 'Custom domain', 'spawn' ),
					__( 'SSL included', 'spawn' ),
					__( 'Priority support', 'spawn' ),
					__( 'Dedicated resources', 'spawn' ),
				],
			],
		];
	}
}
