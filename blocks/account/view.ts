import apiFetch from '@wordpress/api-fetch';

interface TierInfo {
	name: string;
	price: number;
	vps: string;
	ai: string;
	aiLimit: number;
}

interface TiersMap {
	[ key: string ]: TierInfo;
}

interface Customer {
	vps_tier: string;
	credit_balance?: number;
	tier?: string;
	billing_mode?: string;
	[ key: string ]: unknown;
}

interface CreditBalanceResponse {
	balance: number;
	auto_refill?: {
		enabled: boolean;
		threshold: number;
		amount: number;
	};
}

interface AutoRefillSettings {
	enabled: boolean;
	threshold: number;
	amount: number;
}

interface PurchaseResponse {
	checkout_url?: string;
}

interface CustomerResponse {
	success: boolean;
	customer?: Customer;
}

interface InvoicesResponse {
	invoices?: Invoice[];
}

interface Invoice {
	created: number;
	amount_paid: number;
	status: string;
	invoice_pdf: string;
}

interface BillingPortalResponse {
	url?: string;
}

interface ApiError {
	code?: string;
	message?: string;
}

const TIERS: TiersMap = {
	starter: { name: 'Starter', price: 29, vps: 'cx22', ai: '1k', aiLimit: 1000 },
	pro: { name: 'Pro', price: 79, vps: 'cx32', ai: '5k', aiLimit: 5000 },
	business: { name: 'Business', price: 199, vps: 'cx42', ai: '20k', aiLimit: 20000 },
};

document.addEventListener( 'DOMContentLoaded', function (): void {
	const blocks = document.querySelectorAll< HTMLElement >( '.wp-block-spawn-account' );

	blocks.forEach( function ( block: HTMLElement ): void {
		block.innerHTML = '<div class="wp-block-spawn-account__loading">Loading account...</div>';

		apiFetch< CustomerResponse >( { path: '/spawn/v1/customer/me' } )
			.then( function ( response: CustomerResponse ): void {
				if ( ! response.success || ! response.customer ) {
					renderNotCustomer( block );
					return;
				}
				renderAccount( block, response.customer );
			} )
			.catch( function ( error: ApiError ): void {
				if ( error.code === 'rest_not_logged_in' ) {
					renderNotLoggedIn( block );
				} else {
					renderError( block, error.message );
				}
			} );
	} );

	function renderNotLoggedIn( block: HTMLElement ): void {
		block.innerHTML = `
			<div class="wp-block-spawn-account__message">
				<h2>Please Log In</h2>
				<p>You need to be logged in to manage your account.</p>
				<a href="/spawn/login/" class="wp-block-spawn-account__btn">Log In</a>
			</div>
		`;
	}

	function renderNotCustomer( block: HTMLElement ): void {
		block.innerHTML = `
			<div class="wp-block-spawn-account__message">
				<h2>No Active Subscription</h2>
				<p>You don't have an active Spawn subscription.</p>
				<a href="/spawn/" class="wp-block-spawn-account__btn">Get Started</a>
			</div>
		`;
	}

	function renderError( block: HTMLElement, message?: string ): void {
		block.innerHTML = `
			<div class="wp-block-spawn-account__error">
				<h2>Error</h2>
				<p>${ message || 'An error occurred.' }</p>
			</div>
		`;
	}

	function getCurrentTier( vpsTier: string ): TierInfo & { key: string } {
		for ( const [ key, tier ] of Object.entries( TIERS ) ) {
			if ( tier.vps === vpsTier ) {
				return { key, ...tier };
			}
		}
		return { key: 'starter', ...TIERS.starter };
	}

	function renderAccount( block: HTMLElement, customer: Customer ): void {
		const currentTier = getCurrentTier( customer.vps_tier );
		const isByok = customer.billing_mode === 'byok';

		// Credits section HTML (only for managed billing).
		const creditsSection = isByok ? `
				<div class="wp-block-spawn-account__section section-byok">
					<h3>Bring Your Own Key</h3>
					<p>You're using your own API key. Usage is billed directly by your AI provider.</p>
					<p class="byok-hint">Ask your AI to switch to managed credits if you'd like us to handle billing.</p>
				</div>
		` : `
				<div class="wp-block-spawn-account__section section-credits">
					<h3>Credits</h3>
					<div class="credits-balance">
						<span class="credits-amount">Loading...</span>
						<span class="credits-label">available</span>
					</div>
					<div class="credits-purchase">
						<label for="credits-amount">Add Credits</label>
						<div class="credits-input-row">
							<span class="credits-currency">$</span>
							<input type="number" id="credits-amount" min="10" max="500" step="5" value="20" class="credits-input" />
							<button type="button" class="wp-block-spawn-account__btn btn-purchase">
								Buy Credits
							</button>
						</div>
						<small class="credits-hint">Minimum $10. Credits never expire.</small>
					</div>
					<div class="auto-refill">
						<h4>Auto-Refill</h4>
						<p class="auto-refill-desc">Automatically add credits when your balance gets low.</p>
						<label class="auto-refill-toggle">
							<input type="checkbox" id="auto-refill-enabled" />
							<span>Enable auto-refill</span>
						</label>
						<div class="auto-refill-settings" style="display: none;">
							<div class="auto-refill-row">
								<label for="auto-refill-threshold">When balance falls below</label>
								<div class="auto-refill-input-wrap">
									<span>$</span>
									<input type="number" id="auto-refill-threshold" min="1" max="100" value="5" />
								</div>
							</div>
							<div class="auto-refill-row">
								<label for="auto-refill-amount">Add this amount</label>
								<div class="auto-refill-input-wrap">
									<span>$</span>
									<input type="number" id="auto-refill-amount" min="10" max="100" value="20" />
								</div>
							</div>
							<button type="button" class="wp-block-spawn-account__btn btn-save-auto-refill">
								Save Auto-Refill Settings
							</button>
						</div>
					</div>
				</div>
		`;

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

				${ creditsSection }
				
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

		const statusDiv = block.querySelector< HTMLElement >( '.wp-block-spawn-account__status' )!;

		// Tier change buttons
		block.querySelectorAll< HTMLButtonElement >( '.tier-select-btn' ).forEach( function ( btn: HTMLButtonElement ): void {
			btn.addEventListener( 'click', function ( this: HTMLButtonElement ): void {
				const newTier = this.dataset.tier;
				if ( ! newTier ) return;
				
				const tierInfo = TIERS[ newTier ];
				
				if ( ! confirm( `Are you sure you want to change to the ${ tierInfo.name } plan ($${ tierInfo.price }/mo)?` ) ) {
					return;
				}

				this.disabled = true;
				this.textContent = 'Processing...';
				showStatus( statusDiv, 'Processing plan change...', 'info' );

				apiFetch( {
					path: '/spawn/v1/customer/upgrade',
					method: 'POST',
					data: { tier: newTier },
				} )
					.then( function (): void {
						showStatus( statusDiv, 'Plan updated successfully! Reloading...', 'success' );
						setTimeout( () => window.location.reload(), 1500 );
					} )
					.catch( function ( error: ApiError ): void {
						showStatus( statusDiv, error.message || 'Failed to update plan.', 'error' );
						btn.disabled = false;
						btn.textContent = tierInfo.price > currentTier.price ? 'Upgrade' : 'Downgrade';
					} );
			} );
		} );

		// Credits functionality (only for managed billing mode)
		if ( ! isByok ) {
			// Load credits balance and auto-refill settings
			loadCreditsData( block, statusDiv );

			// Purchase credits button
			const purchaseBtn = block.querySelector< HTMLButtonElement >( '.btn-purchase' )!;
			const creditsInput = block.querySelector< HTMLInputElement >( '#credits-amount' )!;
			purchaseBtn.addEventListener( 'click', function (): void {
				const amount = parseInt( creditsInput.value, 10 );
				if ( amount < 10 ) {
					showStatus( statusDiv, 'Minimum purchase is $10.', 'error' );
					return;
				}

				purchaseBtn.disabled = true;
				purchaseBtn.textContent = 'Processing...';

				apiFetch< PurchaseResponse >( {
					path: '/spawn/v1/credits/purchase',
					method: 'POST',
					data: { amount },
				} )
					.then( function ( response: PurchaseResponse ): void {
						if ( response.checkout_url ) {
							window.location.href = response.checkout_url;
						}
					} )
					.catch( function ( error: ApiError ): void {
						showStatus( statusDiv, error.message || 'Failed to create checkout.', 'error' );
						purchaseBtn.disabled = false;
						purchaseBtn.textContent = 'Buy Credits';
					} );
			} );

			// Auto-refill toggle
			const autoRefillCheckbox = block.querySelector< HTMLInputElement >( '#auto-refill-enabled' )!;
			const autoRefillSettings = block.querySelector< HTMLElement >( '.auto-refill-settings' )!;
			autoRefillCheckbox.addEventListener( 'change', function (): void {
				autoRefillSettings.style.display = this.checked ? 'block' : 'none';
			if ( ! this.checked ) {
				// Disable auto-refill immediately when unchecked
				apiFetch( {
					path: '/spawn/v1/account/auto-refill',
					method: 'POST',
					data: { enabled: false, threshold: 5, amount: 20 },
				} )
					.then( function (): void {
						showStatus( statusDiv, 'Auto-refill disabled.', 'success' );
					} )
					.catch( function ( error: ApiError ): void {
						showStatus( statusDiv, error.message || 'Failed to update auto-refill.', 'error' );
					} );
			}
			} );

			// Save auto-refill settings
			const saveAutoRefillBtn = block.querySelector< HTMLButtonElement >( '.btn-save-auto-refill' )!;
			const thresholdInput = block.querySelector< HTMLInputElement >( '#auto-refill-threshold' )!;
			const amountInput = block.querySelector< HTMLInputElement >( '#auto-refill-amount' )!;
			saveAutoRefillBtn.addEventListener( 'click', function (): void {
				const threshold = parseFloat( thresholdInput.value );
				const amount = parseFloat( amountInput.value );

				if ( threshold < 1 || threshold > 100 ) {
					showStatus( statusDiv, 'Threshold must be between $1 and $100.', 'error' );
					return;
				}
				if ( amount < 10 || amount > 100 ) {
					showStatus( statusDiv, 'Refill amount must be between $10 and $100.', 'error' );
					return;
				}

				saveAutoRefillBtn.disabled = true;
				saveAutoRefillBtn.textContent = 'Saving...';

				apiFetch( {
					path: '/spawn/v1/account/auto-refill',
					method: 'POST',
					data: { enabled: true, threshold, amount },
				} )
					.then( function (): void {
						showStatus( statusDiv, 'Auto-refill settings saved!', 'success' );
						saveAutoRefillBtn.disabled = false;
						saveAutoRefillBtn.textContent = 'Save Auto-Refill Settings';
					} )
					.catch( function ( error: ApiError ): void {
						showStatus( statusDiv, error.message || 'Failed to save settings.', 'error' );
						saveAutoRefillBtn.disabled = false;
						saveAutoRefillBtn.textContent = 'Save Auto-Refill Settings';
					} );
			} );
		} // End of if ( ! isByok )

		// Invoices button
		const invoicesBtn = block.querySelector< HTMLButtonElement >( '.btn-invoices' )!;
		invoicesBtn.addEventListener( 'click', function ( this: HTMLButtonElement ): void {
			this.disabled = true;
			this.textContent = 'Loading...';
			const self = this;

			apiFetch< InvoicesResponse >( { path: '/spawn/v1/customer/invoices' } )
				.then( function ( response: InvoicesResponse ): void {
					if ( response.invoices && response.invoices.length > 0 ) {
						renderInvoices( block, response.invoices );
					} else {
						showStatus( statusDiv, 'No invoices found.', 'info' );
					}
					self.disabled = false;
					self.textContent = 'View Invoices';
				} )
				.catch( function ( error: ApiError ): void {
					showStatus( statusDiv, error.message || 'Failed to load invoices.', 'error' );
					self.disabled = false;
					self.textContent = 'View Invoices';
				} );
		} );

		// Billing portal button
		const billingBtn = block.querySelector< HTMLButtonElement >( '.btn-billing' )!;
		billingBtn.addEventListener( 'click', function ( this: HTMLButtonElement ): void {
			this.disabled = true;
			this.textContent = 'Loading...';
			const self = this;

			apiFetch< BillingPortalResponse >( { path: '/spawn/v1/customer/billing-portal' } )
				.then( function ( response: BillingPortalResponse ): void {
					if ( response.url ) {
						window.location.href = response.url;
					}
				} )
				.catch( function ( error: ApiError ): void {
					showStatus( statusDiv, error.message || 'Failed to open billing portal.', 'error' );
					self.disabled = false;
					self.textContent = 'Billing Portal';
				} );
		} );

		// Cancel button
		const cancelBtn = block.querySelector< HTMLButtonElement >( '.btn-cancel' )!;
		cancelBtn.addEventListener( 'click', function ( this: HTMLButtonElement ): void {
			if ( ! confirm( 'Are you sure you want to cancel your subscription? Your site will remain active until the end of your billing period.' ) ) {
				return;
			}

			this.disabled = true;
			this.textContent = 'Cancelling...';
			showStatus( statusDiv, 'Processing cancellation...', 'info' );
			const self = this;

			apiFetch( {
				path: '/spawn/v1/customer/cancel',
				method: 'POST',
			} )
				.then( function (): void {
					showStatus( statusDiv, 'Subscription cancelled. Your site will remain active until the end of your billing period.', 'success' );
					self.disabled = true;
					self.textContent = 'Cancelled';
				} )
				.catch( function ( error: ApiError ): void {
					showStatus( statusDiv, error.message || 'Failed to cancel subscription.', 'error' );
					self.disabled = false;
					self.textContent = 'Cancel Subscription';
				} );
		} );
	}

	function showStatus( element: HTMLElement, message: string, type: string ): void {
		element.className = 'wp-block-spawn-account__status status-' + type;
		element.textContent = message;
		element.style.display = 'block';
	}

	function renderInvoices( block: HTMLElement, invoices: Invoice[] ): void {
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
						${ invoices.map( ( inv: Invoice ) => `
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
		modal.querySelector< HTMLButtonElement >( '.modal-close' )!.addEventListener( 'click', function (): void {
			modal.remove();
		} );
	}

	function loadCreditsData( block: HTMLElement, statusDiv: HTMLElement ): void {
		const balanceEl = block.querySelector< HTMLElement >( '.credits-amount' );
		const autoRefillCheckbox = block.querySelector< HTMLInputElement >( '#auto-refill-enabled' );
		const autoRefillSettings = block.querySelector< HTMLElement >( '.auto-refill-settings' );
		const thresholdInput = block.querySelector< HTMLInputElement >( '#auto-refill-threshold' );
		const amountInput = block.querySelector< HTMLInputElement >( '#auto-refill-amount' );

		if ( ! balanceEl ) return;

		apiFetch< CreditBalanceResponse >( { path: '/spawn/v1/credits/balance' } )
			.then( function ( response: CreditBalanceResponse ): void {
				balanceEl.textContent = '$' + response.balance.toFixed( 2 );

				if ( response.auto_refill && autoRefillCheckbox && autoRefillSettings && thresholdInput && amountInput ) {
					autoRefillCheckbox.checked = response.auto_refill.enabled;
					thresholdInput.value = response.auto_refill.threshold.toString();
					amountInput.value = response.auto_refill.amount.toString();
					autoRefillSettings.style.display = response.auto_refill.enabled ? 'block' : 'none';
				}
			} )
			.catch( function ( error: ApiError ): void {
				balanceEl.textContent = 'Error';
				showStatus( statusDiv, error.message || 'Failed to load credits.', 'error' );
			} );
	}
} );
