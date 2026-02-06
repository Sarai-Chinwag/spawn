<?php
/**
 * Success block render template.
 *
 * Shows provisioning status after Stripe checkout.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 *
 * @package Spawn
 */

use Spawn\Branding;

$wrapper_attributes = get_block_wrapper_attributes();
$brand_name         = Branding::get_brand_name();
$brand_logo_url     = Branding::get_brand_logo_url();

// Get session_id from URL parameter.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only, no state change.
$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';

// Default state - will be updated by JavaScript.
$initial_status = 'loading';
$customer_data  = null;
$error_message  = '';

if ( empty( $session_id ) ) {
	$initial_status = 'error';
	$error_message  = __( 'Missing checkout session. Please try again or contact support.', 'spawn' );
}
?>
<div <?php echo $wrapper_attributes; ?> 
	data-session-id="<?php echo esc_attr( $session_id ); ?>"
	data-initial-status="<?php echo esc_attr( $initial_status ); ?>">
	
	<!-- Top navigation bar -->
	<nav class="spawn-topnav">
		<a href="<?php echo esc_url( home_url( '/spawn/' ) ); ?>" class="spawn-topnav__logo" title="Back to Spawn">
			<?php if ( '' !== $brand_logo_url ) : ?>
				<img src="<?php echo esc_url( $brand_logo_url ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>" width="32" height="32" />
			<?php endif; ?>
			<span><?php echo esc_html( $brand_name ); ?></span>
		</a>
		<div class="spawn-topnav__links">
			<?php if ( is_user_logged_in() ) : ?>
				<a href="<?php echo esc_url( home_url( '/spawn/dashboard/' ) ); ?>">Dashboard</a>
				<a href="<?php echo esc_url( wp_logout_url( home_url( '/spawn/' ) ) ); ?>">Log out</a>
			<?php else : ?>
				<a href="<?php echo esc_url( home_url( '/spawn/login/' ) ); ?>">Log in</a>
			<?php endif; ?>
		</div>
	</nav>

	<div class="spawn-success__container">
		<?php if ( 'error' === $initial_status && ! empty( $error_message ) ) : ?>
			<!-- Error state (missing session_id) -->
			<div class="spawn-success__card spawn-success__card--error">
				<div class="spawn-success__icon spawn-success__icon--error">❌</div>
				<h2 class="spawn-success__title"><?php echo esc_html__( 'Something went wrong', 'spawn' ); ?></h2>
				<p class="spawn-success__message"><?php echo esc_html( $error_message ); ?></p>
				<div class="spawn-success__actions">
					<a href="<?php echo esc_url( home_url( '/spawn/' ) ); ?>" class="spawn-success__button">
						<?php echo esc_html__( 'Try Again', 'spawn' ); ?>
					</a>
					<a href="mailto:support@spawn.ai" class="spawn-success__button spawn-success__button--ghost">
						<?php echo esc_html__( 'Contact Support', 'spawn' ); ?>
					</a>
				</div>
			</div>
		<?php else : ?>
			<!-- Loading state (JavaScript will update) -->
			<div class="spawn-success__card spawn-success__card--loading" data-state="loading">
				<div class="spawn-success__icon spawn-success__icon--loading">
					<div class="spawn-success__spinner"></div>
				</div>
				<h2 class="spawn-success__title"><?php echo esc_html__( 'Verifying payment...', 'spawn' ); ?></h2>
				<p class="spawn-success__message"><?php echo esc_html__( 'Please wait while we confirm your purchase.', 'spawn' ); ?></p>
			</div>

			<!-- Provisioning state (hidden by default) -->
			<div class="spawn-success__card spawn-success__card--provisioning" data-state="provisioning" style="display: none;">
				<div class="spawn-success__icon spawn-success__icon--provisioning">✨</div>
				<h2 class="spawn-success__title"><?php echo esc_html__( 'Setting up your AI...', 'spawn' ); ?></h2>
				
				<div class="spawn-success__progress">
					<div class="spawn-success__progress-bar">
						<div class="spawn-success__progress-fill" data-progress="0"></div>
					</div>
					<span class="spawn-success__progress-text">0%</span>
				</div>

				<div class="spawn-success__steps">
					<div class="spawn-success__step" data-step="payment">
						<span class="spawn-success__step-icon">○</span>
						<span class="spawn-success__step-text"><?php echo esc_html__( 'Payment confirmed', 'spawn' ); ?></span>
					</div>
					<div class="spawn-success__step" data-step="server">
						<span class="spawn-success__step-icon">○</span>
						<span class="spawn-success__step-text"><?php echo esc_html__( 'Server created', 'spawn' ); ?></span>
					</div>
					<div class="spawn-success__step" data-step="wordpress">
						<span class="spawn-success__step-icon">○</span>
						<span class="spawn-success__step-text"><?php echo esc_html__( 'Installing WordPress', 'spawn' ); ?></span>
					</div>
					<div class="spawn-success__step" data-step="ai">
						<span class="spawn-success__step-icon">○</span>
						<span class="spawn-success__step-text"><?php echo esc_html__( 'Configuring AI', 'spawn' ); ?></span>
					</div>
				</div>

				<p class="spawn-success__eta">
					<?php echo esc_html__( 'This usually takes 2-3 minutes.', 'spawn' ); ?><br>
					<?php echo esc_html__( "You'll receive an email when ready.", 'spawn' ); ?>
				</p>
			</div>

			<!-- Ready state (hidden by default) -->
			<div class="spawn-success__card spawn-success__card--ready" data-state="ready" style="display: none;">
				<div class="spawn-success__icon spawn-success__icon--ready">🎉</div>
				<h2 class="spawn-success__title"><?php echo esc_html__( 'Your AI is ready!', 'spawn' ); ?></h2>
				
				<div class="spawn-success__site-info">
					<span class="spawn-success__site-label"><?php echo esc_html__( 'Your site:', 'spawn' ); ?></span>
					<a href="#" class="spawn-success__site-url" data-site-url></a>
				</div>

				<div class="spawn-success__actions">
					<a href="<?php echo esc_url( home_url( '/spawn/chat/' ) ); ?>" class="spawn-success__button spawn-success__button--primary">
						<?php echo esc_html__( 'Start Chatting →', 'spawn' ); ?>
					</a>
				</div>

				<p class="spawn-success__note">
					<?php echo esc_html__( 'Check your email for login details.', 'spawn' ); ?>
				</p>
			</div>

			<!-- Failed state (hidden by default) -->
			<div class="spawn-success__card spawn-success__card--failed" data-state="failed" style="display: none;">
				<div class="spawn-success__icon spawn-success__icon--error">😞</div>
				<h2 class="spawn-success__title"><?php echo esc_html__( 'Setup encountered an issue', 'spawn' ); ?></h2>
				<p class="spawn-success__message" data-error-message>
					<?php echo esc_html__( 'Something went wrong during setup. Our team has been notified.', 'spawn' ); ?>
				</p>
				<div class="spawn-success__actions">
					<a href="mailto:support@spawn.ai" class="spawn-success__button">
						<?php echo esc_html__( 'Contact Support', 'spawn' ); ?>
					</a>
					<a href="<?php echo esc_url( home_url( '/spawn/dashboard/' ) ); ?>" class="spawn-success__button spawn-success__button--ghost">
						<?php echo esc_html__( 'Go to Dashboard', 'spawn' ); ?>
					</a>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
