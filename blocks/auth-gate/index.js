import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

export default function Edit() {
	const blockProps = useBlockProps({
		className: 'wp-block-spawn-auth-gate__editor',
	});

	return (
		<div { ...blockProps }>
			<div className="wp-block-spawn-auth-gate__editor-label">
				<span className="dashicon dashicons-lock"></span>
				Auth Gate (Content shown only when logged in)
			</div>
			<div className="wp-block-spawn-auth-gate__editor-content">
				<InnerBlocks />
			</div>
		</div>
	);
}
