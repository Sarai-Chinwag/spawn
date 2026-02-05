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
?>
<div <?php echo $wrapper_attributes; ?>>
	<div class="spawn-dashboard__card spawn-dashboard__card--chat">
		<h3>💬 Chat with Your AI</h3>
		<p>Message your AI assistant, get help with your site, or just say hi.</p>
		<a href="<?php echo esc_url( home_url( '/spawn/chat/' ) ); ?>" class="spawn-dashboard__button">Open Chat</a>
	</div>
</div>
