<?php
/**
 * Server REST API Controller.
 *
 * @package Spawn
 */

namespace Spawn\Controllers;

use Spawn\Config;
use Spawn\Database;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Server controller for REST API.
 */
class Server_Controller {

	/**
	 * Register server routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'spawn/v1',
			'/servers',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_servers' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		register_rest_route(
			'spawn/v1',
			'/servers/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_server' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'spawn/v1',
			'/servers/(?P<id>\d+)/scale',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'scale' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'id'   => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'tier' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'spawn/v1',
			'/servers/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'delete_server' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * List servers for current user.
	 *
	 * @return WP_REST_Response Response.
	 */
	public static function list_servers(): WP_REST_Response {
		$servers = Database::get_servers_by_user( get_current_user_id() );

		return new WP_REST_Response( array(
			'servers' => array_map( array( __CLASS__, 'format_server' ), $servers ),
		) );
	}

	/**
	 * Get a server for current user.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function get_server( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$server = Database::get_server( (int) $request->get_param( 'id' ) );

		if ( ! $server ) {
			return new WP_Error( 'server_not_found', __( 'Server not found.', 'spawn' ), array( 'status' => 404 ) );
		}

		if ( (int) $server['user_id'] !== get_current_user_id() ) {
			return new WP_Error( 'forbidden', __( 'You do not have access to this server.', 'spawn' ), array( 'status' => 403 ) );
		}

		return new WP_REST_Response( array(
			'server' => self::format_server( $server ),
		) );
	}

	/**
	 * Scale a server tier.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function scale( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$server_id = (int) $request->get_param( 'id' );
		$new_tier  = sanitize_text_field( $request->get_param( 'tier' ) );
		$server    = Database::get_server( $server_id );

		if ( ! $server ) {
			return new WP_Error( 'server_not_found', __( 'Server not found.', 'spawn' ), array( 'status' => 404 ) );
		}

		if ( (int) $server['user_id'] !== get_current_user_id() ) {
			return new WP_Error( 'forbidden', __( 'You do not have access to this server.', 'spawn' ), array( 'status' => 403 ) );
		}

		if ( empty( $new_tier ) ) {
			return new WP_Error( 'missing_tier', __( 'Tier is required.', 'spawn' ), array( 'status' => 400 ) );
		}

		if ( ! Config::get_tier( $new_tier ) ) {
			return new WP_Error( 'invalid_tier', __( 'Invalid tier selected.', 'spawn' ), array( 'status' => 400 ) );
		}

		$updated = Database::update_server( $server_id, array( 'tier' => $new_tier ) );

		if ( ! $updated ) {
			return new WP_Error( 'update_failed', __( 'Failed to update server.', 'spawn' ), array( 'status' => 500 ) );
		}

		$server = Database::get_server( $server_id );

		return new WP_REST_Response( array(
			'success' => true,
			'server'  => self::format_server( $server ?? array() ),
		) );
	}

	/**
	 * Delete a server.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function delete_server( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$server_id = (int) $request->get_param( 'id' );
		$server    = Database::get_server( $server_id );

		if ( ! $server ) {
			return new WP_Error( 'server_not_found', __( 'Server not found.', 'spawn' ), array( 'status' => 404 ) );
		}

		if ( (int) $server['user_id'] !== get_current_user_id() ) {
			return new WP_Error( 'forbidden', __( 'You do not have access to this server.', 'spawn' ), array( 'status' => 403 ) );
		}

		$deleted = Database::delete_server( $server_id );

		if ( ! $deleted ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete server.', 'spawn' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'success' => true ) );
	}

	/**
	 * Format server response for frontend.
	 *
	 * @param array $server Server data.
	 * @return array Formatted server.
	 */
	private static function format_server( array $server ): array {
		return array(
			'id'            => (int) ( $server['id'] ?? 0 ),
			'name'          => $server['name'] ?? '',
			'tier'          => $server['tier'] ?? 'starter',
			'status'        => $server['status'] ?? 'pending',
			'server_ip'     => $server['server_ip'] ?? null,
			'has_wordpress' => ! empty( $server['has_wordpress'] ),
			'created_at'    => $server['created_at'] ?? null,
		);
	}
}
