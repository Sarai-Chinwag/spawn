import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import metadata from './block.json';

registerBlockType( metadata.name as string, {
	...metadata,
	edit: Edit,
	save: () => null,
} as any );
