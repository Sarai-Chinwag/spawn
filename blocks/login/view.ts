import apiFetch from '@wordpress/api-fetch';

interface AuthMeResponse {
	logged_in: boolean;
	user?: {
		email: string;
	};
}

interface GoogleConfiguredResponse {
	configured: boolean;
}

interface GoogleAuthResponse {
	auth_url?: string;
}

interface AuthResponse {
	success: boolean;
	user?: {
		email: string;
	};
}

interface ApiError {
	message?: string;
}

document.addEventListener( 'DOMContentLoaded', function (): void {
	const blocks = document.querySelectorAll< HTMLElement >( '.wp-block-spawn-login' );

	blocks.forEach( function ( block: HTMLElement ): void {
		apiFetch< AuthMeResponse >( { path: '/spawn/v1/auth/me' } )
			.then( function ( response: AuthMeResponse ): void {
				if ( response.logged_in && response.user ) {
					block.innerHTML = `
						<div class="wp-block-spawn-login__logged-in">
							<p>Logged in as <strong>${ response.user.email }</strong></p>
							<a href="/spawn/dashboard/" class="wp-block-spawn-login__dashboard-link">Go to Dashboard</a>
							<button type="button" class="wp-block-spawn-login__logout-btn">Log Out</button>
						</div>
					`;
					const logoutBtn = block.querySelector< HTMLButtonElement >( '.wp-block-spawn-login__logout-btn' );
					logoutBtn?.addEventListener( 'click', function (): void {
						apiFetch( { path: '/spawn/v1/auth/logout', method: 'POST' } )
							.then( function (): void {
								window.location.reload();
							} );
					} );
					return;
				}
				renderLoginForm( block );
			} )
			.catch( function (): void {
				renderLoginForm( block );
			} );
	} );

	function renderLoginForm( block: HTMLElement ): void {
		const form = document.createElement( 'form' );
		form.className = 'wp-block-spawn-login__form';
		form.innerHTML = `
			<h2>Log In</h2>
			<div class="wp-block-spawn-login__oauth" style="display: none;">
				<button type="button" class="wp-block-spawn-login__google">
					<span class="wp-block-spawn-login__google-icon" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
							<path fill="#EA4335" d="M24 9.5c3.54 0 6.72 1.22 9.23 3.61l6.9-6.9C35.93 2.64 30.3 0 24 0 14.62 0 6.5 5.38 2.56 13.22l8.05 6.24C12.5 13.09 17.77 9.5 24 9.5z"/>
							<path fill="#4285F4" d="M46.5 24c0-1.64-.15-3.22-.43-4.74H24v9h12.7c-.55 2.96-2.2 5.46-4.67 7.15l7.5 5.82C43.86 37.24 46.5 31.05 46.5 24z"/>
							<path fill="#FBBC05" d="M10.61 28.98A14.46 14.46 0 0 1 9.5 24c0-1.72.3-3.39.82-4.98l-8.05-6.24A24.03 24.03 0 0 0 0 24c0 3.95.95 7.69 2.64 10.98l7.97-6z"/>
							<path fill="#34A853" d="M24 48c6.3 0 11.93-2.08 15.9-5.68l-7.5-5.82c-2.08 1.4-4.75 2.22-8.4 2.22-6.23 0-11.5-3.59-13.39-8.76l-7.97 6C6.5 42.62 14.62 48 24 48z"/>
						</svg>
					</span>
					<span>Sign in with Google</span>
				</button>
				<div class="wp-block-spawn-login__divider"><span>or</span></div>
			</div>
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
			<p class="wp-block-spawn-login__forgot-link">
				<a href="#" class="spawn-forgot-toggle">Forgot your password?</a>
			</p>
			<p class="wp-block-spawn-login__register-link">
				Don't have an account? <a href="#" class="spawn-register-toggle">Create one</a>
			</p>
		`;

		block.innerHTML = '';
		block.appendChild( form );

		const errorDiv = form.querySelector< HTMLElement >( '.wp-block-spawn-login__error' )!;
		const submitBtn = form.querySelector< HTMLButtonElement >( '.wp-block-spawn-login__submit' )!;
		const oauthWrap = form.querySelector< HTMLElement >( '.wp-block-spawn-login__oauth' )!;
		const googleBtn = form.querySelector< HTMLButtonElement >( '.wp-block-spawn-login__google' );

		apiFetch< GoogleConfiguredResponse >( { path: '/spawn/v1/auth/google/configured' } )
			.then( function ( response: GoogleConfiguredResponse ): void {
				if ( response && response.configured ) {
					oauthWrap.style.display = 'block';
				}
			} )
			.catch( function (): void {
				oauthWrap.style.display = 'none';
			} );

		if ( googleBtn ) {
			googleBtn.addEventListener( 'click', function (): void {
				googleBtn.disabled = true;
				googleBtn.classList.add( 'is-loading' );

				apiFetch< GoogleAuthResponse >( { path: '/spawn/v1/auth/google' } )
					.then( function ( response: GoogleAuthResponse ): void {
						if ( response && response.auth_url ) {
							window.location.href = response.auth_url;
							return;
						}
						throw new Error( 'No auth URL returned.' );
					} )
					.catch( function (): void {
						errorDiv.textContent = 'Google sign-in failed. Please try again.';
						errorDiv.style.display = 'block';
						googleBtn.disabled = false;
						googleBtn.classList.remove( 'is-loading' );
					} );
			} );
		}

		form.addEventListener( 'submit', function ( e: Event ): void {
			e.preventDefault();
			errorDiv.style.display = 'none';
			submitBtn.disabled = true;
			submitBtn.textContent = 'Logging in...';

			const emailInput = form.querySelector< HTMLInputElement >( '#spawn-login-email' )!;
			const passwordInput = form.querySelector< HTMLInputElement >( '#spawn-login-password' )!;
			const email = emailInput.value;
			const password = passwordInput.value;

			apiFetch< AuthResponse >( {
				path: '/spawn/v1/auth/login',
				method: 'POST',
				data: { email, password },
			} )
				.then( function ( response: AuthResponse ): void {
					if ( response.success ) {
						document.dispatchEvent( new CustomEvent( 'spawn:user-logged-in', {
							detail: { user: response.user },
						} ) );
						window.location.href = '/spawn/dashboard/';
					}
				} )
				.catch( function ( error: ApiError ): void {
					errorDiv.textContent = error.message || 'Login failed. Please try again.';
					errorDiv.style.display = 'block';
					submitBtn.disabled = false;
					submitBtn.textContent = 'Log In';
				} );
		} );

		const registerToggle = form.querySelector< HTMLAnchorElement >( '.spawn-register-toggle' );
		registerToggle?.addEventListener( 'click', function ( e: Event ): void {
			e.preventDefault();
			renderRegisterForm( block );
		} );

		const forgotToggle = form.querySelector< HTMLAnchorElement >( '.spawn-forgot-toggle' );
		forgotToggle?.addEventListener( 'click', function ( e: Event ): void {
			e.preventDefault();
			renderForgotPasswordForm( block );
		} );

		// Check URL params for password reset flow.
		const urlParams = new URLSearchParams( window.location.search );
		if ( urlParams.get( 'action' ) === 'reset' ) {
			const key = urlParams.get( 'key' );
			const login = urlParams.get( 'login' );
			if ( key && login ) {
				renderResetPasswordForm( block, key, login );
			}
		} else if ( urlParams.get( 'action' ) === 'forgot' ) {
			renderForgotPasswordForm( block );
		}
	}

	function renderRegisterForm( block: HTMLElement ): void {
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

		const errorDiv = form.querySelector< HTMLElement >( '.wp-block-spawn-login__error' )!;
		const submitBtn = form.querySelector< HTMLButtonElement >( '.wp-block-spawn-login__submit' )!;

		form.addEventListener( 'submit', function ( e: Event ): void {
			e.preventDefault();
			errorDiv.style.display = 'none';

			const emailInput = form.querySelector< HTMLInputElement >( '#spawn-register-email' )!;
			const passwordInput = form.querySelector< HTMLInputElement >( '#spawn-register-password' )!;
			const confirmInput = form.querySelector< HTMLInputElement >( '#spawn-register-confirm' )!;
			const email = emailInput.value;
			const password = passwordInput.value;
			const confirm = confirmInput.value;

			if ( password !== confirm ) {
				errorDiv.textContent = 'Passwords do not match.';
				errorDiv.style.display = 'block';
				return;
			}

			submitBtn.disabled = true;
			submitBtn.textContent = 'Creating account...';

			apiFetch< AuthResponse >( {
				path: '/spawn/v1/auth/register',
				method: 'POST',
				data: { email, password },
			} )
				.then( function ( response: AuthResponse ): void {
					if ( response.success ) {
						document.dispatchEvent( new CustomEvent( 'spawn:user-logged-in', {
							detail: { user: response.user },
						} ) );
						window.location.href = '/spawn/dashboard/';
					}
				} )
				.catch( function ( error: ApiError ): void {
					errorDiv.textContent = error.message || 'Registration failed. Please try again.';
					errorDiv.style.display = 'block';
					submitBtn.disabled = false;
					submitBtn.textContent = 'Create Account';
				} );
		} );

		const loginToggle = form.querySelector< HTMLAnchorElement >( '.spawn-login-toggle' );
		loginToggle?.addEventListener( 'click', function ( e: Event ): void {
			e.preventDefault();
			renderLoginForm( block );
		} );
	}

	function renderForgotPasswordForm( block: HTMLElement ): void {
		const form = document.createElement( 'form' );
		form.className = 'wp-block-spawn-login__form';
		form.innerHTML = `
			<h2>Reset Password</h2>
			<p class="wp-block-spawn-login__info">Enter your email and we'll send you a link to reset your password.</p>
			<div class="wp-block-spawn-login__field">
				<label for="spawn-forgot-email">Email</label>
				<input type="email" id="spawn-forgot-email" name="email" required />
			</div>
			<div class="wp-block-spawn-login__error" style="display: none;"></div>
			<div class="wp-block-spawn-login__success" style="display: none;"></div>
			<button type="submit" class="wp-block-spawn-login__submit">Send Reset Link</button>
			<p class="wp-block-spawn-login__register-link">
				<a href="#" class="spawn-login-toggle">Back to login</a>
			</p>
		`;

		block.innerHTML = '';
		block.appendChild( form );

		const errorDiv = form.querySelector< HTMLElement >( '.wp-block-spawn-login__error' )!;
		const successDiv = form.querySelector< HTMLElement >( '.wp-block-spawn-login__success' )!;
		const submitBtn = form.querySelector< HTMLButtonElement >( '.wp-block-spawn-login__submit' )!;

		form.addEventListener( 'submit', function ( e: Event ): void {
			e.preventDefault();
			errorDiv.style.display = 'none';
			successDiv.style.display = 'none';

			const emailInput = form.querySelector< HTMLInputElement >( '#spawn-forgot-email' )!;
			const email = emailInput.value;

			submitBtn.disabled = true;
			submitBtn.textContent = 'Sending...';

			apiFetch< { success: boolean; message: string } >( {
				path: '/spawn/v1/auth/forgot-password',
				method: 'POST',
				data: { email },
			} )
				.then( function ( response ): void {
					successDiv.textContent = response.message || 'Check your email for a reset link.';
					successDiv.style.display = 'block';
					submitBtn.disabled = true;
					submitBtn.textContent = 'Email Sent';
				} )
				.catch( function ( error: ApiError ): void {
					errorDiv.textContent = error.message || 'Failed to send reset email. Please try again.';
					errorDiv.style.display = 'block';
					submitBtn.disabled = false;
					submitBtn.textContent = 'Send Reset Link';
				} );
		} );

		const loginToggle = form.querySelector< HTMLAnchorElement >( '.spawn-login-toggle' );
		loginToggle?.addEventListener( 'click', function ( e: Event ): void {
			e.preventDefault();
			// Clear URL params when going back to login.
			window.history.replaceState( {}, '', window.location.pathname );
			renderLoginForm( block );
		} );
	}

	function renderResetPasswordForm( block: HTMLElement, key: string, login: string ): void {
		// Get email from login (username) - we need to look it up or just use login.
		const form = document.createElement( 'form' );
		form.className = 'wp-block-spawn-login__form';
		form.innerHTML = `
			<h2>Set New Password</h2>
			<p class="wp-block-spawn-login__info">Enter your new password below.</p>
			<div class="wp-block-spawn-login__field">
				<label for="spawn-reset-password">New Password</label>
				<input type="password" id="spawn-reset-password" name="password" required minlength="8" />
			</div>
			<div class="wp-block-spawn-login__field">
				<label for="spawn-reset-confirm">Confirm Password</label>
				<input type="password" id="spawn-reset-confirm" name="confirm" required />
			</div>
			<div class="wp-block-spawn-login__error" style="display: none;"></div>
			<div class="wp-block-spawn-login__success" style="display: none;"></div>
			<button type="submit" class="wp-block-spawn-login__submit">Reset Password</button>
		`;

		block.innerHTML = '';
		block.appendChild( form );

		const errorDiv = form.querySelector< HTMLElement >( '.wp-block-spawn-login__error' )!;
		const successDiv = form.querySelector< HTMLElement >( '.wp-block-spawn-login__success' )!;
		const submitBtn = form.querySelector< HTMLButtonElement >( '.wp-block-spawn-login__submit' )!;

		form.addEventListener( 'submit', function ( e: Event ): void {
			e.preventDefault();
			errorDiv.style.display = 'none';
			successDiv.style.display = 'none';

			const passwordInput = form.querySelector< HTMLInputElement >( '#spawn-reset-password' )!;
			const confirmInput = form.querySelector< HTMLInputElement >( '#spawn-reset-confirm' )!;
			const password = passwordInput.value;
			const confirm = confirmInput.value;

			if ( password !== confirm ) {
				errorDiv.textContent = 'Passwords do not match.';
				errorDiv.style.display = 'block';
				return;
			}

			submitBtn.disabled = true;
			submitBtn.textContent = 'Resetting...';

			// Use login (username) to find user and reset.
			apiFetch< { success: boolean; message: string } >( {
				path: '/spawn/v1/auth/reset-password',
				method: 'POST',
				data: {
					email: login, // Backend will handle lookup by login/email.
					token: key,
					password,
				},
			} )
				.then( function ( response ): void {
					successDiv.textContent = response.message || 'Password reset! Redirecting to login...';
					successDiv.style.display = 'block';
					// Clear URL params and redirect to login.
					setTimeout( () => {
						window.history.replaceState( {}, '', window.location.pathname );
						renderLoginForm( block );
					}, 2000 );
				} )
				.catch( function ( error: ApiError ): void {
					errorDiv.textContent = error.message || 'Failed to reset password. The link may have expired.';
					errorDiv.style.display = 'block';
					submitBtn.disabled = false;
					submitBtn.textContent = 'Reset Password';
				} );
		} );
	}
} );
