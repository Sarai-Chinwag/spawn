import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit(): JSX.Element {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<div className="wp-block-spawn-dashboard__preview">
				<h2>{ __( 'Your Dashboard', 'spawn' ) }</h2>
				<div className="wp-block-spawn-dashboard__preview-grid">
					<div className="wp-block-spawn-dashboard__preview-card">
						<h3>{ __( 'Credit Balance', 'spawn' ) }</h3>
						<p>{ __( '$5.00 credits', 'spawn' ) }</p>
					</div>
					<div className="wp-block-spawn-dashboard__preview-card wp-block-spawn-dashboard__preview-card--usage">
						<h3>{ __( 'AI Usage This Month', 'spawn' ) }</h3>
						<p>
							<strong>$2.34</strong> { __( 'of', 'spawn' ) } <strong>$5.00</strong> { __( 'included', 'spawn' ) }
						</p>
						<div className="wp-block-spawn-dashboard__preview-bar">
							<div className="wp-block-spawn-dashboard__preview-bar-fill" style={ { width: '47%' } }></div>
						</div>
						<small>142 requests · 45,230 in · 12,456 out</small>
					</div>
					<div className="wp-block-spawn-dashboard__preview-card">
						<h3>{ __( 'Servers', 'spawn' ) }</h3>
						<p>{ __( '1 active', 'spawn' ) }</p>
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
