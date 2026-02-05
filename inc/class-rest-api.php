<?php
/**
 * REST API endpoints.
 *
 * @package Spawn
 */

namespace Spawn;

use StripeIntegration\StripeClient;
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
					'domain'       => [
						'required' => false,
						'type'     => 'string',
					],
					'domain_type'  => [
						'type'    => 'string',
						'enum'    => [ 'subdomain', 'register', 'byod' ],
						'default' => 'subdomain',
					],
					'domain_price' => [
						'type'    => 'number',
						'default' => 0,
					],
					'tier'         => [
						'required' => true,
						'type'     => 'string',
						'enum'     => [ 'starter', 'pro', 'business' ],
					],
					'email'        => [
						'required' => true,
						'type'     => 'string',
						'format'   => 'email',
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

		// Credits: Get balance.
		register_rest_route(
			self::NAMESPACE,
			'/credits/balance',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_credit_balance' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		// Credits: Purchase credits (dynamic amount, $10 minimum).
		register_rest_route(
			self::NAMESPACE,
			'/credits/purchase',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'purchase_credits' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'amount' => [
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 10,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		// Credits: Deduct credits (internal/callback use).
		register_rest_route(
			self::NAMESPACE,
			'/credits/deduct',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'deduct_credits' ],
				'permission_callback' => [ __CLASS__, 'verify_internal_request' ],
				'args'                => [
					'customer_id' => [
						'required' => true,
						'type'     => 'integer',
					],
					'amount'      => [
						'required' => true,
						'type'     => 'number',
					],
					'reason'      => [
						'type'    => 'string',
						'default' => 'api_call',
					],
				],
			]
		);

		// Credits: Get available packages.
		register_rest_route(
			self::NAMESPACE,
			'/credits/packages',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_credit_packages' ],
				'permission_callback' => '__return_true',
			]
		);

		// Credits: Update auto-refill settings.
		register_rest_route(
			self::NAMESPACE,
			'/credits/auto-refill',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'update_auto_refill' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'enabled'   => [
						'required' => true,
						'type'     => 'boolean',
					],
					'threshold' => [
						'type'    => 'integer',
						'default' => 100,
					],
					'amount'    => [
						'type'    => 'integer',
						'default' => 1000,
					],
				],
			]
		);

		// LiteLLM: Usage callback for credit deduction.
		register_rest_route(
			self::NAMESPACE,
			'/litellm/callback',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'litellm_callback' ],
				'permission_callback' => [ __CLASS__, 'verify_litellm_callback' ],
			]
		);

		// Chat: Send message to customer's AI.
		register_rest_route(
			self::NAMESPACE,
			'/chat/send',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'chat_send' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'message'    => [
						'required' => true,
						'type'     => 'string',
					],
					'sessionKey' => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'context'    => [
						'type'    => 'object',
						'default' => [],
					],
				],
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

		// Apply markup to prices.
		$markup = self::get_domain_markup();
		if ( isset( $result['price'] ) && $result['price'] ) {
			$result['price'] = round( $result['price'] * $markup, 2 );
		}
		if ( isset( $result['renewal'] ) && $result['renewal'] ) {
			$result['renewal'] = round( $result['renewal'] * $markup, 2 );
		}

		return new WP_REST_Response( $result );
	}

	/**
	 * Get domain price markup multiplier.
	 *
	 * @return float Markup multiplier (e.g., 1.5 = 50% markup).
	 */
	private static function get_domain_markup(): float {
		return (float) get_option( 'spawn_domain_markup', 1.5 );
	}

	/**
	 * Create Stripe checkout session.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function create_checkout_session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$domain       = sanitize_text_field( $request->get_param( 'domain' ) ?? '' );
		$domain_type  = sanitize_text_field( $request->get_param( 'domain_type' ) ?? 'subdomain' );
		$domain_price = (float) $request->get_param( 'domain_price' );
		$tier         = sanitize_text_field( $request->get_param( 'tier' ) );
		$email        = sanitize_email( $request->get_param( 'email' ) );

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

		// Build line items.
		$line_items = [
			[
				'price'    => $tier_config['stripe_price_id'],
				'quantity' => 1,
			],
		];

		// Add domain registration as one-time fee if applicable.
		if ( 'register' === $domain_type && $domain_price > 0 ) {
			$line_items[] = [
				'price_data' => [
					'currency'     => 'usd',
					'unit_amount'  => (int) ( $domain_price * 100 ), // Stripe uses cents.
					'product_data' => [
						'name'        => sprintf(
							/* translators: %s: domain name */
							__( 'Domain Registration: %s', 'spawn' ),
							$domain
						),
						'description' => __( 'One-time domain registration fee (1 year)', 'spawn' ),
					],
				],
				'quantity'   => 1,
			];
		}

		// Create Stripe checkout session using shared stripe-integration plugin.
		// Save payment method for future charges (auto-refill credits).
		$session = StripeClient::create_checkout_session( [
			'customer_email'    => $email,
			'metadata'          => [
				'domain'       => $domain,
				'domain_type'  => $domain_type,
				'domain_price' => $domain_price,
				'tier'         => $tier,
				'source'       => 'spawn',
			],
			'line_items'        => $line_items,
			'mode'              => 'subscription',
			'payment_method_collection' => 'always',
			'subscription_data' => [
				'metadata' => [
					'tier'   => $tier,
					'source' => 'spawn',
				],
			],
			'success_url'       => home_url( '/spawn/success?session_id={CHECKOUT_SESSION_ID}' ),
			'cancel_url'        => home_url( '/spawn/' ),
		] );

		if ( is_wp_error( $session ) ) {
			return $session;
		}

		return new WP_REST_Response( [
			'url' => $session['url'],
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
				'credit_balance' => (float) $customer['credit_balance'],
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

		$portal = StripeClient::create_billing_portal_session(
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

		// Map tier to VPS tier (credits are separate, purchased as needed).
		$tier_map = [
			'starter'  => [ 'vps' => 'cpx11' ],
			'pro'      => [ 'vps' => 'cpx21' ],
			'business' => [ 'vps' => 'cpx31' ],
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
			$result = Payment_Helpers::update_subscription_price(
				$customer['stripe_subscription'],
				$new_price_id
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Update database.
		Database::update_vps_tier( (int) $customer['id'], $tier_map[ $new_tier ]['vps'] );

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
			$result = StripeClient::cancel_subscription( $customer['stripe_subscription'] );

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

		$invoices = StripeClient::get_invoices( $customer['stripe_customer'] );

		if ( is_wp_error( $invoices ) ) {
			return $invoices;
		}

		return new WP_REST_Response( [
			'invoices' => $invoices,
		] );
	}

	/**
	 * Get current customer's credit balance.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function get_credit_balance(): WP_REST_Response|WP_Error {
		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer ) {
			return new WP_Error(
				'no_customer',
				__( 'No customer account found.', 'spawn' ),
				[ 'status' => 404 ]
			);
		}

		$auto_refill = Database::get_auto_refill_settings( (int) $customer['id'] );

		return new WP_REST_Response( [
			'balance'     => (float) $customer['credit_balance'],
			'auto_refill' => $auto_refill,
		] );
	}

	/**
	 * Purchase credits (create Stripe checkout session).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function purchase_credits( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );
		$amount   = (int) $request->get_param( 'amount' );

		if ( ! $customer ) {
			return new WP_Error(
				'no_customer',
				__( 'No customer account found.', 'spawn' ),
				[ 'status' => 404 ]
			);
		}

		// Validate minimum $10.
		if ( $amount < 10 ) {
			return new WP_Error(
				'amount_too_low',
				__( 'Minimum purchase is $10.', 'spawn' ),
				[ 'status' => 400 ]
			);
		}

		// Credits = dollars * 100 (1 credit = $0.01).
		$credits = $amount * 100;

		// Create Stripe checkout session for one-time payment using Payment_Helpers.
		$session = Payment_Helpers::create_credit_checkout_session( [
			'customer_id'       => $customer['stripe_customer'] ?? null,
			'customer_email'    => $customer['email'],
			'amount'            => $amount * 100, // Stripe uses cents.
			'credits'           => $credits,
			'spawn_customer_id' => $customer['id'],
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
	 * Deduct credits from a customer (internal endpoint for LiteLLM callback).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function deduct_credits( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$customer_id = (int) $request->get_param( 'customer_id' );
		$amount      = (float) $request->get_param( 'amount' );
		$reason      = sanitize_text_field( $request->get_param( 'reason' ) );

		$customer = Database::get_customer( $customer_id );
		if ( ! $customer ) {
			return new WP_Error(
				'customer_not_found',
				__( 'Customer not found.', 'spawn' ),
				[ 'status' => 404 ]
			);
		}

		$current_balance = (float) $customer['credit_balance'];

		// Check if deduction would go negative.
		if ( $current_balance < $amount ) {
			return new WP_Error(
				'insufficient_credits',
				__( 'Insufficient credits.', 'spawn' ),
				[
					'status'   => 402,
					'balance'  => $current_balance,
					'required' => $amount,
				]
			);
		}

		$success = Database::deduct_credits( $customer_id, $amount );

		if ( ! $success ) {
			return new WP_Error(
				'deduction_failed',
				__( 'Failed to deduct credits.', 'spawn' ),
				[ 'status' => 500 ]
			);
		}

		$new_balance = Database::get_credit_balance( $customer_id );

		// Check if auto-refill is needed.
		$auto_refill = Database::get_auto_refill_settings( $customer_id );
		$refill_triggered = false;
		if ( $auto_refill && $auto_refill['enabled'] && $new_balance < $auto_refill['threshold'] ) {
			// Trigger auto-refill (this would typically queue a Stripe charge).
			do_action( 'spawn_credits_auto_refill_needed', $customer_id, $auto_refill );
			$refill_triggered = true;
		}

		return new WP_REST_Response( [
			'success'          => true,
			'previous_balance' => $current_balance,
			'deducted'         => $amount,
			'new_balance'      => $new_balance,
			'reason'           => $reason,
			'refill_triggered' => $refill_triggered,
		] );
	}

	/**
	 * Get available credit packages.
	 *
	 * @return WP_REST_Response Response.
	 */
	public static function get_credit_packages(): WP_REST_Response {
		return new WP_REST_Response( self::get_credit_packages_config() );
	}

	/**
	 * Update auto-refill settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function update_auto_refill( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer ) {
			return new WP_Error(
				'no_customer',
				__( 'No customer account found.', 'spawn' ),
				[ 'status' => 404 ]
			);
		}

		$enabled   = (bool) $request->get_param( 'enabled' );
		$threshold = (int) $request->get_param( 'threshold' );
		$amount    = (int) $request->get_param( 'amount' );

		// Validate threshold and amount.
		if ( $threshold < 0 || $threshold > 10000 ) {
			return new WP_Error(
				'invalid_threshold',
				__( 'Threshold must be between 0 and 10,000.', 'spawn' ),
				[ 'status' => 400 ]
			);
		}

		// Amount must match a valid package.
		$valid_amounts = [ 1000, 3000, 7500 ];
		if ( ! in_array( $amount, $valid_amounts, true ) ) {
			return new WP_Error(
				'invalid_amount',
				__( 'Amount must be 1000, 3000, or 7500 credits.', 'spawn' ),
				[ 'status' => 400 ]
			);
		}

		$success = Database::update_auto_refill(
			(int) $customer['id'],
			$enabled,
			$threshold,
			$amount
		);

		if ( ! $success ) {
			return new WP_Error(
				'update_failed',
				__( 'Failed to update auto-refill settings.', 'spawn' ),
				[ 'status' => 500 ]
			);
		}

		return new WP_REST_Response( [
			'success'  => true,
			'settings' => [
				'enabled'   => $enabled,
				'threshold' => $threshold,
				'amount'    => $amount,
			],
		] );
	}

	/**
	 * Verify internal request (for deduct endpoint).
	 * Checks for internal API key.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if valid, error otherwise.
	 */
	public static function verify_internal_request( WP_REST_Request $request ): bool|WP_Error {
		$api_key = $request->get_header( 'X-Spawn-Internal-Key' );
		$expected_key = get_option( 'spawn_internal_api_key', '' );

		if ( empty( $expected_key ) ) {
			return new WP_Error(
				'not_configured',
				__( 'Internal API key not configured.', 'spawn' ),
				[ 'status' => 500 ]
			);
		}

		if ( ! hash_equals( $expected_key, $api_key ?? '' ) ) {
			return new WP_Error(
				'unauthorized',
				__( 'Invalid internal API key.', 'spawn' ),
				[ 'status' => 401 ]
			);
		}

		return true;
	}

	/**
	 * Get credit packages configuration.
	 *
	 * @return array Credit packages.
	 */
	private static function get_credit_packages_config(): array {
		return [
			'small'  => [
				'name'        => __( 'Small', 'spawn' ),
				'credits'     => 1000,
				'price'       => 10,
				'description' => __( '1,000 credits for $10', 'spawn' ),
				'per_credit'  => 0.01,
			],
			'medium' => [
				'name'        => __( 'Medium', 'spawn' ),
				'credits'     => 3000,
				'price'       => 25,
				'description' => __( '3,000 credits for $25 (17% bonus)', 'spawn' ),
				'per_credit'  => 0.0083,
				'bonus'       => '17%',
			],
			'large'  => [
				'name'        => __( 'Large', 'spawn' ),
				'credits'     => 7500,
				'price'       => 50,
				'description' => __( '7,500 credits for $50 (50% bonus)', 'spawn' ),
				'per_credit'  => 0.0067,
				'bonus'       => '50%',
			],
		];
	}

	/**
	 * Get tier configuration.
	 *
	 * @return array Tier configuration.
	 */
	private static function get_tier_config(): array {
		// Get price IDs from stored options.
		$prices = get_option( 'spawn_stripe_prices', [] );

		return [
			'starter'  => [
				'name'            => __( 'Starter', 'spawn' ),
				'price'           => 19,
				'description'     => __( 'Perfect for personal sites and blogs', 'spawn' ),
				'stripe_price_id' => $prices['vps_starter'] ?? '',
				'hetzner_type'    => 'cx23',
				'features'        => [
					__( '4GB RAM, 2 vCPU, 40GB SSD', 'spawn' ),
					__( 'AI credits (pay-as-you-go)', 'spawn' ),
					__( 'Custom domain', 'spawn' ),
					__( 'SSL included', 'spawn' ),
				],
			],
			'pro'      => [
				'name'            => __( 'Pro', 'spawn' ),
				'price'           => 39,
				'description'     => __( 'For growing businesses', 'spawn' ),
				'stripe_price_id' => $prices['vps_pro'] ?? '',
				'hetzner_type'    => 'cx33',
				'features'        => [
					__( '8GB RAM, 4 vCPU, 80GB SSD', 'spawn' ),
					__( 'AI credits (pay-as-you-go)', 'spawn' ),
					__( 'Custom domain', 'spawn' ),
					__( 'SSL included', 'spawn' ),
					__( 'Priority support', 'spawn' ),
				],
			],
			'business' => [
				'name'            => __( 'Business', 'spawn' ),
				'price'           => 99,
				'description'     => __( 'For high-traffic sites', 'spawn' ),
				'stripe_price_id' => $prices['vps_business'] ?? '',
				'hetzner_type'    => 'cx43',
				'features'        => [
					__( '16GB RAM, 8 vCPU, 160GB SSD', 'spawn' ),
					__( 'AI credits (pay-as-you-go)', 'spawn' ),
					__( 'Custom domain', 'spawn' ),
					__( 'SSL included', 'spawn' ),
					__( 'Priority support', 'spawn' ),
					__( 'Dedicated resources', 'spawn' ),
				],
			],
		];
	}

	/**
	 * Send chat message to customer's AI.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function chat_send( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id     = get_current_user_id();
		$message     = sanitize_textarea_field( $request->get_param( 'message' ) );
		$session_key = sanitize_text_field( $request->get_param( 'sessionKey' ) );
		$context     = $request->get_param( 'context' );

		// Admin users chat with the control plane OpenClaw (if configured).
		if ( current_user_can( 'manage_options' ) ) {
			$gateway_url = get_option( 'spawn_openclaw_gateway_url', '' );
			if ( ! empty( $gateway_url ) ) {
				return self::chat_with_control_plane( $message, $session_key );
			}
			// No gateway configured - fall through to customer logic (will error).
		}

		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer ) {
			return new WP_Error(
				'no_customer',
				__( 'No customer account found.', 'spawn' ),
				[ 'status' => 404 ]
			);
		}

		if ( empty( $message ) ) {
			return new WP_Error(
				'empty_message',
				__( 'Message cannot be empty.', 'spawn' ),
				[ 'status' => 400 ]
			);
		}

		// For now, if no server IP yet (provisioning), use a placeholder response.
		if ( empty( $customer['server_ip'] ) || 'provisioning' === $customer['status'] ) {
			return new WP_REST_Response( [
				'reply' => "Your website is still being set up! This usually takes a few minutes. I'll be fully operational once it's ready. In the meantime, is there anything you'd like to plan for your site?",
			] );
		}

		// Build system context for the AI.
		$system_prompt = sprintf(
			"[Spawn Web Chat - Bootstrap Interface]\n" .
			"Platform: WordPress\n" .
			"Customer: %s\n" .
			"Site: %s\n" .
			"Status: %s\n" .
			"Mobile channel configured: %s\n\n" .
			"This is the Spawn web chat. Help the user with their WordPress site. " .
			"If they haven't set up mobile messaging yet, guide them through setting up " .
			"Telegram, Discord, or Signal for a better experience.",
			$customer['email'],
			$customer['domain'],
			$customer['status'],
			! empty( $context['has_mobile'] ) ? 'yes' : 'no'
		);

		// Customer's OpenClaw gateway - uses chat completions endpoint.
		// Gateway token stored during provisioning.
		$gateway_url   = 'http://' . $customer['server_ip'] . ':18789/v1/chat/completions';
		$gateway_token = $customer['openclaw_token'] ?? '';

		if ( empty( $gateway_token ) ) {
			return new WP_REST_Response( [
				'reply' => "I'm still getting configured. Try again in a moment!",
			] );
		}

		$payload = [
			'model'    => 'openclaw:main',
			'messages' => [
				[
					'role'    => 'system',
					'content' => $system_prompt,
				],
				[
					'role'    => 'user',
					'content' => $message,
				],
			],
		];

		$headers = [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $gateway_token,
		];

		if ( ! empty( $session_key ) ) {
			$headers['x-openclaw-session-key'] = $session_key;
		}

		$response = wp_remote_post( $gateway_url, [
			'headers' => $headers,
			'body'    => wp_json_encode( $payload ),
			'timeout' => 120,
		] );

		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response( [
				'reply' => "I'm having trouble connecting right now. Your site might be restarting. Try again in a moment!",
			] );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 ) {
			$error_msg = $body['error']['message'] ?? "HTTP $code";
			return new WP_REST_Response( [
				'reply' => "Something went wrong: $error_msg. Try again in a moment!",
			] );
		}

		$reply = $body['choices'][0]['message']['content'] ?? null;

		if ( empty( $reply ) ) {
			return new WP_REST_Response( [
				'reply' => "I didn't get a response. Could you try again?",
			] );
		}

		return new WP_REST_Response( [
			'reply' => $reply,
		] );
	}

	/**
	 * Admin chat with control plane OpenClaw instance.
	 *
	 * For SaaS operators to chat with their own agent managing the control plane.
	 * Configured via spawn_openclaw_gateway_url and spawn_openclaw_token options.
	 *
	 * @param string $message     User message.
	 * @param string $session_key Optional session key for conversation continuity.
	 * @return WP_REST_Response Response.
	 */
	private static function chat_with_control_plane( string $message, string $session_key = '' ): WP_REST_Response {
		$gateway_base  = rtrim( get_option( 'spawn_openclaw_gateway_url', '' ), '/' );
		$gateway_token = get_option( 'spawn_openclaw_token', '' );

		if ( empty( $gateway_token ) ) {
			return new WP_REST_Response( [
				'reply' => 'Control plane chat not configured. Set spawn_openclaw_token in Settings → Spawn.',
			] );
		}

		// Use OpenAI-compatible chat completions endpoint for synchronous response.
		$chat_url = $gateway_base . '/v1/chat/completions';

		// Get current user info for context.
		$current_user = wp_get_current_user();
		$site_name    = get_bloginfo( 'name' );
		$site_url     = home_url();

		$system_prompt = sprintf(
			"[Spawn Web Chat - Bootstrap Interface]\n" .
			"Platform: WordPress\n" .
			"Site: %s (%s)\n" .
			"User: %s <%s>\n" .
			"Interface: Web chat block (temporary)\n\n" .
			"This is the Spawn plugin's web-based chat interface. It exists to help users " .
			"get started before they set up a proper messaging channel (Telegram, Discord, Signal, etc.) " .
			"which OpenClaw supports natively. Help them configure a real messaging channel when appropriate.",
			$site_name,
			$site_url,
			$current_user->display_name ?: $current_user->user_login,
			$current_user->user_email
		);

		$payload = [
			'model'    => 'openclaw:main',
			'messages' => [
				[
					'role'    => 'system',
					'content' => $system_prompt,
				],
				[
					'role'    => 'user',
					'content' => $message,
				],
			],
		];

		$headers = [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $gateway_token,
		];

		// Pass session key for conversation continuity.
		if ( ! empty( $session_key ) ) {
			$headers['x-openclaw-session-key'] = $session_key;
		}

		$response = wp_remote_post( $chat_url, [
			'headers' => $headers,
			'body'    => wp_json_encode( $payload ),
			'timeout' => 120,
		] );

		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response( [
				'reply' => 'Connection failed: ' . $response->get_error_message(),
			] );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $code ) {
			return new WP_REST_Response( [
				'reply' => 'Authentication failed. Check spawn_openclaw_token matches your gateway auth token.',
			] );
		}

		if ( $code >= 400 ) {
			$error_msg = $body['error']['message'] ?? "HTTP $code";
			return new WP_REST_Response( [
				'reply' => "Gateway error: $error_msg",
			] );
		}

		// Extract reply from OpenAI chat completions response format.
		$reply = $body['choices'][0]['message']['content'] ?? null;

		if ( empty( $reply ) ) {
			return new WP_REST_Response( [
				'reply' => 'No response received from agent.',
			] );
		}

		return new WP_REST_Response( [
			'reply' => $reply,
		] );
	}

	/**
	 * Verify LiteLLM callback request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if valid, error otherwise.
	 */
	public static function verify_litellm_callback( WP_REST_Request $request ): bool|WP_Error {
		$auth_header = $request->get_header( 'Authorization' );
		$expected    = get_option( 'spawn_litellm_callback_secret', '' );

		// If no secret configured, allow (for initial setup).
		if ( empty( $expected ) ) {
			return true;
		}

		$token = str_replace( 'Bearer ', '', $auth_header ?? '' );
		if ( $token !== $expected ) {
			return new WP_Error(
				'unauthorized',
				__( 'Invalid callback secret.', 'spawn' ),
				[ 'status' => 401 ]
			);
		}

		return true;
	}

	/**
	 * Anthropic model pricing per MTok (pass-through, no markup).
	 * Opus 4.5 only - the only model capable enough for this use case.
	 */
	private const ANTHROPIC_PRICING = [
		'claude-opus-4-20250514'     => [ 'input' => 5.0, 'output' => 25.0 ],
		'claude-opus-4.5'            => [ 'input' => 5.0, 'output' => 25.0 ],
		'anthropic/claude-opus-4-20250514' => [ 'input' => 5.0, 'output' => 25.0 ],
		// Default = Opus pricing (only model we use).
		'default'                    => [ 'input' => 5.0, 'output' => 25.0 ],
	];

	/**
	 * Handle LiteLLM usage callback.
	 *
	 * Deducts credits based on actual token usage at pass-through pricing.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public static function litellm_callback( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();

		// LiteLLM sends usage data in various formats depending on callback type.
		// Standard format: model, usage.prompt_tokens, usage.completion_tokens, metadata.
		$model             = $body['model'] ?? $body['model_id'] ?? '';
		$usage             = $body['usage'] ?? [];
		$prompt_tokens     = (int) ( $usage['prompt_tokens'] ?? 0 );
		$completion_tokens = (int) ( $usage['completion_tokens'] ?? 0 );
		$metadata          = $body['metadata'] ?? $body['litellm_params']['metadata'] ?? [];
		$spawn_customer_id = (int) ( $metadata['spawn_customer_id'] ?? 0 );

		// Also check for user field (can be set as customer ID).
		if ( ! $spawn_customer_id && ! empty( $body['user'] ) ) {
			$spawn_customer_id = (int) $body['user'];
		}

		// Try to identify customer by IP if no ID provided.
		if ( ! $spawn_customer_id ) {
			$client_ip = $body['litellm_params']['metadata']['client_ip'] ?? '';
			if ( ! $client_ip ) {
				// Try X-Forwarded-For from the original request.
				$client_ip = $request->get_header( 'X-Forwarded-For' ) ?? '';
				$client_ip = explode( ',', $client_ip )[0] ?? '';
				$client_ip = trim( $client_ip );
			}
			
			if ( $client_ip ) {
				$customer = Database::get_customer_by_server_ip( $client_ip );
				if ( $customer ) {
					$spawn_customer_id = (int) $customer['id'];
					error_log( "LiteLLM callback: Identified customer $spawn_customer_id by IP $client_ip" );
				}
			}
		}

		if ( ! $spawn_customer_id ) {
			// Log but don't fail - might be a test or admin request.
			error_log( 'LiteLLM callback: No spawn_customer_id in metadata or by IP' );
			return new WP_REST_Response( [
				'status'  => 'skipped',
				'message' => 'No customer ID in metadata.',
			] );
		}

		if ( $prompt_tokens === 0 && $completion_tokens === 0 ) {
			return new WP_REST_Response( [
				'status'  => 'skipped',
				'message' => 'No tokens to charge.',
			] );
		}

		// Calculate cost based on model pricing.
		$pricing = self::ANTHROPIC_PRICING[ $model ] ?? self::ANTHROPIC_PRICING['default'];
		
		// Cost in dollars: (tokens / 1M) * price_per_MTok.
		$input_cost  = ( $prompt_tokens / 1_000_000 ) * $pricing['input'];
		$output_cost = ( $completion_tokens / 1_000_000 ) * $pricing['output'];
		$total_cost  = $input_cost + $output_cost;

		// Convert to credits (1 credit = $0.01).
		$credits_to_deduct = $total_cost * 100;

		// Minimum deduction of 0.01 credits to avoid rounding to zero.
		$credits_to_deduct = max( 0.01, round( $credits_to_deduct, 2 ) );

		// Deduct from customer.
		$customer = Database::get_customer( $spawn_customer_id );
		if ( ! $customer ) {
			error_log( "LiteLLM callback: Customer $spawn_customer_id not found" );
			return new WP_REST_Response( [
				'status'  => 'error',
				'message' => 'Customer not found.',
			], 404 );
		}

		$current_balance = (float) $customer['credit_balance'];

		// Deduct even if it goes negative (we'll handle blocking in pre-request).
		$success = Database::deduct_credits( $spawn_customer_id, $credits_to_deduct );

		if ( ! $success ) {
			error_log( "LiteLLM callback: Failed to deduct credits for customer $spawn_customer_id" );
			return new WP_REST_Response( [
				'status'  => 'error',
				'message' => 'Failed to deduct credits.',
			], 500 );
		}

		$new_balance = Database::get_credit_balance( $spawn_customer_id );

		// Check if auto-refill is needed.
		$auto_refill = Database::get_auto_refill_settings( $spawn_customer_id );
		if ( $auto_refill && $auto_refill['enabled'] && $new_balance < $auto_refill['threshold'] ) {
			do_action( 'spawn_credits_auto_refill_needed', $spawn_customer_id, $auto_refill );
		}

		return new WP_REST_Response( [
			'status'           => 'success',
			'model'            => $model,
			'prompt_tokens'    => $prompt_tokens,
			'completion_tokens'=> $completion_tokens,
			'cost_usd'         => round( $total_cost, 6 ),
			'credits_deducted' => $credits_to_deduct,
			'previous_balance' => $current_balance,
			'new_balance'      => $new_balance,
		] );
	}
}
