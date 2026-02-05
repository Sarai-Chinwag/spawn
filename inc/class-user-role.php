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
		add_filter( 'show_admin_bar', [ __CLASS__, 'hide_admin_bar' ] );
		add_action( 'admin_init', [ __CLASS__, 'redirect_admin' ] );
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
}