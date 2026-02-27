<?php
/**
 * Local OpenCode detection.
 *
 * Detects if OpenCode server is running locally on the same server as WordPress.
 * Used by the chat controller to route admin chat to the local instance.
 *
 * @package Spawn
 */

declare(strict_types=1);

namespace Spawn;

/**
 * Detects a locally-running OpenCode server.
 */
class Local_OpenCode {

	/**
	 * Default server port.
	 *
	 * @var int
	 */
	private const SERVER_PORT = 4096;

	/**
	 * Server host.
	 *
	 * @var string
	 */
	private const SERVER_HOST = '127.0.0.1';

	/**
	 * Check if OpenCode server is running locally.
	 *
	 * @return bool True if a local OpenCode server is responding.
	 */
	public static function is_running(): bool {
		$response = wp_remote_get(
			self::get_server_url() . '/global/health',
			array(
				'timeout'   => 2,
				'sslverify' => false,
			)
		);

		return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;
	}

	/**
	 * Get the local server URL.
	 *
	 * @return string Server URL.
	 */
	public static function get_server_url(): string {
		return sprintf( 'http://%s:%d', self::SERVER_HOST, self::SERVER_PORT );
	}
}
