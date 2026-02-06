/**
 * Shared utility functions for Spawn blocks.
 */

import type { StatusType, ApiError } from './types';

/**
 * Show a status message in an element.
 */
export function showStatus(
	element: HTMLElement,
	message: string,
	type: StatusType = 'info'
): void {
	element.className = `spawn-status spawn-status--${ type }`;
	element.textContent = message;
	element.style.display = 'block';
}

/**
 * Hide a status element.
 */
export function hideStatus( element: HTMLElement ): void {
	element.style.display = 'none';
}

/**
 * Get error message from API error.
 */
export function getErrorMessage( error: ApiError, fallback = 'An error occurred.' ): string {
	return error.message || fallback;
}

/**
 * Format currency value.
 */
export function formatCurrency( value: number, decimals = 2 ): string {
	return `$${ value.toFixed( decimals ) }`;
}

/**
 * Format number with locale.
 */
export function formatNumber( value: number ): string {
	return value.toLocaleString();
}

/**
 * Safely query an element and throw if not found.
 */
export function querySelector< T extends Element >(
	parent: Element | Document,
	selector: string
): T {
	const el = parent.querySelector< T >( selector );
	if ( ! el ) {
		throw new Error( `Element not found: ${ selector }` );
	}
	return el;
}

/**
 * Safely query an element, return null if not found.
 */
export function querySelectorSafe< T extends Element >(
	parent: Element | Document,
	selector: string
): T | null {
	return parent.querySelector< T >( selector );
}

/**
 * Add loading state to a button.
 */
export function setButtonLoading(
	button: HTMLButtonElement,
	loading: boolean,
	loadingText = 'Loading...',
	originalText?: string
): void {
	if ( loading ) {
		button.disabled = true;
		button.dataset.originalText = button.textContent || '';
		button.textContent = loadingText;
	} else {
		button.disabled = false;
		button.textContent = originalText || button.dataset.originalText || '';
	}
}

/**
 * Debounce a function.
 */
export function debounce< T extends ( ...args: unknown[] ) => void >(
	fn: T,
	delay: number
): ( ...args: Parameters< T > ) => void {
	let timeoutId: ReturnType< typeof setTimeout >;
	return ( ...args: Parameters< T > ) => {
		clearTimeout( timeoutId );
		timeoutId = setTimeout( () => fn( ...args ), delay );
	};
}
