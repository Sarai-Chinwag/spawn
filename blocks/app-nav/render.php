<?php
/**
 * App navigation block render template.
 *
 * Shows navigation for logged-in Spawn customers.
 * Hides completely for non-logged-in users.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 *
 * @package Spawn
 */

use Spawn\Branding;

// Don't show anything if not logged in.
if ( ! is_user_logged_in() ) {
	return;
}

$user_id  = get_current_user_id();
$customer = \Spawn\Database::get_customer_by_user_id( $user_id );
$is_admin = current_user_can( 'manage_options' );

// Don't show for non-customers unless they're admin or attribute allows it.
$show_for_non_customers = $attributes['showForNonCustomers'] ?? false;
if ( ! $customer && ! $is_admin && ! $show_for_non_customers ) {
	return;
}

$wrapper_attributes = get_block_wrapper_attributes();
$brand_name         = Branding::get_brand_name();
$brand_logo_url     = Branding::get_brand_logo_url();

// Determine current page for active states.
$current_url  = home_url( add_query_arg( [] ) );
$is_dashboard = strpos( $current_url, '/spawn/dashboard' ) !== false;
$is_chat      = strpos( $current_url, '/spawn/chat' ) !== false;
$is_account   = strpos( $current_url, '/spawn/account' ) !== false;
?>
<div <?php echo $wrapper_attributes; ?>>
	<nav class="spawn-app-nav">
		<a href="<?php echo esc_url( home_url( '/spawn/' ) ); ?>" class="spawn-app-nav__logo" title="<?php echo esc_attr( $brand_name ); ?>">
			<?php if ( '' !== $brand_logo_url ) : ?>
				<img src="<?php echo esc_url( $brand_logo_url ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>" width="32" height="32" />
			<?php endif; ?>
			<span><?php echo esc_html( $brand_name ); ?></span>
		</a>
		
		<?php if ( $customer || $is_admin ) : ?>
			<div class="spawn-app-nav__links">
				<a href="<?php echo esc_url( home_url( '/spawn/dashboard/' ) ); ?>" class="spawn-app-nav__link<?php echo $is_dashboard ? ' is-active' : ''; ?>">
					<?php echo esc_html__( 'Dashboard', 'spawn' ); ?>
				</a>
				<a href="<?php echo esc_url( home_url( '/spawn/chat/' ) ); ?>" class="spawn-app-nav__link<?php echo $is_chat ? ' is-active' : ''; ?>">
					<?php echo esc_html__( 'Chat', 'spawn' ); ?>
				</a>
				<a href="<?php echo esc_url( home_url( '/spawn/account/' ) ); ?>" class="spawn-app-nav__link<?php echo $is_account ? ' is-active' : ''; ?>">
					<?php echo esc_html__( 'Account', 'spawn' ); ?>
				</a>
				<a href="<?php echo esc_url( wp_logout_url( home_url( '/spawn/' ) ) ); ?>" class="spawn-app-nav__link spawn-app-nav__link--logout">
					<?php echo esc_html__( 'Log out', 'spawn' ); ?>
				</a>
			</div>
		<?php else : ?>
			<div class="spawn-app-nav__links">
				<a href="<?php echo esc_url( home_url( '/spawn/login/' ) ); ?>" class="spawn-app-nav__link">
					<?php echo esc_html__( 'Log in', 'spawn' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</nav>
</div>
