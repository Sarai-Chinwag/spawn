import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import './index.css';

registerBlockType( 'spawn/chat', {
	edit: Edit,
	save: () => null,
} );
