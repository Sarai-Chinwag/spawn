document.addEventListener( 'DOMContentLoaded', () => {
	const blocks = document.querySelectorAll( '.wp-block-spawn-dashboard' );

	blocks.forEach( ( block ) => {
		const tabs = block.querySelectorAll( '.spawn-dashboard__tab' );
		const panels = block.querySelectorAll( '.spawn-dashboard__panel' );
		const defaultTab = block.dataset.activeTab || 'overview';
		const url = new URL( window.location.href );
		const tabParam = url.searchParams.get( 'tab' );
		const nextTab = tabParam || defaultTab;

		function activateTab( tabName ) {
			panels.forEach( ( panel ) => {
				panel.classList.toggle( 'is-active', panel.dataset.panel === tabName );
			} );

			tabs.forEach( ( tab ) => {
				const isActive = tab.dataset.tab === tabName;
				tab.classList.toggle( 'is-active', isActive );
				tab.setAttribute( 'aria-current', isActive ? 'page' : 'false' );
			} );
		}

		activateTab( nextTab );
	} );
} );
