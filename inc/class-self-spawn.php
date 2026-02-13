<?php
/**
 * Self-Spawn feature for installing OpenClaw locally.
 *
 * Allows users to install and manage OpenClaw directly on their WordPress server.
 *
 * @package Spawn
 */

declare(strict_types=1);

namespace Spawn;

/**
 * Class Self_Spawn
 *
 * Orchestrates local OpenClaw installation and management.
 *
 * @since 1.0.0
 */
class Self_Spawn {

	/**
	 * Default OpenClaw gateway port.
	 *
	 * @var int
	 */
	private const GATEWAY_PORT = 18789;

	/**
	 * OpenClaw gateway host.
	 *
	 * @var string
	 */
	private const GATEWAY_HOST = '127.0.0.1';

	/**
	 * Initializes Self-Spawn hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function init(): void {
		// Add admin notices for environment issues.
		add_action( 'admin_init', array( __CLASS__, 'admin_init_checks' ) );
	}

	/**
	 * Runs initialization checks on admin pages.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function admin_init_checks(): void {
		// Only run on Spawn admin pages.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'spawn' ) === false ) {
			return;
		}

		// Credential checks removed — wp-ai-client dependency eliminated.
	}

	/**
	 * Gets the current OpenClaw status.
	 *
	 * @since 1.0.0
	 *
	 * @return array{
	 *     installed: bool,
	 *     running: bool,
	 *     gateway_url: string,
	 *     version: string|null,
	 *     config_exists: bool
	 * } Status information.
	 */
	public static function get_status(): array {
		$installed = self::is_openclaw_installed();

		return array(
			'installed'     => $installed,
			'running'       => $installed && self::is_openclaw_running(),
			'gateway_url'   => self::get_gateway_url(),
			'version'       => $installed ? self::get_openclaw_version() : null,
			'config_exists' => self::config_exists(),
		);
	}

	/**
	 * Checks if OpenClaw is installed.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if openclaw command is available.
	 */
	public static function is_openclaw_installed(): bool {
		if ( ! Environment_Detector::check_shell() ) {
			return false;
		}

		$result = self::run_command( 'which openclaw' );

		return $result['success'] && '' !== trim( $result['output'] );
	}

	/**
	 * Checks if OpenClaw gateway is running.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if gateway process is running.
	 */
	public static function is_openclaw_running(): bool {
		if ( ! Environment_Detector::check_shell() ) {
			return false;
		}

		// Method 1: Check via openclaw gateway status command.
		$result = self::run_command( 'openclaw gateway status 2>/dev/null' );
		if ( $result['success'] && strpos( strtolower( $result['output'] ), 'running' ) !== false ) {
			return true;
		}

		// Method 2: Check if process is listening on the gateway port.
		$port_check = self::run_command( sprintf( 'ss -tlnp 2>/dev/null | grep %d', self::GATEWAY_PORT ) );
		if ( $port_check['success'] && '' !== trim( $port_check['output'] ) ) {
			return true;
		}

		// Method 3: Check for openclaw process.
		$ps_check = self::run_command( 'pgrep -f "openclaw" 2>/dev/null' );
		if ( $ps_check['success'] && '' !== trim( $ps_check['output'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Gets the OpenClaw gateway URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string Gateway URL (e.g., 'http://127.0.0.1:18789').
	 */
	public static function get_gateway_url(): string {
		return sprintf( 'http://%s:%d', self::GATEWAY_HOST, self::GATEWAY_PORT );
	}

	/**
	 * Gets the installed OpenClaw version.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null Version string or null if not installed.
	 */
	public static function get_openclaw_version(): ?string {
		if ( ! self::is_openclaw_installed() ) {
			return null;
		}

		$result = self::run_command( 'openclaw --version 2>/dev/null' );

		if ( ! $result['success'] || '' === trim( $result['output'] ) ) {
			return null;
		}

		return trim( $result['output'] );
	}

	/**
	 * Installs OpenClaw via npm.
	 *
	 * @since 1.0.0
	 *
	 * @return array{success: bool, message: string} Installation result.
	 */
	public static function install(): array {
		// Pre-flight checks.
		if ( ! Environment_Detector::can_install() ) {
			$blockers = Environment_Detector::get_blockers();
			return array(
				'success' => false,
				'message' => implode( ' ', $blockers ),
			);
		}

		if ( self::is_openclaw_installed() ) {
			return array(
				'success' => true,
				'message' => __( 'OpenClaw is already installed.', 'spawn' ),
			);
		}

		// Install via npm.
		$result = self::run_command( 'npm install -g openclaw 2>&1' );

		if ( ! $result['success'] ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: Error message */
					__( 'Failed to install OpenClaw: %s', 'spawn' ),
					$result['error'] ? $result['error'] : $result['output']
				),
			);
		}

		// Verify installation.
		if ( ! self::is_openclaw_installed() ) {
			return array(
				'success' => false,
				'message' => __( 'OpenClaw installation completed but command not found. Check your PATH.', 'spawn' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'OpenClaw installed successfully.', 'spawn' ),
		);
	}

	/**
	 * Configures OpenClaw with environment variables.
	 *
	 * Writes configuration to ~/.openclaw/openclaw.json.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $env Optional additional environment variables.
	 * @return array{success: bool, message: string} Configuration result.
	 */
	public static function configure( array $env = array() ): array {
		// Use provided env vars directly (credentials come from caller, not wp-ai-client).
		$all_env = $env;

		if ( empty( $all_env ) ) {
			return array(
				'success' => false,
				'message' => __( 'No AI credentials provided. Pass API keys via the env parameter.', 'spawn' ),
			);
		}

		// Get config directory path.
		$config_dir = self::get_config_dir();
		if ( null === $config_dir ) {
			return array(
				'success' => false,
				'message' => __( 'Could not determine OpenClaw config directory.', 'spawn' ),
			);
		}

		// Create directory if it doesn't exist.
		if ( ! is_dir( $config_dir ) ) {
			$result = self::run_command( sprintf( 'mkdir -p %s', escapeshellarg( $config_dir ) ) );
			if ( ! $result['success'] ) {
				return array(
					'success' => false,
					'message' => __( 'Failed to create OpenClaw config directory.', 'spawn' ),
				);
			}
		}

		// Build configuration.
		$config = array(
			'env' => $all_env,
		);

		$config_path = $config_dir . '/openclaw.json';
		$json        = wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( false === $json ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to encode configuration as JSON.', 'spawn' ),
			);
		}

		// Write config file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$written = file_put_contents( $config_path, $json );

		if ( false === $written ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to write OpenClaw configuration file.', 'spawn' ),
			);
		}

		// Set secure permissions.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		chmod( $config_path, 0600 );

		return array(
			'success' => true,
			'message' => __( 'OpenClaw configured successfully.', 'spawn' ),
		);
	}

	/**
	 * Starts the OpenClaw gateway service.
	 *
	 * @since 1.0.0
	 *
	 * @return array{success: bool, message: string} Start result.
	 */
	public static function start_service(): array {
		if ( ! self::is_openclaw_installed() ) {
			return array(
				'success' => false,
				'message' => __( 'OpenClaw is not installed.', 'spawn' ),
			);
		}

		if ( self::is_openclaw_running() ) {
			return array(
				'success' => true,
				'message' => __( 'OpenClaw gateway is already running.', 'spawn' ),
			);
		}

		// Try systemd first if available.
		if ( Environment_Detector::check_systemd() ) {
			$result = self::run_command( 'systemctl start openclaw 2>&1' );
			if ( $result['success'] ) {
				// Give it a moment to start.
				sleep( 2 );
				if ( self::is_openclaw_running() ) {
					return array(
						'success' => true,
						'message' => __( 'OpenClaw gateway started via systemd.', 'spawn' ),
					);
				}
			}
		}

		// Fall back to openclaw command.
		$result = self::run_command( 'openclaw gateway start 2>&1' );

		// Give it a moment to start.
		sleep( 2 );

		if ( self::is_openclaw_running() ) {
			return array(
				'success' => true,
				'message' => __( 'OpenClaw gateway started successfully.', 'spawn' ),
			);
		}

		return array(
			'success' => false,
			'message' => sprintf(
				/* translators: %s: Error output */
				__( 'Failed to start OpenClaw gateway: %s', 'spawn' ),
				$result['error'] ? $result['error'] : $result['output']
			),
		);
	}

	/**
	 * Stops the OpenClaw gateway service.
	 *
	 * @since 1.0.0
	 *
	 * @return array{success: bool, message: string} Stop result.
	 */
	public static function stop_service(): array {
		if ( ! self::is_openclaw_installed() ) {
			return array(
				'success' => false,
				'message' => __( 'OpenClaw is not installed.', 'spawn' ),
			);
		}

		if ( ! self::is_openclaw_running() ) {
			return array(
				'success' => true,
				'message' => __( 'OpenClaw gateway is not running.', 'spawn' ),
			);
		}

		// Try systemd first if available.
		if ( Environment_Detector::check_systemd() ) {
			$result = self::run_command( 'systemctl stop openclaw 2>&1' );
			if ( $result['success'] ) {
				sleep( 1 );
				if ( ! self::is_openclaw_running() ) {
					return array(
						'success' => true,
						'message' => __( 'OpenClaw gateway stopped via systemd.', 'spawn' ),
					);
				}
			}
		}

		// Fall back to openclaw command.
		$result = self::run_command( 'openclaw gateway stop 2>&1' );

		sleep( 1 );

		if ( ! self::is_openclaw_running() ) {
			return array(
				'success' => true,
				'message' => __( 'OpenClaw gateway stopped successfully.', 'spawn' ),
			);
		}

		// Force kill as last resort.
		self::run_command( 'pkill -f "openclaw" 2>/dev/null' );
		sleep( 1 );

		if ( ! self::is_openclaw_running() ) {
			return array(
				'success' => true,
				'message' => __( 'OpenClaw gateway stopped (forced).', 'spawn' ),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Failed to stop OpenClaw gateway.', 'spawn' ),
		);
	}

	/**
	 * Restarts the OpenClaw gateway service.
	 *
	 * @since 1.0.0
	 *
	 * @return array{success: bool, message: string} Restart result.
	 */
	public static function restart_service(): array {
		$stop_result = self::stop_service();

		// Even if stop "fails", try to start anyway.
		$start_result = self::start_service();

		if ( $start_result['success'] ) {
			return array(
				'success' => true,
				'message' => __( 'OpenClaw gateway restarted successfully.', 'spawn' ),
			);
		}

		return array(
			'success' => false,
			'message' => sprintf(
				/* translators: %s: Error message */
				__( 'Failed to restart OpenClaw gateway: %s', 'spawn' ),
				$start_result['message']
			),
		);
	}

	/**
	 * Uninstalls OpenClaw.
	 *
	 * @since 1.0.0
	 *
	 * @return array{success: bool, message: string} Uninstall result.
	 */
	public static function uninstall(): array {
		if ( ! self::is_openclaw_installed() ) {
			return array(
				'success' => true,
				'message' => __( 'OpenClaw is not installed.', 'spawn' ),
			);
		}

		// Stop service first.
		if ( self::is_openclaw_running() ) {
			self::stop_service();
		}

		// Remove via npm.
		$result = self::run_command( 'npm uninstall -g openclaw 2>&1' );

		if ( ! $result['success'] ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: Error message */
					__( 'Failed to uninstall OpenClaw: %s', 'spawn' ),
					$result['error'] ? $result['error'] : $result['output']
				),
			);
		}

		// Verify uninstallation.
		if ( self::is_openclaw_installed() ) {
			return array(
				'success' => false,
				'message' => __( 'Uninstall command completed but OpenClaw is still detected.', 'spawn' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'OpenClaw uninstalled successfully.', 'spawn' ),
		);
	}

	/**
	 * Runs a shell command and returns structured result.
	 *
	 * @since 1.0.0
	 *
	 * @param string $cmd Command to execute.
	 * @return array{success: bool, output: string, error: string} Command result.
	 */
	public static function run_command( string $cmd ): array {
		if ( ! Environment_Detector::check_shell() ) {
			return array(
				'success' => false,
				'output'  => '',
				'error'   => __( 'Shell execution is not available.', 'spawn' ),
			);
		}

		// Execute command.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
		$output = shell_exec( $cmd );

		if ( null === $output ) {
			return array(
				'success' => false,
				'output'  => '',
				'error'   => __( 'Command execution failed.', 'spawn' ),
			);
		}

		// For most commands, non-empty output indicates success.
		// Error checking is done via 2>&1 redirection in commands.
		$output = trim( $output );

		// Check for common error indicators.
		$has_error = preg_match( '/(error|failed|not found|permission denied)/i', $output );

		return array(
			'success' => ! $has_error,
			'output'  => $output,
			'error'   => $has_error ? $output : '',
		);
	}

	/**
	 * Gets the OpenClaw configuration directory path.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null Config directory path or null if undetermined.
	 */
	private static function get_config_dir(): ?string {
		$home = getenv( 'HOME' );

		if ( false === $home || '' === $home ) {
			// Try to get from shell.
			$result = self::run_command( 'echo $HOME' );
			if ( $result['success'] && '' !== $result['output'] ) {
				$home = $result['output'];
			}
		}

		if ( ! is_string( $home ) || '' === $home ) {
			// Try getent as last resort.
			$result = self::run_command( 'getent passwd $(whoami) | cut -d: -f6' );
			if ( $result['success'] && '' !== $result['output'] ) {
				$home = $result['output'];
			}
		}

		if ( ! is_string( $home ) || '' === $home ) {
			return null;
		}

		return rtrim( $home, '/' ) . '/.openclaw';
	}

	/**
	 * Checks if OpenClaw configuration file exists.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if config file exists.
	 */
	private static function config_exists(): bool {
		$config_dir = self::get_config_dir();

		if ( null === $config_dir ) {
			return false;
		}

		return file_exists( $config_dir . '/openclaw.json' );
	}
}
