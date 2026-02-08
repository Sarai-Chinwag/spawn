<?php
/**
 * User role management for Spawn.
 *
 * @package Spawn
 */

namespace Spawn;

/**
 * Handles user roles for Spawn.
 */
class User_Role {

	/**
	 * Initialize user role functionality.
	 */
	public static function init(): void {
		add_filter( 'show_admin_bar', array( __CLASS__, 'hide_admin_bar' ) );
		add_action( 'admin_init', array( __CLASS__, 'redirect_admin' ) );
		add_action( 'login_init', array( __CLASS__, 'redirect_login_page' ) );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_spawn_landing' ) );
	}

	/**
	 * Register the spawn_customer role.
	 */
	public static function register_role(): void {
		add_role(
			'spawn_customer',
			__( 'Spawn Customer', 'spawn' ),
			array(
				'read'                        => true,
				'spawn_set_domain_auto_renew' => true,
			)
		);

		// Also add the capability to existing spawn_customer role if upgrading.
		$role = get_role( 'spawn_customer' );
		if ( $role && ! $role->has_cap( 'spawn_set_domain_auto_renew' ) ) {
			$role->add_cap( 'spawn_set_domain_auto_renew' );
		}
	}

	/**
	 * Unregister the spawn_customer role.
	 */
	public static function unregister_role(): void {
		remove_role( 'spawn_customer' );
	}

	/**
	 * Hide admin bar for spawn_customer users.
	 *
	 * @param bool $show_admin_bar Whether to show the admin bar.
	 * @return bool
	 */
	public static function hide_admin_bar( bool $show_admin_bar ): bool {
		if ( current_user_can( 'spawn_customer' ) ) {
			return false;
		}
		return $show_admin_bar;
	}

	/**
	 * Redirect spawn_customer users away from wp-admin.
	 */
	public static function redirect_admin(): void {
		if ( current_user_can( 'spawn_customer' ) && is_admin() && ! wp_doing_ajax() ) {
			wp_safe_redirect( home_url( '/spawn/dashboard/' ) );
			exit;
		}
	}

	/**
	 * Redirect wp-login.php to Spawn login page for spawn customers.
	 *
	 * This intercepts attempts to access wp-login.php and redirects to /spawn/login/.
	 * Admins can still access wp-login.php normally.
	 */
	public static function redirect_login_page(): void {
		// Don't redirect if already logged in as admin.
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return;
		}

		// Don't redirect AJAX or POST requests (actual login attempts).
		if ( wp_doing_ajax() ) {
			return;
		}

		// Check if this is an admin user trying to log in.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';

		// Allow logout action to proceed normally.
		if ( 'logout' === $action ) {
			return;
		}

		// Allow password reset via WP if someone has an old link.
		if ( in_array( $action, array( 'lostpassword', 'rp', 'resetpass' ), true ) ) {
			// Redirect to Spawn password reset instead.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';

			if ( $key && $login ) {
				$redirect_url = add_query_arg(
					array(
						'action' => 'reset',
						'key'    => $key,
						'login'  => $login,
					),
					home_url( '/spawn/login/' )
				);
			} else {
				$redirect_url = home_url( '/spawn/login/?action=forgot' );
			}

			wp_safe_redirect( $redirect_url );
			exit;
		}

		// For logged-in spawn_customer users, redirect to dashboard.
		if ( is_user_logged_in() && current_user_can( 'spawn_customer' ) ) {
			wp_safe_redirect( home_url( '/spawn/dashboard/' ) );
			exit;
		}

		// For everyone else trying to access wp-login.php login form, redirect to Spawn.
		// Preserve redirect_to if set.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$redirect_to = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';

		// Only redirect GET requests (the login form), not POST (actual logins).
		if ( 'GET' === $_SERVER['REQUEST_METHOD'] && 'login' !== $action && 'postpass' !== $action ) {
			$spawn_login = home_url( '/spawn/login/' );
			if ( $redirect_to && strpos( $redirect_to, '/spawn/' ) !== false ) {
				$spawn_login = add_query_arg( 'redirect_to', urlencode( $redirect_to ), $spawn_login );
			}
			wp_safe_redirect( $spawn_login );
			exit;
		}
	}

	/**
	 * Redirect logged-in customers from /spawn/ landing page to dashboard.
	 *
	 * The landing page is for signups. Existing customers should go to their dashboard.
	 */
	public static function redirect_spawn_landing(): void {
		// Only on the /spawn/ page (not subpages like /spawn/dashboard/).
		if ( ! is_page( 'spawn' ) ) {
			return;
		}

		// Must be logged in.
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Check if user is a Spawn customer.
		$customer = Database::get_customer_by_user_id( get_current_user_id() );
		if ( ! $customer ) {
			return;
		}

		// Redirect to dashboard.
		wp_safe_redirect( home_url( '/spawn/dashboard/' ) );
		exit;
	}
}
