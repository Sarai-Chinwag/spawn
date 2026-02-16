<?php
/**
 * Auth Gate block render template.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 *
 * @package Spawn
 */

use Spawn\Branding;

$wrapper_attributes = get_block_wrapper_attributes( array(
	'class' => 'wp-block-spawn-auth-gate',
) );

$is_authenticated = $block->context['spawn/isAuthenticated'] ?? is_user_logged_in();

if ( $is_authenticated ) {
	?>
	<div <?php echo $wrapper_attributes; ?>>
		<?php echo $content; ?>
	</div>
	<?php
	return;
}

$brand_name     = Branding::get_brand_name();
$brand_logo_url = Branding::get_brand_logo_url();
?>
<div <?php echo $wrapper_attributes; ?>>
	<div class="wp-block-spawn-auth-gate__login">
		<div class="wp-block-spawn-auth-gate__login-header">
			<?php if ( '' !== $brand_logo_url ) : ?>
				<img src="<?php echo esc_url( $brand_logo_url ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>" width="48" height="48" />
			<?php endif; ?>
			<h2><?php echo esc_html__( 'Welcome', 'spawn' ); ?></h2>
			<p><?php echo esc_html__( 'Log in to access your AI assistant', 'spawn' ); ?></p>
		</div>
		<form class="wp-block-spawn-auth-gate__form" method="post" action="<?php echo esc_url( wp_login_url() ); ?>">
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( home_url( '/spawn/chat/' ) ); ?>" />
			<div class="wp-block-spawn-auth-gate__field">
				<label for="user_login"><?php esc_html_e( 'Email', 'spawn' ); ?></label>
				<input type="text" name="log" id="user_login" class="input" required />
			</div>
			<div class="wp-block-spawn-auth-gate__field">
				<label for="user_pass"><?php esc_html_e( 'Password', 'spawn' ); ?></label>
				<input type="password" name="pwd" id="user_pass" class="input" required />
			</div>
			<div class="wp-block-spawn-auth-gate__actions">
				<button type="submit" class="wp-block-spawn-auth-gate__submit">
					<?php esc_html_e( 'Log In', 'spawn' ); ?>
				</button>
			</div>
		</form>
		<div class="wp-block-spawn-auth-gate__links">
			<a href="<?php echo esc_url( wp_registration_url() ); ?>"><?php esc_html_e( 'Create an account', 'spawn' ); ?></a>
			<span class="wp-block-spawn-auth-gate__separator">|</span>
			<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Forgot password?', 'spawn' ); ?></a>
		</div>
	</div>
</div>
