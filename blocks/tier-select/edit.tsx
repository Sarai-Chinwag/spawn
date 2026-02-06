import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit(): JSX.Element {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<div className="wp-block-spawn-tier-select__preview">
				<h3>{ __( 'Tier Selection Preview', 'spawn' ) }</h3>
				<div className="wp-block-spawn-tier-select__cards">
					<div className="tier-card">
						<h4>Starter</h4>
						<p className="price">$9</p>
						<p>Basic features</p>
						<button disabled>{ __( 'Select', 'spawn' ) }</button>
					</div>
					<div className="tier-card highlighted">
						<h4>Pro</h4>
						<p className="price">$9</p>
						<p>Advanced features</p>
						<button disabled>{ __( 'Select', 'spawn' ) }</button>
					</div>
					<div className="tier-card">
						<h4>Business</h4>
						<p className="price">$99</p>
						<p>All features</p>
						<button disabled>{ __( 'Select', 'spawn' ) }</button>
					</div>
				</div>
				<p className="wp-block-spawn-tier-select__preview-text">
					{ __(
						'Preview: This block will display pricing tiers fetched from the API.',
						'spawn'
					) }
				</p>
			</div>
		</div>
	);
}
