import apiFetch from '@wordpress/api-fetch';
document.addEventListener( 'DOMContentLoaded', function () {
	const blocks = document.querySelectorAll( '.wp-block-spawn-login' );

	blocks.forEach( function ( block ) {
		// Check if user is already logged in
		apiFetch( { path: '/spawn/v1/auth/me' } )
			.then( function ( response ) {
				if ( response.logged_in ) {
					block.innerHTML = `
						<div class="wp-block-spawn-login__logged-in">
							<p>Logged in as <strong>${ response.user.email }</strong></p>
							<a href="/spawn/dashboard/" class="wp-block-spawn-login__dashboard-link">Go to Dashboard</a>
							<button type="button" class="wp-block-spawn-login__logout-btn">Log Out</button>
						</div>
					`;
					block.querySelector( '.wp-block-spawn-login__logout-btn' ).addEventListener( 'click', function () {
						apiFetch( { path: '/spawn/v1/auth/logout', method: 'POST' } )
							.then( function () {
								window.location.reload();
							} );
					} );
					return;
				}
				renderLoginForm( block );
			} )
			.catch( function () {
				renderLoginForm( block );
			} );
	} );

	function renderLoginForm( block ) {
		const form = document.createElement( 'form' );
		form.className = 'wp-block-spawn-login__form';
		form.innerHTML = `
			<h2>Log In</h2>
			<div class="wp-block-spawn-login__field">
				<label for="spawn-login-email">Email</label>
				<input type="email" id="spawn-login-email" name="email" required />
			</div>
			<div class="wp-block-spawn-login__field">
				<label for="spawn-login-password">Password</label>
				<input type="password" id="spawn-login-password" name="password" required />
			</div>
			<div class="wp-block-spawn-login__error" style="display: none;"></div>
			<button type="submit" class="wp-block-spawn-login__submit">Log In</button>
			<p class="wp-block-spawn-login__register-link">
				Don't have an account? <a href="#" class="spawn-register-toggle">Create one</a>
			</p>
		`;

		block.innerHTML = '';
		block.appendChild( form );

		const errorDiv = form.querySelector( '.wp-block-spawn-login__error' );
		const submitBtn = form.querySelector( '.wp-block-spawn-login__submit' );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			errorDiv.style.display = 'none';
			submitBtn.disabled = true;
			submitBtn.textContent = 'Logging in...';

			const email = form.querySelector( '#spawn-login-email' ).value;
			const password = form.querySelector( '#spawn-login-password' ).value;

			apiFetch( {
				path: '/spawn/v1/auth/login',
				method: 'POST',
				data: { email, password },
			} )
				.then( function ( response ) {
					if ( response.success ) {
						document.dispatchEvent( new CustomEvent( 'spawn:user-logged-in', {
							detail: { user: response.user },
						} ) );
						window.location.href = '/spawn/dashboard/';
					}
				} )
				.catch( function ( error ) {
					errorDiv.textContent = error.message || 'Login failed. Please try again.';
					errorDiv.style.display = 'block';
					submitBtn.disabled = false;
					submitBtn.textContent = 'Log In';
				} );
		} );

		// Toggle to register form
		form.querySelector( '.spawn-register-toggle' ).addEventListener( 'click', function ( e ) {
			e.preventDefault();
			renderRegisterForm( block );
		} );
	}

	function renderRegisterForm( block ) {
		const form = document.createElement( 'form' );
		form.className = 'wp-block-spawn-login__form';
		form.innerHTML = `
			<h2>Create Account</h2>
			<div class="wp-block-spawn-login__field">
				<label for="spawn-register-email">Email</label>
				<input type="email" id="spawn-register-email" name="email" required />
			</div>
			<div class="wp-block-spawn-login__field">
				<label for="spawn-register-password">Password</label>
				<input type="password" id="spawn-register-password" name="password" required minlength="8" />
			</div>
			<div class="wp-block-spawn-login__field">
				<label for="spawn-register-confirm">Confirm Password</label>
				<input type="password" id="spawn-register-confirm" name="confirm" required />
			</div>
			<div class="wp-block-spawn-login__error" style="display: none;"></div>
			<button type="submit" class="wp-block-spawn-login__submit">Create Account</button>
			<p class="wp-block-spawn-login__register-link">
				Already have an account? <a href="#" class="spawn-login-toggle">Log in</a>
			</p>
		`;

		block.innerHTML = '';
		block.appendChild( form );

		const errorDiv = form.querySelector( '.wp-block-spawn-login__error' );
		const submitBtn = form.querySelector( '.wp-block-spawn-login__submit' );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			errorDiv.style.display = 'none';

			const email = form.querySelector( '#spawn-register-email' ).value;
			const password = form.querySelector( '#spawn-register-password' ).value;
			const confirm = form.querySelector( '#spawn-register-confirm' ).value;

			if ( password !== confirm ) {
				errorDiv.textContent = 'Passwords do not match.';
				errorDiv.style.display = 'block';
				return;
			}

			submitBtn.disabled = true;
			submitBtn.textContent = 'Creating account...';

			apiFetch( {
				path: '/spawn/v1/auth/register',
				method: 'POST',
				data: { email, password },
			} )
				.then( function ( response ) {
					if ( response.success ) {
						document.dispatchEvent( new CustomEvent( 'spawn:user-logged-in', {
							detail: { user: response.user },
						} ) );
						window.location.href = '/spawn/dashboard/';
					}
				} )
				.catch( function ( error ) {
					errorDiv.textContent = error.message || 'Registration failed. Please try again.';
					errorDiv.style.display = 'block';
					submitBtn.disabled = false;
					submitBtn.textContent = 'Create Account';
				} );
		} );

		// Toggle back to login form
		form.querySelector( '.spawn-login-toggle' ).addEventListener( 'click', function ( e ) {
			e.preventDefault();
			renderLoginForm( block );
		} );
	}
} );
