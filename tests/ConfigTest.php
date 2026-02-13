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
			'description',
			'server_type_us',
			'server_type_eu',
			'vcpu',
			'ram_gb',
			'disk_gb',
			'included_credits',
			'features',
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
		$this->assertEquals( 20, $starter['price'] );
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
	 * Prices: $20 / $50 / $100
	 */
	public function test_pricing_structure(): void {
		$tiers = Config::get_tiers();

		$this->assertEquals( 20, $tiers['starter']['price'] );
		$this->assertEquals( 50, $tiers['pro']['price'] );
		$this->assertEquals( 100, $tiers['business']['price'] );
	}

	/**
	 * Test Hetzner types for US location.
	 *
	 * US types: cpx21 / cpx31 / cpx41
	 */
	public function test_server_types_us(): void {
		$tiers = Config::get_tiers();

		$this->assertEquals( 'cpx21', $tiers['starter']['server_type_us'] );
		$this->assertEquals( 'cpx31', $tiers['pro']['server_type_us'] );
		$this->assertEquals( 'cpx41', $tiers['business']['server_type_us'] );
	}

	/**
	 * Test Hetzner types for EU location.
	 *
	 * EU types: cpx22 / cpx32 / cpx42
	 */
	public function test_server_types_eu(): void {
		$tiers = Config::get_tiers();

		$this->assertEquals( 'cpx22', $tiers['starter']['server_type_eu'] );
		$this->assertEquals( 'cpx32', $tiers['pro']['server_type_eu'] );
		$this->assertEquals( 'cpx42', $tiers['business']['server_type_eu'] );
	}

	/**
	 * Test included credits are correct.
	 *
	 * Credits in dollars: $5 / $10 / $40
	 */
	public function test_included_credits_are_zero(): void {
		$tiers = Config::get_tiers();

		// All tiers have 0 included credits — AI is pay-as-you-go.
		$this->assertEquals( 0, $tiers['starter']['included_credits'] );
		$this->assertEquals( 0, $tiers['pro']['included_credits'] );
		$this->assertEquals( 0, $tiers['business']['included_credits'] );
	}

	/**
	 * Test get_included_credits helper method.
	 */
	public function test_get_included_credits_helper(): void {
		// All tiers return 0 — credits are pay-as-you-go.
		$this->assertEquals( 0, Config::get_included_credits( 'starter' ) );
		$this->assertEquals( 0, Config::get_included_credits( 'pro' ) );
		$this->assertEquals( 0, Config::get_included_credits( 'business' ) );

		// Invalid tier returns default (still used as fallback).
		$this->assertEquals( 5.00, Config::get_included_credits( 'invalid' ) );
	}

	/**
	 * Test server location logic - wants_website affects location.
	 */
	public function test_server_location_with_website(): void {
		// US customer wanting website = US server.
		$location = Config::get_server_location( true, 'us' );
		$this->assertEquals( 'ash', $location );

		// US customer NOT wanting website = EU server (cheaper).
		$location = Config::get_server_location( false, 'us' );
		$this->assertEquals( 'fsn1', $location );
	}

	/**
	 * Test server location logic - EU customers always get EU.
	 */
	public function test_server_location_eu_customer(): void {
		// EU customer wanting website = EU server.
		$location = Config::get_server_location( true, 'eu' );
		$this->assertEquals( 'fsn1', $location );

		// EU customer NOT wanting website = EU server.
		$location = Config::get_server_location( false, 'eu' );
		$this->assertEquals( 'fsn1', $location );
	}

	/**
	 * Test get_server_type returns correct type based on wants_website.
	 */
	public function test_get_server_type_by_location(): void {
		// US customer with website = US type.
		$type = Config::get_server_type( 'starter', true, 'us' );
		$this->assertEquals( 'cpx21', $type );

		// US customer without website = EU type.
		$type = Config::get_server_type( 'starter', false, 'us' );
		$this->assertEquals( 'cpx22', $type );

		// EU customer always gets EU type.
		$type = Config::get_server_type( 'pro', true, 'eu' );
		$this->assertEquals( 'cpx32', $type );
	}

	/**
	 * Test get_server_config returns full configuration.
	 */
	public function test_get_server_config(): void {
		$config = Config::get_server_config( 'starter', true, 'us' );

		$this->assertIsArray( $config );
		$this->assertEquals( 'starter', $config['tier'] );
		$this->assertEquals( 'cpx21', $config['server_type'] );
		$this->assertEquals( 'ash', $config['location'] );
		$this->assertEquals( 'us', $config['customer_region'] );
		$this->assertEquals( 4, $config['ram_gb'] );
	}

	/**
	 * Test get_server_config for AI-only (no website).
	 */
	public function test_get_server_config_ai_only(): void {
		$config = Config::get_server_config( 'pro', false, 'us' );

		$this->assertEquals( 'pro', $config['tier'] );
		$this->assertEquals( 'cpx32', $config['server_type'] ); // EU type.
		$this->assertEquals( 'fsn1', $config['location'] ); // EU location.
	}

	/**
	 * Test get_server_config returns null for invalid tier.
	 */
	public function test_get_server_config_invalid_tier(): void {
		$config = Config::get_server_config( 'nonexistent', true );

		$this->assertNull( $config );
	}

	/**
	 * Test get_public_tiers excludes internal fields.
	 */
	public function test_get_public_tiers(): void {
		$public = Config::get_public_tiers();

		foreach ( $public as $tier_id => $tier ) {
			// Should have public fields.
			$this->assertArrayHasKey( 'name', $tier );
			$this->assertArrayHasKey( 'price', $tier );
			$this->assertArrayHasKey( 'features', $tier );

			// Should NOT have internal fields.
			$this->assertArrayNotHasKey( 'stripe_price_id', $tier );
			$this->assertArrayNotHasKey( 'server_type_us', $tier );
			$this->assertArrayNotHasKey( 'server_type_eu', $tier );
		}
	}

	/**
	 * Test get_tier_by_price finds correct tier.
	 */
	public function test_get_tier_by_price(): void {
		$this->assertEquals( 'starter', Config::get_tier_by_price( 20 ) );
		$this->assertEquals( 'pro', Config::get_tier_by_price( 50 ) );
		$this->assertEquals( 'business', Config::get_tier_by_price( 100 ) );
		$this->assertNull( Config::get_tier_by_price( 999 ) );
	}

	/**
	 * Test margin breakdown calculation.
	 */
	public function test_get_margin_breakdown(): void {
		$margin = Config::get_margin_breakdown( 'starter', true );

		$this->assertIsArray( $margin );
		$this->assertArrayHasKey( 'price', $margin );
		$this->assertArrayHasKey( 'stripe_fee', $margin );
		$this->assertArrayHasKey( 'server_cost', $margin );
		$this->assertArrayHasKey( 'credits_cost', $margin );
		$this->assertArrayHasKey( 'margin', $margin );
		$this->assertArrayHasKey( 'margin_pct', $margin );

		// Margin should be positive.
		$this->assertGreaterThan( 0, $margin['margin'] );
	}

	/**
	 * Test get_tier_ids returns array of valid IDs.
	 */
	public function test_get_tier_ids(): void {
		$ids = Config::get_tier_ids();

		$this->assertIsArray( $ids );
		$this->assertContains( 'starter', $ids );
		$this->assertContains( 'pro', $ids );
		$this->assertContains( 'business', $ids );
		$this->assertCount( 3, $ids );
	}
}
