/**
 * Spawn Success Block - Frontend view script.
 *
 * Polls for provisioning status and updates the UI accordingly.
 */

import apiFetch from '@wordpress/api-fetch';

interface StatusResponse {
	success: boolean;
	status: 'pending' | 'provisioning' | 'active' | 'failed' | 'not_found';
	customer?: {
		id: number;
		domain: string;
		status: string;
		server_ip?: string;
		tier: string;
	};
	progress?: {
		payment: boolean;
		server: boolean;
		wordpress: boolean;
		ai: boolean;
		percent: number;
	};
	error?: string;
	redirect_url?: string;
}

type ProvisioningStep = 'payment' | 'server' | 'wordpress' | 'ai';

document.addEventListener( 'DOMContentLoaded', function (): void {
	const blocks = document.querySelectorAll< HTMLElement >( '.wp-block-spawn-success' );

	blocks.forEach( function ( block: HTMLElement ): void {
		const sessionId = block.dataset.sessionId;
		const initialStatus = block.dataset.initialStatus;

		// If there's an error from PHP (e.g., missing session_id), don't poll.
		if ( initialStatus === 'error' || ! sessionId ) {
			return;
		}

		const states = {
			loading: block.querySelector< HTMLElement >( '[data-state="loading"]' ),
			provisioning: block.querySelector< HTMLElement >( '[data-state="provisioning"]' ),
			ready: block.querySelector< HTMLElement >( '[data-state="ready"]' ),
			failed: block.querySelector< HTMLElement >( '[data-state="failed"]' ),
		};

		const progressBar = block.querySelector< HTMLElement >( '.spawn-success__progress-fill' );
		const progressText = block.querySelector< HTMLElement >( '.spawn-success__progress-text' );
		const siteUrl = block.querySelector< HTMLAnchorElement >( '[data-site-url]' );
		const errorMessage = block.querySelector< HTMLElement >( '[data-error-message]' );

		const steps: Record< ProvisioningStep, HTMLElement | null > = {
			payment: block.querySelector( '[data-step="payment"]' ),
			server: block.querySelector( '[data-step="server"]' ),
			wordpress: block.querySelector( '[data-step="wordpress"]' ),
			ai: block.querySelector( '[data-step="ai"]' ),
		};

		let pollInterval: ReturnType< typeof setInterval > | null = null;
		let pollCount = 0;
		const maxPolls = 120; // 10 minutes max (5 second intervals).

		function showState( state: keyof typeof states ): void {
			Object.keys( states ).forEach( ( key ) => {
				const el = states[ key as keyof typeof states ];
				if ( el ) {
					el.style.display = key === state ? 'block' : 'none';
				}
			} );
		}

		function updateProgress( percent: number ): void {
			if ( progressBar ) {
				progressBar.style.width = `${ percent }%`;
				progressBar.dataset.progress = String( percent );
			}
			if ( progressText ) {
				progressText.textContent = `${ percent }%`;
			}
		}

		function updateStep( step: ProvisioningStep, completed: boolean, active: boolean = false ): void {
			const stepEl = steps[ step ];
			if ( ! stepEl ) return;

			const iconEl = stepEl.querySelector( '.spawn-success__step-icon' );
			if ( ! iconEl ) return;

			stepEl.classList.remove( 'spawn-success__step--completed', 'spawn-success__step--active', 'spawn-success__step--pending' );

			if ( completed ) {
				stepEl.classList.add( 'spawn-success__step--completed' );
				iconEl.textContent = '✓';
			} else if ( active ) {
				stepEl.classList.add( 'spawn-success__step--active' );
				iconEl.textContent = '⋯';
			} else {
				stepEl.classList.add( 'spawn-success__step--pending' );
				iconEl.textContent = '○';
			}
		}

		function updateSteps( progress: StatusResponse[ 'progress' ] ): void {
			if ( ! progress ) return;

			// Update each step based on progress.
			updateStep( 'payment', progress.payment, ! progress.payment );
			updateStep( 'server', progress.server, progress.payment && ! progress.server );
			updateStep( 'wordpress', progress.wordpress, progress.server && ! progress.wordpress );
			updateStep( 'ai', progress.ai, progress.wordpress && ! progress.ai );

			updateProgress( progress.percent );
		}

		async function checkStatus(): Promise< void > {
			pollCount++;

			if ( pollCount > maxPolls ) {
				stopPolling();
				showState( 'failed' );
				if ( errorMessage ) {
					errorMessage.textContent = 'Setup is taking longer than expected. Please check your dashboard or contact support.';
				}
				return;
			}

			try {
				const response = await apiFetch< StatusResponse >( {
					path: `/spawn/v1/checkout/status?session_id=${ encodeURIComponent( sessionId ) }`,
				} );

				if ( ! response.success ) {
					if ( response.status === 'not_found' ) {
						// Session not yet processed - keep polling.
						return;
					}
					throw new Error( response.error || 'Unknown error' );
				}

				switch ( response.status ) {
					case 'pending':
						showState( 'provisioning' );
						updateSteps( {
							payment: true,
							server: false,
							wordpress: false,
							ai: false,
							percent: 10,
						} );
						break;

					case 'provisioning':
						showState( 'provisioning' );
						if ( response.progress ) {
							updateSteps( response.progress );
						}
						break;

					case 'active':
						stopPolling();
						showState( 'ready' );
						if ( siteUrl && response.customer?.domain ) {
							const domain = response.customer.domain;
							const url = domain.startsWith( 'http' ) ? domain : `https://${ domain }`;
							siteUrl.href = url;
							siteUrl.textContent = domain;
						}
						// Optionally redirect to dashboard after a delay.
						if ( response.redirect_url ) {
							setTimeout( () => {
								window.location.href = response.redirect_url!;
							}, 3000 );
						}
						break;

					case 'failed':
						stopPolling();
						showState( 'failed' );
						if ( errorMessage && response.error ) {
							errorMessage.textContent = response.error;
						}
						break;

					default:
						// Unknown status - keep polling.
						break;
				}
			} catch ( error ) {
				console.error( 'Status check failed:', error );
				// Don't stop polling on transient errors.
			}
		}

		function startPolling(): void {
			// Initial check.
			checkStatus();
			// Poll every 5 seconds.
			pollInterval = setInterval( checkStatus, 5000 );
		}

		function stopPolling(): void {
			if ( pollInterval ) {
				clearInterval( pollInterval );
				pollInterval = null;
			}
		}

		// Start polling for status.
		startPolling();

		// Cleanup on page unload.
		window.addEventListener( 'beforeunload', stopPolling );
	} );
} );
