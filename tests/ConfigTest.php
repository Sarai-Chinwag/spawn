<?php
/**
 * Tests for Config class.
 *
 * @package Spawn
 */

declare(strict_types=1);

namespace Spawn\Tests;

use PHPUnit\Framework\TestCase;
use Spawn\Config;

/**
 * Config class tests.
 */
class ConfigTest extends TestCase {

	/**
	 * Test get_tiers returns all three tiers.
	 */
	public function test_get_tiers_returns_all_tiers(): void {
		$tiers = Config::get_tiers();

		$this->assertIsArray( $tiers );
		$this->assertArrayHasKey( 'starter', $tiers );
		$this->assertArrayHasKey( 'pro', $tiers );
		$this->assertArrayHasKey( 'business', $tiers );
	}

	/**
	 * Test each tier has required keys.
	 */
	public function test_tiers_have_required_keys(): void {
		$required_keys = [
			'name',
			'price',
			'hetzner_type',
			'hetzner_cost',
			'included_credits',
			'specs',
		];

		$tiers = Config::get_tiers();

		foreach ( $tiers as $tier_id => $tier ) {
			foreach ( $required_keys as $key ) {
				$this->assertArrayHasKey(
					$key,
					$tier,
					"Tier '$tier_id' missing required key '$key'"
				);
			}
		}
	}

	/**
	 * Test get_tier returns correct tier.
	 */
	public function test_get_tier_returns_correct_tier(): void {
		$starter = Config::get_tier( 'starter' );

		$this->assertIsArray( $starter );
		$this->assertEquals( 'Starter', $starter['name'] );
		$this->assertEquals( 25, $starter['price'] );
	}

	/**
	 * Test get_tier returns null for invalid tier.
	 */
	public function test_get_tier_returns_null_for_invalid(): void {
		$invalid = Config::get_tier( 'nonexistent' );

		$this->assertNull( $invalid );
	}

	/**
	 * Test pricing structure is correct.
	 *
	 * Prices: $25 / $50 / $100
	 */
	public function test_pricing_structure(): void {
		$tiers = Config::get_tiers();

		$this->assertEquals( 25, $tiers['starter']['price'] );
		$this->assertEquals( 50, $tiers['pro']['price'] );
		$this->assertEquals( 100, $tiers['business']['price'] );
	}

	/**
	 * Test Hetzner types are correct.
	 *
	 * Types: cpx21 / cpx31 / cpx41
	 */
	public function test_hetzner_types(): void {
		$tiers = Config::get_tiers();

		$this->assertEquals( 'cpx21', $tiers['starter']['hetzner_type'] );
		$this->assertEquals( 'cpx31', $tiers['pro']['hetzner_type'] );
		$this->assertEquals( 'cpx41', $tiers['business']['hetzner_type'] );
	}

	/**
	 * Test included credits are correct.
	 *
	 * Credits: 1000 / 2000 / 4000 (1 credit = 1 cent)
	 */
	public function test_included_credits(): void {
		$tiers = Config::get_tiers();

		$this->assertEquals( 1000, $tiers['starter']['included_credits'] );
		$this->assertEquals( 2000, $tiers['pro']['included_credits'] );
		$this->assertEquals( 4000, $tiers['business']['included_credits'] );
	}

	/**
	 * Test margins are positive (price > hetzner_cost).
	 */
	public function test_margins_are_positive(): void {
		$tiers = Config::get_tiers();

		foreach ( $tiers as $tier_id => $tier ) {
			$margin = $tier['price'] - $tier['hetzner_cost'];
			$this->assertGreaterThan(
				0,
				$margin,
				"Tier '$tier_id' has non-positive margin: \${$tier['price']} - \${$tier['hetzner_cost']} = \$$margin"
			);
		}
	}

	/**
	 * Test specs have required keys.
	 */
	public function test_specs_have_required_keys(): void {
		$required_spec_keys = [ 'vcpu', 'ram', 'storage' ];
		$tiers              = Config::get_tiers();

		foreach ( $tiers as $tier_id => $tier ) {
			foreach ( $required_spec_keys as $key ) {
				$this->assertArrayHasKey(
					$key,
					$tier['specs'],
					"Tier '$tier_id' specs missing required key '$key'"
				);
			}
		}
	}
}
