import { useBlockProps } from '@wordpress/block-editor';

export default function Edit() {
	const blockProps = useBlockProps({
		className: 'wp-block-spawn-credit-balance__editor',
	});

	return (
		<div { ...blockProps }>
			<div className="wp-block-spawn-credit-balance__editor-placeholder">
				<span className="wp-block-spawn-credit-balance__editor-icon">
					<span className="dashicon dashicons-cart"></span>
				</span>
				<div className="wp-block-spawn-credit-balance__editor-info">
					<span className="wp-block-spawn-credit-balance__editor-label">Credit Balance</span>
					<span className="wp-block-spawn-credit-balance__editor-value">$0.00</span>
				</div>
			</div>
		</div>
	);
}
