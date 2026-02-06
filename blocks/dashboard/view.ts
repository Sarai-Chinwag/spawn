document.addEventListener( 'DOMContentLoaded', (): void => {
	const blocks = document.querySelectorAll< HTMLElement >( '.wp-block-spawn-dashboard' );

	blocks.forEach( ( block: HTMLElement ): void => {
		const tabs = block.querySelectorAll< HTMLElement >( '.spawn-dashboard__tab' );
		const panels = block.querySelectorAll< HTMLElement >( '.spawn-dashboard__panel' );
		const defaultTab = block.dataset.activeTab || 'overview';
		const url = new URL( window.location.href );
		const tabParam = url.searchParams.get( 'tab' );
		const nextTab = tabParam || defaultTab;

		function activateTab( tabName: string ): void {
			panels.forEach( ( panel: HTMLElement ): void => {
				panel.classList.toggle( 'is-active', panel.dataset.panel === tabName );
			} );

			tabs.forEach( ( tab: HTMLElement ): void => {
				const isActive = tab.dataset.tab === tabName;
				tab.classList.toggle( 'is-active', isActive );
				tab.setAttribute( 'aria-current', isActive ? 'page' : 'false' );
			} );
		}

		activateTab( nextTab );
	} );
} );
