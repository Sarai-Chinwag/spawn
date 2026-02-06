import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit(): JSX.Element {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<div className="wp-block-spawn-account__preview">
				<h2>{ __( 'Account Settings', 'spawn' ) }</h2>
				<div className="wp-block-spawn-account__section">
					<h3>{ __( 'Current Plan', 'spawn' ) }</h3>
					<p>Starter - $29/mo</p>
					<button type="button" disabled>{ __( 'Change Plan', 'spawn' ) }</button>
				</div>
				<div className="wp-block-spawn-account__section">
					<h3>{ __( 'Subscription', 'spawn' ) }</h3>
					<button type="button" disabled>{ __( 'View Invoices', 'spawn' ) }</button>
					<button type="button" disabled className="btn-danger">{ __( 'Cancel Subscription', 'spawn' ) }</button>
				</div>
				<p className="wp-block-spawn-account__preview-note">
					{ __( 'Preview: This form will manage subscription settings when viewed on the frontend.', 'spawn' ) }
				</p>
			</div>
		</div>
	);
}
