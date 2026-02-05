<?php
/**
 * Simple test to verify test runner works.
 *
 * @package Spawn
 */

echo ">>> SimpleTest.php LOADED <<<\n";

/**
 * Simple test class - no WP dependencies.
 */
class SimpleTest extends \PHPUnit\Framework\TestCase {

	/**
	 * Test that true is true.
	 */
	public function test_true_is_true(): void {
		fwrite( STDERR, ">>> test_true_is_true RUNNING <<<\n" );
		$this->assertTrue( true );
	}

	/**
	 * Test arithmetic.
	 */
	public function test_addition(): void {
		$this->assertEquals( 4, 2 + 2 );
	}

	/**
	 * Test that Config class exists.
	 */
	public function test_config_class_exists(): void {
		$this->assertTrue( class_exists( 'Spawn\\Config' ) );
	}
}
