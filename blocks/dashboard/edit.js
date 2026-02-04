import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit() {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<div className="wp-block-spawn-dashboard__preview">
				<h2>{ __( 'Your Dashboard', 'spawn' ) }</h2>
				<div className="wp-block-spawn-dashboard__cards">
					<div className="wp-block-spawn-dashboard__card">
						<h3>{ __( 'Server Status', 'spawn' ) }</h3>
						<span className="status-badge status-active">Active</span>
					</div>
					<div className="wp-block-spawn-dashboard__card">
						<h3>{ __( 'Domain', 'spawn' ) }</h3>
						<p>example.saraichinwag.com</p>
					</div>
					<div className="wp-block-spawn-dashboard__card">
						<h3>{ __( 'AI Usage', 'spawn' ) }</h3>
						<p>250 / 1,000 calls</p>
					</div>
					<div className="wp-block-spawn-dashboard__card">
						<h3>{ __( 'Plan', 'spawn' ) }</h3>
						<p>Starter</p>
					</div>
				</div>
				<p className="wp-block-spawn-dashboard__preview-note">
					{ __( 'Preview: This dashboard will show real customer data when viewed on the frontend.', 'spawn' ) }
				</p>
			</div>
		</div>
	);
}
