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

use Spawn\Branding;

$is_fullpage     = ! empty( $attributes['fullpage'] );
$extra_class     = $is_fullpage ? 'wp-block-spawn-chat--fullpage' : '';
$session_key     = isset( $attributes['sessionKey'] ) ? $attributes['sessionKey'] : '';
$wrapper_attributes = get_block_wrapper_attributes( array(
	'class'            => $extra_class,
	'data-session-key' => $session_key,
) );

$brand_name     = Branding::get_brand_name();
$brand_logo_url = Branding::get_brand_logo_url();

$is_authenticated = is_user_logged_in();
$is_admin         = current_user_can( 'manage_options' );
$customer_id      = 0;
$credit_balance   = 0.0;
$billing_mode     = 'managed';
$billing_type     = 'paid';
$username         = '';
$domain           = '';
$status           = '';
$server_ready     = true;

if ( $is_authenticated ) {
	$user_id       = get_current_user_id();
	$current_user  = wp_get_current_user();
	$username      = $current_user->display_name ?: $current_user->user_login;

	$customer = \Spawn\Database::get_customer_by_user_id( $user_id );

	if ( $customer ) {
		$customer_id     = (int) $customer['id'];
		$credit_balance  = (float) $customer['credit_balance'];
		$billing_mode     = $customer['billing_mode'] ?? 'managed';
		$billing_type     = $customer['billing_type'] ?? 'paid';
		$domain           = $customer['domain'] ?? '';
		$status           = $customer['status'] ?? '';
		$server_ready     = ! empty( $customer['server_ip'] ) && 'provisioning' !== $customer['status'];
	} elseif ( $is_admin ) {
		$customer_id   = 0;
		$billing_mode   = 'managed';
		$billing_type   = 'comped';
		$domain         = Branding::get_subdomain_suffix();
		$status         = 'admin';
		$server_ready   = true;
	}
}

$login_url       = wp_login_url( get_permalink() );
$register_url    = wp_registration_url();
$lost_password_url = wp_lostpassword_url( get_permalink() );
$purchase_url    = rest_url( 'spawn/v1/credits/purchase' );

$spawn_state = array(
	'isAuthenticated' => $is_authenticated,
	'customerId'      => $customer_id,
	'creditBalance'   => $credit_balance,
	'billingMode'      => $billing_mode,
	'billingType'      => $billing_type,
	'username'         => $username,
	'domain'           => $domain,
	'status'           => $status,
	'serverReady'      => $server_ready,
	'loginUrl'         => $login_url,
	'registerUrl'      => $register_url,
	'lostPasswordUrl'  => $lost_password_url,
	'purchaseUrl'      => $purchase_url,
	'brandName'        => $brand_name,
	'brandLogoUrl'     => $brand_logo_url,
);
?>
<div <?php echo $wrapper_attributes; ?> data-spawn-state="<?php echo esc_attr( wp_json_encode( $spawn_state ) ); ?>">
	<?php if ( ! $is_fullpage ) : ?>
	<div class="wp-block-spawn-chat__topnav">
		<a href="<?php echo esc_url( home_url( '/spawn/' ) ); ?>" class="wp-block-spawn-chat__logo" title="Back to Spawn">
			<?php if ( '' !== $brand_logo_url ) : ?>
				<img src="<?php echo esc_url( $brand_logo_url ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>" width="32" height="32" />
			<?php endif; ?>
			<span><?php echo Branding::get_brand_name_html(); ?></span>
		</a>
		<nav class="wp-block-spawn-chat__nav">
			<?php if ( $is_authenticated ) : ?>
				<span class="wp-block-spawn-chat__balance"></span>
				<a href="<?php echo esc_url( home_url( '/spawn/dashboard/' ) ); ?>">Dashboard</a>
				<a href="<?php echo esc_url( wp_logout_url( home_url( '/spawn/' ) ) ); ?>">Log out</a>
			<?php else : ?>
				<a href="<?php echo esc_url( $login_url ); ?>">Log in</a>
			<?php endif; ?>
		</nav>
	</div>
	<?php endif; ?>

	<div class="wp-block-spawn-chat__layout">
		<aside class="wp-block-spawn-chat__sidebar">
			<div class="wp-block-spawn-chat__sidebar-header">
				<button class="wp-block-spawn-chat__new-convo" type="button" title="Start new conversation">
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M12 5v14M5 12h14"></path>
					</svg>
					New Chat
				</button>
			</div>
			<div class="wp-block-spawn-chat__sessions">
				<div class="wp-block-spawn-chat__sessions-loading">Loading chats...</div>
			</div>
			<div class="wp-block-spawn-chat__sidebar-footer">
				<span class="wp-block-spawn-chat__session-id" aria-live="polite"></span>
			</div>
		</aside>

		<div class="wp-block-spawn-chat__main">
			<?php if ( $is_fullpage ) : ?>
			<div class="wp-block-spawn-chat__topnav">
				<button class="wp-block-spawn-chat__sidebar-toggle" type="button" aria-label="Toggle sidebar">
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="3" y1="12" x2="21" y2="12"></line>
						<line x1="3" y1="6" x2="21" y2="6"></line>
						<line x1="3" y1="18" x2="21" y2="18"></line>
					</svg>
				</button>
				<a href="<?php echo esc_url( home_url( '/spawn/' ) ); ?>" class="wp-block-spawn-chat__logo" title="Back to Spawn">
					<?php if ( '' !== $brand_logo_url ) : ?>
						<img src="<?php echo esc_url( $brand_logo_url ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>" width="32" height="32" />
					<?php endif; ?>
					<span><?php echo Branding::get_brand_name_html(); ?></span>
				</a>
				<nav class="wp-block-spawn-chat__nav">
					<?php if ( $is_authenticated ) : ?>
						<span class="wp-block-spawn-chat__balance"></span>
						<a href="<?php echo esc_url( home_url( '/spawn/dashboard/' ) ); ?>">Dashboard</a>
						<a href="<?php echo esc_url( wp_logout_url( home_url( '/spawn/' ) ) ); ?>">Log out</a>
					<?php else : ?>
						<a href="<?php echo esc_url( $login_url ); ?>">Log in</a>
					<?php endif; ?>
				</nav>
			</div>
			<?php endif; ?>

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
</div>
