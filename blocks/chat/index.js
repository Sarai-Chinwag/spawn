import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

const TEMPLATE = [
	[
		'spawn/auth-gate',
		{},
		[
			[
				'core/group',
				{},
				[
					[
						'spawn/credit-balance',
						{},
					],
					[
						'spawn/credit-purchase',
						{},
					],
				],
			],
		],
	],
];

export default function Edit() {
	const blockProps = useBlockProps({
		className: 'wp-block-spawn-chat__editor',
	});

	return (
		<div { ...blockProps }>
			<div className="wp-block-spawn-chat__editor-header">
				<span className="wp-block-spawn-chat__editor-label">Spawn Chat</span>
			</div>
			<InnerBlocks
				allowedBlocks={ [
					'spawn/auth-gate',
					'spawn/credit-balance',
					'spawn/credit-purchase',
				] }
				template={ TEMPLATE }
				templateLock={ false }
			/>
		</div>
	);
}
