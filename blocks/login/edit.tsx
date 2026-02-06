import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit(): JSX.Element {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<div className="wp-block-spawn-login__preview">
				<h2>{ __( 'Login', 'spawn' ) }</h2>
				<div className="wp-block-spawn-login__form-preview">
					<label>{ __( 'Email', 'spawn' ) }</label>
					<input type="email" disabled placeholder="email@example.com" />
					<label>{ __( 'Password', 'spawn' ) }</label>
					<input type="password" disabled placeholder="••••••••" />
					<button type="button" disabled>
						{ __( 'Log In', 'spawn' ) }
					</button>
				</div>
				<p className="wp-block-spawn-login__preview-note">
					{ __( 'Preview: This form will authenticate Spawn customers.', 'spawn' ) }
				</p>
			</div>
		</div>
	);
}
