import apiFetch from '@wordpress/api-fetch';

document.addEventListener( 'DOMContentLoaded', function () {
	const blocks = document.querySelectorAll( '.wp-block-spawn-tier-select' );

	blocks.forEach( function ( block ) {
		// Clear loading state from PHP render
		block.innerHTML = '';

		const container = document.createElement( 'div' );
		container.className = 'wp-block-spawn-tier-select__container';

		const loading = document.createElement( 'p' );
		loading.textContent = 'Loading tiers...';
		container.appendChild( loading );
		block.appendChild( container );

		apiFetch( {
			path: '/spawn/v1/tiers',
		} )
			.then( function ( tiersObj ) {
				container.innerHTML = '';
				const cardsContainer = document.createElement( 'div' );
				cardsContainer.className = 'wp-block-spawn-tier-select__cards';

				// Convert object to array with id
				const tiers = Object.entries( tiersObj ).map(
					( [ id, tier ] ) => ( { id, ...tier } )
				);

				tiers.forEach( function ( tier ) {
					const card = document.createElement( 'div' );
					card.className = 'tier-card';
					if ( tier.name === 'Pro' ) {
						card.classList.add( 'highlighted' );
					}

					const title = document.createElement( 'h4' );
					title.textContent = tier.name;
					card.appendChild( title );

					const price = document.createElement( 'p' );
					price.className = 'price';
					price.textContent = `$${ tier.price }`;
					card.appendChild( price );

					const features = document.createElement( 'ul' );
					if ( tier.features && tier.features.length > 0 ) {
						tier.features.forEach( function ( feature ) {
							const li = document.createElement( 'li' );
							li.textContent = feature;
							features.appendChild( li );
						} );
					} else {
						const li = document.createElement( 'li' );
						li.textContent = 'Features coming soon';
						features.appendChild( li );
					}
					card.appendChild( features );

					const button = document.createElement( 'button' );
					button.textContent = 'Select';
					button.className = 'select-btn';
					button.addEventListener( 'click', function () {
						// Mark selected
						cardsContainer
							.querySelectorAll( '.tier-card' )
							.forEach( ( c ) => c.classList.remove( 'selected' ) );
						card.classList.add( 'selected' );

						const event = new CustomEvent( 'spawn:tier-selected', {
							detail: {
								id: tier.id,
								name: tier.name,
								price: tier.price,
							},
						} );
						document.dispatchEvent( event );
					} );
					card.appendChild( button );

					cardsContainer.appendChild( card );
				} );

				container.appendChild( cardsContainer );

				// Trust signals footer
				const trustFooter = document.createElement( 'div' );
				trustFooter.className = 'wp-block-spawn-tier-select__trust';
				trustFooter.innerHTML = `
					<p class="trust-text">
						<strong>Cancel anytime.</strong> No contracts, no hidden fees.
						<br>
						<span class="powered-by">Powered by <a href="https://extrachill.com" target="_blank" rel="noopener">Extra Chill</a></span>
					</p>
				`;
				container.appendChild( trustFooter );
			} )
			.catch( function () {
				container.innerHTML =
					'<p>Error loading tiers. Please try again.</p>';
			} );
	} );
} );
