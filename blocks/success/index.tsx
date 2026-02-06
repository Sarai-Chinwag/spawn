/**
 * Spawn Success Block - Editor view.
 *
 * This block displays after Stripe checkout to show provisioning status.
 * The actual functionality is in render.php and view.ts.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import './index.css';

registerBlockType( 'spawn/success', {
	edit: function Edit() {
		const blockProps = useBlockProps( {
			className: 'spawn-success-editor',
		} );

		return (
			<div { ...blockProps }>
				<div className="spawn-success-editor__preview">
					<div className="spawn-success-editor__icon">✨</div>
					<h3>Spawn Success Block</h3>
					<p>
						This block displays after Stripe checkout completion.
						It shows the provisioning status and guides users to their new AI.
					</p>
					<div className="spawn-success-editor__states">
						<span className="spawn-success-editor__state">Loading</span>
						<span className="spawn-success-editor__arrow">→</span>
						<span className="spawn-success-editor__state">Provisioning</span>
						<span className="spawn-success-editor__arrow">→</span>
						<span className="spawn-success-editor__state">Ready</span>
					</div>
					<p className="spawn-success-editor__note">
						Reads <code>session_id</code> from URL query parameter.
					</p>
				</div>
			</div>
		);
	},

	save: function Save() {
		// Dynamic block - rendered by PHP.
		return null;
	},
} );
