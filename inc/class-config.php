<?php
/**
 * Spawn configuration - Single product model.
 *
 * $20/month = AI Assistant (core product)
 * Credits = Usage-based (purchased separately)
 * Website = Optional add-on (free, same price)
 * Server = Auto-selected based on wants_website
 *
 * @package Spawn
 */

namespace Spawn;

/**
 * Configuration class for Spawn.
 */
class Config {

	/**
	 * Monthly subscription price.
	 */
	public const MONTHLY_PRICE = 20;

	/**
	 * Get server configuration based on website preference.
	 *
	 * - wants_website = true → US server (latency matters for WordPress visitors)
	 * - wants_website = false → EU server (cheaper, latency irrelevant for chat API)
	 *
	 * @param bool $wants_website Whether customer wants a website.
	 * @return array Server configuration.
	 */
	public static function get_server_config( bool $wants_website ): array {
		if ( $wants_website ) {
			// US server - better latency for WordPress visitors (mostly US-based)
			return [
				'hetzner_type' => 'cpx21',
				'location'     => 'ash', // Ashburn, VA (US East)
				'vcpu'         => 3,
				'vcpu_shared'  => true,
				'ram_gb'       => 4,
				'disk_gb'      => 80,
				'monthly_cost' => 9.99,
			];
		}

		// EU server - cheaper, latency doesn't matter for chat API
		return [
			'hetzner_type' => 'cpx22',
			'location'     => 'fsn1', // Falkenstein, Germany
			'vcpu'         => 2,
			'vcpu_shared'  => true,
			'ram_gb'       => 4,
			'disk_gb'      => 80,
			'monthly_cost' => 6.99,
		];
	}

	/**
	 * Get the Stripe price ID for the subscription.
	 *
	 * @return string Stripe price ID or empty string if not configured.
	 */
	public static function get_stripe_price_id(): string {
		return get_option( 'spawn_stripe_subscription_price_id', '' );
	}

	/**
	 * Get public product info (safe for API responses and landing page).
	 *
	 * @return array Product information.
	 */
	public static function get_public_info(): array {
		return [
			'name'        => __( 'Spawn', 'spawn' ),
			'tagline'     => __( 'Spawn Your Own AI', 'spawn' ),
			'price'       => self::MONTHLY_PRICE,
			'description' => __( 'Your personal AI assistant in a box. Add a website if you want one.', 'spawn' ),
			'features'    => [
				__( 'Personal AI assistant powered by Claude', 'spawn' ),
				__( 'Chat from anywhere - phone, computer, anywhere', 'spawn' ),
				__( 'Your AI learns your preferences over time', 'spawn' ),
				__( 'Optional WordPress website included free', 'spawn' ),
				__( 'Full data portability - export anytime', 'spawn' ),
			],
		];
	}

	/**
	 * Calculate margin for a customer.
	 *
	 * @param bool $wants_website Whether customer has a website.
	 * @return array Margin breakdown.
	 */
	public static function get_margin_breakdown( bool $wants_website ): array {
		$server       = self::get_server_config( $wants_website );
		$stripe_fee   = ( self::MONTHLY_PRICE * 0.029 ) + 0.30; // 2.9% + $0.30
		$net_received = self::MONTHLY_PRICE - $stripe_fee;
		$margin       = $net_received - $server['monthly_cost'];

		return [
			'price'        => self::MONTHLY_PRICE,
			'stripe_fee'   => round( $stripe_fee, 2 ),
			'net_received' => round( $net_received, 2 ),
			'server_cost'  => $server['monthly_cost'],
			'margin'       => round( $margin, 2 ),
			'margin_pct'   => round( ( $margin / self::MONTHLY_PRICE ) * 100, 1 ),
		];
	}
}
