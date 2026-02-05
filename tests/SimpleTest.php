<?php
/**
 * Simple test to verify test runner works.
 *
 * @package Spawn
 */

/**
 * Simple test class.
 */
class SimpleTest extends \PHPUnit\Framework\TestCase {

	/**
	 * Test that true is true.
	 */
	public function test_true_is_true(): void {
		$this->assertTrue( true );
	}

	/**
	 * Test arithmetic.
	 */
	public function test_addition(): void {
		$this->assertEquals( 4, 2 + 2 );
	}

	/**
	 * Test that Config class exists after WordPress loads.
	 */
	public function test_config_class_exists(): void {
		$this->assertTrue( class_exists( 'Spawn\\Config' ) );
	}
}
