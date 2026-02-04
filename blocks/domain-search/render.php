<?php
/**
 * Domain search block render template.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 *
 * @package Spawn
 */

$wrapper_attributes = get_block_wrapper_attributes();
?>
<div <?php echo $wrapper_attributes; ?>>
	<div class="wp-block-spawn-domain-search__container">
		<label for="spawn-domain-input">Check domain availability:</label>
		<div class="wp-block-spawn-domain-search__input-wrap">
			<input type="text" id="spawn-domain-input" placeholder="yoursite.com" />
			<button type="button" class="wp-block-spawn-domain-search__btn">Check</button>
		</div>
		<div class="wp-block-spawn-domain-search__result"></div>
	</div>
</div>
