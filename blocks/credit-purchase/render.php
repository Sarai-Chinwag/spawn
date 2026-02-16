<?php
/**
 * Credit Purchase block render template.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 *
 * @package Spawn
 */

use Spawn\Controllers\Credits_Controller;

$wrapper_attributes = get_block_wrapper_attributes( array(
	'class' => 'wp-block-spawn-credit-purchase',
) );

$customer_id = $block->context['spawn/customerId'] ?? 0;

if ( ! $customer_id ) {
	return;
}

$packages = Credits_Controller::get_credit_packages_config();
?>
<div <?php echo $wrapper_attributes; ?>>
	<div class="wp-block-spawn-credit-purchase__header">
		<span class="wp-block-spawn-credit-purchase__title">
			<?php esc_html_e( 'Add Credits', 'spawn' ); ?>
		</span>
	</div>
	<div class="wp-block-spawn-credit-purchase__options">
		<?php foreach ( $packages as $key => $package ) : ?>
			<button
				class="wp-block-spawn-credit-purchase__option"
				data-amount="<?php echo esc_attr( $package['price'] ); ?>"
				type="button"
			>
				<span class="wp-block-spawn-credit-purchase__option-amount">$<?php echo esc_html( $package['price'] ); ?></span>
				<span class="wp-block-spawn-credit-purchase__option-credits">
					<?php echo esc_html( number_format( $package['credits'] ) ); ?> credits
				</span>
				<?php if ( ! empty( $package['bonus'] ) ) : ?>
					<span class="wp-block-spawn-credit-purchase__option-bonus">
						<?php echo esc_html( $package['bonus'] ); ?> bonus
					</span>
				<?php endif; ?>
			</button>
		<?php endforeach; ?>
	</div>
	<div class="wp-block-spawn-credit-purchase__loading" style="display: none;">
		<span class="spinner"></span>
		<?php esc_html_e( 'Redirecting to checkout...', 'spawn' ); ?>
	</div>
</div>
