<?php
/**
 * Autoloader for Spawn plugin classes.
 *
 * @package Spawn
 */

namespace Spawn;

spl_autoload_register(
	function ( string $class ): void {
		// Only autoload Spawn classes.
		if ( strpos( $class, 'Spawn\\' ) !== 0 ) {
			return;
		}

		// Remove namespace prefix.
		$relative_class = substr( $class, strlen( 'Spawn\\' ) );

		// Convert to file path.
		$file = SPAWN_PLUGIN_DIR . 'inc/class-' . strtolower( str_replace( [ '\\', '_' ], [ '/', '-' ], $relative_class ) ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);
