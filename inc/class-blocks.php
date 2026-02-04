<?php
/**
 * Block registration and management.
 *
 * @package Spawn
 */

namespace Spawn;

/**
 * Handles Gutenberg block registration.
 */
class Blocks {

	/**
	 * Initialize blocks.
	 */
	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register_blocks' ] );
	}

	/**
	 * Register all blocks.
	 */
	public static function register_blocks(): void {
		$blocks = [
			'domain-search',
			'tier-select',
			'checkout',
			'login',
			'dashboard',
			'account',
		];

		foreach ( $blocks as $block ) {
			$block_path = SPAWN_PLUGIN_DIR . 'blocks/' . $block;
			
			if ( file_exists( $block_path . '/block.json' ) ) {
				register_block_type( $block_path );
			}
		}
	}
}
