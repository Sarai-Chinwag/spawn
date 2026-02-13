<?php
/**
 * Local OpenClaw detection.
 *
 * Detects if OpenClaw is running locally on the same server as WordPress.
 * Used by the chat controller to route admin chat to the local instance.
 *
 * @package Spawn
 */

declare(strict_types=1);

namespace Spawn;

/**
 * Detects a locally-running OpenClaw gateway.
 */
class Local_OpenClaw {

	/**
	 * Default gateway port.
	 *
	 * @var int
	 */
	private const GATEWAY_PORT = 18789;

	/**
	 * Gateway host.
	 *
	 * @var string
	 */
	private const GATEWAY_HOST = '127.0.0.1';

	/**
	 * Check if OpenClaw is running locally by hitting the gateway.
	 *
	 * @return bool True if a local OpenClaw gateway is responding.
	 */
	public static function is_running(): bool {
		$response = wp_remote_get(
			self::get_gateway_url() . '/health',
			array(
				'timeout'   => 2,
				'sslverify' => false,
			)
		);

		return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;
	}

	/**
	 * Get the local gateway URL.
	 *
	 * @return string Gateway URL.
	 */
	public static function get_gateway_url(): string {
		return sprintf( 'http://%s:%d', self::GATEWAY_HOST, self::GATEWAY_PORT );
	}
}
