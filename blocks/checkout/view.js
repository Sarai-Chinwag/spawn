import apiFetch from '@wordpress/api-fetch';

document.addEventListener( 'DOMContentLoaded', function () {
	const blocks = document.querySelectorAll( '.wp-block-spawn-checkout' );

	blocks.forEach( function ( block ) {
		let selectedTier = null;
		let wantsWebsite = false; // Default to AI-only

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

			// Order summary
			if ( block.dataset.showOrderSummary !== 'false' ) {
				const orderSummary = document.createElement( 'div' );
				orderSummary.className = 'wp-block-spawn-checkout__summary';
				orderSummary.innerHTML = `
					<h4>Order Summary</h4>
					<div class="summary-line"><span>Plan:</span> <span>${ selectedTier.name } - $${ selectedTier.price }/mo</span></div>
					<div class="summary-line summary-note"><span>Includes:</span> <span>$${ selectedTier.credits || '5' } AI credits</span></div>
				`;
				container.appendChild( orderSummary );
			}

			const form = document.createElement( 'form' );
			form.className = 'wp-block-spawn-checkout__form';

			const emailInput = document.createElement( 'input' );
			emailInput.type = 'email';
			emailInput.placeholder = 'Enter your email address';
			emailInput.className = 'wp-block-spawn-checkout__input';
			emailInput.required = true;

			// Website toggle (subtle, unchecked by default)
			const websiteToggle = document.createElement( 'div' );
			websiteToggle.className = 'wp-block-spawn-checkout__website-toggle';

			const websiteLabel = document.createElement( 'label' );
			websiteLabel.className = 'wp-block-spawn-checkout__website-label';

			const websiteCheckbox = document.createElement( 'input' );
			websiteCheckbox.type = 'checkbox';
			websiteCheckbox.checked = wantsWebsite;
			websiteCheckbox.className =
				'wp-block-spawn-checkout__website-checkbox';
			websiteCheckbox.addEventListener( 'change', function () {
				wantsWebsite = websiteCheckbox.checked;
			} );

			const websiteLabelText = document.createElement( 'span' );
			websiteLabelText.textContent = 'Include a free website';

			websiteLabel.appendChild( websiteCheckbox );
			websiteLabel.appendChild( websiteLabelText );

			const websiteHelp = document.createElement( 'p' );
			websiteHelp.className = 'wp-block-spawn-checkout__website-help';
			websiteHelp.textContent =
				'Your AI can build and manage a website for you.';

			websiteToggle.appendChild( websiteLabel );
			websiteToggle.appendChild( websiteHelp );

			const checkoutButton = document.createElement( 'button' );
			checkoutButton.type = 'submit';
			checkoutButton.textContent =
				block.dataset.buttonText || 'Get Started';
			checkoutButton.className = 'wp-block-spawn-checkout__button';

			form.appendChild( emailInput );
			form.appendChild( websiteToggle );
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
						tier: selectedTier.id,
						wants_website: wantsWebsite,
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

		document.addEventListener( 'spawn:tier-selected', function ( e ) {
			selectedTier = e.detail;
			updateDisplay();
		} );

		updateDisplay();
	} );
} );
