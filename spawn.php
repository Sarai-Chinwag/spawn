<?php
/**
 * Plugin Name: Spawn
 * Plugin URI: https://github.com/Sarai-Chinwag/spawn
 * Description: AI Website Service by Sarai Chinwag - spawn AI-powered WordPress sites
 * Version: 0.3.0
 * Author: Sarai Chinwag
 * Author URI: https://saraichinwag.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: spawn
 * Requires at least: 6.9
 * Requires PHP: 8.1
 * Requires Plugins: stripe-integration
 *
 * @package Spawn
 */

namespace Spawn;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'SPAWN_VERSION', '0.2.0' );
define( 'SPAWN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPAWN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SPAWN_PLUGIN_FILE', __FILE__ );

// Autoloader.
require_once SPAWN_PLUGIN_DIR . 'inc/autoload.php';

// Initialize the plugin.
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );

/**
 * Initialize the plugin.
 */
function init(): void {
	// Load components.
	Blocks::init();
	REST_API::init();
	Webhook::init();
	Abilities\Abilities::init();
	User_Role::init();
	Cron::init();

	// Admin settings.
	if ( is_admin() ) {
		Admin::init();
	}
}

/**
 * Activation hook.
 */
function activate(): void {
	// Create database tables if needed.
	Database::create_tables();

	// Register user role.
	User_Role::register_role();

	// Schedule cron events.
	Cron::schedule_events();

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );

/**
 * Deactivation hook.
 */
function deactivate(): void {
	// Unregister user role.
	User_Role::unregister_role();

	// Unschedule cron events.
	Cron::unschedule_events();

	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );
