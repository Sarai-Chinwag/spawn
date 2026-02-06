<?php
/**
 * Environment detector for Self-Spawn feature.
 *
 * Checks if the server can run OpenClaw locally.
 *
 * @package Spawn
 */

declare(strict_types=1);

namespace Spawn;

/**
 * Class Environment_Detector
 *
 * Detects server environment capabilities for running OpenClaw.
 *
 * @since 1.0.0
 */
class Environment_Detector {

	/**
	 * Minimum required Node.js version.
	 *
	 * @var string
	 */
	private const MIN_NODE_VERSION = '18.0.0';

	/**
	 * Performs a full environment check.
	 *
	 * @since 1.0.0
	 *
	 * @return array{
	 *     node_available: bool,
	 *     node_version: string|null,
	 *     shell_access: bool,
	 *     is_vps: bool,
	 *     writable_home: bool,
	 *     systemd: bool,
	 *     can_install: bool,
	 *     blockers: array<string>
	 * } Environment check results.
	 */
	public static function check(): array {
		$node_available = self::check_node();
		$node_version   = self::get_node_version();
		$shell_access   = self::check_shell();
		$is_vps         = self::detect_vps();
		$writable_home  = self::check_home_dir();
		$systemd        = self::check_systemd();

		return array(
			'node_available' => $node_available,
			'node_version'   => $node_version,
			'shell_access'   => $shell_access,
			'is_vps'         => $is_vps,
			'writable_home'  => $writable_home,
			'systemd'        => $systemd,
			'can_install'    => self::can_install(),
			'blockers'       => self::get_blockers(),
		);
	}

	/**
	 * Checks if Node.js is available and meets minimum version requirement.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if Node.js is available and version is sufficient.
	 */
	public static function check_node(): bool {
		$version = self::get_node_version();

		if ( null === $version ) {
			return false;
		}

		// Remove 'v' prefix if present.
		$clean_version = ltrim( $version, 'v' );

		return version_compare( $clean_version, self::MIN_NODE_VERSION, '>=' );
	}

	/**
	 * Gets the installed Node.js version.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null Version string like "v22.12.0" or null if not available.
	 */
	public static function get_node_version(): ?string {
		if ( ! self::check_shell() ) {
			return null;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
		$output = shell_exec( 'node --version 2>/dev/null' );

		if ( null === $output || '' === trim( $output ) ) {
			return null;
		}

		$version = trim( $output );

		// Validate version format (vX.Y.Z).
		if ( ! preg_match( '/^v?\d+\.\d+\.\d+/', $version ) ) {
			return null;
		}

		return $version;
	}

	/**
	 * Checks if shell command execution is available.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if shell_exec is available and not disabled.
	 */
	public static function check_shell(): bool {
		// Check if function exists.
		if ( ! function_exists( 'shell_exec' ) ) {
			return false;
		}

		// Check if function is disabled.
		$disabled_functions = ini_get( 'disable_functions' );
		if ( false !== $disabled_functions && '' !== $disabled_functions ) {
			$disabled = array_map( 'trim', explode( ',', $disabled_functions ) );
			if ( in_array( 'shell_exec', $disabled, true ) ) {
				return false;
			}
		}

		// Try a simple command to verify it works.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
		$result = shell_exec( 'echo "test" 2>/dev/null' );

		return null !== $result && 'test' === trim( $result );
	}

	/**
	 * Detects if running on a VPS vs shared hosting.
	 *
	 * Checks for indicators of VPS/dedicated server environment:
	 * - systemd availability
	 * - /proc/1/cgroup contents
	 * - Presence of virtualization indicators
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if likely running on VPS/dedicated server.
	 */
	public static function detect_vps(): bool {
		if ( ! self::check_shell() ) {
			return false;
		}

		// Check for systemd (strong VPS indicator).
		if ( self::check_systemd() ) {
			return true;
		}

		// Check /proc/1/cgroup for container/VM indicators.
		if ( is_readable( '/proc/1/cgroup' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$cgroup = file_get_contents( '/proc/1/cgroup' );
			if ( false !== $cgroup ) {
				// Docker, LXC, or systemd slices indicate VPS/container.
				if ( preg_match( '/(docker|lxc|systemd)/', $cgroup ) ) {
					return true;
				}
			}
		}

		// Check for virtualization via systemd-detect-virt.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
		$virt = shell_exec( 'systemd-detect-virt 2>/dev/null' );
		if ( null !== $virt && '' !== trim( $virt ) && 'none' !== trim( $virt ) ) {
			return true;
		}

		// Check if we can write to system directories (root access indicator).
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
		$whoami = shell_exec( 'whoami 2>/dev/null' );
		if ( null !== $whoami && 'root' === trim( $whoami ) ) {
			return true;
		}

		// Check for common VPS files/directories.
		$vps_indicators = array(
			'/etc/systemd/system',
			'/var/run/systemd',
			'/run/systemd',
		);

		foreach ( $vps_indicators as $path ) {
			if ( is_dir( $path ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if the home directory for OpenClaw is writable.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if ~/.openclaw can be created/written to.
	 */
	public static function check_home_dir(): bool {
		if ( ! self::check_shell() ) {
			return false;
		}

		// Get home directory.
		$home = getenv( 'HOME' );
		if ( false === $home || '' === $home ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
			$home = shell_exec( 'echo $HOME 2>/dev/null' );
			if ( null === $home || '' === trim( $home ) ) {
				// Try to get from /etc/passwd for www-data or current user.
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
				$home = shell_exec( 'getent passwd $(whoami) | cut -d: -f6 2>/dev/null' );
			}
			$home = null !== $home ? trim( $home ) : '';
		}

		if ( '' === $home ) {
			return false;
		}

		$openclaw_dir = rtrim( $home, '/' ) . '/.openclaw';

		// Check if directory exists and is writable.
		if ( is_dir( $openclaw_dir ) ) {
			return is_writable( $openclaw_dir );
		}

		// Check if parent directory is writable (can create .openclaw).
		return is_writable( $home );
	}

	/**
	 * Checks if systemd is available for service management.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if systemd is available.
	 */
	public static function check_systemd(): bool {
		if ( ! self::check_shell() ) {
			return false;
		}

		// Check for systemctl binary.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
		$systemctl = shell_exec( 'which systemctl 2>/dev/null' );
		if ( null === $systemctl || '' === trim( $systemctl ) ) {
			return false;
		}

		// Verify systemd is actually running (not just installed).
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
		$is_active = shell_exec( 'systemctl is-system-running 2>/dev/null' );
		if ( null === $is_active ) {
			return false;
		}

		$status = trim( $is_active );

		// These states indicate systemd is operational.
		return in_array( $status, array( 'running', 'degraded', 'maintenance' ), true );
	}

	/**
	 * Checks if all critical requirements pass for installation.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if OpenClaw can be installed.
	 */
	public static function can_install(): bool {
		// Critical requirements.
		if ( ! self::check_shell() ) {
			return false;
		}

		if ( ! self::check_node() ) {
			return false;
		}

		if ( ! self::check_home_dir() ) {
			return false;
		}

		// VPS is strongly recommended but not strictly required.
		// We can run without systemd using background processes.
		return true;
	}

	/**
	 * Gets a list of blocking issues preventing installation.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string> List of blocking issue descriptions.
	 */
	public static function get_blockers(): array {
		$blockers = array();

		if ( ! self::check_shell() ) {
			$blockers[] = __( 'Shell command execution (shell_exec) is not available. This is required for OpenClaw installation.', 'spawn' );
		}

		if ( ! self::check_node() ) {
			$version = self::get_node_version();
			if ( null === $version ) {
				$blockers[] = __( 'Node.js is not installed. Please install Node.js v18 or later.', 'spawn' );
			} else {
				$blockers[] = sprintf(
					/* translators: 1: Current Node.js version, 2: Minimum required version */
					__( 'Node.js version %1$s is too old. Please upgrade to v%2$s or later.', 'spawn' ),
					$version,
					self::MIN_NODE_VERSION
				);
			}
		}

		if ( ! self::check_home_dir() ) {
			$blockers[] = __( 'Cannot write to home directory. The ~/.openclaw directory must be writable.', 'spawn' );
		}

		if ( ! self::detect_vps() ) {
			$blockers[] = __( 'Shared hosting detected. OpenClaw requires a VPS or dedicated server with process control.', 'spawn' );
		}

		return $blockers;
	}
}
