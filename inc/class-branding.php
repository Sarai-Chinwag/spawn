<?php
/**
 * Branding helpers.
 *
 * @package Spawn
 */

declare(strict_types=1);

namespace Spawn;

/**
 * Provides branding configuration.
 */
class Branding {
	/**
	 * Get configured subdomain suffix.
	 */
	public static function get_subdomain_suffix(): string {
		return (string) get_option( 'spawn_subdomain_suffix', '' );
	}

	/**
	 * Get brand name.
	 */
	public static function get_brand_name(): string {
		return (string) get_option( 'spawn_brand_name', 'Spawn' );
	}

	/**
	 * Get brand logo URL.
	 *
	 * Falls back to site icon if no custom brand logo is configured.
	 */
	public static function get_brand_logo_url(): string {
		$custom_logo = get_option( 'spawn_brand_logo_url', '' );
		if ( '' !== $custom_logo ) {
			return (string) $custom_logo;
		}

		// Fall back to site icon.
		return (string) get_site_icon_url( 64 );
	}

	/**
	 * Get API base URL.
	 */
	public static function get_api_base_url(): string {
		return (string) get_option( 'spawn_api_base_url', '' );
	}

	/**
	 * Get full subdomain for a prefix.
	 *
	 * @param string $subdomain Subdomain prefix.
	 */
	public static function get_full_subdomain( string $subdomain ): string {
		return $subdomain . '.' . self::get_subdomain_suffix();
	}
}
