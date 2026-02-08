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
		return array(
			'username' => get_option( 'spawn_namecom_username', '' ),
			'token'    => get_option( 'spawn_namecom_token', '' ),
		);
	}

	/**
	 * Make API request.
	 *
	 * @param string $endpoint API endpoint.
	 * @param string $method   HTTP method.
	 * @param array  $data     Request data.
	 * @return array|WP_Error Response or error.
	 */
	private static function request( string $endpoint, string $method = 'GET', array $data = array() ): array|WP_Error {
		$creds = self::get_credentials();

		if ( empty( $creds['username'] ) || empty( $creds['token'] ) ) {
			return new WP_Error(
				'namecom_not_configured',
				__( 'Name.com is not configured', 'spawn' ),
				array( 'status' => 500 )
			);
		}

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $creds['username'] . ':' . $creds['token'] ),
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
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
				array( 'status' => $code )
			);
		}

		return $body ?? array();
	}

	/**
	 * Check domain availability.
	 *
	 * @param string $domain Domain to check.
	 * @return array|WP_Error Availability result or error.
	 */
	public static function check_availability( string $domain ): array|WP_Error {
		$result = self::request( '/domains:checkAvailability', 'POST', array(
			'domainNames' => array( $domain ),
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$results = $result['results'] ?? array();

		if ( empty( $results ) ) {
			return new WP_Error(
				'no_results',
				__( 'No availability results returned', 'spawn' ),
				array( 'status' => 500 )
			);
		}

		$domain_result = $results[0];

		return array(
			'domain'    => $domain_result['domainName'] ?? $domain,
			'available' => $domain_result['purchasable'] ?? false,
			'premium'   => $domain_result['premium'] ?? false,
			'price'     => $domain_result['purchasePrice'] ?? null,
			'renewal'   => $domain_result['renewalPrice'] ?? null,
		);
	}

	/**
	 * Search for available domains.
	 *
	 * @param string $keyword Keyword to search.
	 * @param array  $tlds    TLDs to check (default: com, net, org).
	 * @return array|WP_Error Search results or error.
	 */
	public static function search( string $keyword, array $tlds = array( 'com', 'net', 'org' ) ): array|WP_Error {
		$domains = array();
		foreach ( $tlds as $tld ) {
			$domains[] = $keyword . '.' . $tld;
		}

		$result = self::request( '/domains:checkAvailability', 'POST', array(
			'domainNames' => $domains,
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$available = array();
		foreach ( $result['results'] ?? array() as $domain_result ) {
			if ( $domain_result['purchasable'] ?? false ) {
				$available[] = array(
					'domain'  => $domain_result['domainName'],
					'premium' => $domain_result['premium'] ?? false,
					'price'   => $domain_result['purchasePrice'] ?? null,
				);
			}
		}

		return $available;
	}

	/**
	 * Get domain details including expiration.
	 *
	 * @param string $domain Domain name.
	 * @return array|WP_Error Domain details or error.
	 */
	public static function get_domain( string $domain ): array|WP_Error {
		return self::request( '/domains/' . rawurlencode( $domain ) );
	}

	/**
	 * Renew a domain for specified years.
	 *
	 * @param string $domain Domain to renew.
	 * @param int    $years  Number of years to renew (default: 1).
	 * @return array|WP_Error Renewal result with new expiration or error.
	 */
	public static function renew( string $domain, int $years = 1 ): array|WP_Error {
		$result = self::request(
			'/domains/' . rawurlencode( $domain ) . ':renew',
			'POST',
			array( 'years' => $years )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'domain'     => $result['domainName'] ?? $domain,
			'expires_at' => $result['expireDate'] ?? null,
			'renewed'    => true,
		);
	}

	/**
	 * Register a domain for specified years.
	 *
	 * @param string $domain Domain to register.
	 * @param int    $years  Number of years to register (default: 1).
	 * @return array|WP_Error Registration result with expiration or error.
	 */
	public static function register( string $domain, int $years = 1 ): array|WP_Error {
		$result = self::request(
			'/domains',
			'POST',
			array(
				'domainName' => $domain,
				'years'      => $years,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'domain'     => $result['domainName'] ?? $domain,
			'expires_at' => $result['expireDate'] ?? null,
			'registered' => true,
		);
	}

	/**
	 * Get renewal price for a domain.
	 *
	 * @param string $domain Domain name.
	 * @return float|WP_Error Renewal price in dollars or error.
	 */
	public static function get_renewal_price( string $domain ): float|WP_Error {
		$domain_info = self::get_domain( $domain );

		if ( is_wp_error( $domain_info ) ) {
			return $domain_info;
		}

		$renewal_price = $domain_info['renewalPrice'] ?? null;

		if ( null === $renewal_price ) {
			return new WP_Error(
				'no_renewal_price',
				__( 'Unable to determine renewal price', 'spawn' ),
				array( 'status' => 500 )
			);
		}

		return (float) $renewal_price;
	}
}
