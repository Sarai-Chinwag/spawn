import apiFetch from '@wordpress/api-fetch';

const searchIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>';
const loadingIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>';

document.addEventListener( 'DOMContentLoaded', function () {
	const blocks = document.querySelectorAll( '.wp-block-spawn-domain-search' );

	blocks.forEach( function ( block ) {
		// Clear loading state from PHP render
		block.innerHTML = '';

		const form = document.createElement( 'form' );
		form.className = 'wp-block-spawn-domain-search__form';

		const input = document.createElement( 'input' );
		input.type = 'text';
		input.placeholder =
			block.dataset.placeholder || 'Enter a domain name...';
		input.className = 'wp-block-spawn-domain-search__input';
		input.required = true;

		const button = document.createElement( 'button' );
		button.type = 'submit';
		button.innerHTML = searchIcon;
		button.className = 'wp-block-spawn-domain-search__button';
		button.setAttribute( 'aria-label', 'Search' );

		const results = document.createElement( 'div' );
		results.className = 'wp-block-spawn-domain-search__results';

		form.appendChild( input );
		form.appendChild( button );
		block.appendChild( form );
		block.appendChild( results );

		// Skip for Now option - always visible
		const skipSection = document.createElement( 'div' );
		skipSection.className = 'wp-block-spawn-domain-search__skip';
		skipSection.innerHTML = `<p>Or</p>`;
		const skipBtn = document.createElement( 'button' );
		skipBtn.type = 'button';
		skipBtn.textContent = 'Skip for Now';
		skipBtn.className = 'wp-block-spawn-domain-search__skip-btn';
		skipBtn.addEventListener( 'click', function () {
			const event = new CustomEvent( 'spawn:domain-selected', {
				detail: {
					domain: null,
					price: 0,
					type: 'subdomain',
				},
			} );
			document.dispatchEvent( event );
			skipSection.innerHTML = `<p class="skip-selected">✓ We'll set up a free subdomain for you. You can add a custom domain later.</p>`;
		} );
		skipSection.appendChild( skipBtn );
		skipSection.innerHTML += `<p class="skip-note">Get a free subdomain. Add your own domain later in dashboard.</p>`;
		block.appendChild( skipSection );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			const domain = input.value.trim();
			if ( ! domain ) {
				return;
			}

			button.disabled = true;
			button.innerHTML = loadingIcon;
			results.innerHTML = '';

			apiFetch( {
				path:
					'/spawn/v1/domain/search?domain=' +
					encodeURIComponent( domain ),
			} )
				.then( function ( response ) {
					results.innerHTML = '';

					if ( response.available ) {
						const availableDiv = document.createElement( 'div' );
						availableDiv.className = 'domain-available';
						availableDiv.innerHTML = `
						<p><span class="checkmark">✓</span> ${ domain } is available!</p>
						<p>Price: $${ response.price }/year</p>
					`;
						results.appendChild( availableDiv );

						const selectBtn = document.createElement( 'button' );
						selectBtn.textContent = 'Choose This Domain';
						selectBtn.className =
							'wp-block-spawn-domain-search__select-btn';
						selectBtn.addEventListener( 'click', function () {
							block.setAttribute(
								'data-selected-domain',
								domain
							);
							const event = new CustomEvent(
								'spawn:domain-selected',
								{
									detail: {
										domain,
										price: response.price,
										type: 'register',
									},
								}
							);
							document.dispatchEvent( event );
						} );
						results.appendChild( selectBtn );
					} else {
						const takenDiv = document.createElement( 'div' );
						takenDiv.className = 'domain-taken';
						takenDiv.innerHTML = `<p><span class="cross">✗</span> ${ domain } is taken.</p>`;
						results.appendChild( takenDiv );

						if (
							response.suggestions &&
							response.suggestions.length > 0 &&
							block.dataset.showSuggestions !== 'false'
						) {
							const suggestionsDiv =
								document.createElement( 'div' );
							suggestionsDiv.className = 'domain-suggestions';
							suggestionsDiv.innerHTML =
								'<p>Try one of these:</p><ul></ul>';
							const ul = suggestionsDiv.querySelector( 'ul' );
							response.suggestions.forEach(
								function ( suggestion ) {
									const li = document.createElement( 'li' );
									const btn =
										document.createElement( 'button' );
									btn.textContent = suggestion.domain;
									btn.className = 'suggestion-btn';
									btn.addEventListener( 'click', function () {
										input.value = suggestion.domain;
										form.dispatchEvent(
											new Event( 'submit' )
										);
									} );
									li.appendChild( btn );
									ul.appendChild( li );
								}
							);
							results.appendChild( suggestionsDiv );
						}
					}

					button.disabled = false;
					button.innerHTML = searchIcon;
				} )
				.catch( function () {
					results.innerHTML =
						'<p>Error searching domain. Please try again.</p>';
					button.disabled = false;
					button.innerHTML = searchIcon;
				} );
		} );
	} );
} );
