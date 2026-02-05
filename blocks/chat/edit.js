import { useBlockProps } from '@wordpress/block-editor';

export default function Edit() {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<div className="wp-block-spawn-chat__container" style={ { height: '300px' } }>
				<div className="wp-block-spawn-chat__messages" style={ { flex: 1, padding: '15px', background: '#f5f5f5' } }>
					<p style={ { textAlign: 'center', color: '#666' } }>
						Chat interface will appear here for logged-in customers.
					</p>
				</div>
				<div className="wp-block-spawn-chat__input-area" style={ { display: 'flex', gap: '10px', padding: '15px', borderTop: '2px solid #ddd' } }>
					<input
						type="text"
						placeholder="Message your AI..."
						disabled
						style={ { flex: 1, padding: '10px', border: '2px solid #ddd', borderRadius: '8px' } }
					/>
					<button disabled style={ { padding: '10px 15px', background: '#1fc5e2', color: '#fff', border: 'none', borderRadius: '8px' } }>
						Send
					</button>
				</div>
			</div>
		</div>
	);
}
