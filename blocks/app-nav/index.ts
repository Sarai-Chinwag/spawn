import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import metadata from './block.json';
import './index.css';

registerBlockType( metadata.name, {
	edit: Edit,
} );
