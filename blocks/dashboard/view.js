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
						<div class="credit-actions">
							<button type="button" class="wp-block-spawn-dashboard__btn btn-small btn-buy-credits">
								Buy Credits
							</button>
							<button type="button" class="wp-block-spawn-dashboard__btn btn-small btn-secondary btn-auto-refill">
								${ creditData?.auto_refill?.enabled ? '✓ Auto-Refill On' : 'Auto-Refill' }
							</button>
						</div>
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
					<p class="modal-description">Enter amount ($10 minimum):</p>
					<div class="credit-purchase-form">
						<div class="amount-input-wrapper">
							<span class="currency-symbol">$</span>
							<input type="number" id="credit-amount" min="10" step="1" value="10" class="credit-amount-input" />
						</div>
						<p class="credits-preview">= <span id="credits-preview-value">1,000</span> credits</p>
						<button type="button" class="credit-purchase-btn" id="purchase-credits-btn">
							Purchase Credits
						</button>
					</div>
				</div>
			</div>
			
			<!-- Auto-Refill Modal -->
			<div class="wp-block-spawn-dashboard__modal" id="auto-refill-modal" style="display: none;">
				<div class="modal-backdrop"></div>
				<div class="modal-content">
					<button type="button" class="modal-close">&times;</button>
					<h3>Auto-Refill Settings</h3>
					<p class="modal-description">Automatically purchase credits when your balance is low.</p>
					<div class="auto-refill-form">
						<label class="auto-refill-toggle">
							<input type="checkbox" id="auto-refill-enabled" ${ creditData?.auto_refill?.enabled ? 'checked' : '' } />
							<span>Enable auto-refill</span>
						</label>
						<div class="auto-refill-settings" id="auto-refill-settings">
							<div class="form-row">
								<label for="auto-refill-threshold">Refill when balance falls below:</label>
								<div class="amount-input-wrapper small">
									<input type="number" id="auto-refill-threshold" min="50" step="50" value="${ creditData?.auto_refill?.threshold || 100 }" />
									<span class="unit">credits</span>
								</div>
							</div>
							<div class="form-row">
								<label for="auto-refill-amount">Amount to refill:</label>
								<div class="amount-input-wrapper small">
									<span class="currency-symbol">$</span>
									<input type="number" id="auto-refill-amount" min="10" step="5" value="${ ( creditData?.auto_refill?.amount || 1000 ) / 100 }" />
								</div>
								<p class="form-hint">= <span id="auto-refill-credits-preview">${ ( creditData?.auto_refill?.amount || 1000 ).toLocaleString() }</span> credits</p>
							</div>
						</div>
						<button type="button" class="wp-block-spawn-dashboard__btn" id="save-auto-refill-btn">
							Save Settings
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
		const amountInput = modal.querySelector( '#credit-amount' );
		const creditsPreview = modal.querySelector( '#credits-preview-value' );
		const purchaseBtn = modal.querySelector( '#purchase-credits-btn' );

		function openModal() {
			modal.style.display = 'flex';
			document.body.style.overflow = 'hidden';
		}

		function closeModal() {
			modal.style.display = 'none';
			document.body.style.overflow = '';
		}

		function updateCreditsPreview() {
			const amount = parseInt( amountInput.value, 10 ) || 0;
			const credits = amount * 100; // 1 credit = $0.01
			creditsPreview.textContent = credits.toLocaleString();
		}

		buyButton.addEventListener( 'click', openModal );
		closeButton.addEventListener( 'click', closeModal );
		backdrop.addEventListener( 'click', closeModal );
		amountInput.addEventListener( 'input', updateCreditsPreview );

		// Handle escape key.
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && modal.style.display === 'flex' ) {
				closeModal();
			}
		} );

		// Handle credit purchase.
		purchaseBtn.addEventListener( 'click', function () {
			const amount = parseInt( amountInput.value, 10 );

			if ( ! amount || amount < 10 ) {
				alert( 'Minimum purchase is $10.' );
				return;
			}

			purchaseBtn.disabled = true;
			purchaseBtn.textContent = 'Processing...';

			apiFetch( {
				path: '/spawn/v1/credits/purchase',
				method: 'POST',
				data: { amount: amount },
			} )
				.then( function ( response ) {
					if ( response.checkout_url ) {
						window.location.href = response.checkout_url;
					}
				} )
				.catch( function ( error ) {
					alert( error.message || 'Failed to start checkout.' );
					purchaseBtn.disabled = false;
					purchaseBtn.textContent = 'Purchase Credits';
				} );
		} );

		// Auto-refill modal handling.
		const autoRefillModal = block.querySelector( '#auto-refill-modal' );
		const autoRefillBtn = block.querySelector( '.btn-auto-refill' );
		const autoRefillClose = autoRefillModal.querySelector( '.modal-close' );
		const autoRefillBackdrop = autoRefillModal.querySelector( '.modal-backdrop' );
		const autoRefillEnabled = autoRefillModal.querySelector( '#auto-refill-enabled' );
		const autoRefillSettings = autoRefillModal.querySelector( '#auto-refill-settings' );
		const autoRefillThreshold = autoRefillModal.querySelector( '#auto-refill-threshold' );
		const autoRefillAmount = autoRefillModal.querySelector( '#auto-refill-amount' );
		const autoRefillPreview = autoRefillModal.querySelector( '#auto-refill-credits-preview' );
		const saveAutoRefillBtn = autoRefillModal.querySelector( '#save-auto-refill-btn' );

		function openAutoRefillModal() {
			autoRefillModal.style.display = 'flex';
			document.body.style.overflow = 'hidden';
			updateAutoRefillSettingsVisibility();
		}

		function closeAutoRefillModal() {
			autoRefillModal.style.display = 'none';
			document.body.style.overflow = '';
		}

		function updateAutoRefillSettingsVisibility() {
			autoRefillSettings.style.opacity = autoRefillEnabled.checked ? '1' : '0.5';
			autoRefillSettings.style.pointerEvents = autoRefillEnabled.checked ? 'auto' : 'none';
		}

		function updateAutoRefillPreview() {
			const amount = parseInt( autoRefillAmount.value, 10 ) || 0;
			const credits = amount * 100;
			autoRefillPreview.textContent = credits.toLocaleString();
		}

		autoRefillBtn.addEventListener( 'click', openAutoRefillModal );
		autoRefillClose.addEventListener( 'click', closeAutoRefillModal );
		autoRefillBackdrop.addEventListener( 'click', closeAutoRefillModal );
		autoRefillEnabled.addEventListener( 'change', updateAutoRefillSettingsVisibility );
		autoRefillAmount.addEventListener( 'input', updateAutoRefillPreview );

		saveAutoRefillBtn.addEventListener( 'click', function () {
			const enabled = autoRefillEnabled.checked;
			const threshold = parseInt( autoRefillThreshold.value, 10 ) || 100;
			const amountDollars = parseInt( autoRefillAmount.value, 10 ) || 10;
			const amountCredits = amountDollars * 100;

			if ( enabled && amountDollars < 10 ) {
				alert( 'Minimum refill amount is $10.' );
				return;
			}

			saveAutoRefillBtn.disabled = true;
			saveAutoRefillBtn.textContent = 'Saving...';

			apiFetch( {
				path: '/spawn/v1/credits/auto-refill',
				method: 'POST',
				data: {
					enabled: enabled,
					threshold: threshold,
					amount: amountCredits,
				},
			} )
				.then( function () {
					// Update button text.
					autoRefillBtn.textContent = enabled ? '✓ Auto-Refill On' : 'Auto-Refill';
					closeAutoRefillModal();
				} )
				.catch( function ( error ) {
					alert( error.message || 'Failed to save settings.' );
				} )
				.finally( function () {
					saveAutoRefillBtn.disabled = false;
					saveAutoRefillBtn.textContent = 'Save Settings';
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
