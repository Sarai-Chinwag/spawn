<?php
/**
 * Dashboard block render template.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 *
 * @package Spawn
 */

$wrapper_attributes = get_block_wrapper_attributes();
$is_admin           = current_user_can( 'manage_options' );
$user_id            = get_current_user_id();
$customer           = $user_id ? \Spawn\Database::get_customer_by_user_id( $user_id ) : null;

if ( ! $user_id ) {
	?>
	<div <?php echo $wrapper_attributes; ?>>
		<div class="spawn-dashboard__card">
			<h3>Log In Required</h3>
			<p>Please log in to view your dashboard.</p>
			<a href="<?php echo esc_url( home_url( '/spawn/login/' ) ); ?>" class="spawn-dashboard__button">Log In</a>
		</div>
	</div>
	<?php
	return;
}

if ( ! $customer && ! $is_admin ) {
	?>
	<div <?php echo $wrapper_attributes; ?>>
		<div class="spawn-dashboard__card">
			<h3>No Active Subscription</h3>
			<p>You don't have an active Spawn subscription yet.</p>
			<a href="<?php echo esc_url( home_url( '/spawn/' ) ); ?>" class="spawn-dashboard__button">Get Started</a>
		</div>
	</div>
	<?php
	return;
}

$domain        = $customer['domain'] ?? 'Admin view';
$status        = $customer['status'] ?? 'admin';
$server_ip     = $customer['server_ip'] ?? '';
$subscription  = $customer['vps_tier'] ?? 'N/A';
$is_provisioning = ( 'provisioning' === $status );
$site_url      = $server_ip ? 'http://' . $server_ip : '';
?>
<div <?php echo $wrapper_attributes; ?>>
	<div class="spawn-dashboard__card">
		<h3>Site Status</h3>
		<p><strong>Domain:</strong> <?php echo esc_html( $domain ); ?></p>
		<p><strong>Server status:</strong> <?php echo esc_html( ucfirst( $status ) ); ?></p>
		<p><strong>Subscription tier:</strong> <?php echo esc_html( $subscription ); ?></p>
	</div>

	<div class="spawn-dashboard__card">
		<h3>Chat</h3>
		<p>Message your AI assistant, get help with your site, or just say hi.</p>
		<a href="<?php echo esc_url( home_url( '/spawn/chat/' ) ); ?>" class="spawn-dashboard__button">Open Chat</a>
	</div>

	<div class="spawn-dashboard__card">
		<h3>Quick Actions</h3>
		<?php if ( $site_url ) : ?>
			<a href="<?php echo esc_url( $site_url ); ?>" class="spawn-dashboard__button" target="_blank" rel="noopener">Visit Site</a>
		<?php else : ?>
			<p>Your site is not ready yet.</p>
		<?php endif; ?>
		<a href="<?php echo esc_url( home_url( '/spawn/account/' ) ); ?>" class="spawn-dashboard__button">Manage Account</a>
	</div>

	<?php if ( $is_provisioning ) : ?>
		<div class="spawn-dashboard__card">
			<h3>Getting Started</h3>
			<p>Your server is provisioning. This usually takes a few minutes.</p>
			<p>We'll notify you once your site is ready.</p>
		</div>
	<?php endif; ?>
</div>
