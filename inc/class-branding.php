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

	/**
	 * Get formatted brand name HTML.
	 *
	 * Formats "Spawn by Sarai Chinwag" as:
	 * <strong>Spawn</strong> <em>by Sarai Chinwag</em>
	 *
	 * @return string HTML for the brand name.
	 */
	public static function get_brand_name_html(): string {
		$name = self::get_brand_name();

		// Check if name contains " by " to split
		if ( preg_match( '/^(.+?)(\s+by\s+.+)$/i', $name, $matches ) ) {
			return '<strong>' . esc_html( $matches[1] ) . '</strong><em>' . esc_html( $matches[2] ) . '</em>';
		}

		// No "by" found, return as-is
		return '<strong>' . esc_html( $name ) . '</strong>';
	}
}
