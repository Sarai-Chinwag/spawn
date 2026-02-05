<?php
/**
 * Spawn configuration - Single source of truth for tier data.
 *
 * ALL tier configuration lives here. No hardcoded tier values anywhere else.
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
	 * Starter tier gets this amount, Pro = 2x, Business = 4x.
	 */
	public const DEFAULT_STARTER_CREDITS = 10.00;

	/**
	 * Get all tier configurations.
	 *
	 * This is THE source of truth for tier data. All other code reads from here.
	 *
	 * VPS specs verified from Hetzner CLI (hcloud server-type list):
	 * - cpx11: 2 vCPU (shared), 2 GB RAM, 40 GB SSD
	 * - cpx21: 3 vCPU (shared), 4 GB RAM, 80 GB SSD
	 * - cpx31: 4 vCPU (shared), 8 GB RAM, 160 GB SSD
	 *
	 * @return array Tier configurations keyed by tier ID.
	 */
	public static function get_tiers(): array {
		// Get Stripe price IDs from stored options.
		$prices = get_option( 'spawn_stripe_prices', [] );

		return [
			'starter'  => [
				'name'            => __( 'Starter', 'spawn' ),
				'price'           => 20,
				'description'     => __( 'Perfect for personal sites and blogs', 'spawn' ),
				'stripe_price_id' => $prices['vps_starter'] ?? '',
				'hetzner_type'    => 'cpx11',
				'vcpu'            => 2,
				'vcpu_shared'     => true,
				'ram_gb'          => 2,
				'disk_gb'         => 40,
				'included_credits' => self::DEFAULT_STARTER_CREDITS,
				'features'        => [
					__( '2 vCPU, 2 GB RAM, 40 GB SSD', 'spawn' ),
					__( '$10 AI credits included', 'spawn' ),
					__( 'Custom domain', 'spawn' ),
					__( 'SSL included', 'spawn' ),
				],
			],
			'pro'      => [
				'name'            => __( 'Pro', 'spawn' ),
				'price'           => 40,
				'description'     => __( 'For growing businesses', 'spawn' ),
				'stripe_price_id' => $prices['vps_pro'] ?? '',
				'hetzner_type'    => 'cpx21',
				'vcpu'            => 3,
				'vcpu_shared'     => true,
				'ram_gb'          => 4,
				'disk_gb'         => 80,
				'included_credits' => self::DEFAULT_STARTER_CREDITS * 2,
				'features'        => [
					__( '3 vCPU, 4 GB RAM, 80 GB SSD', 'spawn' ),
					__( '$20 AI credits included', 'spawn' ),
					__( 'Custom domain', 'spawn' ),
					__( 'SSL included', 'spawn' ),
					__( 'Priority support', 'spawn' ),
				],
			],
			'business' => [
				'name'            => __( 'Business', 'spawn' ),
				'price'           => 100,
				'description'     => __( 'For high-traffic sites', 'spawn' ),
				'stripe_price_id' => $prices['vps_business'] ?? '',
				'hetzner_type'    => 'cpx31',
				'vcpu'            => 4,
				'vcpu_shared'     => true,
				'ram_gb'          => 8,
				'disk_gb'         => 160,
				'included_credits' => self::DEFAULT_STARTER_CREDITS * 4,
				'features'        => [
					__( '4 vCPU, 8 GB RAM, 160 GB SSD', 'spawn' ),
					__( '$40 AI credits included', 'spawn' ),
					__( 'Custom domain', 'spawn' ),
					__( 'SSL included', 'spawn' ),
					__( 'Priority support', 'spawn' ),
					__( 'Dedicated resources', 'spawn' ),
				],
			],
		];
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
	 * Get valid Hetzner server types for tiers.
	 *
	 * @return array Array of Hetzner server type names.
	 */
	public static function get_valid_hetzner_types(): array {
		$tiers = self::get_tiers();
		return array_unique( array_column( $tiers, 'hetzner_type' ) );
	}

	/**
	 * Get Hetzner type for a tier.
	 *
	 * @param string $tier_id Tier ID.
	 * @return string|null Hetzner type or null if not found.
	 */
	public static function get_hetzner_type( string $tier_id ): ?string {
		$tier = self::get_tier( $tier_id );
		return $tier['hetzner_type'] ?? null;
	}

	/**
	 * Get tier ID by Hetzner type.
	 *
	 * @param string $hetzner_type Hetzner server type.
	 * @return string|null Tier ID or null if not found.
	 */
	public static function get_tier_by_hetzner_type( string $hetzner_type ): ?string {
		foreach ( self::get_tiers() as $tier_id => $tier ) {
			if ( $tier['hetzner_type'] === $hetzner_type ) {
				return $tier_id;
			}
		}
		return null;
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
	 * Excludes internal fields like stripe_price_id and hetzner_type.
	 *
	 * @return array Public tier data.
	 */
	public static function get_public_tiers(): array {
		$tiers = self::get_tiers();
		$public = [];

		foreach ( $tiers as $id => $tier ) {
			$public[ $id ] = [
				'name'        => $tier['name'],
				'price'       => $tier['price'],
				'description' => $tier['description'],
				'features'    => $tier['features'],
			];
		}

		return $public;
	}
}
