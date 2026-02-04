document.addEventListener( 'DOMContentLoaded', function () {
	const blocks = document.querySelectorAll( '.wp-block-spawn-account' );

	const TIERS = {
		starter: { name: 'Starter', price: 29, vps: 'cx22', ai: '1k', aiLimit: 1000 },
		pro: { name: 'Pro', price: 79, vps: 'cx32', ai: '5k', aiLimit: 5000 },
		business: { name: 'Business', price: 199, vps: 'cx42', ai: '20k', aiLimit: 20000 },
	};

	blocks.forEach( function ( block ) {
		block.innerHTML = '<div class="wp-block-spawn-account__loading">Loading account...</div>';

		wp.apiFetch( { path: '/spawn/v1/customer/me' } )
			.then( function ( response ) {
				if ( ! response.success || ! response.customer ) {
					renderNotCustomer( block );
					return;
				}
				renderAccount( block, response.customer );
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
			<div class="wp-block-spawn-account__message">
				<h2>Please Log In</h2>
				<p>You need to be logged in to manage your account.</p>
				<a href="/spawn/login/" class="wp-block-spawn-account__btn">Log In</a>
			</div>
		`;
	}

	function renderNotCustomer( block ) {
		block.innerHTML = `
			<div class="wp-block-spawn-account__message">
				<h2>No Active Subscription</h2>
				<p>You don't have an active Spawn subscription.</p>
				<a href="/spawn/" class="wp-block-spawn-account__btn">Get Started</a>
			</div>
		`;
	}

	function renderError( block, message ) {
		block.innerHTML = `
			<div class="wp-block-spawn-account__error">
				<h2>Error</h2>
				<p>${ message || 'An error occurred.' }</p>
			</div>
		`;
	}

	function getCurrentTier( vpsTier ) {
		for ( const [ key, tier ] of Object.entries( TIERS ) ) {
			if ( tier.vps === vpsTier ) {
				return { key, ...tier };
			}
		}
		return { key: 'starter', ...TIERS.starter };
	}

	function renderAccount( block, customer ) {
		const currentTier = getCurrentTier( customer.vps_tier );

		block.innerHTML = `
			<div class="wp-block-spawn-account__container">
				<h2>Account Settings</h2>
				
				<div class="wp-block-spawn-account__section">
					<h3>Current Plan</h3>
					<div class="current-plan">
						<span class="plan-name">${ currentTier.name }</span>
						<span class="plan-price">$${ currentTier.price }/mo</span>
					</div>
					<p class="plan-details">
						${ currentTier.aiLimit.toLocaleString() } AI calls/month
					</p>
				</div>
				
				<div class="wp-block-spawn-account__section">
					<h3>Change Plan</h3>
					<div class="tier-options">
						${ Object.entries( TIERS ).map( ( [ key, tier ] ) => `
							<div class="tier-option ${ key === currentTier.key ? 'tier-current' : '' }" data-tier="${ key }">
								<div class="tier-header">
									<span class="tier-name">${ tier.name }</span>
									<span class="tier-price">$${ tier.price }/mo</span>
								</div>
								<ul class="tier-features">
									<li>${ tier.aiLimit.toLocaleString() } AI calls/month</li>
								</ul>
								${ key === currentTier.key 
									? '<span class="tier-badge">Current Plan</span>'
									: `<button type="button" class="tier-select-btn" data-tier="${ key }">
										${ tier.price > currentTier.price ? 'Upgrade' : 'Downgrade' }
									</button>`
								}
							</div>
						` ).join( '' ) }
					</div>
				</div>
				
				<div class="wp-block-spawn-account__section">
					<h3>Billing</h3>
					<div class="billing-actions">
						<button type="button" class="wp-block-spawn-account__btn btn-invoices">
							View Invoices
						</button>
						<button type="button" class="wp-block-spawn-account__btn btn-billing">
							Billing Portal
						</button>
					</div>
				</div>
				
				<div class="wp-block-spawn-account__section section-danger">
					<h3>Cancel Subscription</h3>
					<p>If you cancel, your site will remain active until the end of your billing period.</p>
					<button type="button" class="wp-block-spawn-account__btn btn-cancel">
						Cancel Subscription
					</button>
				</div>
				
				<div class="wp-block-spawn-account__status" style="display: none;"></div>
			</div>
		`;

		const statusDiv = block.querySelector( '.wp-block-spawn-account__status' );

		// Tier change buttons
		block.querySelectorAll( '.tier-select-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				const newTier = this.dataset.tier;
				const tierInfo = TIERS[ newTier ];
				
				if ( ! confirm( `Are you sure you want to change to the ${ tierInfo.name } plan ($${ tierInfo.price }/mo)?` ) ) {
					return;
				}

				this.disabled = true;
				this.textContent = 'Processing...';
				showStatus( statusDiv, 'Processing plan change...', 'info' );

				wp.apiFetch( {
					path: '/spawn/v1/customer/upgrade',
					method: 'POST',
					data: { tier: newTier },
				} )
					.then( function ( response ) {
						showStatus( statusDiv, 'Plan updated successfully! Reloading...', 'success' );
						setTimeout( () => window.location.reload(), 1500 );
					} )
					.catch( function ( error ) {
						showStatus( statusDiv, error.message || 'Failed to update plan.', 'error' );
						btn.disabled = false;
						btn.textContent = tierInfo.price > currentTier.price ? 'Upgrade' : 'Downgrade';
					} );
			} );
		} );

		// Invoices button
		block.querySelector( '.btn-invoices' ).addEventListener( 'click', function () {
			this.disabled = true;
			this.textContent = 'Loading...';

			wp.apiFetch( { path: '/spawn/v1/customer/invoices' } )
				.then( function ( response ) {
					if ( response.invoices && response.invoices.length > 0 ) {
						renderInvoices( block, response.invoices );
					} else {
						showStatus( statusDiv, 'No invoices found.', 'info' );
					}
					this.disabled = false;
					this.textContent = 'View Invoices';
				}.bind( this ) )
				.catch( function ( error ) {
					showStatus( statusDiv, error.message || 'Failed to load invoices.', 'error' );
					this.disabled = false;
					this.textContent = 'View Invoices';
				}.bind( this ) );
		} );

		// Billing portal button
		block.querySelector( '.btn-billing' ).addEventListener( 'click', function () {
			this.disabled = true;
			this.textContent = 'Loading...';

			wp.apiFetch( { path: '/spawn/v1/customer/billing-portal' } )
				.then( function ( response ) {
					if ( response.url ) {
						window.location.href = response.url;
					}
				} )
				.catch( function ( error ) {
					showStatus( statusDiv, error.message || 'Failed to open billing portal.', 'error' );
					this.disabled = false;
					this.textContent = 'Billing Portal';
				}.bind( this ) );
		} );

		// Cancel button
		block.querySelector( '.btn-cancel' ).addEventListener( 'click', function () {
			if ( ! confirm( 'Are you sure you want to cancel your subscription? Your site will remain active until the end of your billing period.' ) ) {
				return;
			}

			this.disabled = true;
			this.textContent = 'Cancelling...';
			showStatus( statusDiv, 'Processing cancellation...', 'info' );

			wp.apiFetch( {
				path: '/spawn/v1/customer/cancel',
				method: 'POST',
			} )
				.then( function ( response ) {
					showStatus( statusDiv, 'Subscription cancelled. Your site will remain active until the end of your billing period.', 'success' );
					this.disabled = true;
					this.textContent = 'Cancelled';
				}.bind( this ) )
				.catch( function ( error ) {
					showStatus( statusDiv, error.message || 'Failed to cancel subscription.', 'error' );
					this.disabled = false;
					this.textContent = 'Cancel Subscription';
				}.bind( this ) );
		} );
	}

	function showStatus( element, message, type ) {
		element.className = 'wp-block-spawn-account__status status-' + type;
		element.textContent = message;
		element.style.display = 'block';
	}

	function renderInvoices( block, invoices ) {
		const modal = document.createElement( 'div' );
		modal.className = 'wp-block-spawn-account__modal';
		modal.innerHTML = `
			<div class="modal-content">
				<h3>Invoices</h3>
				<table class="invoices-table">
					<thead>
						<tr>
							<th>Date</th>
							<th>Amount</th>
							<th>Status</th>
							<th>PDF</th>
						</tr>
					</thead>
					<tbody>
						${ invoices.map( inv => `
							<tr>
								<td>${ new Date( inv.created * 1000 ).toLocaleDateString() }</td>
								<td>$${ ( inv.amount_paid / 100 ).toFixed( 2 ) }</td>
								<td><span class="invoice-status status-${ inv.status }">${ inv.status }</span></td>
								<td><a href="${ inv.invoice_pdf }" target="_blank">Download</a></td>
							</tr>
						` ).join( '' ) }
					</tbody>
				</table>
				<button type="button" class="modal-close">Close</button>
			</div>
		`;

		block.appendChild( modal );
		modal.querySelector( '.modal-close' ).addEventListener( 'click', function () {
			modal.remove();
		} );
	}
} );
