<?php
/**
 * Plugin Name: Spawn
 * Plugin URI: https://github.com/Sarai-Chinwag/spawn
 * Description: AI Website Service by Sarai Chinwag - spawn AI-powered WordPress sites
 * Version: 0.4.0
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
define( 'SPAWN_VERSION', '0.4.0' );
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
	Cleanup::init();

	// Admin settings.
	if ( is_admin() ) {
		Admin::init();
	}

	// Disable Grow widget on Spawn pages (cleaner app experience).
	add_action( 'wp', __NAMESPACE__ . '\\maybe_disable_grow' );
}

/**
 * Disable Grow plugin on Spawn pages for cleaner app experience.
 */
function maybe_disable_grow(): void {
	if ( ! is_page() ) {
		return;
	}

	// Check if this is a Spawn page (chat, dashboard, login, or main spawn page).
	$spawn_slugs = [ 'chat', 'dashboard', 'login', 'spawn' ];
	$post        = get_post();

	if ( ! $post ) {
		return;
	}

	// Check current page or parent page.
	$is_spawn_page = in_array( $post->post_name, $spawn_slugs, true )
		|| ( $post->post_parent && in_array( get_post( $post->post_parent )->post_name ?? '', $spawn_slugs, true ) );

	if ( ! $is_spawn_page ) {
		return;
	}

	// Remove Grow's scripts at late priority.
	add_action( 'wp_enqueue_scripts', function () {
		wp_dequeue_script( 'grow-me-sdk' );
		wp_dequeue_script( 'grow-sdk' );
		wp_dequeue_script( 'grow-for-wp' );
		wp_deregister_script( 'grow-me-sdk' );
		wp_deregister_script( 'grow-sdk' );
		wp_deregister_script( 'grow-for-wp' );
	}, 999 );

	// Hide Grow widget via CSS + JS removal (Grow loads dynamically after footer).
	add_action( 'wp_head', function () {
		echo '<style>#grow-me-container, .grow-me-widget, #grow-wp-data, [data-grow-faves-site-id] { display: none !important; }</style>';
	}, 999 );

	// Remove Grow elements via JS after they load.
	add_action( 'wp_footer', function () {
		?>
		<script>
		(function() {
			function removeGrow() {
				var els = document.querySelectorAll('#grow-me-container, .grow-me-widget, #grow-wp-data, [data-grow-initializer], [data-grow-faves-site-id]');
				els.forEach(function(el) { el.remove(); });
			}
			removeGrow();
			// Run again after Grow might have loaded dynamically.
			setTimeout(removeGrow, 1000);
			setTimeout(removeGrow, 3000);
		})();
		</script>
		<?php
	}, 9999 );
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
