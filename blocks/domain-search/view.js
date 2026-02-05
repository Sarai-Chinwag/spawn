import apiFetch from '@wordpress/api-fetch';
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
		button.textContent = 'Search';
		button.className = 'wp-block-spawn-domain-search__button';

		const results = document.createElement( 'div' );
		results.className = 'wp-block-spawn-domain-search__results';

		form.appendChild( input );
		form.appendChild( button );
		block.appendChild( form );
		block.appendChild( results );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			const domain = input.value.trim();
			if ( ! domain ) {
				return;
			}

			button.disabled = true;
			button.textContent = 'Searching...';
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
						<p>Price: $${ response.price }</p>
					`;
						results.appendChild( availableDiv );

						const selectBtn = document.createElement( 'button' );
						selectBtn.textContent = 'Select Domain';
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
									},
								}
							);
							document.dispatchEvent( event );
						} );
						results.appendChild( selectBtn );

						// Subdomain option
						const subdomainDiv = document.createElement( 'div' );
						subdomainDiv.className = 'domain-subdomain';
						subdomainDiv.innerHTML = `<p>Or get <strong>${ domain }.saraichinwag.com</strong> for free</p>`;
						const selectSubBtn = document.createElement( 'button' );
						selectSubBtn.textContent = 'Select Subdomain';
						selectSubBtn.className =
							'wp-block-spawn-domain-search__select-btn subdomain';
						selectSubBtn.addEventListener( 'click', function () {
							const subdomain = `${ domain }.saraichinwag.com`;
							block.setAttribute(
								'data-selected-domain',
								subdomain
							);
							const event = new CustomEvent(
								'spawn:domain-selected',
								{
									detail: { domain: subdomain, price: 0 },
								}
							);
							document.dispatchEvent( event );
						} );
						subdomainDiv.appendChild( selectSubBtn );
						results.appendChild( subdomainDiv );
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
								'<p>Suggestions:</p><ul></ul>';
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
					button.textContent = 'Search';
				} )
				.catch( function () {
					results.innerHTML =
						'<p>Error searching domain. Please try again.</p>';
					button.disabled = false;
					button.textContent = 'Search';
				} );
		} );
	} );
} );
