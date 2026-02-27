<?php
/**
 * Agent Adapter interface.
 *
 * Defines the contract for AI agent runtimes (OpenCode, wp-agent, etc.).
 * Spawn is agent-agnostic — all agent communication goes through this interface.
 *
 * @package Spawn
 */

declare(strict_types=1);

namespace Spawn;

use WP_Error;

/**
 * Abstract base class for agent adapters.
 */
abstract class Agent_Adapter {

	/**
	 * Server URL.
	 *
	 * @var string
	 */
	protected string $server_url;

	/**
	 * Authentication credential (password, token, etc.).
	 *
	 * @var string
	 */
	protected string $credential;

	/**
	 * Constructor.
	 *
	 * @param string $server_url Server base URL.
	 * @param string $credential Authentication credential.
	 */
	public function __construct( string $server_url, string $credential = '' ) {
		$this->server_url = rtrim( $server_url, '/' );
		$this->credential = $credential;
	}

	/**
	 * Check if the agent server is healthy.
	 *
	 * @return bool True if the server is responding.
	 */
	abstract public function health_check(): bool;

	/**
	 * Create a new chat session.
	 *
	 * @return string|WP_Error Session ID or error.
	 */
	abstract public function create_session(): string|WP_Error;

	/**
	 * Send a message to a session.
	 *
	 * @param string $session_id    Session ID.
	 * @param string $message       User message text.
	 * @param string $system_prompt Optional system prompt / context.
	 * @return array|WP_Error Response array with 'reply' and 'session_id' keys, or error.
	 */
	abstract public function send_message( string $session_id, string $message, string $system_prompt = '' ): array|WP_Error;

	/**
	 * List all sessions.
	 *
	 * @return array|WP_Error Array of session data, or error.
	 */
	abstract public function list_sessions(): array|WP_Error;

	/**
	 * Get messages for a session.
	 *
	 * @param string $session_id Session ID.
	 * @param int    $limit      Max messages to return (0 = all).
	 * @return array|WP_Error Array of messages, or error.
	 */
	abstract public function get_messages( string $session_id, int $limit = 50 ): array|WP_Error;

	/**
	 * Get the server URL.
	 *
	 * @return string Server URL.
	 */
	public function get_server_url(): string {
		return $this->server_url;
	}

	/**
	 * Get HTTP auth headers for this adapter.
	 *
	 * @return array HTTP headers.
	 */
	abstract protected function get_auth_headers(): array;

	/**
	 * Extract a text reply from the agent's response body.
	 *
	 * @param array $body Decoded response body.
	 * @return string|null Extracted text or null.
	 */
	abstract protected function extract_reply( array $body ): ?string;

	/**
	 * Get the default port for this agent type.
	 *
	 * @return int Default port number.
	 */
	abstract public static function get_default_port(): int;

	/**
	 * Get the health check endpoint path.
	 *
	 * @return string Health check path.
	 */
	abstract public static function get_health_path(): string;
}
