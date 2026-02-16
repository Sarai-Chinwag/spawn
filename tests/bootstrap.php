<?php
/**
 * PHPUnit bootstrap for Spawn unit tests.
 *
 * Loads mock classes BEFORE the autoloader so they take precedence.
 * No WordPress installation required.
 *
 * @package Spawn\Tests
 */

declare(strict_types=1);

// Test state singleton.
require_once __DIR__ . '/stubs/test-state.php';

// WP class stubs (WP_Error, WP_REST_Request, WP_REST_Response).
require_once __DIR__ . '/stubs/wp-classes.php';

// Global WP function stubs.
require_once __DIR__ . '/stubs/wordpress.php';

// Spawn-namespace function overrides (intercept unqualified calls from Spawn classes).
require_once __DIR__ . '/stubs/spawn-functions.php';

// Mock Spawn classes — MUST load before autoloader so Webhook sees these, not the real ones.
require_once __DIR__ . '/stubs/spawn-classes.php';

// Composer autoloader (PHPUnit).
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Plugin constants.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/fake-wp/' );
}
if ( ! defined( 'SPAWN_PLUGIN_DIR' ) ) {
	define( 'SPAWN_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'SPAWN_PLUGIN_URL' ) ) {
	define( 'SPAWN_PLUGIN_URL', 'https://example.com/wp-content/plugins/spawn/' );
}
if ( ! defined( 'SPAWN_PLUGIN_FILE' ) ) {
	define( 'SPAWN_PLUGIN_FILE', dirname( __DIR__ ) . '/spawn.php' );
}
if ( ! defined( 'SPAWN_VERSION' ) ) {
	define( 'SPAWN_VERSION', '0.9.1-test' );
}

// Load the Spawn autoloader (won't re-declare already-loaded classes).
require_once dirname( __DIR__ ) . '/inc/autoload.php';

// Now load the real Webhook class (it'll use our mock deps).
require_once dirname( __DIR__ ) . '/inc/class-webhook.php';
