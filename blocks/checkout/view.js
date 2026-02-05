import apiFetch from '@wordpress/api-fetch';

document.addEventListener( 'DOMContentLoaded', function () {
	const blocks = document.querySelectorAll( '.wp-block-spawn-checkout' );

	blocks.forEach( function ( block ) {
		let selectedDomain = null;
		let selectedTier = null;
		let orderSummary = null;

		// Clear loading state
		block.innerHTML = '';

		const container = document.createElement( 'div' );
		container.className = 'wp-block-spawn-checkout__container';
		block.appendChild( container );

		function updateDisplay() {
			container.innerHTML = '';

			if ( ! selectedTier ) {
				const waiting = document.createElement( 'p' );
				waiting.className = 'wp-block-spawn-checkout__waiting';
				waiting.textContent =
					'Please select a plan above to proceed.';
				container.appendChild( waiting );
				return;
			}

			if ( block.dataset.showOrderSummary !== 'false' ) {
				orderSummary = document.createElement( 'div' );
				orderSummary.className = 'wp-block-spawn-checkout__summary';

				let domainText = 'Free subdomain (chosen after signup)';
				let domainPriceText = '';
				if ( selectedDomain ) {
					domainText = selectedDomain.domain;
					if (
						selectedDomain.type === 'register' &&
						selectedDomain.price > 0
					) {
						domainPriceText = ` - $${ selectedDomain.price }/year`;
					} else if ( selectedDomain.type === 'byod' ) {
						domainPriceText = ' (bring your own)';
					}
				}

				const vpsPrice = parseFloat( selectedTier.price );
				const domainPrice =
					selectedDomain?.type === 'register'
						? parseFloat( selectedDomain.price )
						: 0;
				const firstPayment = vpsPrice + domainPrice;

				let summaryHtml = `
					<h4>Order Summary</h4>
					<div class="summary-line"><span>Domain:</span> <span>${ domainText }${ domainPriceText }</span></div>
					<div class="summary-line"><span>Plan:</span> <span>${ selectedTier.name } - $${ selectedTier.price }/mo</span></div>
				`;

				if ( domainPrice > 0 ) {
					summaryHtml += `
					<div class="summary-divider"></div>
					<div class="summary-line summary-total"><span>First payment:</span> <span>$${ firstPayment.toFixed( 2 ) }</span></div>
					<div class="summary-note">Includes one-time domain registration. Renewals are $${ domainPrice }/year.</div>
					`;
				}

				orderSummary.innerHTML = summaryHtml;
				container.appendChild( orderSummary );
			}

			const form = document.createElement( 'form' );
			form.className = 'wp-block-spawn-checkout__form';

			const emailInput = document.createElement( 'input' );
			emailInput.type = 'email';
			emailInput.placeholder = 'Enter your email address';
			emailInput.className = 'wp-block-spawn-checkout__input';
			emailInput.required = true;

			const checkoutButton = document.createElement( 'button' );
			checkoutButton.type = 'submit';
			checkoutButton.textContent =
				block.dataset.buttonText || 'Get Started';
			checkoutButton.className = 'wp-block-spawn-checkout__button';

			form.appendChild( emailInput );
			form.appendChild( checkoutButton );
			container.appendChild( form );

			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				const email = emailInput.value.trim();
				if ( ! email ) {
					return;
				}

				checkoutButton.disabled = true;
				checkoutButton.textContent = 'Processing...';

				apiFetch( {
					path: '/spawn/v1/checkout/session',
					method: 'POST',
					data: {
						domain: selectedDomain ? selectedDomain.domain : null,
						domain_type: selectedDomain
							? selectedDomain.type
							: 'subdomain',
						domain_price:
							selectedDomain?.type === 'register'
								? selectedDomain.price
								: 0,
						tier: selectedTier.id,
						email,
					},
				} )
					.then( function ( response ) {
						if ( response.url ) {
							window.location.href = response.url;
						} else {
							throw new Error( 'No checkout URL received' );
						}
					} )
					.catch( function () {
						checkoutButton.disabled = false;
						checkoutButton.textContent =
							block.dataset.buttonText || 'Get Started';
					} );
			} );
		}

		document.addEventListener( 'spawn:domain-selected', function ( e ) {
			selectedDomain = e.detail;
			updateDisplay();
		} );

		document.addEventListener( 'spawn:tier-selected', function ( e ) {
			selectedTier = e.detail;
			updateDisplay();
		} );

		updateDisplay();
	} );
} );
