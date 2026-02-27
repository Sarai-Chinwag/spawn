<?php
/**
 * OpenCode agent adapter.
 *
 * Implements the Agent_Adapter interface for OpenCode's HTTP server API.
 * API docs: https://opencode.ai/docs/server/
 *
 * @package Spawn
 */

declare(strict_types=1);

namespace Spawn;

use WP_Error;

/**
 * OpenCode adapter — talks to `opencode serve` HTTP API.
 */
class OpenCode_Adapter extends Agent_Adapter {

	/**
	 * Default server port.
	 */
	private const DEFAULT_PORT = 4096;

	/**
	 * Health check endpoint.
	 */
	private const HEALTH_PATH = '/global/health';

	/**
	 * {@inheritDoc}
	 */
	public function health_check(): bool {
		$response = wp_remote_get(
			$this->server_url . self::HEALTH_PATH,
			array(
				'timeout'   => 2,
				'sslverify' => false,
			)
		);

		return ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response );
	}

	/**
	 * {@inheritDoc}
	 */
	public function create_session(): string|WP_Error {
		$response = wp_remote_post(
			$this->server_url . '/session',
			array(
				'headers' => $this->get_auth_headers(),
				'body'    => wp_json_encode( array() ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'agent_connection_failed',
				__( 'Failed to connect to agent: ', 'spawn' ) . $response->get_error_message()
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 401 === $code ) {
			return new WP_Error( 'agent_auth_failed', __( 'Agent authentication failed. Check password in Settings → Spawn.', 'spawn' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$id   = $body['id'] ?? '';

		if ( empty( $id ) ) {
			return new WP_Error( 'agent_session_failed', __( 'Failed to create agent session.', 'spawn' ) );
		}

		return $id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function send_message( string $session_id, string $message, string $system_prompt = '' ): array|WP_Error {
		$payload = array(
			'parts' => array(
				array(
					'type' => 'text',
					'text' => $message,
				),
			),
		);

		if ( ! empty( $system_prompt ) ) {
			$payload['system'] = $system_prompt;
		}

		$response = wp_remote_post(
			$this->server_url . '/session/' . $session_id . '/message',
			array(
				'headers' => $this->get_auth_headers(),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'agent_connection_failed',
				__( 'Failed to connect to agent: ', 'spawn' ) . $response->get_error_message()
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $code ) {
			return new WP_Error( 'agent_auth_failed', __( 'Agent authentication failed.', 'spawn' ) );
		}

		if ( $code >= 400 ) {
			$error_msg = $body['error']['message'] ?? $body['error'] ?? "HTTP $code";
			return new WP_Error( 'agent_error', "Agent error: $error_msg" );
		}

		$reply = $this->extract_reply( $body );

		return array(
			'reply'      => $reply,
			'session_id' => $session_id,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function list_sessions(): array|WP_Error {
		$response = wp_remote_get(
			$this->server_url . '/session',
			array(
				'headers' => $this->get_auth_headers(),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'agent_connection_failed',
				__( 'Failed to connect to agent server', 'spawn' ),
				array( 'status' => 502 )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			return new WP_Error(
				'agent_error',
				$body['error'] ?? __( 'Agent request failed', 'spawn' ),
				array( 'status' => $code )
			);
		}

		return is_array( $body ) ? $body : array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_messages( string $session_id, int $limit = 50 ): array|WP_Error {
		$url = $this->server_url . '/session/' . urlencode( $session_id ) . '/message';

		if ( $limit > 0 ) {
			$url .= '?limit=' . $limit;
		}

		$response = wp_remote_get(
			$url,
			array(
				'headers' => $this->get_auth_headers(),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'agent_connection_failed',
				__( 'Failed to connect to agent server', 'spawn' ),
				array( 'status' => 502 )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			return new WP_Error(
				'agent_error',
				$body['error'] ?? __( 'Agent request failed', 'spawn' ),
				array( 'status' => $code )
			);
		}

		return is_array( $body ) ? $body : array();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_auth_headers(): array {
		$headers = array(
			'Content-Type' => 'application/json',
		);

		if ( ! empty( $this->credential ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			$headers['Authorization'] = 'Basic ' . base64_encode( 'opencode:' . $this->credential );
		}

		return $headers;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function extract_reply( array $body ): ?string {
		// Direct parts array in response.
		$parts = $body['parts'] ?? array();

		foreach ( $parts as $part ) {
			if ( isset( $part['type'] ) && 'text' === $part['type'] && ! empty( $part['content'] ) ) {
				return $part['content'];
			}
		}

		// Fallback: try nested under result.
		if ( isset( $body['result']['parts'] ) ) {
			foreach ( $body['result']['parts'] as $part ) {
				if ( isset( $part['type'] ) && 'text' === $part['type'] && ! empty( $part['content'] ) ) {
					return $part['content'];
				}
			}
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_default_port(): int {
		return self::DEFAULT_PORT;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_health_path(): string {
		return self::HEALTH_PATH;
	}
}
