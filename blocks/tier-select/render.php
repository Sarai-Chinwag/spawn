<?php
/**
 * Tier select block render template.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 *
 * @package Spawn
 */

$wrapper_attributes = get_block_wrapper_attributes( [
	'data-highlighted-tier' => esc_attr( $attributes['highlightedTier'] ?? 'pro' ),
	'data-show-features'    => esc_attr( $attributes['showFeatures'] ?? true ? 'true' : 'false' ),
] );
?>
<div <?php echo $wrapper_attributes; ?>>
	<div class="wp-block-spawn-tier-select__loading">Loading pricing...</div>
</div>
