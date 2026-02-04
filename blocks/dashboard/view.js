document.addEventListener( 'DOMContentLoaded', function () {
	const blocks = document.querySelectorAll( '.wp-block-spawn-dashboard' );

	blocks.forEach( function ( block ) {
		block.innerHTML = '<div class="wp-block-spawn-dashboard__loading">Loading dashboard...</div>';

		wp.apiFetch( { path: '/spawn/v1/customer/me' } )
			.then( function ( response ) {
				if ( ! response.success || ! response.customer ) {
					renderNotCustomer( block );
					return;
				}
				renderDashboard( block, response.customer );
			} )
			.catch( function ( error ) {
				if ( error.code === 'rest_not_logged_in' ) {
					renderNotLoggedIn( block );
				} else {
					renderError( block, error.message );
				}
			} );
	} );

	function renderNotLoggedIn( block ) {
		block.innerHTML = `
			<div class="wp-block-spawn-dashboard__message">
				<h2>Please Log In</h2>
				<p>You need to be logged in to view your dashboard.</p>
				<a href="/spawn/login/" class="wp-block-spawn-dashboard__btn">Log In</a>
			</div>
		`;
	}

	function renderNotCustomer( block ) {
		block.innerHTML = `
			<div class="wp-block-spawn-dashboard__message">
				<h2>No Active Subscription</h2>
				<p>You don't have an active Spawn subscription yet.</p>
				<a href="/spawn/" class="wp-block-spawn-dashboard__btn">Get Started</a>
			</div>
		`;
	}

	function renderError( block, message ) {
		block.innerHTML = `
			<div class="wp-block-spawn-dashboard__error">
				<h2>Error Loading Dashboard</h2>
				<p>${ message || 'An unexpected error occurred.' }</p>
				<button onclick="window.location.reload()" class="wp-block-spawn-dashboard__btn">Retry</button>
			</div>
		`;
	}

	function getStatusClass( status ) {
		const statusMap = {
			active: 'status-active',
			provisioning: 'status-provisioning',
			pending: 'status-pending',
			suspended: 'status-suspended',
			cancelled: 'status-cancelled',
		};
		return statusMap[ status ] || 'status-unknown';
	}

	function getStatusLabel( status ) {
		const labelMap = {
			active: 'Active',
			provisioning: 'Setting Up...',
			pending: 'Pending',
			suspended: 'Suspended',
			cancelled: 'Cancelled',
		};
		return labelMap[ status ] || status;
	}

	function getTierLabel( vpsTier ) {
		const tierMap = {
			cx22: 'Starter',
			cx32: 'Pro',
			cx42: 'Business',
		};
		return tierMap[ vpsTier ] || vpsTier;
	}

	function renderDashboard( block, customer ) {
		const usagePercent = Math.round( ( customer.ai_calls_used / customer.ai_calls_limit ) * 100 );
		const usageClass = usagePercent > 80 ? 'usage-high' : usagePercent > 50 ? 'usage-medium' : 'usage-low';

		block.innerHTML = `
			<div class="wp-block-spawn-dashboard__container">
				<h2>Your Dashboard</h2>
				
				<div class="wp-block-spawn-dashboard__cards">
					<div class="wp-block-spawn-dashboard__card">
						<h3>Server Status</h3>
						<span class="status-badge ${ getStatusClass( customer.status ) }">
							${ getStatusLabel( customer.status ) }
						</span>
						${ customer.status === 'provisioning' ? '<p class="status-note">Your site is being set up. This usually takes 5-10 minutes.</p>' : '' }
					</div>
					
					<div class="wp-block-spawn-dashboard__card">
						<h3>Your Site</h3>
						<p class="domain-name">
							<a href="https://${ customer.domain }" target="_blank" rel="noopener">
								${ customer.domain }
							</a>
						</p>
						${ customer.server_ip ? `<p class="server-ip">IP: ${ customer.server_ip }</p>` : '' }
					</div>
					
					<div class="wp-block-spawn-dashboard__card">
						<h3>AI Usage This Month</h3>
						<div class="usage-bar ${ usageClass }">
							<div class="usage-fill" style="width: ${ usagePercent }%"></div>
						</div>
						<p class="usage-text">
							${ customer.ai_calls_used.toLocaleString() } / ${ customer.ai_calls_limit.toLocaleString() } calls
							<span class="usage-percent">(${ usagePercent }%)</span>
						</p>
					</div>
					
					<div class="wp-block-spawn-dashboard__card">
						<h3>Current Plan</h3>
						<p class="plan-name">${ getTierLabel( customer.vps_tier ) }</p>
						<a href="/spawn/account/" class="plan-link">Manage Plan →</a>
					</div>
				</div>
				
				<div class="wp-block-spawn-dashboard__actions">
					<button type="button" class="wp-block-spawn-dashboard__btn btn-billing">
						Manage Billing
					</button>
					<a href="/spawn/account/" class="wp-block-spawn-dashboard__btn btn-secondary">
						Account Settings
					</a>
				</div>
			</div>
		`;

		// Handle billing portal button
		block.querySelector( '.btn-billing' ).addEventListener( 'click', function () {
			this.disabled = true;
			this.textContent = 'Loading...';

			wp.apiFetch( { path: '/spawn/v1/customer/billing-portal' } )
				.then( function ( response ) {
					if ( response.url ) {
						window.location.href = response.url;
					}
				} )
				.catch( function ( error ) {
					alert( error.message || 'Failed to load billing portal.' );
					this.disabled = false;
					this.textContent = 'Manage Billing';
				}.bind( this ) );
		} );
	}
} );
