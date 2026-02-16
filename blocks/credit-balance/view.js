/**
 * Credit Balance block view script.
 */
( function () {
	'use strict';

	const API_ROOT = '/wp-json/spawn/v1';
	const POLL_INTERVAL = 30000;

	let pollTimer = null;

	async function fetchBalance() {
		try {
			const response = await fetch( `${ API_ROOT }/credits/balance`, {
				credentials: 'include',
			} );

			if ( ! response.ok ) {
				return;
			}

			const data = await response.json();
			updateBalanceDisplay( data.balance );
		} catch ( error ) {
			console.error( 'Failed to fetch balance:', error );
		}
	}

	function updateBalanceDisplay( balance ) {
		const containers = document.querySelectorAll( '.wp-block-spawn-credit-balance' );

		containers.forEach( function ( container ) {
			const amountEl = container.querySelector( '.wp-block-spawn-credit-balance__amount' );
			if ( amountEl ) {
				amountEl.textContent = `$${ balance.toFixed( 2 ) }`;
				amountEl.dataset.balance = balance;
			}
		} );
	}

	function startPolling() {
		if ( pollTimer ) {
			return;
		}

		fetchBalance();
		pollTimer = setInterval( fetchBalance, POLL_INTERVAL );
	}

	function stopPolling() {
		if ( pollTimer ) {
			clearInterval( pollTimer );
			pollTimer = null;
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		const containers = document.querySelectorAll( '.wp-block-spawn-credit-balance' );

		if ( containers.length > 0 ) {
			startPolling();
		}
	} );

	document.addEventListener( 'visibilitychange', function () {
		if ( document.hidden ) {
			stopPolling();
		} else {
			const containers = document.querySelectorAll( '.wp-block-spawn-credit-balance' );
			if ( containers.length > 0 ) {
				startPolling();
			}
		}
	} );

	window.addEventListener( 'spawn-credit-purchased', function () {
		fetchBalance();
	} );
} )();
