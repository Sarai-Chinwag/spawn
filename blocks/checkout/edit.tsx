import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { Fragment } from '@wordpress/element';
import type { BlockEditProps } from '@wordpress/blocks';

interface CheckoutBlockAttributes {
	buttonText: string;
	showOrderSummary: boolean;
}

export default function Edit( { attributes, setAttributes }: BlockEditProps< CheckoutBlockAttributes > ): JSX.Element {
	const { buttonText, showOrderSummary } = attributes;
	const blockProps = useBlockProps();

	return (
		<Fragment>
			<InspectorControls>
				<PanelBody title={ __( 'Checkout Settings', 'spawn' ) }>
					<TextControl
						label={ __( 'Button Text', 'spawn' ) }
						value={ buttonText }
						onChange={ ( value: string ) =>
							setAttributes( { buttonText: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show Order Summary', 'spawn' ) }
						checked={ showOrderSummary }
						onChange={ ( value: boolean ) =>
							setAttributes( { showOrderSummary: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="wp-block-spawn-checkout__preview">
					<input
						type="email"
						placeholder="Enter your email..."
						disabled
						className="wp-block-spawn-checkout__input"
					/>
					<button
						type="button"
						disabled
						className="wp-block-spawn-checkout__button"
					>
						{ buttonText }
					</button>
					<p className="wp-block-spawn-checkout__preview-text">
						{ __(
							'Preview: This block will handle checkout after domain and tier selection.',
							'spawn'
						) }
					</p>
				</div>
			</div>
		</Fragment>
	);
}
