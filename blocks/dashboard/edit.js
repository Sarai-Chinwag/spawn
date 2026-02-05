import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit() {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<div className="wp-block-spawn-dashboard__preview">
				<h2>{ __( 'Your Dashboard', 'spawn' ) }</h2>
				<div className="wp-block-spawn-dashboard__preview-grid">
					<div className="wp-block-spawn-dashboard__preview-card">
						<h3>{ __( 'Credit Balance', 'spawn' ) }</h3>
						<p>{ __( '1,250 credits', 'spawn' ) }</p>
					</div>
					<div className="wp-block-spawn-dashboard__preview-card">
						<h3>{ __( 'Usage', 'spawn' ) }</h3>
						<p>{ __( 'Chart placeholder', 'spawn' ) }</p>
					</div>
					<div className="wp-block-spawn-dashboard__preview-card">
						<h3>{ __( 'Servers', 'spawn' ) }</h3>
						<p>{ __( '0 active', 'spawn' ) }</p>
					</div>
					<div className="wp-block-spawn-dashboard__preview-card">
						<h3>{ __( 'Domains', 'spawn' ) }</h3>
						<p>{ __( '0 registered', 'spawn' ) }</p>
					</div>
				</div>
				<p className="wp-block-spawn-dashboard__preview-note">
					{ __( 'Preview: This dashboard will show real customer data when viewed on the frontend.', 'spawn' ) }
				</p>
			</div>
		</div>
	);
}
