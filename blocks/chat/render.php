<?php
/**
 * Chat block render template.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 *
 * @package Spawn
 */

// Check for fullpage attribute.
$is_fullpage = ! empty( $attributes['fullpage'] );
$extra_class = $is_fullpage ? 'wp-block-spawn-chat--fullpage' : '';

$session_key = isset( $attributes['sessionKey'] ) ? $attributes['sessionKey'] : '';
$wrapper_attributes = get_block_wrapper_attributes( [
	'class'            => $extra_class,
	'data-session-key' => $session_key,
] );

// Check if user is logged in.
if ( ! is_user_logged_in() ) {
	?>
	<div <?php echo $wrapper_attributes; ?>>
		<div class="wp-block-spawn-chat__login-required">
			<p>Please <a href="<?php echo esc_url( home_url( '/spawn/login/' ) ); ?>">log in</a> to chat with your AI.</p>
		</div>
	</div>
	<?php
	return;
}

// Get customer data.
$user_id  = get_current_user_id();
$customer = \Spawn\Database::get_customer_by_user_id( $user_id );
$is_admin = current_user_can( 'manage_options' );

if ( ! $customer && ! $is_admin ) {
	?>
	<div <?php echo $wrapper_attributes; ?>>
		<div class="wp-block-spawn-chat__no-subscription">
			<p>You don't have an active subscription. <a href="<?php echo esc_url( home_url( '/spawn/' ) ); ?>">Get started</a></p>
		</div>
	</div>
	<?php
	return;
}

// Pass customer context to JS.
if ( $is_admin && ! $customer ) {
	$chat_context = [
		'customer_id' => 0,
		'domain'      => 'saraichinwag.com',
		'status'      => 'admin',
		'has_mobile'  => true,
		'is_admin'    => true,
	];
} else {
	$chat_context = [
		'customer_id' => $customer['id'],
		'domain'      => $customer['domain'],
		'status'      => $customer['status'],
		'has_mobile'  => false, // TODO: Check if they have mobile channel configured.
		'first_visit' => empty( $customer['server_ip'] ) ? false : true,
	];
}
?>
<div <?php echo $wrapper_attributes; ?> data-context="<?php echo esc_attr( wp_json_encode( $chat_context ) ); ?>">
	<div class="wp-block-spawn-chat__container">
		<div class="wp-block-spawn-chat__topnav">
			<a href="<?php echo esc_url( home_url( '/spawn/' ) ); ?>" class="wp-block-spawn-chat__logo" title="Back to Spawn">
				<img src="https://saraichinwag.com/wp-content/uploads/2023/08/sarai-chinwag.jpeg" alt="Sarai Chinwag" width="32" height="32" />
				<span>Spawn <em>by Sarai Chinwag</em></span>
			</a>
			<nav class="wp-block-spawn-chat__nav">
				<a href="<?php echo esc_url( home_url( '/spawn/dashboard/' ) ); ?>">Dashboard</a>
				<a href="<?php echo esc_url( wp_logout_url( home_url( '/spawn/' ) ) ); ?>">Log out</a>
			</nav>
		</div>
		<div class="wp-block-spawn-chat__header">
			<span class="wp-block-spawn-chat__session-id"></span>
			<button class="wp-block-spawn-chat__new-convo" type="button" title="Start new conversation">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M12 5v14M5 12h14"></path>
				</svg>
				New
			</button>
		</div>
		<div class="wp-block-spawn-chat__messages"></div>
		<div class="wp-block-spawn-chat__input-area">
			<textarea class="wp-block-spawn-chat__input" placeholder="Message your AI..." rows="1"></textarea>
			<button class="wp-block-spawn-chat__send" aria-label="Send">
				<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<line x1="22" y1="2" x2="11" y2="13"></line>
					<polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
				</svg>
			</button>
		</div>
	</div>
</div>
