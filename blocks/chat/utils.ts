/**
 * Pure utility functions for the chat block.
 *
 * @package Spawn
 */

import type { ContentBlock } from './types';
import { WORD_BANK } from './constants';

export function extractMessageText( content: string | ContentBlock[] ): string {
	if ( typeof content === 'string' ) return content;
	if ( Array.isArray( content ) ) {
		return content
			.filter( ( block ) => block.type === 'text' && block.text )
			.map( ( block ) => block.text )
			.join( '\n' );
	}
	return String( content );
}

export function generateFallbackName(): string {
	const adj = WORD_BANK[ Math.floor( Math.random() * 15 ) ];
	const noun = WORD_BANK[ 15 + Math.floor( Math.random() * 15 ) ];
	return `${ adj.toLowerCase() }-${ noun.toLowerCase() }`;
}

export function escapeHtml( text: string ): string {
	const div = document.createElement( 'div' );
	div.textContent = text;
	return div.innerHTML;
}

export function escapeAttr( text: string ): string {
	return text.replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' );
}

export function parseMarkdown( text: string ): string {
	let html = escapeHtml( text );

	html = html.replace( /```(\w*)\n?([\s\S]*?)```/g, ( _, lang, code ) =>
		`<pre><code class="language-${ lang }">${ code.trim() }</code></pre>`
	);

	html = html.replace( /`([^`]+)`/g, '<code>$1</code>' );

	html = html.replace( /\*\*([^*]+)\*\*/g, '<strong>$1</strong>' );
	html = html.replace( /__([^_]+)__/g, '<strong>$1</strong>' );

	html = html.replace( /\*([^*]+)\*/g, '<em>$1</em>' );
	html = html.replace( /_([^_]+)_/g, '<em>$1</em>' );

	html = html.replace( /\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>' );

	html = html.replace( /\n/g, '<br>' );

	return html;
}

export function formatDate( dateStr: string ): string {
	const date = new Date( dateStr );
	const now = new Date();
	const diffMs = now.getTime() - date.getTime();
	const diffDays = Math.floor( diffMs / ( 1000 * 60 * 60 * 24 ) );

	if ( diffDays === 0 ) {
		return date.toLocaleTimeString( [], { hour: '2-digit', minute: '2-digit' } );
	} else if ( diffDays === 1 ) {
		return 'Yesterday';
	} else if ( diffDays < 7 ) {
		return date.toLocaleDateString( [], { weekday: 'short' } );
	}
	return date.toLocaleDateString( [], { month: 'short', day: 'numeric' } );
}