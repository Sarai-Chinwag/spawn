/**
 * Account block view script.
 *
 * @package Spawn
 */

import apiFetch from '@wordpress/api-fetch';
import {
	showStatus,
	setButtonLoading,
	getErrorMessage,
	type ApiError,
	type Invoice,
	type CreditBalanceResponse,
	type StatusType,
} from '../shared';

// Account-specific types
interface AccountTier {
	name: string;
	price: number;
	vps: string;
	aiLimit: number;
}

interface Customer {
	server_type: string;
	tier?: string;
	billing_mode?: 'managed' | 'byok';
}

interface CustomerResponse {
	success: boolean;
	customer?: Customer;
}

interface PurchaseResponse {
	checkout_url?: string;
}

interface InvoicesResponse {
	invoices?: Invoice[];
}

interface BillingPortalResponse {
	url?: string;
}

// Tier configuration
const TIERS: Record< string, AccountTier > = {
	starter: { name: 'Starter', price: 29, vps: 'cx22', aiLimit: 1000 },
	pro: { name: 'Pro', price: 79, vps: 'cx32', aiLimit: 5000 },
	business: { name: 'Business', price: 199, vps: 'cx42', aiLimit: 20000 },
};

// API endpoints
const API = {
	customer: '/spawn/v1/customer/me',
	upgrade: '/spawn/v1/customer/upgrade',
	cancel: '/spawn/v1/customer/cancel',
	invoices: '/spawn/v1/customer/invoices',
	billingPortal: '/spawn/v1/customer/billing-portal',
	credits: '/spawn/v1/credits/balance',
	purchaseCredits: '/spawn/v1/credits/purchase',
	autoRefill: '/spawn/v1/account/auto-refill',
};

/**
 * Initialize account blocks.
 */
function init(): void {
	document.querySelectorAll< HTMLElement >( '.wp-block-spawn-account' ).forEach( initBlock );
}

/**
 * Initialize a single account block.
 */
function initBlock( block: HTMLElement ): void {
	block.innerHTML = '<div class="wp-block-spawn-account__loading">Loading account...</div>';

	apiFetch< CustomerResponse >( { path: API.customer } )
		.then( ( response ) => {
			if ( ! response.success || ! response.customer ) {
				renderMessage( block, 'No Active Subscription', "You don't have an active Spawn subscription.", '/spawn/', 'Get Started' );
				return;
			}
			renderAccount( block, response.customer );
		} )
		.catch( ( error: ApiError ) => {
			if ( error.code === 'rest_not_logged_in' ) {
				renderMessage( block, 'Please Log In', 'You need to be logged in to manage your account.', '/spawn/login/', 'Log In' );
			} else {
				renderError( block, getErrorMessage( error ) );
			}
		} );
}

/**
 * Render a simple message with CTA.
 */
function renderMessage( block: HTMLElement, title: string, message: string, href: string, btnText: string ): void {
	block.innerHTML = `
		<div class="wp-block-spawn-account__message">
			<h2>${ title }</h2>
			<p>${ message }</p>
			<a href="${ href }" class="wp-block-spawn-account__btn">${ btnText }</a>
		</div>
	`;
}

/**
 * Render an error message.
 */
function renderError( block: HTMLElement, message: string ): void {
	block.innerHTML = `
		<div class="wp-block-spawn-account__error">
			<h2>Error</h2>
			<p>${ message }</p>
		</div>
	`;
}

/**
 * Get tier info by VPS type.
 */
function getTierByVps( vpsTier: string ): AccountTier & { key: string } {
	for ( const [ key, tier ] of Object.entries( TIERS ) ) {
		if ( tier.vps === vpsTier ) {
			return { key, ...tier };
		}
	}
	return { key: 'starter', ...TIERS.starter };
}

/**
 * Render the full account UI.
 */
function renderAccount( block: HTMLElement, customer: Customer ): void {
	const currentTier = getTierByVps( customer.server_type );
	const isByok = customer.billing_mode === 'byok';

	block.innerHTML = `
		<div class="wp-block-spawn-account__container">
			<h2>Account Settings</h2>
			
			<div class="wp-block-spawn-account__section">
				<h3>Current Plan</h3>
				<div class="current-plan">
					<span class="plan-name">${ currentTier.name }</span>
					<span class="plan-price">$${ currentTier.price }/mo</span>
				</div>
				<p class="plan-details">${ currentTier.aiLimit.toLocaleString() } AI calls/month</p>
			</div>

			${ isByok ? renderByokSection() : renderCreditsSection() }
			${ renderTierOptions( currentTier ) }
			${ renderBillingSection() }
			${ renderCancelSection() }
			
			<div class="wp-block-spawn-account__status" style="display: none;"></div>
		</div>
	`;

	const statusEl = block.querySelector< HTMLElement >( '.wp-block-spawn-account__status' )!;
	
	// Set up event handlers
	setupTierChangeHandlers( block, statusEl, currentTier );
	setupBillingHandlers( block, statusEl );
	setupCancelHandler( block, statusEl );
	
	if ( ! isByok ) {
		setupCreditsHandlers( block, statusEl );
		loadCreditsData( block, statusEl );
	}
}

/**
 * Render BYOK section.
 */
function renderByokSection(): string {
	return `
		<div class="wp-block-spawn-account__section section-byok">
			<h3>Bring Your Own Key</h3>
			<p>You're using your own API key. Usage is billed directly by your AI provider.</p>
			<p class="byok-hint">Ask your AI to switch to managed credits if you'd like us to handle billing.</p>
		</div>
	`;
}

/**
 * Render credits section.
 */
function renderCreditsSection(): string {
	return `
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
					<button type="button" class="wp-block-spawn-account__btn btn-purchase">Buy Credits</button>
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
					<button type="button" class="wp-block-spawn-account__btn btn-save-auto-refill">Save Auto-Refill Settings</button>
				</div>
			</div>
		</div>
	`;
}

/**
 * Render tier options.
 */
function renderTierOptions( currentTier: AccountTier & { key: string } ): string {
	const options = Object.entries( TIERS ).map( ( [ key, tier ] ) => {
		const isCurrent = key === currentTier.key;
		const action = isCurrent
			? '<span class="tier-badge">Current Plan</span>'
			: `<button type="button" class="tier-select-btn" data-tier="${ key }">${ tier.price > currentTier.price ? 'Upgrade' : 'Downgrade' }</button>`;
		
		return `
			<div class="tier-option ${ isCurrent ? 'tier-current' : '' }" data-tier="${ key }">
				<div class="tier-header">
					<span class="tier-name">${ tier.name }</span>
					<span class="tier-price">$${ tier.price }/mo</span>
				</div>
				<ul class="tier-features">
					<li>${ tier.aiLimit.toLocaleString() } AI calls/month</li>
				</ul>
				${ action }
			</div>
		`;
	} ).join( '' );

	return `
		<div class="wp-block-spawn-account__section">
			<h3>Change Plan</h3>
			<div class="tier-options">${ options }</div>
		</div>
	`;
}

/**
 * Render billing section.
 */
function renderBillingSection(): string {
	return `
		<div class="wp-block-spawn-account__section">
			<h3>Billing</h3>
			<div class="billing-actions">
				<button type="button" class="wp-block-spawn-account__btn btn-invoices">View Invoices</button>
				<button type="button" class="wp-block-spawn-account__btn btn-billing">Billing Portal</button>
			</div>
		</div>
	`;
}

/**
 * Render cancel section.
 */
function renderCancelSection(): string {
	return `
		<div class="wp-block-spawn-account__section section-danger">
			<h3>Cancel Subscription</h3>
			<p>If you cancel, your site will remain active until the end of your billing period.</p>
			<button type="button" class="wp-block-spawn-account__btn btn-cancel">Cancel Subscription</button>
		</div>
	`;
}

/**
 * Set up tier change handlers.
 */
function setupTierChangeHandlers( block: HTMLElement, statusEl: HTMLElement, currentTier: AccountTier & { key: string } ): void {
	block.querySelectorAll< HTMLButtonElement >( '.tier-select-btn' ).forEach( ( btn ) => {
		btn.addEventListener( 'click', () => {
			const newTier = btn.dataset.tier;
			if ( ! newTier ) return;

			const tierInfo = TIERS[ newTier ];
			if ( ! confirm( `Are you sure you want to change to the ${ tierInfo.name } plan ($${ tierInfo.price }/mo)?` ) ) {
				return;
			}

			setButtonLoading( btn, true, 'Processing...' );
			showStatus( statusEl, 'Processing plan change...', 'info' );

			apiFetch( { path: API.upgrade, method: 'POST', data: { tier: newTier } } )
				.then( () => {
					showStatus( statusEl, 'Plan updated successfully! Reloading...', 'success' );
					setTimeout( () => window.location.reload(), 1500 );
				} )
				.catch( ( error: ApiError ) => {
					showStatus( statusEl, getErrorMessage( error, 'Failed to update plan.' ), 'error' );
					setButtonLoading( btn, false, '', tierInfo.price > currentTier.price ? 'Upgrade' : 'Downgrade' );
				} );
		} );
	} );
}

/**
 * Set up billing handlers.
 */
function setupBillingHandlers( block: HTMLElement, statusEl: HTMLElement ): void {
	const invoicesBtn = block.querySelector< HTMLButtonElement >( '.btn-invoices' )!;
	const billingBtn = block.querySelector< HTMLButtonElement >( '.btn-billing' )!;

	invoicesBtn.addEventListener( 'click', () => {
		setButtonLoading( invoicesBtn, true, 'Loading...' );

		apiFetch< InvoicesResponse >( { path: API.invoices } )
			.then( ( response ) => {
				if ( response.invoices?.length ) {
					renderInvoicesModal( block, response.invoices );
				} else {
					showStatus( statusEl, 'No invoices found.', 'info' );
				}
			} )
			.catch( ( error: ApiError ) => {
				showStatus( statusEl, getErrorMessage( error, 'Failed to load invoices.' ), 'error' );
			} )
			.finally( () => {
				setButtonLoading( invoicesBtn, false, '', 'View Invoices' );
			} );
	} );

	billingBtn.addEventListener( 'click', () => {
		setButtonLoading( billingBtn, true, 'Loading...' );

		apiFetch< BillingPortalResponse >( { path: API.billingPortal } )
			.then( ( response ) => {
				if ( response.url ) {
					window.location.href = response.url;
				}
			} )
			.catch( ( error: ApiError ) => {
				showStatus( statusEl, getErrorMessage( error, 'Failed to open billing portal.' ), 'error' );
				setButtonLoading( billingBtn, false, '', 'Billing Portal' );
			} );
	} );
}

/**
 * Set up cancel handler.
 */
function setupCancelHandler( block: HTMLElement, statusEl: HTMLElement ): void {
	const cancelBtn = block.querySelector< HTMLButtonElement >( '.btn-cancel' )!;

	cancelBtn.addEventListener( 'click', () => {
		if ( ! confirm( 'Are you sure you want to cancel your subscription? Your site will remain active until the end of your billing period.' ) ) {
			return;
		}

		setButtonLoading( cancelBtn, true, 'Cancelling...' );
		showStatus( statusEl, 'Processing cancellation...', 'info' );

		apiFetch( { path: API.cancel, method: 'POST' } )
			.then( () => {
				showStatus( statusEl, 'Subscription cancelled. Your site will remain active until the end of your billing period.', 'success' );
				cancelBtn.disabled = true;
				cancelBtn.textContent = 'Cancelled';
			} )
			.catch( ( error: ApiError ) => {
				showStatus( statusEl, getErrorMessage( error, 'Failed to cancel subscription.' ), 'error' );
				setButtonLoading( cancelBtn, false, '', 'Cancel Subscription' );
			} );
	} );
}

/**
 * Set up credits handlers.
 */
function setupCreditsHandlers( block: HTMLElement, statusEl: HTMLElement ): void {
	const purchaseBtn = block.querySelector< HTMLButtonElement >( '.btn-purchase' )!;
	const creditsInput = block.querySelector< HTMLInputElement >( '#credits-amount' )!;
	const autoRefillCheckbox = block.querySelector< HTMLInputElement >( '#auto-refill-enabled' )!;
	const autoRefillSettings = block.querySelector< HTMLElement >( '.auto-refill-settings' )!;
	const saveAutoRefillBtn = block.querySelector< HTMLButtonElement >( '.btn-save-auto-refill' )!;
	const thresholdInput = block.querySelector< HTMLInputElement >( '#auto-refill-threshold' )!;
	const amountInput = block.querySelector< HTMLInputElement >( '#auto-refill-amount' )!;

	// Purchase credits
	purchaseBtn.addEventListener( 'click', () => {
		const amount = parseInt( creditsInput.value, 10 );
		if ( amount < 10 ) {
			showStatus( statusEl, 'Minimum purchase is $10.', 'error' );
			return;
		}

		setButtonLoading( purchaseBtn, true, 'Processing...' );

		apiFetch< PurchaseResponse >( { path: API.purchaseCredits, method: 'POST', data: { amount } } )
			.then( ( response ) => {
				if ( response.checkout_url ) {
					window.location.href = response.checkout_url;
				}
			} )
			.catch( ( error: ApiError ) => {
				showStatus( statusEl, getErrorMessage( error, 'Failed to create checkout.' ), 'error' );
				setButtonLoading( purchaseBtn, false, '', 'Buy Credits' );
			} );
	} );

	// Auto-refill toggle
	autoRefillCheckbox.addEventListener( 'change', () => {
		autoRefillSettings.style.display = autoRefillCheckbox.checked ? 'block' : 'none';
		
		if ( ! autoRefillCheckbox.checked ) {
			apiFetch( { path: API.autoRefill, method: 'POST', data: { enabled: false, threshold: 5, amount: 20 } } )
				.then( () => showStatus( statusEl, 'Auto-refill disabled.', 'success' ) )
				.catch( ( error: ApiError ) => showStatus( statusEl, getErrorMessage( error, 'Failed to update auto-refill.' ), 'error' ) );
		}
	} );

	// Save auto-refill settings
	saveAutoRefillBtn.addEventListener( 'click', () => {
		const threshold = parseFloat( thresholdInput.value );
		const amount = parseFloat( amountInput.value );

		if ( threshold < 1 || threshold > 100 ) {
			showStatus( statusEl, 'Threshold must be between $1 and $100.', 'error' );
			return;
		}
		if ( amount < 10 || amount > 100 ) {
			showStatus( statusEl, 'Refill amount must be between $10 and $100.', 'error' );
			return;
		}

		setButtonLoading( saveAutoRefillBtn, true, 'Saving...' );

		apiFetch( { path: API.autoRefill, method: 'POST', data: { enabled: true, threshold, amount } } )
			.then( () => {
				showStatus( statusEl, 'Auto-refill settings saved!', 'success' );
				setButtonLoading( saveAutoRefillBtn, false, '', 'Save Auto-Refill Settings' );
			} )
			.catch( ( error: ApiError ) => {
				showStatus( statusEl, getErrorMessage( error, 'Failed to save settings.' ), 'error' );
				setButtonLoading( saveAutoRefillBtn, false, '', 'Save Auto-Refill Settings' );
			} );
	} );
}

/**
 * Load credits data.
 */
function loadCreditsData( block: HTMLElement, statusEl: HTMLElement ): void {
	const balanceEl = block.querySelector< HTMLElement >( '.credits-amount' );
	const autoRefillCheckbox = block.querySelector< HTMLInputElement >( '#auto-refill-enabled' );
	const autoRefillSettings = block.querySelector< HTMLElement >( '.auto-refill-settings' );
	const thresholdInput = block.querySelector< HTMLInputElement >( '#auto-refill-threshold' );
	const amountInput = block.querySelector< HTMLInputElement >( '#auto-refill-amount' );

	if ( ! balanceEl ) return;

	apiFetch< CreditBalanceResponse >( { path: API.credits } )
		.then( ( response ) => {
			balanceEl.textContent = '$' + response.balance.toFixed( 2 );

			if ( response.auto_refill && autoRefillCheckbox && autoRefillSettings && thresholdInput && amountInput ) {
				autoRefillCheckbox.checked = response.auto_refill.enabled;
				thresholdInput.value = response.auto_refill.threshold.toString();
				amountInput.value = response.auto_refill.amount.toString();
				autoRefillSettings.style.display = response.auto_refill.enabled ? 'block' : 'none';
			}
		} )
		.catch( ( error: ApiError ) => {
			balanceEl.textContent = 'Error';
			showStatus( statusEl, getErrorMessage( error, 'Failed to load credits.' ), 'error' );
		} );
}

/**
 * Render invoices modal.
 */
function renderInvoicesModal( block: HTMLElement, invoices: Invoice[] ): void {
	const modal = document.createElement( 'div' );
	modal.className = 'wp-block-spawn-account__modal';
	modal.innerHTML = `
		<div class="modal-content">
			<h3>Invoices</h3>
			<table class="invoices-table">
				<thead>
					<tr><th>Date</th><th>Amount</th><th>Status</th><th>PDF</th></tr>
				</thead>
				<tbody>
					${ invoices.map( ( inv ) => `
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
	modal.querySelector< HTMLButtonElement >( '.modal-close' )!.addEventListener( 'click', () => modal.remove() );
}

// Initialize on DOM ready
document.addEventListener( 'DOMContentLoaded', init );
