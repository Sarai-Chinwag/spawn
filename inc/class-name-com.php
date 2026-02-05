<?php
/**
 * Name.com API integration.
 *
 * @package Spawn
 */

namespace Spawn;

use WP_Error;

/**
 * Handles Name.com API operations.
 */
class Name_Com {

	/**
	 * Get API base URL.
	 *
	 * @return string API base URL.
	 */
	private static function get_api_base(): string {
		// Use dev API if test mode or test username
		$username = get_option( 'spawn_namecom_username', '' );
		if ( str_contains( $username, 'test' ) || get_option( 'spawn_namecom_test_mode', false ) ) {
			return 'https://api.dev.name.com/v4';
		}
		return 'https://api.name.com/v4';
	}

	/**
	 * Get credentials.
	 *
	 * @return array{username: string, token: string} Credentials.
	 */
	private static function get_credentials(): array {
		return [
			'username' => get_option( 'spawn_namecom_username', '' ),
			'token'    => get_option( 'spawn_namecom_token', '' ),
		];
	}

	/**
	 * Make API request.
	 *
	 * @param string $endpoint API endpoint.
	 * @param string $method   HTTP method.
	 * @param array  $data     Request data.
	 * @return array|WP_Error Response or error.
	 */
	private static function request( string $endpoint, string $method = 'GET', array $data = [] ): array|WP_Error {
		$creds = self::get_credentials();

		if ( empty( $creds['username'] ) || empty( $creds['token'] ) ) {
			return new WP_Error(
				'namecom_not_configured',
				__( 'Name.com is not configured', 'spawn' ),
				[ 'status' => 500 ]
			);
		}

		$args = [
			'method'  => $method,
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode( $creds['username'] . ':' . $creds['token'] ),
				'Content-Type'  => 'application/json',
			],
			'timeout' => 30,
		];

		if ( ! empty( $data ) && in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( self::get_api_base() . $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 ) {
			$message = $body['message'] ?? __( 'Name.com API error', 'spawn' );
			return new WP_Error(
				'namecom_api_error',
				$message,
				[ 'status' => $code ]
			);
		}

		return $body ?? [];
	}

	/**
	 * Check domain availability.
	 *
	 * @param string $domain Domain to check.
	 * @return array|WP_Error Availability result or error.
	 */
	public static function check_availability( string $domain ): array|WP_Error {
		$result = self::request( '/domains:checkAvailability', 'POST', [
			'domainNames' => [ $domain ],
		] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$results = $result['results'] ?? [];
		
		if ( empty( $results ) ) {
			return new WP_Error(
				'no_results',
				__( 'No availability results returned', 'spawn' ),
				[ 'status' => 500 ]
			);
		}

		$domain_result = $results[0];

		return [
			'domain'      => $domain_result['domainName'] ?? $domain,
			'available'   => $domain_result['purchasable'] ?? false,
			'premium'     => $domain_result['premium'] ?? false,
			'price'       => $domain_result['purchasePrice'] ?? null,
			'renewal'     => $domain_result['renewalPrice'] ?? null,
		];
	}

	/**
	 * Search for available domains.
	 *
	 * @param string $keyword Keyword to search.
	 * @param array  $tlds    TLDs to check (default: com, net, org).
	 * @return array|WP_Error Search results or error.
	 */
	public static function search( string $keyword, array $tlds = [ 'com', 'net', 'org' ] ): array|WP_Error {
		$domains = [];
		foreach ( $tlds as $tld ) {
			$domains[] = $keyword . '.' . $tld;
		}

		$result = self::request( '/domains:checkAvailability', 'POST', [
			'domainNames' => $domains,
		] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$available = [];
		foreach ( $result['results'] ?? [] as $domain_result ) {
			if ( $domain_result['purchasable'] ?? false ) {
				$available[] = [
					'domain'  => $domain_result['domainName'],
					'premium' => $domain_result['premium'] ?? false,
					'price'   => $domain_result['purchasePrice'] ?? null,
				];
			}
		}

		return $available;
	}
}
