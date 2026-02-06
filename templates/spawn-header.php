<?php
/**
 * Spawn app header - consistent branding across all Spawn pages.
 *
 * @package Spawn
 */

use Spawn\Branding;

$is_logged_in   = is_user_logged_in();
$brand_logo_url = Branding::get_brand_logo_url();
?>

<header class="spawn-topnav">
	<a href="<?php echo esc_url( home_url( '/spawn/' ) ); ?>" class="spawn-topnav__logo" title="<?php echo esc_attr( Branding::get_brand_name() ); ?>">
		<?php if ( $brand_logo_url ) : ?>
			<img src="<?php echo esc_url( $brand_logo_url ); ?>" alt="" width="32" height="32" />
		<?php endif; ?>
		<span><?php echo Branding::get_brand_name_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
	</a>

	<nav class="spawn-topnav__links">
		<?php if ( $is_logged_in ) : ?>
			<a href="<?php echo esc_url( home_url( '/spawn/chat/' ) ); ?>">Chat</a>
			<a href="<?php echo esc_url( home_url( '/spawn/dashboard/' ) ); ?>">Dashboard</a>
			<a href="<?php echo esc_url( wp_logout_url( home_url( '/spawn/' ) ) ); ?>">Log out</a>
		<?php else : ?>
			<a href="<?php echo esc_url( home_url( '/spawn/login/' ) ); ?>">Log in</a>
			<a href="<?php echo esc_url( home_url( '/spawn/' ) ); ?>">Get Started</a>
		<?php endif; ?>
	</nav>
</header>
