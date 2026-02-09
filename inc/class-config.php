<?php
/**
 * Spawn configuration - Single source of truth for tier and server data.
 *
 * Tiers determine server SIZE (cpx21/cpx31/cpx41).
 * wants_website determines server LOCATION (US for websites, EU for AI-only).
 *
 * @package Spawn
 */

namespace Spawn;

/**
 * Configuration class for Spawn.
 */
class Config {

	/**
	 * Default free credits for new customers.
	 * Starter = $5, Pro = $20, Business = $40.
	 */
	public const DEFAULT_STARTER_CREDITS = 5.00;

	/**
	 * Default AI model for customer instances.
	 * Update this when changing the model in LiteLLM config.
	 */
	public const DEFAULT_AI_MODEL = 'anthropic/claude-opus-4-6';

	/**
	 * Get AI model display info for dashboards.
	 *
	 * @return array Model info with 'provider', 'name', 'version', 'display'.
	 */
	public static function get_ai_model_info(): array {
		$model = self::DEFAULT_AI_MODEL;

		// Parse common model string formats.
		// e.g. "anthropic/claude-opus-4-6" => provider=anthropic, name=Claude, version=Opus 4.6
		if ( preg_match( '/anthropic\/claude-(\w+)-(\d+)-?(\d+)?/', $model, $matches ) ) {
			$tier    = ucfirst( $matches[1] ); // opus, sonnet, haiku
			$major   = $matches[2];
			$minor   = $matches[3] ?? '';
			$version = $minor ? "{$major}.{$minor}" : $major;

			return array(
				'provider' => 'Anthropic',
				'name'     => 'Claude',
				'tier'     => $tier,
				'version'  => $version,
				'display'  => "Claude {$tier} {$version}",
			);
		}

		// Fallback for unknown formats.
		return array(
			'provider' => 'Unknown',
			'name'     => $model,
			'tier'     => '',
			'version'  => '',
			'display'  => $model,
		);
	}

	/**
	 * Get all tier configurations.
	 *
	 * This is THE source of truth for tier data. All other code reads from here.
	 *
	 * VPS specs verified from Hetzner CLI (hcloud server-type describe):
	 * US (ash):
	 * - cpx21: 3 vCPU (shared), 4 GB RAM, 80 GB SSD   - $9.99/mo
	 * - cpx31: 4 vCPU (shared), 8 GB RAM, 160 GB SSD  - $17.99/mo
	 * - cpx41: 8 vCPU (shared), 16 GB RAM, 240 GB SSD - $33.49/mo
	 *
	 * EU (fsn1) - newer generation, slightly different specs:
	 * - cpx22: 2 vCPU (shared), 4 GB RAM, 80 GB SSD   - $6.99/mo (equiv to cpx21)
	 * - cpx32: 4 vCPU (shared), 8 GB RAM, 160 GB SSD  - $11.99/mo (equiv to cpx31)
	 * - cpx42: 8 vCPU (shared), 16 GB RAM, 320 GB SSD - $21.99/mo (equiv to cpx41)
	 *
	 * Pricing:
	 * - Starter: $20 (cpx21/22 + $10 credits)
	 * - Pro:     $50 (cpx31/32 + $20 credits)
	 * - Business: $100 (cpx41/42 + $40 credits)
	 *
	 * @return array Tier configurations keyed by tier ID.
	 */
	public static function get_tiers(): array {
		// Get Stripe price IDs from stored options.
		$prices = get_option( 'spawn_stripe_prices', array() );

		return array(
			'starter'  => array(
				'name'             => __( 'Starter', 'spawn' ),
				'price'            => 20,
				'description'      => __( 'Perfect for personal use', 'spawn' ),
				'stripe_price_id'  => $prices['vps_starter'] ?? '',
				'hetzner_type_us'  => 'cpx21',
				'hetzner_type_eu'  => 'cpx22',
				'vcpu'             => 3, // cpx21 has 3, cpx22 has 2
				'vcpu_shared'      => true,
				'ram_gb'           => 4,
				'disk_gb'          => 80,
				'included_credits' => self::DEFAULT_STARTER_CREDITS,
				'features'         => array(
					__( '$5 AI credits/month', 'spawn' ),
					__( 'Free website (optional)', 'spawn' ),
					__( 'Add custom domain anytime', 'spawn' ),
				),
			),
			'pro'      => array(
				'name'             => __( 'Pro', 'spawn' ),
				'price'            => 50,
				'description'      => __( 'More power for bigger projects', 'spawn' ),
				'stripe_price_id'  => $prices['vps_pro'] ?? '',
				'hetzner_type_us'  => 'cpx31',
				'hetzner_type_eu'  => 'cpx32',
				'vcpu'             => 4,
				'vcpu_shared'      => true,
				'ram_gb'           => 8,
				'disk_gb'          => 160,
				'included_credits' => 20.00,
				'features'         => array(
					__( '$20 AI credits/month', 'spawn' ),
					__( 'Free website (optional)', 'spawn' ),
					__( 'Add custom domain anytime', 'spawn' ),
					__( 'Priority support', 'spawn' ),
				),
			),
			'business' => array(
				'name'             => __( 'Business', 'spawn' ),
				'price'            => 100,
				'description'      => __( 'Maximum power for teams', 'spawn' ),
				'stripe_price_id'  => $prices['vps_business'] ?? '',
				'hetzner_type_us'  => 'cpx41',
				'hetzner_type_eu'  => 'cpx42',
				'vcpu'             => 8,
				'vcpu_shared'      => true,
				'ram_gb'           => 16,
				'disk_gb'          => 240, // cpx41=240, cpx42=320
				'included_credits' => self::DEFAULT_STARTER_CREDITS * 8,
				'features'         => array(
					__( '$40 AI credits/month', 'spawn' ),
					__( 'Free website (optional)', 'spawn' ),
					__( 'Add custom domain anytime', 'spawn' ),
					__( 'Priority support', 'spawn' ),
					__( 'Best for heavy work', 'spawn' ),
				),
			),
		);
	}

	/**
	 * Get a single tier configuration.
	 *
	 * @param string $tier_id Tier ID (starter, pro, business).
	 * @return array|null Tier config or null if not found.
	 */
	public static function get_tier( string $tier_id ): ?array {
		$tiers = self::get_tiers();
		return $tiers[ $tier_id ] ?? null;
	}

	/**
	 * Get valid tier IDs.
	 *
	 * @return array Array of tier IDs.
	 */
	public static function get_tier_ids(): array {
		return array_keys( self::get_tiers() );
	}

	/**
	 * Get Hetzner type for a tier based on location preference.
	 *
	 * @param string $tier_id         Tier ID.
	 * @param bool   $wants_website   Whether customer wants a website.
	 * @param string $customer_region Customer region ('us' or 'eu').
	 * @return string|null Hetzner type or null if tier not found.
	 */
	public static function get_hetzner_type( string $tier_id, bool $wants_website = true, string $customer_region = 'us' ): ?string {
		$tier = self::get_tier( $tier_id );
		if ( ! $tier ) {
			return null;
		}

		// Location determines type: EU location uses _eu types, US uses _us types.
		$location = self::get_server_location( $wants_website, $customer_region );
		return 'fsn1' === $location ? $tier['hetzner_type_eu'] : $tier['hetzner_type_us'];
	}

	/**
	 * Get server location based on website preference and customer region.
	 *
	 * EU customers always get EU servers (lower latency for them, cheaper for us).
	 * US customers get US servers if they want websites (latency), EU if AI-only (cheaper).
	 *
	 * @param bool   $wants_website   Whether customer wants a website.
	 * @param string $customer_region Customer region ('us' or 'eu').
	 * @return string Hetzner location code.
	 */
	public static function get_server_location( bool $wants_website, string $customer_region = 'us' ): string {
		// EU customers always get EU servers (cheaper, better latency for them).
		if ( 'eu' === $customer_region ) {
			return 'fsn1';
		}

		// US customers: US if website (latency matters), EU if AI-only (cheaper).
		return $wants_website ? 'ash' : 'fsn1';
	}

	/**
	 * Get full server configuration for a tier, website preference, and region.
	 *
	 * @param string $tier_id         Tier ID.
	 * @param bool   $wants_website   Whether customer wants a website.
	 * @param string $customer_region Customer region ('us' or 'eu').
	 * @return array|null Server configuration or null if tier not found.
	 */
	public static function get_server_config( string $tier_id, bool $wants_website, string $customer_region = 'us' ): ?array {
		$tier = self::get_tier( $tier_id );
		if ( ! $tier ) {
			return null;
		}

		$location     = self::get_server_location( $wants_website, $customer_region );
		$hetzner_type = self::get_hetzner_type( $tier_id, $wants_website, $customer_region );

		return array(
			'tier'            => $tier_id,
			'hetzner_type'    => $hetzner_type,
			'location'        => $location,
			'customer_region' => $customer_region,
			'vcpu'            => $tier['vcpu'],
			'vcpu_shared'     => $tier['vcpu_shared'],
			'ram_gb'          => $tier['ram_gb'],
			'disk_gb'         => $tier['disk_gb'],
		);
	}

	/**
	 * Get included credits for a tier.
	 *
	 * @param string $tier_id Tier ID.
	 * @return float Credits amount (defaults to starter credits).
	 */
	public static function get_included_credits( string $tier_id ): float {
		$tier = self::get_tier( $tier_id );
		return $tier['included_credits'] ?? self::DEFAULT_STARTER_CREDITS;
	}

	/**
	 * Get public tier data (safe for API responses).
	 *
	 * Excludes internal fields like stripe_price_id and hetzner types.
	 *
	 * @return array Public tier data.
	 */
	public static function get_public_tiers(): array {
		$tiers  = self::get_tiers();
		$public = array();

		foreach ( $tiers as $id => $tier ) {
			$public[ $id ] = array(
				'name'        => $tier['name'],
				'price'       => $tier['price'],
				'description' => $tier['description'],
				'ram_gb'      => $tier['ram_gb'],
				'disk_gb'     => $tier['disk_gb'],
				'features'    => $tier['features'],
			);
		}

		return $public;
	}

	/**
	 * Get tier ID by price.
	 *
	 * @param int $price Monthly price.
	 * @return string|null Tier ID or null if not found.
	 */
	public static function get_tier_by_price( int $price ): ?string {
		foreach ( self::get_tiers() as $tier_id => $tier ) {
			if ( (int) $tier['price'] === $price ) {
				return $tier_id;
			}
		}
		return null;
	}

	/**
	 * Calculate margin for a tier and location.
	 *
	 * @param string $tier_id       Tier ID.
	 * @param bool   $wants_website Whether customer wants a website.
	 * @return array|null Margin breakdown or null if tier not found.
	 */
	public static function get_margin_breakdown( string $tier_id, bool $wants_website ): ?array {
		$tier = self::get_tier( $tier_id );
		if ( ! $tier ) {
			return null;
		}

		// Server costs (approximate).
		$server_costs = array(
			'cpx21' => 9.99,
			'cpx22' => 6.99,
			'cpx31' => 17.99,
			'cpx32' => 11.99,
			'cpx41' => 33.49,
			'cpx42' => 21.99,
		);

		$hetzner_type = self::get_hetzner_type( $tier_id, $wants_website );
		$server_cost  = $server_costs[ $hetzner_type ] ?? 9.99;
		$credits_cost = $tier['included_credits']; // $1 = $1 (pass-through).
		$stripe_fee   = ( $tier['price'] * 0.029 ) + 0.30;
		$net_received = $tier['price'] - $stripe_fee;
		$total_cost   = $server_cost + $credits_cost;
		$margin       = $net_received - $total_cost;

		return array(
			'price'        => $tier['price'],
			'stripe_fee'   => round( $stripe_fee, 2 ),
			'net_received' => round( $net_received, 2 ),
			'server_cost'  => $server_cost,
			'credits_cost' => $credits_cost,
			'total_cost'   => round( $total_cost, 2 ),
			'margin'       => round( $margin, 2 ),
			'margin_pct'   => round( ( $margin / $tier['price'] ) * 100, 1 ),
		);
	}
}
