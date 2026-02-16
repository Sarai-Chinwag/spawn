import { useBlockProps } from '@wordpress/block-editor';

export default function Edit() {
	const blockProps = useBlockProps({
		className: 'wp-block-spawn-credit-purchase__editor',
	});

	return (
		<div { ...blockProps }>
			<div className="wp-block-spawn-credit-purchase__editor-placeholder">
				<div className="wp-block-spawn-credit-purchase__editor-label">Credit Purchase</div>
				<div className="wp-block-spawn-credit-purchase__editor-buttons">
					<button className="wp-block-spawn-credit-purchase__editor-btn" type="button">$10</button>
					<button className="wp-block-spawn-credit-purchase__editor-btn" type="button">$25</button>
					<button className="wp-block-spawn-credit-purchase__editor-btn" type="button">$50</button>
				</div>
			</div>
		</div>
	);
}
