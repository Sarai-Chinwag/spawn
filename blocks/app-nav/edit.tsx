import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface EditProps {
	attributes: {
		showForNonCustomers: boolean;
	};
	setAttributes: ( attrs: Partial< EditProps[ 'attributes' ] > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ): JSX.Element {
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'spawn' ) }>
					<ToggleControl
						label={ __( 'Show for non-customers', 'spawn' ) }
						help={ __( 'Show navigation even for logged-in users who are not Spawn customers.', 'spawn' ) }
						checked={ attributes.showForNonCustomers }
						onChange={ ( value: boolean ) => setAttributes( { showForNonCustomers: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<nav className="spawn-app-nav spawn-app-nav--preview">
					<div className="spawn-app-nav__logo">
						<span>{ __( 'Spawn', 'spawn' ) }</span>
					</div>
					<div className="spawn-app-nav__links">
						<span className="spawn-app-nav__link">{ __( 'Dashboard', 'spawn' ) }</span>
						<span className="spawn-app-nav__link">{ __( 'Chat', 'spawn' ) }</span>
						<span className="spawn-app-nav__link">{ __( 'Account', 'spawn' ) }</span>
						<span className="spawn-app-nav__link spawn-app-nav__link--logout">{ __( 'Log out', 'spawn' ) }</span>
					</div>
				</nav>
				<p style={ { fontSize: '0.8rem', opacity: 0.6, marginTop: '0.5rem' } }>
					{ __( 'This navigation only appears for logged-in Spawn customers.', 'spawn' ) }
				</p>
			</div>
		</>
	);
}
