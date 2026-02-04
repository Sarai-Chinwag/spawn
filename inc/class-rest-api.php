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
