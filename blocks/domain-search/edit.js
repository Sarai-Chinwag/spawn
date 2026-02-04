import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { Fragment } from '@wordpress/element';

export default function Edit( { attributes, setAttributes } ) {
	const { placeholder, showSuggestions } = attributes;
	const blockProps = useBlockProps();

	return (
		<Fragment>
			<InspectorControls>
				<PanelBody title={ __( 'Domain Search Settings', 'spawn' ) }>
					<TextControl
						label={ __( 'Placeholder Text', 'spawn' ) }
						value={ placeholder }
						onChange={ ( value ) =>
							setAttributes( { placeholder: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show Suggestions', 'spawn' ) }
						checked={ showSuggestions }
						onChange={ ( value ) =>
							setAttributes( { showSuggestions: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="wp-block-spawn-domain-search__preview">
					<input
						type="text"
						placeholder={ placeholder }
						disabled
						className="wp-block-spawn-domain-search__input"
					/>
					<button
						type="button"
						disabled
						className="wp-block-spawn-domain-search__button"
					>
						{ __( 'Search', 'spawn' ) }
					</button>
					<p className="wp-block-spawn-domain-search__preview-text">
						{ __(
							'Preview: This block will allow users to search for available domains.',
							'spawn'
						) }
					</p>
				</div>
			</div>
		</Fragment>
	);
}
