/**
 * Auth Gate block view script.
 */
( function () {
	'use strict';

	const containers = document.querySelectorAll( '.wp-block-spawn-auth-gate' );

	containers.forEach( function ( container ) {
		const form = container.querySelector( '.wp-block-spawn-auth-gate__form' );
		if ( ! form ) {
			return;
		}

		form.addEventListener( 'submit', function ( e ) {
			const redirectTo = form.querySelector( 'input[name="redirect_to"]' );
			if ( redirectTo && redirectTo.value ) {
				sessionStorage.setItem( 'spawn_auth_redirect', redirectTo.value );
			}
		} );
	} );

	const handleAuthChange = () => {
		const isLoggedIn = document.body.classList.contains( 'logged-in' ) ||
			document.querySelector( '.wp-block-spawn-chat[data-context]' );

		if ( isLoggedIn ) {
			window.location.reload();
		}
	};

	const urlParams = new URLSearchParams( window.location.search );
	if ( urlParams.get( 'loggedout' ) === 'true' ) {
		handleAuthChange();
	}
} )();
