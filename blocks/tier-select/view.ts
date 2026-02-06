import apiFetch from '@wordpress/api-fetch';

interface Tier {
	id: string;
	name: string;
	price: number;
	features?: string[];
}

interface TiersResponse {
	[ key: string ]: Omit< Tier, 'id' >;
}

document.addEventListener( 'DOMContentLoaded', function (): void {
	const blocks = document.querySelectorAll< HTMLElement >( '.wp-block-spawn-tier-select' );

	blocks.forEach( function ( block: HTMLElement ): void {
		block.innerHTML = '';

		const container = document.createElement( 'div' );
		container.className = 'wp-block-spawn-tier-select__container';

		const loading = document.createElement( 'p' );
		loading.textContent = 'Loading tiers...';
		container.appendChild( loading );
		block.appendChild( container );

		apiFetch< TiersResponse >( {
			path: '/spawn/v1/tiers',
		} )
			.then( function ( tiersObj: TiersResponse ): void {
				container.innerHTML = '';
				const cardsContainer = document.createElement( 'div' );
				cardsContainer.className = 'wp-block-spawn-tier-select__cards';

				const tiers: Tier[] = Object.entries( tiersObj ).map(
					( [ id, tier ] ) => ( { id, ...tier } )
				);

				tiers.forEach( function ( tier: Tier ): void {
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
						tier.features.forEach( function ( feature: string ): void {
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
					button.addEventListener( 'click', function (): void {
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
			} )
			.catch( function (): void {
				container.innerHTML = '<p>Error loading tiers. Please try again.</p>';
			} );
	} );
} );
