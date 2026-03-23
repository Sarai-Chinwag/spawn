/**
 * Spawn Chat Block - Index
 *
 * Registers the block with WordPress.
 *
 * @package Spawn\Blocks
 */

import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';

// Import block.json to get metadata.
import metadata from './block.json';

// Register the block.
registerBlockType( metadata.name, {
	edit: Edit,
	// No save function - uses render.php on frontend.
	save: () => null,
} );
