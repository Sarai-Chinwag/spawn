import apiFetch from '@wordpress/api-fetch';

interface TierSelection {
	id: string;
	name: string;
	price: number;
}

interface CheckoutSessionResponse {
	url?: string;
}

interface TierSelectedEvent extends CustomEvent {
	detail: TierSelection;
}

document.addEventListener( 'DOMContentLoaded', function (): void {
	const blocks = document.querySelectorAll< HTMLElement >( '.wp-block-spawn-checkout' );

	blocks.forEach( function ( block: HTMLElement ): void {
		let selectedTier: TierSelection | null = null;
		let wantsWebsite = false;

		block.innerHTML = '';

		const container = document.createElement( 'div' );
		container.className = 'wp-block-spawn-checkout__container';
		block.appendChild( container );

		function updateDisplay(): void {
			container.innerHTML = '';

			if ( ! selectedTier ) {
				const waiting = document.createElement( 'p' );
				waiting.className = 'wp-block-spawn-checkout__waiting';
				waiting.textContent = 'Please select a plan above to proceed.';
				container.appendChild( waiting );
				return;
			}

			if ( block.dataset.showOrderSummary !== 'false' ) {
				const orderSummary = document.createElement( 'div' );
				orderSummary.className = 'wp-block-spawn-checkout__summary';
				orderSummary.innerHTML = `
					<h4>Order Summary</h4>
					<div class="summary-line"><span>Plan:</span> <span>${ selectedTier.name } - $${ selectedTier.price }/mo</span></div>
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

			const websiteToggle = document.createElement( 'div' );
			websiteToggle.className = 'wp-block-spawn-checkout__website-toggle';

			const websiteLabel = document.createElement( 'label' );
			websiteLabel.className = 'wp-block-spawn-checkout__website-label';

			const websiteCheckbox = document.createElement( 'input' );
			websiteCheckbox.type = 'checkbox';
			websiteCheckbox.checked = wantsWebsite;
			websiteCheckbox.className = 'wp-block-spawn-checkout__website-checkbox';
			websiteCheckbox.addEventListener( 'change', function (): void {
				wantsWebsite = websiteCheckbox.checked;
			} );

			const websiteLabelText = document.createElement( 'span' );
			websiteLabelText.textContent = 'Include a free website';

			websiteLabel.appendChild( websiteCheckbox );
			websiteLabel.appendChild( websiteLabelText );

			const websiteHelp = document.createElement( 'p' );
			websiteHelp.className = 'wp-block-spawn-checkout__website-help';
			websiteHelp.textContent = 'Your AI can build and manage a website for you.';

			websiteToggle.appendChild( websiteLabel );
			websiteToggle.appendChild( websiteHelp );

			const checkoutButton = document.createElement( 'button' );
			checkoutButton.type = 'submit';
			checkoutButton.textContent = block.dataset.buttonText || 'Get Started';
			checkoutButton.className = 'wp-block-spawn-checkout__button';

			form.appendChild( emailInput );
			form.appendChild( websiteToggle );
			form.appendChild( checkoutButton );
			container.appendChild( form );

			form.addEventListener( 'submit', function ( e: Event ): void {
				e.preventDefault();
				const email = emailInput.value.trim();
				if ( ! email || ! selectedTier ) {
					return;
				}

				checkoutButton.disabled = true;
				checkoutButton.textContent = 'Processing...';

				apiFetch< CheckoutSessionResponse >( {
					path: '/spawn/v1/checkout/session',
					method: 'POST',
					data: {
						tier: selectedTier.id,
						wants_website: wantsWebsite,
						email,
					},
				} )
					.then( function ( response: CheckoutSessionResponse ): void {
						if ( response.url ) {
							window.location.href = response.url;
						} else {
							throw new Error( 'No checkout URL received' );
						}
					} )
					.catch( function (): void {
						checkoutButton.disabled = false;
						checkoutButton.textContent = block.dataset.buttonText || 'Get Started';
					} );
			} );
		}

		document.addEventListener( 'spawn:tier-selected', function ( e: Event ): void {
			selectedTier = ( e as TierSelectedEvent ).detail;
			updateDisplay();
		} );

		updateDisplay();
	} );
} );
