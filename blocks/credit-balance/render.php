<?php
/**
 * Credit Balance block render template.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 *
 * @package Spawn
 */

$wrapper_attributes = get_block_wrapper_attributes( array(
	'class' => 'wp-block-spawn-credit-balance',
) );

$customer_id = $block->context['spawn/customerId'] ?? 0;

if ( ! $customer_id ) {
	return;
}

$credit_balance = \Spawn\Database::get_credit_balance( $customer_id );
?>
<div <?php echo $wrapper_attributes; ?>>
	<div class="wp-block-spawn-credit-balance__display">
		<span class="wp-block-spawn-credit-balance__label">
			<?php esc_html_e( 'Balance', 'spawn' ); ?>
		</span>
		<span class="wp-block-spawn-credit-balance__amount" data-balance="<?php echo esc_attr( $credit_balance ); ?>">
			$<?php echo esc_html( number_format( $credit_balance, 2 ) ); ?>
		</span>
	</div>
</div>
