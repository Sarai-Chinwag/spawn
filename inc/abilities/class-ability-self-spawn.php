<?php
/**
 * Self-Spawn abilities for local OpenClaw installation.
 *
 * @package Spawn
 */

declare(strict_types=1);

namespace Spawn\Abilities;

use Spawn\Environment_Detector;
use Spawn\Self_Spawn;

/**
 * Class Ability_Self_Spawn
 *
 * Provides abilities for self-spawn operations.
 *
 * @since 1.0.0
 */
class Ability_Self_Spawn {

	/**
	 * Executes environment check.
	 *
	 * @param array $input Input parameters (none required).
	 * @return array Environment check results.
	 */
	public static function check_environment( array $input ): array {
		$env = Environment_Detector::check();

		return array(
			'environment' => $env,
			'can_install' => $env['can_install'],
			'blockers'    => $env['blockers'],
		);
	}

	/**
	 * Gets OpenClaw installation status.
	 *
	 * @param array $input Input parameters (none required).
	 * @return array Status information.
	 */
	public static function get_status( array $input ): array {
		return Self_Spawn::get_status();
	}

	/**
	 * Installs OpenClaw locally.
	 *
	 * @param array $input Input parameters. Optional 'env' array of API key env vars.
	 * @return array Installation result.
	 */
	public static function install( array $input ): array {
		// Check environment first.
		if ( ! Environment_Detector::can_install() ) {
			return array(
				'success'  => false,
				'message'  => __( 'Environment check failed. Cannot install OpenClaw.', 'spawn' ),
				'blockers' => Environment_Detector::get_blockers(),
			);
		}

		// Run installation.
		$result = Self_Spawn::install();

		if ( ! $result['success'] ) {
			return $result;
		}

		// Configure with provided env vars.
		$env           = $input['env'] ?? array();
		$config_result = Self_Spawn::configure( $env );

		if ( ! $config_result['success'] ) {
			return array(
				'success' => false,
				'message' => __( 'OpenClaw installed but configuration failed.', 'spawn' ),
				'install' => $result,
				'config'  => $config_result,
			);
		}

		return array(
			'success' => true,
			'message' => __( 'OpenClaw installed and configured successfully.', 'spawn' ),
			'install' => $result,
			'config'  => $config_result,
		);
	}

	/**
	 * Configures OpenClaw with provided credentials.
	 *
	 * @param array $input Input parameters. Required 'env' array of API key env vars.
	 * @return array Configuration result.
	 */
	public static function configure( array $input ): array {
		if ( ! Self_Spawn::is_openclaw_installed() ) {
			return array(
				'success' => false,
				'message' => __( 'OpenClaw is not installed. Please install first.', 'spawn' ),
			);
		}

		$env = $input['env'] ?? array();

		if ( empty( $env ) ) {
			return array(
				'success' => false,
				'message' => __( 'No AI credentials provided. Pass API keys in the env parameter (e.g. ANTHROPIC_API_KEY).', 'spawn' ),
			);
		}

		return Self_Spawn::configure( $env );
	}

	/**
	 * Starts the OpenClaw service.
	 *
	 * @param array $input Input parameters (none required).
	 * @return array Start result.
	 */
	public static function start( array $input ): array {
		if ( ! Self_Spawn::is_openclaw_installed() ) {
			return array(
				'success' => false,
				'message' => __( 'OpenClaw is not installed.', 'spawn' ),
			);
		}

		return Self_Spawn::start_service();
	}

	/**
	 * Stops the OpenClaw service.
	 *
	 * @param array $input Input parameters (none required).
	 * @return array Stop result.
	 */
	public static function stop( array $input ): array {
		if ( ! Self_Spawn::is_openclaw_installed() ) {
			return array(
				'success' => false,
				'message' => __( 'OpenClaw is not installed.', 'spawn' ),
			);
		}

		return Self_Spawn::stop_service();
	}

	/**
	 * Restarts the OpenClaw service.
	 *
	 * @param array $input Input parameters (none required).
	 * @return array Restart result.
	 */
	public static function restart( array $input ): array {
		if ( ! Self_Spawn::is_openclaw_installed() ) {
			return array(
				'success' => false,
				'message' => __( 'OpenClaw is not installed.', 'spawn' ),
			);
		}

		return Self_Spawn::restart_service();
	}

	/**
	 * Uninstalls OpenClaw.
	 *
	 * @param array $input Input parameters.
	 * @return array Uninstall result.
	 */
	public static function uninstall( array $input ): array {
		$confirm = $input['confirm'] ?? false;

		if ( ! $confirm ) {
			return array(
				'success' => false,
				'message' => __( 'Please confirm uninstallation by setting confirm=true.', 'spawn' ),
				'warning' => __( 'This will remove OpenClaw and all its data. This action cannot be undone.', 'spawn' ),
			);
		}

		if ( ! Self_Spawn::is_openclaw_installed() ) {
			return array(
				'success' => false,
				'message' => __( 'OpenClaw is not installed.', 'spawn' ),
			);
		}

		return Self_Spawn::uninstall();
	}
}
