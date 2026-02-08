<?php
/**
 * Page template registration.
 *
 * @package Spawn
 */

declare(strict_types=1);

namespace Spawn;

/**
 * Registers plugin page templates.
 */
class Templates {
	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		add_filter( 'theme_page_templates', array( __CLASS__, 'register_templates' ) );
		add_filter( 'template_include', array( __CLASS__, 'load_template' ) );

		// Bridge hook for ad blockers - fire theme hook too.
		add_action( 'spawn_before_page_content', array( __CLASS__, 'bridge_theme_hook' ) );
	}

	/**
	 * Register our page templates.
	 *
	 * @param array $templates Existing templates.
	 * @return array Modified templates.
	 */
	public static function register_templates( array $templates ): array {
		$templates['spawn-app'] = __( 'Spawn App', 'spawn' );
		return $templates;
	}

	/**
	 * Load our template file when selected.
	 *
	 * @param string $template Current template path.
	 * @return string Modified template path.
	 */
	public static function load_template( string $template ): string {
		if ( ! is_page() ) {
			return $template;
		}

		$page_template = get_page_template_slug();

		if ( 'spawn-app' === $page_template || 'template-spawn.php' === $page_template ) {
			$plugin_template = SPAWN_PLUGIN_DIR . 'templates/template-spawn.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		return $template;
	}

	/**
	 * Bridge to theme hook for compatibility with ad blockers.
	 */
	public static function bridge_theme_hook(): void {
		do_action( 'sarai_chinwag_before_page_content' );
	}
}
