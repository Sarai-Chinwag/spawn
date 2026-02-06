<?php
/**
 * Plugin Name: Spawn
 * Plugin URI: https://github.com/Sarai-Chinwag/spawn
 * Description: AI Website Service by Sarai Chinwag - spawn AI-powered WordPress sites
 * Version: 0.6.0
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
define( 'SPAWN_VERSION', '0.6.0' );
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
	Google_OAuth::init();
	Abilities\Abilities::init();
	User_Role::init();
	Cron::init();
	Cleanup::init();

	// Admin settings.
	if ( is_admin() ) {
		Admin::init();
	}

	// Disable Grow widget on Spawn pages by filtering the site_id option.
	// When site_id is empty, Grow won't initialize at all.
	add_filter( 'pre_option_grow_site_id', __NAMESPACE__ . '\\maybe_disable_grow_site_id' );
}

/**
 * Return empty site_id for Grow on Spawn pages to prevent it from loading.
 *
 * @param mixed $value Pre-filtered value.
 * @return mixed Empty string on Spawn pages, unchanged otherwise.
 */
function maybe_disable_grow_site_id( $value ) {
	// Only run on frontend.
	if ( is_admin() ) {
		return $value;
	}

	// Check if this is a Spawn page by URL since we're early in the request.
	$request_uri = $_SERVER['REQUEST_URI'] ?? '';

	// Match /spawn, /spawn/, /spawn/chat, /spawn/dashboard, etc.
	if ( preg_match( '#^/spawn(/|$)#', $request_uri ) ) {
		return ''; // Empty site_id prevents Grow from initializing.
	}

	return $value;
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
	Cleanup::deactivate();

	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );
