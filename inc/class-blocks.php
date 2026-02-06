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
			'tier-select',
			'checkout',
			'login',
			'dashboard',
			'account',
			'chat',
			'success',
		];

		foreach ( $blocks as $block ) {
			// Use build directory if it exists (production), otherwise source (development).
			$build_path  = SPAWN_PLUGIN_DIR . 'build/blocks/' . $block;
			$source_path = SPAWN_PLUGIN_DIR . 'blocks/' . $block;

			if ( file_exists( $build_path . '/block.json' ) ) {
				register_block_type( $build_path );
			} elseif ( file_exists( $source_path . '/block.json' ) ) {
				register_block_type( $source_path );
			}
		}
	}
}
