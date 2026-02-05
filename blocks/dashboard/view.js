import apiFetch from '@wordpress/api-fetch';
document.addEventListener( 'DOMContentLoaded', function () {
	const blocks = document.querySelectorAll( '.wp-block-spawn-dashboard' );

	blocks.forEach( function ( block ) {
		block.innerHTML = '<div class="wp-block-spawn-dashboard__loading">Loading dashboard...</div>';

		// Fetch customer data and credit balance in parallel.
		Promise.all( [
			apiFetch( { path: '/spawn/v1/customer/me' } ),
			apiFetch( { path: '/spawn/v1/credits/balance' } ).catch( () => null ),
		] )
			.then( function ( [ customerResponse, creditResponse ] ) {
				if ( ! customerResponse.success || ! customerResponse.customer ) {
					renderNotCustomer( block );
					return;
				}
				renderDashboard( block, customerResponse.customer, creditResponse );
			} )
			.catch( function ( error ) {
				if ( error.code === 'rest_not_logged_in' ) {
					renderNotLoggedIn( block );
				} else {
					renderError( block, error.message );
				}
			} );
	} );

	function renderNotLoggedIn( block ) {
		block.innerHTML = `
			<div class="wp-block-spawn-dashboard__message">
				<h2>Please Log In</h2>
				<p>You need to be logged in to view your dashboard.</p>
				<a href="/spawn/login/" class="wp-block-spawn-dashboard__btn">Log In</a>
			</div>
		`;
	}

	function renderNotCustomer( block ) {
		block.innerHTML = `
			<div class="wp-block-spawn-dashboard__message">
				<h2>No Active Subscription</h2>
				<p>You don't have an active Spawn subscription yet.</p>
				<a href="/spawn/" class="wp-block-spawn-dashboard__btn">Get Started</a>
			</div>
		`;
	}

	function renderError( block, message ) {
		block.innerHTML = `
			<div class="wp-block-spawn-dashboard__error">
				<h2>Error Loading Dashboard</h2>
				<p>${ message || 'An unexpected error occurred.' }</p>
				<button onclick="window.location.reload()" class="wp-block-spawn-dashboard__btn">Retry</button>
			</div>
		`;
	}

	function getStatusClass( status ) {
		const statusMap = {
			active: 'status-active',
			provisioning: 'status-provisioning',
			pending: 'status-pending',
			suspended: 'status-suspended',
			cancelled: 'status-cancelled',
		};
		return statusMap[ status ] || 'status-unknown';
	}

	function getStatusLabel( status ) {
		const labelMap = {
			active: 'Active',
			provisioning: 'Setting Up...',
			pending: 'Pending',
			suspended: 'Suspended',
			cancelled: 'Cancelled',
		};
		return labelMap[ status ] || status;
	}

	function getTierLabel( vpsTier ) {
		const tierMap = {
			cx22: 'Starter',
			cx32: 'Pro',
			cx42: 'Business',
		};
		return tierMap[ vpsTier ] || vpsTier;
	}

	function renderDashboard( block, customer, creditData ) {
		const usagePercent = Math.round( ( customer.ai_calls_used / customer.ai_calls_limit ) * 100 );
		const usageClass = usagePercent > 80 ? 'usage-high' : usagePercent > 50 ? 'usage-medium' : 'usage-low';
		const creditBalance = creditData ? creditData.balance : 0;
		const creditClass = creditBalance < 100 ? 'credits-low' : creditBalance < 500 ? 'credits-medium' : 'credits-ok';

		block.innerHTML = `
			<div class="wp-block-spawn-dashboard__container">
				<h2>Your Dashboard</h2>
				
				<div class="wp-block-spawn-dashboard__cards">
					<div class="wp-block-spawn-dashboard__card">
						<h3>Server Status</h3>
						<span class="status-badge ${ getStatusClass( customer.status ) }">
							${ getStatusLabel( customer.status ) }
						</span>
						${ customer.status === 'provisioning' ? '<p class="status-note">Your site is being set up. This usually takes 5-10 minutes.</p>' : '' }
					</div>
					
					<div class="wp-block-spawn-dashboard__card">
						<h3>Your Site</h3>
						<p class="domain-name">
							<a href="https://${ customer.domain }" target="_blank" rel="noopener">
								${ customer.domain }
							</a>
						</p>
						${ customer.server_ip ? `<p class="server-ip">IP: ${ customer.server_ip }</p>` : '' }
					</div>
					
					<div class="wp-block-spawn-dashboard__card card-credits ${ creditClass }">
						<h3>Credit Balance</h3>
						<p class="credit-balance">${ creditBalance.toLocaleString() } <span class="credit-label">credits</span></p>
						${ creditBalance < 100 ? '<p class="credit-warning">⚠️ Low balance</p>' : '' }
						<button type="button" class="wp-block-spawn-dashboard__btn btn-small btn-buy-credits">
							Buy Credits
						</button>
					</div>
					
					<div class="wp-block-spawn-dashboard__card">
						<h3>AI Usage This Month</h3>
						<div class="usage-bar ${ usageClass }">
							<div class="usage-fill" style="width: ${ usagePercent }%"></div>
						</div>
						<p class="usage-text">
							${ customer.ai_calls_used.toLocaleString() } / ${ customer.ai_calls_limit.toLocaleString() } calls
							<span class="usage-percent">(${ usagePercent }%)</span>
						</p>
					</div>
					
					<div class="wp-block-spawn-dashboard__card">
						<h3>Current Plan</h3>
						<p class="plan-name">${ getTierLabel( customer.vps_tier ) }</p>
						<a href="/spawn/account/" class="plan-link">Manage Plan →</a>
					</div>
					
					<div class="wp-block-spawn-dashboard__card card-chat">
						<h3>💬 Chat with your AI</h3>
						<p class="chat-description">Ask questions, request changes, get help with your site.</p>
						<a href="/spawn/chat/" class="wp-block-spawn-dashboard__btn btn-chat">
							Open Chat →
						</a>
					</div>
				</div>
				
				<div class="wp-block-spawn-dashboard__actions">
					<button type="button" class="wp-block-spawn-dashboard__btn btn-billing">
						Manage Billing
					</button>
					<a href="/spawn/account/" class="wp-block-spawn-dashboard__btn btn-secondary">
						Account Settings
					</a>
				</div>
			</div>
			
			<!-- Credit Purchase Modal -->
			<div class="wp-block-spawn-dashboard__modal" id="credit-modal" style="display: none;">
				<div class="modal-backdrop"></div>
				<div class="modal-content">
					<button type="button" class="modal-close">&times;</button>
					<h3>Buy Credits</h3>
					<p class="modal-description">Choose a credit package:</p>
					<div class="credit-packages">
						<button type="button" class="credit-package" data-package="small">
							<span class="package-credits">1,000</span>
							<span class="package-price">$10</span>
							<span class="package-rate">$0.01/credit</span>
						</button>
						<button type="button" class="credit-package package-popular" data-package="medium">
							<span class="package-badge">Popular</span>
							<span class="package-credits">3,000</span>
							<span class="package-price">$25</span>
							<span class="package-rate">$0.0083/credit</span>
							<span class="package-bonus">17% bonus!</span>
						</button>
						<button type="button" class="credit-package" data-package="large">
							<span class="package-badge">Best Value</span>
							<span class="package-credits">7,500</span>
							<span class="package-price">$50</span>
							<span class="package-rate">$0.0067/credit</span>
							<span class="package-bonus">50% bonus!</span>
						</button>
					</div>
				</div>
			</div>
		`;

		// Handle billing portal button.
		block.querySelector( '.btn-billing' ).addEventListener( 'click', function () {
			this.disabled = true;
			this.textContent = 'Loading...';

			apiFetch( { path: '/spawn/v1/customer/billing-portal' } )
				.then( function ( response ) {
					if ( response.url ) {
						window.location.href = response.url;
					}
				} )
				.catch( function ( error ) {
					alert( error.message || 'Failed to load billing portal.' );
					this.disabled = false;
					this.textContent = 'Manage Billing';
				}.bind( this ) );
		} );

		// Credit modal handling.
		const modal = block.querySelector( '#credit-modal' );
		const buyButton = block.querySelector( '.btn-buy-credits' );
		const closeButton = modal.querySelector( '.modal-close' );
		const backdrop = modal.querySelector( '.modal-backdrop' );
		const packageButtons = modal.querySelectorAll( '.credit-package' );

		function openModal() {
			modal.style.display = 'flex';
			document.body.style.overflow = 'hidden';
		}

		function closeModal() {
			modal.style.display = 'none';
			document.body.style.overflow = '';
		}

		buyButton.addEventListener( 'click', openModal );
		closeButton.addEventListener( 'click', closeModal );
		backdrop.addEventListener( 'click', closeModal );

		// Handle escape key.
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && modal.style.display === 'flex' ) {
				closeModal();
			}
		} );

		// Handle credit package selection.
		packageButtons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				const packageType = this.dataset.package;
				
				// Disable all buttons and show loading.
				packageButtons.forEach( ( b ) => {
					b.disabled = true;
				} );
				this.classList.add( 'loading' );
				this.innerHTML += '<span class="spinner"></span>';

				apiFetch( {
					path: '/spawn/v1/credits/purchase',
					method: 'POST',
					data: { package: packageType },
				} )
					.then( function ( response ) {
						if ( response.checkout_url ) {
							window.location.href = response.checkout_url;
						}
					} )
					.catch( function ( error ) {
						alert( error.message || 'Failed to start checkout.' );
						packageButtons.forEach( ( b ) => {
							b.disabled = false;
							b.classList.remove( 'loading' );
							const spinner = b.querySelector( '.spinner' );
							if ( spinner ) {
								spinner.remove();
							}
						} );
					} );
			} );
		} );

		// Check for successful purchase redirect.
		const urlParams = new URLSearchParams( window.location.search );
		if ( urlParams.get( 'credits_purchased' ) === '1' ) {
			// Show success message.
			const successBanner = document.createElement( 'div' );
			successBanner.className = 'wp-block-spawn-dashboard__success';
			successBanner.innerHTML = '✓ Credits added to your account!';
			block.querySelector( '.wp-block-spawn-dashboard__container' ).prepend( successBanner );
			
			// Remove query param from URL.
			window.history.replaceState( {}, '', window.location.pathname );
			
			// Auto-dismiss after 5 seconds.
			setTimeout( () => {
				successBanner.remove();
			}, 5000 );
		}
	}
} );
