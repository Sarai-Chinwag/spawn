/**
 * Login block view script.
 *
 * @package Spawn
 */

import apiFetch from '@wordpress/api-fetch';
import { setButtonLoading, getErrorMessage, type ApiError } from '../shared';

// Login-specific types
interface AuthMeResponse {
	logged_in: boolean;
	user?: { email: string };
}

interface AuthResponse {
	success: boolean;
	user?: { email: string };
	message?: string;
}

interface GoogleConfiguredResponse {
	configured: boolean;
}

interface GoogleAuthResponse {
	auth_url?: string;
}

// API endpoints
const API = {
	me: '/spawn/v1/auth/me',
	login: '/spawn/v1/auth/login',
	logout: '/spawn/v1/auth/logout',
	register: '/spawn/v1/auth/register',
	googleConfigured: '/spawn/v1/auth/google/configured',
	googleAuth: '/spawn/v1/auth/google',
	forgotPassword: '/spawn/v1/auth/forgot-password',
	resetPassword: '/spawn/v1/auth/reset-password',
};

// Google icon SVG
const GOOGLE_ICON = `<svg width="18" height="18" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
	<path fill="#EA4335" d="M24 9.5c3.54 0 6.72 1.22 9.23 3.61l6.9-6.9C35.93 2.64 30.3 0 24 0 14.62 0 6.5 5.38 2.56 13.22l8.05 6.24C12.5 13.09 17.77 9.5 24 9.5z"/>
	<path fill="#4285F4" d="M46.5 24c0-1.64-.15-3.22-.43-4.74H24v9h12.7c-.55 2.96-2.2 5.46-4.67 7.15l7.5 5.82C43.86 37.24 46.5 31.05 46.5 24z"/>
	<path fill="#FBBC05" d="M10.61 28.98A14.46 14.46 0 0 1 9.5 24c0-1.72.3-3.39.82-4.98l-8.05-6.24A24.03 24.03 0 0 0 0 24c0 3.95.95 7.69 2.64 10.98l7.97-6z"/>
	<path fill="#34A853" d="M24 48c6.3 0 11.93-2.08 15.9-5.68l-7.5-5.82c-2.08 1.4-4.75 2.22-8.4 2.22-6.23 0-11.5-3.59-13.39-8.76l-7.97 6C6.5 42.62 14.62 48 24 48z"/>
</svg>`;

/**
 * Initialize login blocks.
 */
function init(): void {
	document.querySelectorAll< HTMLElement >( '.wp-block-spawn-login' ).forEach( initBlock );
}

/**
 * Initialize a single login block.
 */
function initBlock( block: HTMLElement ): void {
	apiFetch< AuthMeResponse >( { path: API.me } )
		.then( ( response ) => {
			if ( response.logged_in && response.user ) {
				renderLoggedIn( block, response.user.email );
			} else {
				renderLoginForm( block );
			}
		} )
		.catch( () => renderLoginForm( block ) );
}

/**
 * Render logged-in state.
 */
function renderLoggedIn( block: HTMLElement, email: string ): void {
	block.innerHTML = `
		<div class="wp-block-spawn-login__logged-in">
			<p>Logged in as <strong>${ email }</strong></p>
			<a href="/spawn/dashboard/" class="wp-block-spawn-login__dashboard-link">Go to Dashboard</a>
			<button type="button" class="wp-block-spawn-login__logout-btn">Log Out</button>
		</div>
	`;

	block.querySelector< HTMLButtonElement >( '.wp-block-spawn-login__logout-btn' )?.addEventListener( 'click', () => {
		apiFetch( { path: API.logout, method: 'POST' } ).then( () => window.location.reload() );
	} );
}

/**
 * Show error in form.
 */
function showFormError( form: HTMLElement, message: string ): void {
	const errorDiv = form.querySelector< HTMLElement >( '.wp-block-spawn-login__error' );
	if ( errorDiv ) {
		errorDiv.textContent = message;
		errorDiv.style.display = 'block';
	}
}

/**
 * Show success in form.
 */
function showFormSuccess( form: HTMLElement, message: string ): void {
	const successDiv = form.querySelector< HTMLElement >( '.wp-block-spawn-login__success' );
	if ( successDiv ) {
		successDiv.textContent = message;
		successDiv.style.display = 'block';
	}
}

/**
 * Hide form messages.
 */
function hideFormMessages( form: HTMLElement ): void {
	const errorDiv = form.querySelector< HTMLElement >( '.wp-block-spawn-login__error' );
	const successDiv = form.querySelector< HTMLElement >( '.wp-block-spawn-login__success' );
	if ( errorDiv ) errorDiv.style.display = 'none';
	if ( successDiv ) successDiv.style.display = 'none';
}

/**
 * Dispatch login event and redirect.
 */
function onLoginSuccess( user?: { email: string } ): void {
	document.dispatchEvent( new CustomEvent( 'spawn:user-logged-in', { detail: { user } } ) );
	window.location.href = '/spawn/dashboard/';
}

/**
 * Render login form.
 */
function renderLoginForm( block: HTMLElement ): void {
	block.innerHTML = `
		<form class="wp-block-spawn-login__form">
			<h2>Log In</h2>
			<div class="wp-block-spawn-login__oauth" style="display: none;">
				<button type="button" class="wp-block-spawn-login__google">
					<span class="wp-block-spawn-login__google-icon" aria-hidden="true">${ GOOGLE_ICON }</span>
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
		</form>
	`;

	const form = block.querySelector< HTMLFormElement >( 'form' )!;
	const submitBtn = form.querySelector< HTMLButtonElement >( '.wp-block-spawn-login__submit' )!;
	const oauthWrap = form.querySelector< HTMLElement >( '.wp-block-spawn-login__oauth' )!;
	const googleBtn = form.querySelector< HTMLButtonElement >( '.wp-block-spawn-login__google' );

	// Check if Google OAuth is configured
	apiFetch< GoogleConfiguredResponse >( { path: API.googleConfigured } )
		.then( ( response ) => {
			if ( response?.configured ) oauthWrap.style.display = 'block';
		} )
		.catch( () => {} );

	// Google sign-in
	googleBtn?.addEventListener( 'click', () => {
		setButtonLoading( googleBtn, true );
		googleBtn.classList.add( 'is-loading' );

		apiFetch< GoogleAuthResponse >( { path: API.googleAuth } )
			.then( ( response ) => {
				if ( response?.auth_url ) {
					window.location.href = response.auth_url;
				} else {
					throw new Error( 'No auth URL returned.' );
				}
			} )
			.catch( () => {
				showFormError( form, 'Google sign-in failed. Please try again.' );
				setButtonLoading( googleBtn, false );
				googleBtn.classList.remove( 'is-loading' );
			} );
	} );

	// Form submit
	form.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		hideFormMessages( form );
		setButtonLoading( submitBtn, true, 'Logging in...' );

		const email = form.querySelector< HTMLInputElement >( '#spawn-login-email' )!.value;
		const password = form.querySelector< HTMLInputElement >( '#spawn-login-password' )!.value;

		apiFetch< AuthResponse >( { path: API.login, method: 'POST', data: { email, password } } )
			.then( ( response ) => {
				if ( response.success ) onLoginSuccess( response.user );
			} )
			.catch( ( error: ApiError ) => {
				showFormError( form, getErrorMessage( error, 'Login failed. Please try again.' ) );
				setButtonLoading( submitBtn, false, '', 'Log In' );
			} );
	} );

	// Navigation links
	form.querySelector( '.spawn-register-toggle' )?.addEventListener( 'click', ( e ) => {
		e.preventDefault();
		renderRegisterForm( block );
	} );

	form.querySelector( '.spawn-forgot-toggle' )?.addEventListener( 'click', ( e ) => {
		e.preventDefault();
		renderForgotPasswordForm( block );
	} );

	// Check URL params for password reset flow
	const urlParams = new URLSearchParams( window.location.search );
	const action = urlParams.get( 'action' );
	if ( action === 'reset' ) {
		const key = urlParams.get( 'key' );
		const login = urlParams.get( 'login' );
		if ( key && login ) renderResetPasswordForm( block, key, login );
	} else if ( action === 'forgot' ) {
		renderForgotPasswordForm( block );
	}
}

/**
 * Render register form.
 */
function renderRegisterForm( block: HTMLElement ): void {
	block.innerHTML = `
		<form class="wp-block-spawn-login__form">
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
		</form>
	`;

	const form = block.querySelector< HTMLFormElement >( 'form' )!;
	const submitBtn = form.querySelector< HTMLButtonElement >( '.wp-block-spawn-login__submit' )!;

	form.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		hideFormMessages( form );

		const email = form.querySelector< HTMLInputElement >( '#spawn-register-email' )!.value;
		const password = form.querySelector< HTMLInputElement >( '#spawn-register-password' )!.value;
		const confirm = form.querySelector< HTMLInputElement >( '#spawn-register-confirm' )!.value;

		if ( password !== confirm ) {
			showFormError( form, 'Passwords do not match.' );
			return;
		}

		setButtonLoading( submitBtn, true, 'Creating account...' );

		apiFetch< AuthResponse >( { path: API.register, method: 'POST', data: { email, password } } )
			.then( ( response ) => {
				if ( response.success ) onLoginSuccess( response.user );
			} )
			.catch( ( error: ApiError ) => {
				showFormError( form, getErrorMessage( error, 'Registration failed. Please try again.' ) );
				setButtonLoading( submitBtn, false, '', 'Create Account' );
			} );
	} );

	form.querySelector( '.spawn-login-toggle' )?.addEventListener( 'click', ( e ) => {
		e.preventDefault();
		renderLoginForm( block );
	} );
}

/**
 * Render forgot password form.
 */
function renderForgotPasswordForm( block: HTMLElement ): void {
	block.innerHTML = `
		<form class="wp-block-spawn-login__form">
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
		</form>
	`;

	const form = block.querySelector< HTMLFormElement >( 'form' )!;
	const submitBtn = form.querySelector< HTMLButtonElement >( '.wp-block-spawn-login__submit' )!;

	form.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		hideFormMessages( form );
		setButtonLoading( submitBtn, true, 'Sending...' );

		const email = form.querySelector< HTMLInputElement >( '#spawn-forgot-email' )!.value;

		apiFetch< AuthResponse >( { path: API.forgotPassword, method: 'POST', data: { email } } )
			.then( ( response ) => {
				showFormSuccess( form, response.message || 'Check your email for a reset link.' );
				submitBtn.disabled = true;
				submitBtn.textContent = 'Email Sent';
			} )
			.catch( ( error: ApiError ) => {
				showFormError( form, getErrorMessage( error, 'Failed to send reset email. Please try again.' ) );
				setButtonLoading( submitBtn, false, '', 'Send Reset Link' );
			} );
	} );

	form.querySelector( '.spawn-login-toggle' )?.addEventListener( 'click', ( e ) => {
		e.preventDefault();
		window.history.replaceState( {}, '', window.location.pathname );
		renderLoginForm( block );
	} );
}

/**
 * Render reset password form.
 */
function renderResetPasswordForm( block: HTMLElement, key: string, login: string ): void {
	block.innerHTML = `
		<form class="wp-block-spawn-login__form">
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
		</form>
	`;

	const form = block.querySelector< HTMLFormElement >( 'form' )!;
	const submitBtn = form.querySelector< HTMLButtonElement >( '.wp-block-spawn-login__submit' )!;

	form.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		hideFormMessages( form );

		const password = form.querySelector< HTMLInputElement >( '#spawn-reset-password' )!.value;
		const confirm = form.querySelector< HTMLInputElement >( '#spawn-reset-confirm' )!.value;

		if ( password !== confirm ) {
			showFormError( form, 'Passwords do not match.' );
			return;
		}

		setButtonLoading( submitBtn, true, 'Resetting...' );

		apiFetch< AuthResponse >( { path: API.resetPassword, method: 'POST', data: { email: login, token: key, password } } )
			.then( ( response ) => {
				showFormSuccess( form, response.message || 'Password reset! Redirecting to login...' );
				setTimeout( () => {
					window.history.replaceState( {}, '', window.location.pathname );
					renderLoginForm( block );
				}, 2000 );
			} )
			.catch( ( error: ApiError ) => {
				showFormError( form, getErrorMessage( error, 'Failed to reset password. The link may have expired.' ) );
				setButtonLoading( submitBtn, false, '', 'Reset Password' );
			} );
	} );
}

// Initialize on DOM ready
document.addEventListener( 'DOMContentLoaded', init );
