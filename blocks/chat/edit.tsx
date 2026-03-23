/**
 * Spawn Chat Block - Editor Script
 *
 * Block editor interface for configuring the chat block.
 *
 * @package Spawn\Blocks
 */

import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

// Import styles.
import './editor.css';

interface EditProps {
	attributes: {
		welcomeMessage: string;
		placeholder: string;
		showSessions: boolean;
	};
	setAttributes: ( attrs: Partial< EditProps[ 'attributes' ] > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const blockProps = useBlockProps( {
		className: 'spawn-chat-editor',
	} );

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody
					title={ __( 'Chat Settings', 'spawn' ) }
					initialOpen={ true }
				>
					<TextControl
						label={ __( 'Welcome Message', 'spawn' ) }
						help={ __(
							'Shown when the chat is empty.',
							'spawn'
						) }
						value={ attributes.welcomeMessage }
						onChange={ ( value ) =>
							setAttributes( { welcomeMessage: value } )
						}
					/>
					<TextControl
						label={ __( 'Placeholder Text', 'spawn' ) }
						help={ __(
							'Shown in the input field.',
							'spawn'
						) }
						value={ attributes.placeholder }
						onChange={ ( value ) =>
							setAttributes( { placeholder: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show Session History', 'spawn' ) }
						help={ __(
							'Allow users to see and switch between past conversations.',
							'spawn'
						) }
						checked={ attributes.showSessions }
						onChange={ ( value ) =>
							setAttributes( { showSessions: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div className="spawn-chat-preview">
				<div className="spawn-chat-preview-header">
					<div className="spawn-chat-preview-logo">🚀</div>
					<span>{ __( 'Spawn AI Chat', 'spawn' ) }</span>
				</div>
				<div className="spawn-chat-preview-body">
					<div className="spawn-chat-preview-welcome">
						<p>{ attributes.welcomeMessage }</p>
					</div>
					<div className="spawn-chat-preview-messages">
						<div className="spawn-chat-preview-message assistant">
							<div className="spawn-chat-preview-avatar">🤖</div>
							<div className="spawn-chat-preview-content">
								<p>
									{ __(
										'This is a preview of how the chat will appear.',
										'spawn'
									) }
								</p>
							</div>
						</div>
					</div>
				</div>
				<div className="spawn-chat-preview-input">
					<span className="spawn-chat-preview-placeholder">
						{ attributes.placeholder }
					</span>
					<span className="spawn-chat-preview-send">➤</span>
				</div>
				<div className="spawn-chat-preview-notice">
					{ __( 'Chat will be live on the frontend', 'spawn' ) }
				</div>
			</div>
		</div>
	);
}
