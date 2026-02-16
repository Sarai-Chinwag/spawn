<?php
/**
 * Spawn-namespace function overrides for unit testing.
 *
 * PHP resolves unqualified function calls to the current namespace first.
 * By defining these functions in the Spawn namespace, we intercept all calls
 * from Spawn classes without touching global functions.
 *
 * @package Spawn\Tests
 */

declare(strict_types=1);

namespace Spawn;

function get_option( string $option, mixed $default = false ): mixed {
	return \SpawnTestState::$options[ $option ] ?? $default;
}

function wp_mail( string|false $to, string $subject, string $message, string|array $headers = '', array $attachments = [] ): bool {
	\SpawnTestState::$emails[] = compact( 'to', 'subject', 'message' );
	return true;
}

function wp_remote_request( string $url, array $args = [] ): mixed {
	\SpawnTestState::$http_requests[] = compact( 'url', 'args' );
	if ( \SpawnTestState::$next_http_response !== null ) {
		return \SpawnTestState::$next_http_response;
	}
	return [ 'response' => [ 'code' => 200 ], 'body' => '{}' ];
}

function wp_remote_retrieve_response_code( mixed $response ): int {
	if ( is_wp_error( $response ) ) {
		return 0;
	}
	return $response['response']['code'] ?? 200;
}

function wp_remote_retrieve_body( mixed $response ): string {
	if ( is_wp_error( $response ) ) {
		return '';
	}
	return $response['body'] ?? '{}';
}

function error_log( string $message ): bool {
	\SpawnTestState::$error_log[] = $message;
	return true;
}

function current_time( string $type = 'mysql', bool $gmt = false ): string {
	return '2026-02-16 19:00:00';
}

function wp_date( string $format, ?int $timestamp = null ): string {
	return date( $format, $timestamp ?? time() );
}

function sanitize_text_field( string $str ): string {
	return trim( strip_tags( $str ) );
}

function wp_json_encode( mixed $data, int $options = 0, int $depth = 512 ): string|false {
	return json_encode( $data, $options, $depth );
}

function is_wp_error( mixed $thing ): bool {
	return $thing instanceof \WP_Error;
}

function add_action( string $hook, callable|array $callback, int $priority = 10, int $args = 1 ): bool {
	return true;
}

function register_rest_route( string $namespace, string $route, array $args = [], bool $override = false ): bool {
	return true;
}

function hash_equals( string $known, string $user ): bool {
	return \hash_equals( $known, $user );
}

function wp_next_scheduled( string $hook ): int|false {
	return false;
}

function wp_schedule_event( int $timestamp, string $recurrence, string $hook ): bool {
	return true;
}

function __( string $text, string $domain = 'default' ): string {
	return $text;
}

function filter_var( mixed $value, int $filter = FILTER_DEFAULT, array|int|null $options = null ): mixed {
	if ( $options !== null ) {
		return \filter_var( $value, $filter, $options );
	}
	return \filter_var( $value, $filter );
}

function apply_filters( string $hook, mixed ...$args ): mixed {
	return $args[0] ?? null;
}

function get_admin_email(): string {
	return \SpawnTestState::$options['admin_email'] ?? 'admin@example.com';
}
