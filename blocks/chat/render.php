<?php
/**
 * Spawn Chat Block Render
 *
 * @package Spawn\Blocks
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only show if user is logged in.
if ( ! is_user_logged_in() ) {
	printf(
		'<div class="spawn-chat-login-prompt"><p>%s</p><a href="%s" class="button">%s</a></div>',
		esc_html__( 'Please log in to use Spawn AI.', 'spawn' ),
		esc_url( wp_login_url( get_permalink() ) ),
		esc_html__( 'Log In', 'spawn' )
	);
	return;
}

// Get Spawn customer for this user.
$customer = \Spawn\Database::get_customer_by_user_id( get_current_user_id() );
if ( ! $customer ) {
	printf(
		'<div class="spawn-chat-error"><p>%s</p></div>',
		esc_html__( 'Spawn account not found. Please sign up first.', 'spawn' )
	);
	return;
}

// Check if customer has active site.
$site_id = $customer->site_id ?? 0;
if ( ! $site_id ) {
	printf(
		'<div class="spawn-chat-no-site"><p>%s</p><a href="%s" class="button">%s</a></div>',
		esc_html__( 'Your site is being provisioned. Please check back shortly.', 'spawn' ),
		esc_url( home_url( '/spawn/dashboard/' ) ),
		esc_html__( 'Go to Dashboard', 'spawn' )
	);
	return;
}

// Build config for React.
$config = array(
	'agentId'        => $customer->agent_id ?? 0,
	'welcomeMessage' => $attributes['welcomeMessage'] ?? __( 'Hi! I\'m your Spawn AI assistant. How can I help you today?', 'spawn' ),
	'placeholder'    => $attributes['placeholder'] ?? __( 'Ask me anything...', 'spawn' ),
	'showSessions'   => $attributes['showSessions'] ?? true,
	'logoUrl'        => plugins_url( 'assets/images/spawn-logo.svg', SPAWN_PLUGIN_FILE ),
	'customerId'     => $customer->id,
	'siteId'         => $site_id,
);

// Container for React to mount.
printf(
	'<div id="spawn-chat-root" class="spawn-chat-container" data-config="%s"></div>',
	esc_attr( wp_json_encode( $config ) )
);
