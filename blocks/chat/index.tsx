import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import './index.css';

registerBlockType( 'spawn/chat' as any, {
	edit: Edit,
	save: () => null,
} );
