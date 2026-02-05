<?php
/**
 * Simple WordPress test.
 *
 * @package Spawn
 */

/**
 * WordPress test case.
 */
class WPSimpleTest extends WP_UnitTestCase {

	/**
	 * Test WordPress is loaded.
	 */
	public function test_wordpress_loaded(): void {
		$this->assertTrue( function_exists( 'wp_insert_post' ) );
	}

	/**
	 * Test true is true.
	 */
	public function test_true(): void {
		$this->assertTrue( true );
	}
}
