import { registerBlockType } from '@wordpress/blocks';
import edit from './edit';
import save from './save';
import './index.css';

registerBlockType( 'spawn/tier-select', {
	edit,
	save,
} );
