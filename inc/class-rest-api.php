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
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'rest_api_init', array( Controllers\Auth_Controller::class, 'register_routes' ) );
		add_action( 'rest_api_init', array( Controllers\Chat_Controller::class, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes(): void {
		// Domain search.
		register_rest_route(
			self::NAMESPACE,
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

		// Create checkout session.
		register_rest_route(
			self::NAMESPACE,
			'/checkout/session',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_checkout_session' ),
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

		// Get tiers/pricing.
		register_rest_route(
			self::NAMESPACE,
			'/tiers',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_tiers' ),
				'permission_callback' => '__return_true',
			)
		);

		// Get checkout/provisioning status by session ID.
		register_rest_route(
			self::NAMESPACE,
			'/checkout/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_checkout_status' ),
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

		// Auth: Login.
		register_rest_route(
			self::NAMESPACE,
			'/auth/login',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'auth_login' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'email'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'password' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);

		// Auth: Register.
		register_rest_route(
			self::NAMESPACE,
			'/auth/register',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'auth_register' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'email'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'password' => array(
						'required'  => true,
						'type'      => 'string',
						'minLength' => 8,
					),
				),
			)
		);

		// Auth: Get current user.
		register_rest_route(
			self::NAMESPACE,
			'/auth/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'auth_me' ),
				'permission_callback' => '__return_true',
			)
		);

		// Auth: Logout.
		register_rest_route(
			self::NAMESPACE,
			'/auth/logout',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'auth_logout' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		// Auth: Request password reset.
		register_rest_route(
			self::NAMESPACE,
			'/auth/forgot-password',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'auth_forgot_password' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'email' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
				),
			)
		);

		// Auth: Reset password with token.
		register_rest_route(
			self::NAMESPACE,
			'/auth/reset-password',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'auth_reset_password' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'email'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'token'    => array(
						'required' => true,
						'type'     => 'string',
					),
					'password' => array(
						'required'  => true,
						'type'      => 'string',
						'minLength' => 8,
					),
				),
			)
		);

		// Auth: Google OAuth configured.
		register_rest_route(
			self::NAMESPACE,
			'/auth/google/configured',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'auth_google_configured' ),
				'permission_callback' => '__return_true',
			)
		);

		// Auth: Start Google OAuth.
		register_rest_route(
			self::NAMESPACE,
			'/auth/google',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'auth_google_start' ),
				'permission_callback' => '__return_true',
			)
		);

		// Auth: Google OAuth callback.
		register_rest_route(
			self::NAMESPACE,
			'/auth/google/callback',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'auth_google_callback' ),
				'permission_callback' => '__return_true',
			)
		);

		// Customer: Get current customer.
		register_rest_route(
			self::NAMESPACE,
			'/customer/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_customer' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		// Customer: Billing portal.
		register_rest_route(
			self::NAMESPACE,
			'/customer/billing-portal',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_billing_portal' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		// Customer: Upgrade/change plan.
		register_rest_route(
			self::NAMESPACE,
			'/customer/upgrade',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'upgrade_plan' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'tier' => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'starter', 'pro', 'business' ),
					),
				),
			)
		);

		// Customer: Toggle website preference.
		register_rest_route(
			self::NAMESPACE,
			'/customer/toggle-website',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'toggle_website' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'wants_website' => array(
						'required' => true,
						'type'     => 'boolean',
					),
				),
			)
		);

		// Customer: Cancel subscription.
		register_rest_route(
			self::NAMESPACE,
			'/customer/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'cancel_subscription' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		// Customer: Get invoices.
		register_rest_route(
			self::NAMESPACE,
			'/customer/invoices',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_invoices' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		// Credits: Get balance.
		register_rest_route(
			self::NAMESPACE,
			'/credits/balance',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_credit_balance' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		// Credits: Purchase credits (dynamic amount, $10 minimum).
		register_rest_route(
			self::NAMESPACE,
			'/credits/purchase',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'purchase_credits' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'amount' => array(
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 10,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Credits: Deduct credits (internal/callback use).
		register_rest_route(
			self::NAMESPACE,
			'/credits/deduct',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'deduct_credits' ),
				'permission_callback' => array( __CLASS__, 'verify_internal_request' ),
				'args'                => array(
					'customer_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
					'amount'      => array(
						'required' => true,
						'type'     => 'number',
					),
					'reason'      => array(
						'type'    => 'string',
						'default' => 'api_call',
					),
				),
			)
		);

		// Credits: Get available packages.
		register_rest_route(
			self::NAMESPACE,
			'/credits/packages',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_credit_packages' ),
				'permission_callback' => '__return_true',
			)
		);

		// Credits: Get auto-refill settings.
		register_rest_route(
			self::NAMESPACE,
			'/account/auto-refill',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_auto_refill' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		// Credits: Update auto-refill settings.
		register_rest_route(
			self::NAMESPACE,
			'/account/auto-refill',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'update_auto_refill_settings' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'enabled'   => array(
						'required' => true,
						'type'     => 'boolean',
					),
					'threshold' => array(
						'type'    => 'number',
						'default' => 5.00,
						'minimum' => 1.00,
						'maximum' => 100.00,
					),
					'amount'    => array(
						'type'    => 'number',
						'default' => 10.00,
						'minimum' => 10.00,
						'maximum' => 100.00,
					),
				),
			)
		);

		// Legacy: Credits auto-refill (old endpoint, forwards to new one).
		register_rest_route(
			self::NAMESPACE,
			'/credits/auto-refill',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'update_auto_refill' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'enabled'   => array(
						'required' => true,
						'type'     => 'boolean',
					),
					'threshold' => array(
						'type'    => 'integer',
						'default' => 100,
					),
					'amount'    => array(
						'type'    => 'integer',
						'default' => 1000,
					),
				),
			)
		);

		// LiteLLM: Usage callback for credit deduction.
		register_rest_route(
			self::NAMESPACE,
			'/litellm/callback',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'litellm_callback' ),
				'permission_callback' => array( __CLASS__, 'verify_litellm_callback' ),
			)
		);

		// LiteLLM: Pre-request balance check.
		register_rest_route(
			self::NAMESPACE,
			'/balance/check',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'balance_check' ),
				'permission_callback' => '__return_true', // Called from local LiteLLM.
				'args'                => array(
					'ip' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Agent: Update billing mode (called from customer's agent).
		register_rest_route(
			self::NAMESPACE,
			'/agent/billing-mode',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'agent_set_billing_mode' ),
				'permission_callback' => '__return_true', // Auth by server IP lookup.
				'args'                => array(
					'ip'           => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'billing_mode' => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'managed', 'byok' ),
					),
				),
			)
		);

		// Agent: Get customer status (called from customer's agent).
		register_rest_route(
			self::NAMESPACE,
			'/agent/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'agent_get_status' ),
				'permission_callback' => '__return_true', // Auth by server IP lookup.
				'args'                => array(
					'ip' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Chat: Send message to customer's AI.
		register_rest_route(
			self::NAMESPACE,
			'/chat/send',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'chat_send' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'message'    => array(
						'required' => true,
						'type'     => 'string',
					),
					'sessionKey' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'context'    => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);

		// Chat: List sessions from customer's OpenClaw.
		register_rest_route(
			self::NAMESPACE,
			'/chat/sessions',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'chat_sessions_list' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		// Chat: Get session history from customer's OpenClaw.
		register_rest_route(
			self::NAMESPACE,
			'/chat/sessions/(?P<sessionKey>[a-zA-Z0-9_:-]+)/history',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'chat_session_history' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'sessionKey' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit'      => array(
						'type'    => 'integer',
						'default' => 50,
					),
				),
			)
		);

		// Chat: Generate session title via system agent.
		register_rest_route(
			self::NAMESPACE,
			'/chat/generate-title',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'chat_generate_title' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'username' => array(
						'type'              => 'string',
						'default'           => 'friend',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'wordBank' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Servers: List servers.
		register_rest_route(
			self::NAMESPACE,
			'/servers',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_servers' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		// Servers: Get server details.
		register_rest_route(
			self::NAMESPACE,
			'/servers/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_server' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Servers: Scale server tier.
		register_rest_route(
			self::NAMESPACE,
			'/servers/(?P<id>\d+)/scale',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'scale_server' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'id'   => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'tier' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Servers: Delete server.
		register_rest_route(
			self::NAMESPACE,
			'/servers/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'delete_server' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Domains: List domains.
		register_rest_route(
			self::NAMESPACE,
			'/domains',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_domains' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		// Domains: Assign domain to server.
		register_rest_route(
			self::NAMESPACE,
			'/domains/(?P<id>\d+)/assign',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'assign_domain' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'id'        => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'server_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Domains: Toggle auto-renew.
		register_rest_route(
			self::NAMESPACE,
			'/domains/(?P<id>\d+)/auto-renew',
			array(
				'methods'             => 'PUT',
				'callback'            => array( __CLASS__, 'update_domain_auto_renew_for_domain' ),
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

		// Usage: Get usage summary.
		register_rest_route(
			self::NAMESPACE,
			'/usage',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_usage' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'months' => array(
						'type'              => 'integer',
						'default'           => 3,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Account: Get domain auto-renew setting.
		register_rest_route(
			self::NAMESPACE,
			'/account/domain-auto-renew',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_domain_auto_renew' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		// Account: Update domain auto-renew setting.
		register_rest_route(
			self::NAMESPACE,
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

		// Provisioning status (for success page polling).
		register_rest_route(
			self::NAMESPACE,
			'/provisioning/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_provisioning_status' ),
				'permission_callback' => 'is_user_logged_in',
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
		$domain = $request->get_param( 'domain' );

		// Validate domain format.
		if ( ! preg_match( '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z]{2,})+$/i', $domain ) ) {
			return new WP_Error(
				'invalid_domain',
				__( 'Invalid domain format', 'spawn' ),
				array( 'status' => 400 )
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
	 * European country codes for region detection.
	 */
	private const EU_COUNTRIES = array(
		'AT',
		'BE',
		'BG',
		'HR',
		'CY',
		'CZ',
		'DK',
		'EE',
		'FI',
		'FR',
		'DE',
		'GR',
		'HU',
		'IE',
		'IT',
		'LV',
		'LT',
		'LU',
		'MT',
		'NL',
		'PL',
		'PT',
		'RO',
		'SK',
		'SI',
		'ES',
		'SE', // EU members
		'GB',
		'CH',
		'NO',
		'IS',
		'LI',
		'UA',
		'BY',
		'MD',
		'RS',
		'BA',
		'ME',
		'MK',
		'AL',
		'XK', // Other European countries
	);

	/**
	 * Detect customer region from IP/headers.
	 *
	 * Uses Cloudflare CF-IPCountry header if available, otherwise defaults to 'us'.
	 *
	 * @return string Region code ('us' or 'eu').
	 */
	private static function detect_customer_region(): string {
		// Cloudflare provides country code via CF-IPCountry header.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$country_code = isset( $_SERVER['HTTP_CF_IPCOUNTRY'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) )
			: '';

		if ( empty( $country_code ) || 'XX' === $country_code ) {
			// Fallback: default to US.
			return 'us';
		}

		// Check if country is in EU region.
		if ( in_array( $country_code, self::EU_COUNTRIES, true ) ) {
			return 'eu';
		}

		// Americas and rest of world default to US servers.
		return 'us';
	}

	/**
	 * Create Stripe checkout session.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function create_checkout_session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		// Stripe integration is required for checkout.
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

		// Get tier pricing.
		$tier_config = Config::get_tier( $tier );
		if ( ! $tier_config ) {
			return new WP_Error(
				'invalid_tier',
				__( 'Invalid tier selected', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		// Build line items.
		$line_items = array(
			array(
				'price'    => $tier_config['stripe_price_id'],
				'quantity' => 1,
			),
		);

		// Add domain registration as one-time fee if applicable.
		if ( $wants_website && 'register' === $domain_type && $domain_price > 0 ) {
			$line_items[] = array(
				'price_data' => array(
					'currency'     => 'usd',
					'unit_amount'  => (int) ( $domain_price * 100 ), // Stripe uses cents.
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

		// Create Stripe checkout session using shared stripe-integration plugin.
		// Save payment method for future charges (auto-refill credits).
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
	 * This endpoint is used by the success page to poll for provisioning status
	 * after a Stripe checkout is completed.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function get_checkout_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		// Stripe integration is required for checkout status.
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

		// Retrieve the Stripe checkout session to get customer info.
		$session = StripeClient::retrieve_checkout_session( $session_id );

		if ( is_wp_error( $session ) ) {
			return new WP_REST_Response( array(
				'success' => false,
				'status'  => 'not_found',
				'error'   => __( 'Session not found or expired.', 'spawn' ),
			) );
		}

		// Get customer email from session.
		$customer_email = $session['customer_email'] ?? $session['customer_details']['email'] ?? '';

		if ( empty( $customer_email ) ) {
			// Try to get from Stripe customer object.
			if ( ! empty( $session['customer'] ) ) {
				$stripe_customer = StripeClient::retrieve_customer( $session['customer'] );
				if ( ! is_wp_error( $stripe_customer ) ) {
					$customer_email = $stripe_customer['email'] ?? '';
				}
			}
		}

		if ( empty( $customer_email ) ) {
			return new WP_REST_Response( array(
				'success' => false,
				'status'  => 'not_found',
				'error'   => __( 'Could not determine customer from session.', 'spawn' ),
			) );
		}

		// Look up customer in our database by email.
		$customer = Database::get_customer_by_email( $customer_email );

		if ( ! $customer ) {
			// Customer record not yet created - webhook may not have fired yet.
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

		// Map customer status to provisioning progress.
		$status   = $customer['status'] ?? 'pending';
		$progress = self::get_provisioning_progress( $status, $customer );

		// Build response based on status.
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
	private static function get_provisioning_progress( string $status, array $customer ): array {
		$has_server_id = ! empty( $customer['hetzner_server_id'] ) || ! empty( $customer['server_id'] );
		$has_server_ip = ! empty( $customer['server_ip'] );
		$has_wordpress = ! empty( $customer['openclaw_token'] );
		$is_active     = in_array( $status, array( 'active', 'ready' ), true );

		// Determine which steps are complete.
		$payment_done   = true; // If we got here, payment is done.
		$server_done    = $has_server_id || $has_server_ip || $is_active;
		$wordpress_done = $has_wordpress || $is_active;
		$ai_done        = $is_active;

		// Calculate percentage.
		$steps_done = (int) $payment_done + (int) $server_done + (int) $wordpress_done + (int) $ai_done;
		$percent    = (int) ( ( $steps_done / 4 ) * 100 );

		// Adjust for in-progress states.
		if ( ! $is_active && $percent < 100 ) {
			// Add a bit more granularity based on status.
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
				array( 'status' => 401 )
			);
		}

		// Set auth cookie.
		wp_set_auth_cookie( $user->ID, true );
		wp_set_current_user( $user->ID );

		return new WP_REST_Response( array(
			'success' => true,
			'user'    => array(
				'id'    => $user->ID,
				'email' => $user->user_email,
				'name'  => $user->display_name,
			),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		) );
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
				array( 'status' => 400 )
			);
		}

		// Create user.
		$user_id = wp_create_user( $email, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			return new WP_Error(
				'registration_failed',
				$user_id->get_error_message(),
				array( 'status' => 400 )
			);
		}

		// Set user role.
		$user = get_user_by( 'ID', $user_id );
		$user->set_role( 'spawn_customer' );

		// Set auth cookie.
		wp_set_auth_cookie( $user_id, true );
		wp_set_current_user( $user_id );

		return new WP_REST_Response( array(
			'success' => true,
			'user'    => array(
				'id'    => $user_id,
				'email' => $email,
				'name'  => $email,
			),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		) );
	}

	/**
	 * Get current user info.
	 *
	 * @return WP_REST_Response Response.
	 */
	public static function auth_me(): WP_REST_Response {
		if ( ! is_user_logged_in() ) {
			return new WP_REST_Response( array(
				'logged_in' => false,
			) );
		}

		$user = wp_get_current_user();

		return new WP_REST_Response( array(
			'logged_in' => true,
			'user'      => array(
				'id'    => $user->ID,
				'email' => $user->user_email,
				'name'  => $user->display_name,
			),
		) );
	}

	/**
	 * Handle user logout.
	 *
	 * @return WP_REST_Response Response.
	 */
	public static function auth_logout(): WP_REST_Response {
		wp_logout();

		return new WP_REST_Response( array(
			'success' => true,
		) );
	}

	/**
	 * Request a password reset email.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public static function auth_forgot_password( WP_REST_Request $request ): WP_REST_Response {
		$email = $request->get_param( 'email' );
		$user  = get_user_by( 'email', $email );

		// Always return success to prevent email enumeration.
		if ( ! $user ) {
			return new WP_REST_Response( array(
				'success' => true,
				'message' => __( 'If an account exists with this email, a reset link has been sent.', 'spawn' ),
			) );
		}

		// Generate reset key.
		$reset_key = get_password_reset_key( $user );

		if ( is_wp_error( $reset_key ) ) {
			return new WP_REST_Response( array(
				'success' => true,
				'message' => __( 'If an account exists with this email, a reset link has been sent.', 'spawn' ),
			) );
		}

		// Build reset URL (goes to Spawn reset page, not wp-login.php).
		$reset_url = add_query_arg(
			array(
				'action' => 'reset',
				'key'    => $reset_key,
				'login'  => rawurlencode( $user->user_login ),
			),
			home_url( '/spawn/login/' )
		);

		// Send email.
		$site_name = get_bloginfo( 'name' );
		$subject   = sprintf( __( '[%s] Password Reset Request', 'spawn' ), $site_name );
		$message   = sprintf(
			__(
				"Hi %s,\n\n" .
				"Someone requested a password reset for your account.\n\n" .
				"To reset your password, click the link below:\n%s\n\n" .
				"This link will expire in 24 hours.\n\n" .
				"If you didn't request this, you can safely ignore this email.\n\n" .
				'— %s',
				'spawn'
			),
			$user->display_name ?: $user->user_login,
			$reset_url,
			$site_name
		);

		wp_mail( $email, $subject, $message );

		return new WP_REST_Response( array(
			'success' => true,
			'message' => __( 'If an account exists with this email, a reset link has been sent.', 'spawn' ),
		) );
	}

	/**
	 * Reset password with token.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function auth_reset_password( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$email    = $request->get_param( 'email' );
		$token    = $request->get_param( 'token' );
		$password = $request->get_param( 'password' );

		// Try to find user by email first, then by login.
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			$user = get_user_by( 'login', $email );
		}

		if ( ! $user ) {
			return new WP_Error(
				'invalid_reset',
				__( 'Invalid password reset request.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		// Verify the reset key.
		$check = check_password_reset_key( $token, $user->user_login );

		if ( is_wp_error( $check ) ) {
			return new WP_Error(
				'invalid_token',
				__( 'This reset link has expired or is invalid. Please request a new one.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		// Reset the password.
		reset_password( $user, $password );

		return new WP_REST_Response( array(
			'success' => true,
			'message' => __( 'Password has been reset. You can now log in.', 'spawn' ),
		) );
	}

	/**
	 * Check if Google OAuth is configured.
	 *
	 * @return WP_REST_Response Response.
	 */
	public static function auth_google_configured(): WP_REST_Response {
		return new WP_REST_Response( array(
			'configured' => Google_OAuth::is_configured(),
		) );
	}

	/**
	 * Start Google OAuth flow.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function auth_google_start(): WP_REST_Response|WP_Error {
		if ( ! Google_OAuth::is_configured() ) {
			return new WP_Error(
				'google_oauth_not_configured',
				__( 'Google OAuth is not configured.', 'spawn' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( array(
			'auth_url' => Google_OAuth::get_auth_url(),
		) );
	}

	/**
	 * Handle Google OAuth callback.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function auth_google_callback( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$state = $request->get_param( 'state' );
		$code  = $request->get_param( 'code' );

		$state = sanitize_text_field( wp_unslash( $state ?? '' ) );
		$code  = sanitize_text_field( wp_unslash( $code ?? '' ) );

		if ( empty( $state ) || ! wp_verify_nonce( $state, 'spawn_google_oauth' ) ) {
			return new WP_Error(
				'invalid_state',
				__( 'Invalid OAuth state.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $code ) ) {
			return new WP_Error(
				'missing_code',
				__( 'Missing authorization code.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		$token = Google_OAuth::exchange_code_for_token( $code );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$user_info = Google_OAuth::get_user_info( $token['access_token'] );
		if ( is_wp_error( $user_info ) ) {
			return $user_info;
		}

		$user_id = Google_OAuth::find_or_create_user( $user_info );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		wp_safe_redirect( home_url( '/spawn/dashboard/' ) );
		exit;
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
			return new WP_REST_Response( array(
				'success'  => false,
				'customer' => null,
			) );
		}

		return new WP_REST_Response( array(
			'success'  => true,
			'customer' => array(
				'id'             => (int) $customer['id'],
				'domain'         => $customer['domain'],
				'subdomain'      => (bool) $customer['subdomain'],
				'tier'           => $customer['tier'] ?? 'starter',
				'billing_mode'   => $customer['billing_mode'] ?? 'managed',
				'wants_website'  => (bool) ( $customer['wants_website'] ?? true ),
				'hetzner_type'   => $customer['hetzner_type'] ?? 'cpx21',
				'credit_balance' => (float) $customer['credit_balance'],
				'server_ip'      => $customer['server_ip'],
				'status'         => $customer['status'],
				'created_at'     => $customer['created_at'],
			),
		) );
	}

	/**
	 * Get Stripe billing portal URL.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function get_billing_portal(): WP_REST_Response|WP_Error {
		// Stripe integration is required for billing portal.
		if ( ! class_exists( '\\StripeIntegration\\StripeClient' ) ) {
			return new WP_Error(
				'stripe_not_available',
				__( 'Billing portal is not available on this site.', 'spawn' ),
				array( 'status' => 503 )
			);
		}

		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer || empty( $customer['stripe_customer'] ) ) {
			return new WP_Error(
				'no_subscription',
				__( 'No active subscription found.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		$portal = StripeClient::create_billing_portal_session(
			$customer['stripe_customer'],
			home_url( '/spawn/dashboard/' )
		);

		if ( is_wp_error( $portal ) ) {
			return $portal;
		}

		return new WP_REST_Response( array(
			'url' => $portal['url'],
		) );
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
				array( 'status' => 404 )
			);
		}

		// Get tier config from single source of truth.
		$tier_config = Config::get_tier( $new_tier );
		if ( ! $tier_config ) {
			return new WP_Error(
				'invalid_tier',
				__( 'Invalid tier selected.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		$new_price_id = $tier_config['stripe_price_id'] ?? '';

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

		// Pro-rate credits on upgrade.
		$old_tier       = $customer['tier'] ?? 'starter';
		$old_credits    = Config::get_included_credits( $old_tier );
		$new_credits    = Config::get_included_credits( $new_tier );
		$credits_to_add = 0;

		if ( $new_credits > $old_credits ) {
			$credits_to_add = $new_credits - $old_credits;
			Database::add_credits( (int) $customer['id'], $credits_to_add );

			error_log( sprintf(
				'[Spawn] Pro-rated credits for customer #%d: %s → %s, added $%.2f',
				$customer['id'],
				$old_tier,
				$new_tier,
				$credits_to_add
			) );
		}
		// On downgrade: don't remove credits (they already paid for them).

		// Update database.
		Database::update_tier( (int) $customer['id'], $new_tier );

		return new WP_REST_Response( array(
			'success'       => true,
			'tier'          => $new_tier,
			'credits_added' => $credits_to_add,
			'new_balance'   => Database::get_credit_balance( (int) $customer['id'] ),
		) );
	}

	/**
	 * Toggle website preference for customer.
	 *
	 * Note: This only updates the preference record. The actual server
	 * type is determined at creation time and cannot be changed after
	 * provisioning.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function toggle_website( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id       = get_current_user_id();
		$customer      = Database::get_customer_by_user_id( $user_id );
		$wants_website = (bool) $request->get_param( 'wants_website' );

		if ( ! $customer ) {
			return new WP_Error(
				'no_subscription',
				__( 'No active subscription found.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		// Update preference (note: doesn't change server specs after provisioning).
		Database::update_wants_website( (int) $customer['id'], $wants_website );

		return new WP_REST_Response( array(
			'success'       => true,
			'wants_website' => $wants_website,
			'note'          => 'active' === $customer['status']
				? __( 'Preference updated. Server type cannot be changed after provisioning.', 'spawn' )
				: __( 'Preference updated.', 'spawn' ),
		) );
	}

	/**
	 * Cancel subscription.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function cancel_subscription(): WP_REST_Response|WP_Error {
		// Stripe integration is required for subscription management.
		if ( ! class_exists( '\\StripeIntegration\\StripeClient' ) ) {
			return new WP_Error(
				'stripe_not_available',
				__( 'Subscription management is not available on this site.', 'spawn' ),
				array( 'status' => 503 )
			);
		}

		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer ) {
			return new WP_Error(
				'no_subscription',
				__( 'No active subscription found.', 'spawn' ),
				array( 'status' => 404 )
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
		Database::update_customer( (int) $customer['id'], array(
			'status'       => 'cancelled',
			'cancelled_at' => current_time( 'mysql' ),
		) );

		return new WP_REST_Response( array(
			'success' => true,
		) );
	}

	/**
	 * Get customer invoices from Stripe.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function get_invoices(): WP_REST_Response|WP_Error {
		// Stripe integration is required for invoices.
		if ( ! class_exists( '\\StripeIntegration\\StripeClient' ) ) {
			return new WP_Error(
				'stripe_not_available',
				__( 'Invoice history is not available on this site.', 'spawn' ),
				array( 'status' => 503 )
			);
		}

		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer || empty( $customer['stripe_customer'] ) ) {
			return new WP_Error(
				'no_subscription',
				__( 'No active subscription found.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		$invoices = StripeClient::get_invoices( $customer['stripe_customer'] );

		if ( is_wp_error( $invoices ) ) {
			return $invoices;
		}

		return new WP_REST_Response( array(
			'invoices' => $invoices,
		) );
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
				array( 'status' => 404 )
			);
		}

		$auto_refill = Database::get_auto_refill_settings( (int) $customer['id'] );

		return new WP_REST_Response( array(
			'balance'     => (float) $customer['credit_balance'],
			'auto_refill' => $auto_refill,
		) );
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
				array( 'status' => 404 )
			);
		}

		// Validate minimum $10.
		if ( $amount < 10 ) {
			return new WP_Error(
				'amount_too_low',
				__( 'Minimum purchase is $10.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		// Credits = dollars * 100 (1 credit = $0.01).
		$credits = $amount * 100;

		// Create Stripe checkout session for one-time payment using Payment_Helpers.
		$session = Payment_Helpers::create_credit_checkout_session( array(
			'customer_id'       => $customer['stripe_customer'] ?? null,
			'customer_email'    => $customer['email'],
			'amount'            => $amount * 100, // Stripe uses cents.
			'credits'           => $credits,
			'spawn_customer_id' => $customer['id'],
		) );

		if ( is_wp_error( $session ) ) {
			return $session;
		}

		return new WP_REST_Response( array(
			'session_id'   => $session['id'],
			'checkout_url' => $session['url'],
		) );
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
				array( 'status' => 404 )
			);
		}

		$current_balance = (float) $customer['credit_balance'];

		// Check if deduction would go negative.
		if ( $current_balance < $amount ) {
			return new WP_Error(
				'insufficient_credits',
				__( 'Insufficient credits.', 'spawn' ),
				array(
					'status'   => 402,
					'balance'  => $current_balance,
					'required' => $amount,
				)
			);
		}

		$success = Database::deduct_credits( $customer_id, $amount );

		if ( ! $success ) {
			return new WP_Error(
				'deduction_failed',
				__( 'Failed to deduct credits.', 'spawn' ),
				array( 'status' => 500 )
			);
		}

		$new_balance = Database::get_credit_balance( $customer_id );

		// Check if auto-refill is needed.
		$auto_refill      = Database::get_auto_refill_settings( $customer_id );
		$refill_triggered = false;
		if ( $auto_refill && $auto_refill['enabled'] && $new_balance < $auto_refill['threshold'] ) {
			// Trigger auto-refill (this would typically queue a Stripe charge).
			do_action( 'spawn_credits_auto_refill_needed', $customer_id, $auto_refill );
			$refill_triggered = true;
		}

		return new WP_REST_Response( array(
			'success'          => true,
			'previous_balance' => $current_balance,
			'deducted'         => $amount,
			'new_balance'      => $new_balance,
			'reason'           => $reason,
			'refill_triggered' => $refill_triggered,
		) );
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
	 * List servers for current user.
	 *
	 * @return WP_REST_Response Response.
	 */
	public static function get_servers(): WP_REST_Response {
		$user_id = get_current_user_id();
		$servers = Database::get_servers_by_user( $user_id );

		return new WP_REST_Response( array(
			'servers' => array_map( array( __CLASS__, 'format_server' ), $servers ),
		) );
	}

	/**
	 * Get a server for current user.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function get_server( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$server_id = (int) $request->get_param( 'id' );
		$server    = Database::get_server( $server_id );

		if ( ! $server ) {
			return new WP_Error(
				'server_not_found',
				__( 'Server not found.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		if ( (int) $server['user_id'] !== get_current_user_id() ) {
			return new WP_Error(
				'forbidden',
				__( 'You do not have access to this server.', 'spawn' ),
				array( 'status' => 403 )
			);
		}

		return new WP_REST_Response( array(
			'server' => self::format_server( $server ),
		) );
	}

	/**
	 * Scale a server tier.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function scale_server( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$server_id = (int) $request->get_param( 'id' );
		$new_tier  = sanitize_text_field( $request->get_param( 'tier' ) );
		$server    = Database::get_server( $server_id );

		if ( ! $server ) {
			return new WP_Error(
				'server_not_found',
				__( 'Server not found.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		if ( (int) $server['user_id'] !== get_current_user_id() ) {
			return new WP_Error(
				'forbidden',
				__( 'You do not have access to this server.', 'spawn' ),
				array( 'status' => 403 )
			);
		}

		if ( empty( $new_tier ) ) {
			return new WP_Error(
				'missing_tier',
				__( 'Tier is required.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		$tier_config = Config::get_tier( $new_tier );
		if ( ! $tier_config ) {
			return new WP_Error(
				'invalid_tier',
				__( 'Invalid tier selected.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		$updated = Database::update_server( $server_id, array(
			'tier' => $new_tier,
		) );

		if ( ! $updated ) {
			return new WP_Error(
				'update_failed',
				__( 'Failed to update server.', 'spawn' ),
				array( 'status' => 500 )
			);
		}

		$server = Database::get_server( $server_id );

		return new WP_REST_Response( array(
			'success' => true,
			'server'  => self::format_server( $server ?? array() ),
		) );
	}

	/**
	 * Delete a server.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function delete_server( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$server_id = (int) $request->get_param( 'id' );
		$server    = Database::get_server( $server_id );

		if ( ! $server ) {
			return new WP_Error(
				'server_not_found',
				__( 'Server not found.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		if ( (int) $server['user_id'] !== get_current_user_id() ) {
			return new WP_Error(
				'forbidden',
				__( 'You do not have access to this server.', 'spawn' ),
				array( 'status' => 403 )
			);
		}

		$deleted = Database::delete_server( $server_id );

		if ( ! $deleted ) {
			return new WP_Error(
				'delete_failed',
				__( 'Failed to delete server.', 'spawn' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( array(
			'success' => true,
		) );
	}

	/**
	 * List domains for current user.
	 *
	 * @return WP_REST_Response Response.
	 */
	public static function get_domains(): WP_REST_Response {
		$user_id = get_current_user_id();
		$domains = Database::get_domains_by_user( $user_id );

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
	public static function assign_domain( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$domain_id = (int) $request->get_param( 'id' );
		$server_id = $request->get_param( 'server_id' );
		if ( null === $server_id || '' === $server_id || 0 === (int) $server_id ) {
			$server_id = null;
		} else {
			$server_id = (int) $server_id;
		}
		$domain = Database::get_domain( $domain_id );

		if ( ! $domain ) {
			return new WP_Error(
				'domain_not_found',
				__( 'Domain not found.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		if ( (int) $domain['user_id'] !== get_current_user_id() ) {
			return new WP_Error(
				'forbidden',
				__( 'You do not have access to this domain.', 'spawn' ),
				array( 'status' => 403 )
			);
		}

		if ( null !== $server_id ) {
			$server = Database::get_server( $server_id );
			if ( ! $server ) {
				return new WP_Error(
					'server_not_found',
					__( 'Server not found.', 'spawn' ),
					array( 'status' => 404 )
				);
			}

			if ( (int) $server['user_id'] !== get_current_user_id() ) {
				return new WP_Error(
					'forbidden',
					__( 'You do not have access to this server.', 'spawn' ),
					array( 'status' => 403 )
				);
			}
		}

		$updated = Database::assign_domain_to_server( $domain_id, $server_id );

		if ( ! $updated ) {
			return new WP_Error(
				'update_failed',
				__( 'Failed to assign domain.', 'spawn' ),
				array( 'status' => 500 )
			);
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
	public static function update_domain_auto_renew_for_domain( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$domain_id = (int) $request->get_param( 'id' );
		$enabled   = (bool) $request->get_param( 'enabled' );
		$domain    = Database::get_domain( $domain_id );

		if ( ! $domain ) {
			return new WP_Error(
				'domain_not_found',
				__( 'Domain not found.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		if ( (int) $domain['user_id'] !== get_current_user_id() ) {
			return new WP_Error(
				'forbidden',
				__( 'You do not have access to this domain.', 'spawn' ),
				array( 'status' => 403 )
			);
		}

		$updated = Database::update_domain( $domain_id, array(
			'auto_renew' => $enabled ? 1 : 0,
		) );

		if ( ! $updated ) {
			return new WP_Error(
				'update_failed',
				__( 'Failed to update domain.', 'spawn' ),
				array( 'status' => 500 )
			);
		}

		$domain = Database::get_domain( $domain_id );

		return new WP_REST_Response( array(
			'success' => true,
			'domain'  => self::format_domain( $domain ?? array() ),
		) );
	}

	/**
	 * Get usage summary for current user.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public static function get_usage( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$months  = (int) $request->get_param( 'months' );
		$months  = $months > 0 ? $months : 3;
		$usage   = Database::get_user_usage( $user_id, $months );

		return new WP_REST_Response( array(
			'usage' => array_map( array( __CLASS__, 'format_usage_period' ), $usage ),
		) );
	}

	/**
	 * Format server response for frontend.
	 *
	 * @param array $server Server data.
	 * @return array Formatted server.
	 */
	private static function format_server( array $server ): array {
		return array(
			'id'            => (int) ( $server['id'] ?? 0 ),
			'name'          => $server['name'] ?? '',
			'tier'          => $server['tier'] ?? 'starter',
			'status'        => $server['status'] ?? 'pending',
			'server_ip'     => $server['server_ip'] ?? null,
			'has_wordpress' => ! empty( $server['has_wordpress'] ),
			'created_at'    => $server['created_at'] ?? null,
		);
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
	 * Format usage period response for frontend.
	 *
	 * @param array $usage Usage data.
	 * @return array Formatted usage.
	 */
	private static function format_usage_period( array $usage ): array {
		return array(
			'period_start'   => $usage['period_start'] ?? null,
			'period_end'     => $usage['period_end'] ?? null,
			'credits_used'   => (float) ( $usage['credits_used'] ?? 0 ),
			'requests_count' => (int) ( $usage['requests_count'] ?? 0 ),
			'tokens_input'   => (int) ( $usage['tokens_input'] ?? 0 ),
			'tokens_output'  => (int) ( $usage['tokens_output'] ?? 0 ),
		);
	}

	/**
	 * Get auto-refill settings for the current customer.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function get_auto_refill(): WP_REST_Response|WP_Error {
		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer ) {
			return new WP_Error(
				'no_customer',
				__( 'No customer account found.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		$settings = Database::get_auto_refill_settings( (int) $customer['id'] );

		return new WP_REST_Response( array(
			'enabled'   => $settings['enabled'] ?? false,
			'threshold' => (float) ( $settings['threshold'] ?? 5.00 ),
			'amount'    => (float) ( $settings['amount'] ?? 10.00 ),
		) );
	}

	/**
	 * Update auto-refill settings (new endpoint with dollar amounts).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function update_auto_refill_settings( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer ) {
			return new WP_Error(
				'no_customer',
				__( 'No customer account found.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		$enabled   = (bool) $request->get_param( 'enabled' );
		$threshold = (float) $request->get_param( 'threshold' );
		$amount    = (float) $request->get_param( 'amount' );

		// Validate threshold (in dollars).
		if ( $threshold < 1.00 || $threshold > 100.00 ) {
			return new WP_Error(
				'invalid_threshold',
				__( 'Threshold must be between $1 and $100.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		// Validate amount (in dollars, minimum $10).
		if ( $amount < 10.00 || $amount > 100.00 ) {
			return new WP_Error(
				'invalid_amount',
				__( 'Refill amount must be between $10 and $100.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		$success = Database::update_auto_refill_settings(
			(int) $customer['id'],
			$enabled,
			$threshold,
			$amount
		);

		if ( ! $success ) {
			return new WP_Error(
				'update_failed',
				__( 'Failed to update auto-refill settings.', 'spawn' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( array(
			'success'  => true,
			'settings' => array(
				'enabled'   => $enabled,
				'threshold' => $threshold,
				'amount'    => $amount,
			),
		) );
	}

	/**
	 * Update auto-refill settings (legacy endpoint with credit amounts).
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
				array( 'status' => 404 )
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
				array( 'status' => 400 )
			);
		}

		// Amount must match a valid package.
		$valid_amounts = array( 1000, 3000, 7500 );
		if ( ! in_array( $amount, $valid_amounts, true ) ) {
			return new WP_Error(
				'invalid_amount',
				__( 'Amount must be 1000, 3000, or 7500 credits.', 'spawn' ),
				array( 'status' => 400 )
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
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( array(
			'success'  => true,
			'settings' => array(
				'enabled'   => $enabled,
				'threshold' => $threshold,
				'amount'    => $amount,
			),
		) );
	}

	/**
	 * Verify internal request (for deduct endpoint).
	 * Checks for internal API key.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if valid, error otherwise.
	 */
	public static function verify_internal_request( WP_REST_Request $request ): bool|WP_Error {
		$api_key      = $request->get_header( 'X-Spawn-Internal-Key' );
		$expected_key = get_option( 'spawn_internal_api_key', '' );

		if ( empty( $expected_key ) ) {
			return new WP_Error(
				'not_configured',
				__( 'Internal API key not configured.', 'spawn' ),
				array( 'status' => 500 )
			);
		}

		if ( ! hash_equals( $expected_key, $api_key ?? '' ) ) {
			return new WP_Error(
				'unauthorized',
				__( 'Invalid internal API key.', 'spawn' ),
				array( 'status' => 401 )
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
		return array(
			'small'  => array(
				'name'        => __( 'Small', 'spawn' ),
				'credits'     => 1000,
				'price'       => 10,
				'description' => __( '1,000 credits for $10', 'spawn' ),
				'per_credit'  => 0.01,
			),
			'medium' => array(
				'name'        => __( 'Medium', 'spawn' ),
				'credits'     => 3000,
				'price'       => 25,
				'description' => __( '3,000 credits for $25 (17% bonus)', 'spawn' ),
				'per_credit'  => 0.0083,
				'bonus'       => '17%',
			),
			'large'  => array(
				'name'        => __( 'Large', 'spawn' ),
				'credits'     => 7500,
				'price'       => 50,
				'description' => __( '7,500 credits for $50 (50% bonus)', 'spawn' ),
				'per_credit'  => 0.0067,
				'bonus'       => '50%',
			),
		);
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
				array( 'status' => 404 )
			);
		}

		if ( empty( $message ) ) {
			return new WP_Error(
				'empty_message',
				__( 'Message cannot be empty.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		// For now, if no server IP yet (provisioning), use a placeholder response.
		if ( empty( $customer['server_ip'] ) || 'provisioning' === $customer['status'] ) {
			return new WP_REST_Response( array(
				'reply' => "Your website is still being set up! This usually takes a few minutes. I'll be fully operational once it's ready. In the meantime, is there anything you'd like to plan for your site?",
			) );
		}

		// Build system context for the AI.
		$system_prompt = sprintf(
			"[Spawn Web Chat - Bootstrap Interface]\n" .
			"Platform: WordPress\n" .
			"Customer: %s\n" .
			"Site: %s\n" .
			"Status: %s\n" .
			"Mobile channel configured: %s\n\n" .
			'This is the Spawn web chat. Help the user with their WordPress site. ' .
			"If they haven't set up mobile messaging yet, guide them through setting up " .
			'Telegram, Discord, or Signal for a better experience.',
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
			return new WP_REST_Response( array(
				'reply' => "I'm still getting configured. Try again in a moment!",
			) );
		}

		$payload = array(
			'model'    => 'openclaw:main',
			'messages' => array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
				),
				array(
					'role'    => 'user',
					'content' => $message,
				),
			),
		);

		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $gateway_token,
		);

		if ( ! empty( $session_key ) ) {
			$headers['x-openclaw-session-key'] = $session_key;
		}

		$response = wp_remote_post( $gateway_url, array(
			'headers' => $headers,
			'body'    => wp_json_encode( $payload ),
			'timeout' => 120,
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response( array(
				'reply' => "I'm having trouble connecting right now. Your site might be restarting. Try again in a moment!",
			) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 ) {
			$error_msg = $body['error']['message'] ?? "HTTP $code";
			return new WP_REST_Response( array(
				'reply' => "Something went wrong: $error_msg. Try again in a moment!",
			) );
		}

		$reply = $body['choices'][0]['message']['content'] ?? null;

		if ( empty( $reply ) ) {
			return new WP_REST_Response( array(
				'reply' => "I didn't get a response. Could you try again?",
			) );
		}

		return new WP_REST_Response( array(
			'reply' => $reply,
		) );
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
			return new WP_REST_Response( array(
				'reply' => 'Control plane chat not configured. Set spawn_openclaw_token in Settings → Spawn.',
			) );
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
			'get started before they set up a proper messaging channel (Telegram, Discord, Signal, etc.) ' .
			'which OpenClaw supports natively. Help them configure a real messaging channel when appropriate.',
			$site_name,
			$site_url,
			$current_user->display_name ?: $current_user->user_login,
			$current_user->user_email
		);

		$payload = array(
			'model'    => 'openclaw:main',
			'messages' => array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
				),
				array(
					'role'    => 'user',
					'content' => $message,
				),
			),
		);

		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $gateway_token,
		);

		// Pass session key for conversation continuity.
		if ( ! empty( $session_key ) ) {
			$headers['x-openclaw-session-key'] = $session_key;
		}

		$response = wp_remote_post( $chat_url, array(
			'headers' => $headers,
			'body'    => wp_json_encode( $payload ),
			'timeout' => 120,
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response( array(
				'reply' => 'Connection failed: ' . $response->get_error_message(),
			) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $code ) {
			return new WP_REST_Response( array(
				'reply' => 'Authentication failed. Check spawn_openclaw_token matches your gateway auth token.',
			) );
		}

		if ( $code >= 400 ) {
			$error_msg = $body['error']['message'] ?? "HTTP $code";
			return new WP_REST_Response( array(
				'reply' => "Gateway error: $error_msg",
			) );
		}

		// Extract reply from OpenAI chat completions response format.
		$reply = $body['choices'][0]['message']['content'] ?? null;

		if ( empty( $reply ) ) {
			return new WP_REST_Response( array(
				'reply' => 'No response received from agent.',
			) );
		}

		return new WP_REST_Response( array(
			'reply' => $reply,
		) );
	}

	/**
	 * Generate a creative session title using Data Machine's system agent.
	 *
	 * Uses word bank + username to generate a fun title without
	 * accessing user's chat content (privacy-safe).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with title.
	 */
	public static function chat_generate_title( WP_REST_Request $request ): WP_REST_Response {
		$username  = sanitize_text_field( $request->get_param( 'username' ) ) ?: 'friend';
		$word_bank = sanitize_text_field( $request->get_param( 'wordBank' ) );

		// Default word bank if not provided.
		if ( empty( $word_bank ) ) {
			$word_bank = 'curious, mystical, cosmic, enchanted, wandering, crow, phoenix, bloom, quest, star';
		}

		$words = array_map( 'trim', explode( ',', $word_bank ) );

		// Try Data Machine's RequestBuilder if available.
		if ( class_exists( '\\DataMachine\\Engine\\AI\\RequestBuilder' ) ) {
			$provider = \DataMachine\Core\PluginSettings::get( 'default_provider', '' );
			$model    = \DataMachine\Core\PluginSettings::get( 'default_model', '' );

			if ( ! empty( $provider ) && ! empty( $model ) ) {
				$prompt = sprintf(
					"Generate a two-word code name like 'azure-phoenix' or 'cosmic-owl' for user '%s'. " .
					'Use words from this bank: %s. ' .
					'Format: adjective-noun, lowercase, hyphenated. Return ONLY the code name.',
					$username,
					$word_bank
				);

				$messages = array(
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				);

				try {
					$result = \DataMachine\Engine\AI\RequestBuilder::build(
						$messages,
						$provider,
						$model,
						array(), // No tools
						'system',
						array()
					);

					if ( ! empty( $result['success'] ) && ! empty( $result['data']['content'] ) ) {
						$title = trim( $result['data']['content'] );
						$title = trim( $title, '"\'.`' );
						$title = strtolower( $title );

						if ( ! empty( $title ) && strlen( $title ) <= 40 ) {
							return new WP_REST_Response( array(
								'title'  => $title,
								'method' => 'datamachine',
							) );
						}
					}
				} catch ( \Exception $e ) {
					// Fall through to fallback.
				}
			}
		}

		// Fallback: random word combo in code name format.
		$adj   = $words[ array_rand( array_slice( $words, 0, (int) ( count( $words ) / 2 ) ) ) ];
		$noun  = $words[ array_rand( array_slice( $words, (int) ( count( $words ) / 2 ) ) ) ];
		$title = strtolower( trim( $adj ) ) . '-' . strtolower( trim( $noun ) );

		return new WP_REST_Response( array(
			'title'  => $title,
			'method' => 'fallback',
		) );
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
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Anthropic model pricing per MTok (pass-through, no markup).
	 * Opus 4.5 only - the only model capable enough for this use case.
	 */
	private const ANTHROPIC_PRICING = array(
		'claude-opus-4-20250514'           => array(
			'input'  => 5.0,
			'output' => 25.0,
		),
		'claude-opus-4.5'                  => array(
			'input'  => 5.0,
			'output' => 25.0,
		),
		'anthropic/claude-opus-4-20250514' => array(
			'input'  => 5.0,
			'output' => 25.0,
		),
		// Default = Opus pricing (only model we use).
		'default'                          => array(
			'input'  => 5.0,
			'output' => 25.0,
		),
	);

	/**
	 * Pre-request balance check for LiteLLM.
	 *
	 * Called by LiteLLM before proxying to Anthropic to check if customer has credits.
	 * If balance <= 0 and auto_refill is disabled, returns allow: false.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public static function balance_check( WP_REST_Request $request ): WP_REST_Response {
		$ip = $request->get_param( 'ip' );

		// Find customer by IP.
		$customer = Database::get_customer_by_server_ip( $ip );

		if ( ! $customer ) {
			// Unknown IP - allow (might be internal/test).
			return new WP_REST_Response( array( 'allow' => true ) );
		}

		$balance     = (float) $customer['credit_balance'];
		$auto_refill = (bool) $customer['auto_refill_enabled'];

		// If auto-refill enabled, always allow (they'll be charged).
		if ( $auto_refill ) {
			return new WP_REST_Response( array( 'allow' => true ) );
		}

		// If balance > 0, allow.
		if ( $balance > 0 ) {
			return new WP_REST_Response( array( 'allow' => true ) );
		}

		// Balance depleted and no auto-refill.
		// Dashboard is on the control plane (wherever Spawn is installed).
		$dashboard = home_url( '/spawn/dashboard/' );

		return new WP_REST_Response( array(
			'allow'         => false,
			'message'       => "Your AI credits have been depleted. Add credits or enable auto-refill at: {$dashboard}",
			'balance'       => $balance,
			'dashboard_url' => $dashboard,
		) );
	}

	/**
	 * Set billing mode from customer's agent.
	 *
	 * Called by the Spawn skill on customer instances to update billing mode.
	 * Auth is by server IP - only the customer's VPS has that IP.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public static function agent_set_billing_mode( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$ip           = $request->get_param( 'ip' );
		$billing_mode = $request->get_param( 'billing_mode' );

		// Find customer by IP.
		$customer = Database::get_customer_by_server_ip( $ip );

		if ( ! $customer ) {
			return new WP_Error(
				'customer_not_found',
				__( 'No customer found for this server IP.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		// Update billing mode.
		$success = Database::update_customer( (int) $customer['id'], array( 'billing_mode' => $billing_mode ) );

		if ( ! $success ) {
			return new WP_Error(
				'update_failed',
				__( 'Failed to update billing mode.', 'spawn' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( array(
			'success'      => true,
			'billing_mode' => $billing_mode,
			'message'      => 'byok' === $billing_mode
				? __( 'Switched to Bring Your Own Key mode. You will be billed directly by your AI provider.', 'spawn' )
				: __( 'Switched to managed credits. Usage will be deducted from your Spawn credit balance.', 'spawn' ),
		) );
	}

	/**
	 * Get customer status from customer's agent.
	 *
	 * Returns billing mode, credit balance, and usage info for the Spawn skill.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public static function agent_get_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$ip = $request->get_param( 'ip' );

		// Find customer by IP.
		$customer = Database::get_customer_by_server_ip( $ip );

		if ( ! $customer ) {
			return new WP_Error(
				'customer_not_found',
				__( 'No customer found for this server IP.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		$tier_config = Config::get_tier( $customer['tier'] ?? 'starter' );
		$model_info  = Config::get_ai_model_info();

		// Get current month usage.
		$usage_data   = Database::get_server_usage( (int) $customer['id'], 1 );
		$current_usage = $usage_data[0] ?? null;

		return new WP_REST_Response( array(
			'customer_id'      => (int) $customer['id'],
			'tier'             => $customer['tier'] ?? 'starter',
			'billing_mode'     => $customer['billing_mode'] ?? 'managed',
			'credit_balance'   => (float) ( $customer['credit_balance'] ?? 0 ),
			'included_credits' => $tier_config['included_credits'] ?? 5.0,
			'model'            => $model_info,
			'usage'            => array(
				'credits_used'   => (float) ( $current_usage['credits_used'] ?? 0 ),
				'requests_count' => (int) ( $current_usage['requests_count'] ?? 0 ),
				'tokens_input'   => (int) ( $current_usage['tokens_input'] ?? 0 ),
				'tokens_output'  => (int) ( $current_usage['tokens_output'] ?? 0 ),
			),
			'dashboard_url'    => home_url( '/spawn/dashboard/' ),
		) );
	}

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

		// LiteLLM StandardLoggingPayload format:
		// - prompt_tokens, completion_tokens at root level
		// - response_cost: pre-calculated cost in USD
		// - metadata.requester_ip_address: client IP
		// - metadata.user_api_key_alias: API key alias if set
		$model             = $body['model'] ?? '';
		$prompt_tokens     = (int) ( $body['prompt_tokens'] ?? 0 );
		$completion_tokens = (int) ( $body['completion_tokens'] ?? 0 );
		$response_cost     = (float) ( $body['response_cost'] ?? 0.0 );
		$metadata          = $body['metadata'] ?? array();
		$spawn_customer_id = (int) ( $metadata['spawn_customer_id'] ?? 0 );

		// Also check for user field (can be set as customer ID).
		if ( ! $spawn_customer_id && ! empty( $body['user'] ) ) {
			$spawn_customer_id = (int) $body['user'];
		}

		// Try to identify customer by API key alias format: spawn-customer-{id}.
		if ( ! $spawn_customer_id ) {
			$api_key = $metadata['user_api_key_alias'] ?? '';

			if ( preg_match( '/^spawn-customer-(\d+)$/', $api_key, $matches ) ) {
				$spawn_customer_id = (int) $matches[1];
			}
		}

		// Try to identify customer by server IP.
		if ( ! $spawn_customer_id ) {
			// requester_ip_address is also at root level in StandardLoggingPayload.
			$client_ip = $body['requester_ip_address'] ?? $metadata['requester_ip_address'] ?? '';

			if ( $client_ip ) {
				$customer = Database::get_customer_by_server_ip( $client_ip );
				if ( $customer ) {
					$spawn_customer_id = (int) $customer['id'];
				}
			}
		}

		if ( ! $spawn_customer_id ) {
			return new WP_REST_Response( array(
				'status'  => 'skipped',
				'message' => 'No customer ID in metadata or by IP.',
			) );
		}

		if ( 0 === $prompt_tokens && 0 === $completion_tokens ) {
			return new WP_REST_Response( array(
				'status'  => 'skipped',
				'message' => 'No tokens to charge.',
			) );
		}

		// Use LiteLLM's pre-calculated response_cost if available, otherwise calculate.
		if ( $response_cost > 0 ) {
			$total_cost = $response_cost;
		} else {
			$pricing     = self::ANTHROPIC_PRICING[ $model ] ?? self::ANTHROPIC_PRICING['default'];
			$input_cost  = ( $prompt_tokens / 1_000_000 ) * $pricing['input'];
			$output_cost = ( $completion_tokens / 1_000_000 ) * $pricing['output'];
			$total_cost  = $input_cost + $output_cost;
		}

		// credit_balance is stored in dollars as decimal(10,2), so round to 2 decimal places.
		// Minimum deduction of $0.01 to avoid rounding to zero on tiny requests.
		$amount_to_deduct = max( 0.01, round( $total_cost, 2 ) );

		// Deduct from customer.
		$customer = Database::get_customer( $spawn_customer_id );
		if ( ! $customer ) {
			error_log( "LiteLLM callback: Customer $spawn_customer_id not found" );
			return new WP_REST_Response( array(
				'status'  => 'error',
				'message' => 'Customer not found.',
			), 404 );
		}

		$current_balance = (float) $customer['credit_balance'];

		// Deduct even if it goes negative (we'll handle blocking in pre-request).
		$success = Database::deduct_credits( $spawn_customer_id, $amount_to_deduct );

		if ( ! $success ) {
			error_log( "LiteLLM callback: Failed to deduct $amount_to_deduct from customer $spawn_customer_id" );
			return new WP_REST_Response( array(
				'status'  => 'error',
				'message' => 'Failed to deduct credits.',
			), 500 );
		}

		// Record usage for tracking/billing reconciliation.
		$user_id   = (int) ( $customer['user_id'] ?? 0 );
		$server_id = (int) ( $customer['id'] ?? 0 ); // Use customer ID as server proxy.
		Database::record_usage( $user_id, $server_id, $total_cost, $prompt_tokens, $completion_tokens );

		$new_balance = Database::get_credit_balance( $spawn_customer_id );

		// Check if auto-refill is needed.
		$auto_refill = Database::get_auto_refill_settings( $spawn_customer_id );
		if ( $auto_refill && $auto_refill['enabled'] && $new_balance < $auto_refill['threshold'] ) {
			do_action( 'spawn_credits_auto_refill_needed', $spawn_customer_id, $auto_refill );
		}

		return new WP_REST_Response( array(
			'status'            => 'success',
			'model'             => $model,
			'prompt_tokens'     => $prompt_tokens,
			'completion_tokens' => $completion_tokens,
			'cost_usd'          => round( $total_cost, 6 ),
			'amount_deducted'   => $amount_to_deduct,
			'previous_balance'  => $current_balance,
			'new_balance'       => $new_balance,
		) );
	}

	/**
	 * Check if current user can set domain auto-renew.
	 *
	 * @return bool|WP_Error True if allowed, error otherwise.
	 */
	public static function can_set_domain_auto_renew(): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'not_logged_in',
				__( 'You must be logged in.', 'spawn' ),
				array( 'status' => 401 )
			);
		}

		// Check capability if it exists (for future role-based restrictions).
		if ( ! current_user_can( 'spawn_set_domain_auto_renew' ) && ! current_user_can( 'manage_options' ) ) {
			// For now, allow all logged-in customers with a Spawn account.
			$user_id  = get_current_user_id();
			$customer = Database::get_customer_by_user_id( $user_id );
			if ( ! $customer ) {
				return new WP_Error(
					'no_customer',
					__( 'No customer account found.', 'spawn' ),
					array( 'status' => 404 )
				);
			}
		}

		return true;
	}

	/**
	 * Get domain auto-renew setting for current customer.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function get_domain_auto_renew(): WP_REST_Response|WP_Error {
		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer ) {
			return new WP_Error(
				'no_customer',
				__( 'No customer account found.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		// Check if this is a registered domain.
		$is_renewable = 'register' === ( $customer['domain_type'] ?? '' );

		return new WP_REST_Response( array(
			'enabled'     => (bool) ( $customer['domain_auto_renew'] ?? false ),
			'domain'      => $customer['domain'],
			'domain_type' => $customer['domain_type'] ?? 'subdomain',
			'renewable'   => $is_renewable,
		) );
	}

	/**
	 * Get provisioning status for current customer.
	 *
	 * Used by the success page to poll for completion.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function get_provisioning_status(): WP_REST_Response|WP_Error {
		$user_id  = get_current_user_id();
		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer ) {
			return new WP_REST_Response( array(
				'status'  => 'not_found',
				'message' => __( 'No customer account found. Your account may still be processing.', 'spawn' ),
			) );
		}

		$status = $customer['status'] ?? 'pending';

		// Determine provisioning progress.
		$progress = match ( $status ) {
			'pending'       => array(
				'percent' => 10,
				'step'    => 'payment',
				'message' => __( 'Payment confirmed, starting setup...', 'spawn' ),
			),
			'provisioning'  => array(
				'percent' => 50,
				'step'    => 'provisioning',
				'message' => __( 'Setting up your server...', 'spawn' ),
			),
			'active'        => array(
				'percent' => 100,
				'step'    => 'complete',
				'message' => __( 'Your AI is ready!', 'spawn' ),
			),
			'failed'        => array(
				'percent' => 0,
				'step'    => 'failed',
				'message' => __( 'Setup failed. We\'ve been notified and will contact you.', 'spawn' ),
			),
			default         => array(
				'percent' => 0,
				'step'    => 'unknown',
				'message' => __( 'Checking status...', 'spawn' ),
			),
		};

		return new WP_REST_Response( array(
			'status'        => $status,
			'progress'      => $progress,
			'domain'        => $customer['domain'] ?? '',
			'server_ip'     => $customer['server_ip'] ?? '',
			'chat_url'      => home_url( '/spawn/chat/' ),
			'dashboard_url' => home_url( '/spawn/dashboard/' ),
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
			return new WP_Error(
				'no_customer',
				__( 'No customer account found.', 'spawn' ),
				array( 'status' => 404 )
			);
		}

		// Validate domain type.
		if ( 'register' !== ( $customer['domain_type'] ?? '' ) ) {
			return new WP_Error(
				'not_renewable',
				__( 'Auto-renewal is only available for registered domains.', 'spawn' ),
				array( 'status' => 400 )
			);
		}

		$enabled = (bool) $request->get_param( 'enabled' );

		// If enabling, verify payment method exists.
		if ( $enabled ) {
			$stripe_customer = $customer['stripe_customer'] ?? '';
			$payment_method  = $customer['stripe_payment_method'] ?? '';

			if ( empty( $stripe_customer ) ) {
				return new WP_Error(
					'no_payment_method',
					__( 'A payment method is required for auto-renewal. Please add a payment method first.', 'spawn' ),
					array( 'status' => 400 )
				);
			}

			// Try to get default payment method if not stored.
			if ( empty( $payment_method ) ) {
				$payment_method = Payment_Helpers::get_default_payment_method( $stripe_customer );
				if ( is_wp_error( $payment_method ) ) {
					return new WP_Error(
						'no_payment_method',
						__( 'No valid payment method found. Please add a payment method in the billing portal.', 'spawn' ),
						array( 'status' => 400 )
					);
				}
			}
		}

		$success = Database::update_customer( (int) $customer['id'], array(
			'domain_auto_renew' => $enabled ? 1 : 0,
		) );

		if ( ! $success ) {
			return new WP_Error(
				'update_failed',
				__( 'Failed to update auto-renewal setting.', 'spawn' ),
				array( 'status' => 500 )
			);
		}

		// Log the change.
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
		// Renew domain via Name.com API.
		$renewal_result = Name_Com::renew( $domain, 1 );

		if ( is_wp_error( $renewal_result ) ) {
			// Log the error - payment succeeded but renewal failed.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf(
				'[Spawn] Domain renewal API failed after payment for %s (customer #%d): %s',
				$domain,
				$customer_id,
				$renewal_result->get_error_message()
			) );

			// Notify admin for manual intervention.
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

		// Update database with new expiration.
		$db_updated = Database::renew_domain( $customer_id );

		if ( ! $db_updated ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[Spawn] Failed to update database after domain renewal for customer #%d', $customer_id ) );
		}

		// Clear renewal warnings since domain is now renewed.
		Cron::clear_warnings_sent( $customer_id );

		// Get customer for email notification.
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
		$email   = $customer['email'];
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

		wp_mail( $email, $subject, $message );
	}

	/**
	 * List chat sessions from customer's OpenClaw.
	 *
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function chat_sessions_list(): WP_REST_Response|WP_Error {
		$user_id = get_current_user_id();

		// Admin uses control plane.
		if ( current_user_can( 'manage_options' ) ) {
			$gateway_url   = get_option( 'spawn_openclaw_gateway_url', '' );
			$gateway_token = get_option( 'spawn_openclaw_token', '' );

			if ( empty( $gateway_url ) || empty( $gateway_token ) ) {
				return new WP_REST_Response( array( 'sessions' => array() ) );
			}

			return self::invoke_openclaw_tool( $gateway_url, $gateway_token, 'sessions_list', array() );
		}

		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer || empty( $customer['server_ip'] ) || empty( $customer['openclaw_token'] ) ) {
			return new WP_REST_Response( array( 'sessions' => array() ) );
		}

		$gateway_url   = 'http://' . $customer['server_ip'] . ':18789';
		$gateway_token = $customer['openclaw_token'];

		return self::invoke_openclaw_tool( $gateway_url, $gateway_token, 'sessions_list', array() );
	}

	/**
	 * Get chat session history from customer's OpenClaw.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function chat_session_history( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id     = get_current_user_id();
		$session_key = sanitize_text_field( $request->get_param( 'sessionKey' ) );
		$limit       = (int) $request->get_param( 'limit' );

		// Admin uses control plane.
		if ( current_user_can( 'manage_options' ) ) {
			$gateway_url   = get_option( 'spawn_openclaw_gateway_url', '' );
			$gateway_token = get_option( 'spawn_openclaw_token', '' );

			if ( empty( $gateway_url ) || empty( $gateway_token ) ) {
				return new WP_REST_Response( array( 'messages' => array() ) );
			}

			return self::invoke_openclaw_tool(
				$gateway_url,
				$gateway_token,
				'sessions_history',
				array(
					'sessionKey' => $session_key,
					'limit'      => $limit,
				)
			);
		}

		$customer = Database::get_customer_by_user_id( $user_id );

		if ( ! $customer || empty( $customer['server_ip'] ) || empty( $customer['openclaw_token'] ) ) {
			return new WP_REST_Response( array( 'messages' => array() ) );
		}

		$gateway_url   = 'http://' . $customer['server_ip'] . ':18789';
		$gateway_token = $customer['openclaw_token'];

		return self::invoke_openclaw_tool(
			$gateway_url,
			$gateway_token,
			'sessions_history',
			array(
				'sessionKey' => $session_key,
				'limit'      => $limit,
			)
		);
	}

	/**
	 * Invoke an OpenClaw tool via the /tools/invoke endpoint.
	 *
	 * @param string $gateway_url   Base URL of the OpenClaw gateway.
	 * @param string $gateway_token Auth token.
	 * @param string $tool          Tool name.
	 * @param array  $args          Tool arguments.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	private static function invoke_openclaw_tool(
		string $gateway_url,
		string $gateway_token,
		string $tool,
		array $args
	): WP_REST_Response|WP_Error {
		$url = rtrim( $gateway_url, '/' ) . '/tools/invoke';

		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $gateway_token,
			),
			'body'    => wp_json_encode( array(
				'tool' => $tool,
				'args' => $args,
			) ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'openclaw_error',
				__( 'Failed to connect to OpenClaw', 'spawn' ),
				array( 'status' => 502 )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			return new WP_Error(
				'openclaw_error',
				$body['error']['message'] ?? __( 'OpenClaw request failed', 'spawn' ),
				array( 'status' => $code )
			);
		}

		// Return the result from the tool invocation.
		// OpenClaw tools return { ok, result: { content, details } } - we want details.
		$result = $body['result'] ?? $body;
		if ( isset( $result['details'] ) ) {
			return new WP_REST_Response( $result['details'] );
		}
		return new WP_REST_Response( $result );
	}
}
