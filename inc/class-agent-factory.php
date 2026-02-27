<?php
/**
 * Agent Factory.
 *
 * Creates the appropriate Agent_Adapter based on configuration.
 * Central point for resolving which agent runtime to use.
 *
 * @package Spawn
 */

declare(strict_types=1);

namespace Spawn;

/**
 * Factory for creating Agent_Adapter instances.
 */
class Agent_Factory {

	/**
	 * Registered adapter classes keyed by type slug.
	 *
	 * @var array<string, class-string<Agent_Adapter>>
	 */
	private static array $adapters = array(
		'opencode' => OpenCode_Adapter::class,
	);

	/**
	 * Get the configured agent type.
	 *
	 * @return string Agent type slug (default: 'opencode').
	 */
	public static function get_agent_type(): string {
		return get_option( 'spawn_agent_type', 'opencode' );
	}

	/**
	 * Create an adapter for the control plane (admin chat).
	 *
	 * @return Agent_Adapter|null Adapter or null if not configured.
	 */
	public static function for_control_plane(): ?Agent_Adapter {
		$server_url = get_option( 'spawn_agent_url', '' );
		$password   = get_option( 'spawn_agent_password', '' );

		if ( empty( $server_url ) ) {
			return null;
		}

		return self::create( $server_url, $password );
	}

	/**
	 * Create an adapter for a local (self-spawn) agent.
	 *
	 * @return Agent_Adapter|null Adapter or null if not running.
	 */
	public static function for_local(): ?Agent_Adapter {
		$adapter = self::create_local_adapter();

		if ( ! $adapter || ! $adapter->health_check() ) {
			return null;
		}

		return $adapter;
	}

	/**
	 * Create an adapter for a customer's agent.
	 *
	 * @param array $customer Customer database record.
	 * @return Agent_Adapter|null Adapter or null if customer not ready.
	 */
	public static function for_customer( array $customer ): ?Agent_Adapter {
		if ( empty( $customer['server_ip'] ) ) {
			return null;
		}

		$adapter_class = self::get_adapter_class();
		$port          = $adapter_class::get_default_port();
		$server_url    = 'http://' . $customer['server_ip'] . ':' . $port;
		$password      = $customer['agent_password'] ?? '';

		return self::create( $server_url, $password );
	}

	/**
	 * Create an adapter with explicit URL and credential.
	 *
	 * @param string $server_url Server URL.
	 * @param string $credential Auth credential.
	 * @return Agent_Adapter Adapter instance.
	 */
	public static function create( string $server_url, string $credential = '' ): Agent_Adapter {
		$class = self::get_adapter_class();
		return new $class( $server_url, $credential );
	}

	/**
	 * Check if a local agent is running.
	 *
	 * @return bool True if a local agent is responding.
	 */
	public static function is_local_running(): bool {
		$adapter = self::create_local_adapter();
		return $adapter && $adapter->health_check();
	}

	/**
	 * Get the local agent server URL.
	 *
	 * @return string Local server URL.
	 */
	public static function get_local_url(): string {
		$adapter_class = self::get_adapter_class();
		return sprintf( 'http://127.0.0.1:%d', $adapter_class::get_default_port() );
	}

	/**
	 * Register a custom adapter type.
	 *
	 * @param string                       $type  Agent type slug.
	 * @param class-string<Agent_Adapter>  $class Adapter class name.
	 */
	public static function register( string $type, string $class ): void {
		self::$adapters[ $type ] = $class;
	}

	/**
	 * Get available adapter types.
	 *
	 * @return string[] Registered type slugs.
	 */
	public static function get_available_types(): array {
		return array_keys( self::$adapters );
	}

	/**
	 * Get the adapter class for the configured agent type.
	 *
	 * @return class-string<Agent_Adapter> Adapter class.
	 */
	private static function get_adapter_class(): string {
		$type = self::get_agent_type();
		return self::$adapters[ $type ] ?? self::$adapters['opencode'];
	}

	/**
	 * Create a local adapter instance (without health check).
	 *
	 * @return Agent_Adapter|null Adapter or null.
	 */
	private static function create_local_adapter(): ?Agent_Adapter {
		$password = get_option( 'spawn_local_agent_password', '' );
		$url      = self::get_local_url();

		return self::create( $url, $password );
	}
}
