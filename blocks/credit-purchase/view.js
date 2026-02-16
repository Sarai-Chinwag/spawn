/**
 * Credit Purchase block view script.
 */
( function () {
	'use strict';

	const API_ROOT = '/wp-json/spawn/v1';

	async function purchaseCredits( amount, button ) {
		const container = button.closest( '.wp-block-spawn-credit-purchase' );
		if ( ! container ) {
			return;
		}

		const options = container.querySelector( '.wp-block-spawn-credit-purchase__options' );
		const loading = container.querySelector( '.wp-block-spawn-credit-purchase__loading' );

		if ( ! options || ! loading ) {
			return;
		}

		options.style.display = 'none';
		loading.style.display = 'flex';

		try {
			const response = await fetch( `${ API_ROOT }/credits/purchase`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify( { amount } ),
				credentials: 'include',
			} );

			if ( ! response.ok ) {
				const error = await response.json();
				alert( error.message || 'Failed to create checkout session' );
				options.style.display = 'grid';
				loading.style.display = 'none';
				return;
			}

			const data = await response.json();

			if ( data.checkout_url ) {
				window.location.href = data.checkout_url;
			} else {
				throw new Error( 'No checkout URL returned' );
			}
		} catch ( error ) {
			console.error( 'Purchase failed:', error );
			alert( 'Failed to process purchase. Please try again.' );
			options.style.display = 'grid';
			loading.style.display = 'none';
		}
	}

	function init() {
		const containers = document.querySelectorAll( '.wp-block-spawn-credit-purchase' );

		containers.forEach( function ( container ) {
			const options = container.querySelectorAll( '.wp-block-spawn-credit-purchase__option' );

			options.forEach( function ( option ) {
				option.addEventListener( 'click', function () {
					const amount = parseInt( this.dataset.amount, 10 );
					if ( amount >= 10 ) {
						purchaseCredits( amount, this );
					}
				} );
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
