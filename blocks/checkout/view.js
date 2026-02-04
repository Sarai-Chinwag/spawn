document.addEventListener( 'DOMContentLoaded', function () {
	const blocks = document.querySelectorAll( '.wp-block-spawn-checkout' );

	blocks.forEach( function ( block ) {
		let selectedDomain = null;
		let selectedTier = null;
		let orderSummary = null;

		const container = document.createElement( 'div' );
		container.className = 'wp-block-spawn-checkout__container';
		block.appendChild( container );

		function updateDisplay() {
			container.innerHTML = '';

			if ( ! selectedDomain || ! selectedTier ) {
				const waiting = document.createElement( 'p' );
				waiting.className = 'wp-block-spawn-checkout__waiting';
				waiting.textContent =
					'Please select a domain and tier to proceed with checkout.';
				container.appendChild( waiting );
				return;
			}

			if ( block.dataset.showOrderSummary !== 'false' ) {
				orderSummary = document.createElement( 'div' );
				orderSummary.className = 'wp-block-spawn-checkout__summary';
				orderSummary.innerHTML = `
					<h4>Order Summary</h4>
					<p>Domain: ${ selectedDomain.domain }</p>
					<p>Tier: ${ selectedTier.name } - $${ selectedTier.price }</p>
					<p>Total: $${ selectedTier.price }</p>
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

				wp.apiFetch( {
					path: '/spawn/v1/checkout/session',
					method: 'POST',
					data: {
						domain: selectedDomain.domain,
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
