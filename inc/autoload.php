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

		// Handle Abilities namespace specially.
		if ( strpos( $relative_class, 'Abilities\\' ) === 0 ) {
			$ability_class = substr( $relative_class, strlen( 'Abilities\\' ) );
			$file = SPAWN_PLUGIN_DIR . 'inc/abilities/class-' . strtolower( str_replace( '_', '-', $ability_class ) ) . '.php';
		} else {
			// Convert to file path.
			$file = SPAWN_PLUGIN_DIR . 'inc/class-' . strtolower( str_replace( [ '\\', '_' ], [ '/', '-' ], $relative_class ) ) . '.php';
		}

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);
