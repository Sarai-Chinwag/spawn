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

		// BYOD (Bring Your Own Domain) section
		const byodSection = document.createElement( 'div' );
		byodSection.className = 'wp-block-spawn-domain-search__byod';
		byodSection.innerHTML = `
			<p class="byod-label">Already have a domain?</p>
			<div class="byod-form">
				<input type="text" placeholder="yourdomain.com" class="byod-input" />
				<button type="button" class="byod-btn">Use My Domain</button>
			</div>
			<p class="byod-note">You'll need to point your domain's DNS to our servers after signup.</p>
		`;
		block.appendChild( byodSection );

		const byodInput = byodSection.querySelector( '.byod-input' );
		const byodBtn = byodSection.querySelector( '.byod-btn' );
		byodBtn.addEventListener( 'click', function () {
			const domain = byodInput.value.trim();
			if ( ! domain ) {
				return;
			}
			block.setAttribute( 'data-selected-domain', domain );
			const event = new CustomEvent( 'spawn:domain-selected', {
				detail: {
					domain,
					price: 0,
					type: 'byod',
				},
			} );
			document.dispatchEvent( event );
			// Visual feedback
			byodSection.innerHTML = `<p class="byod-selected">✓ Using your domain: <strong>${ domain }</strong></p>`;
		} );

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
										type: 'register',
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
									detail: {
										domain: subdomain,
										price: 0,
										type: 'subdomain',
									},
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
