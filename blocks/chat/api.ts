/**
 * API functions for the chat block.
 *
 * @package Spawn
 */

import apiFetch from '@wordpress/api-fetch';
import type {
	BalanceResponse,
	TitleResponse,
	PurchaseResponse,
	SessionsResponse,
	HistoryResponse,
	ChatSendResponse,
	Session,
	ChatMessage,
} from './types';
import { API, WORD_BANK } from './constants';

export async function fetchBalance(): Promise< number > {
	try {
		const response = await apiFetch< BalanceResponse >( { path: API.balance } );
		return response.balance;
	} catch {
		throw new Error( 'Failed to fetch balance' );
	}
}

export async function generateTitle( username: string ): Promise< string > {
	const wordBankSample = [ ...WORD_BANK ].sort( () => 0.5 - Math.random() ).slice( 0, 12 ).join( ', ' );

	try {
		const response = await apiFetch< TitleResponse >( {
			path: API.generateTitle,
			method: 'POST',
			data: { username, wordBank: wordBankSample },
		} );
		if ( response.title ) {
			return response.title;
		}
	} catch {
		console.log( 'Title generation failed, using fallback' );
	}
	throw new Error( 'Title generation failed' );
}

export async function purchaseCredits( amount: number ): Promise< PurchaseResponse > {
	const response = await apiFetch< PurchaseResponse >( {
		path: API.purchase,
		method: 'POST',
		data: { amount },
	} );
	return response;
}

/**
 * Build HTTP Basic auth headers for OpenCode server.
 */
function getOpenCodeHeaders( password: string ): Record< string, string > {
	const headers: Record< string, string > = {
		'Content-Type': 'application/json',
	};

	if ( password ) {
		headers.Authorization = 'Basic ' + btoa( 'opencode:' + password );
	}

	return headers;
}

export async function loadSessions( gatewayUrl: string, gatewayToken: string ): Promise< Session[] > {
	const response = await fetch( gatewayUrl + '/session', {
		method: 'GET',
		headers: getOpenCodeHeaders( gatewayToken ),
	} );

	if ( response.ok ) {
		const data = await response.json();
		// OpenCode GET /session returns an array of sessions directly.
		return Array.isArray( data ) ? data : ( data.sessions || [] );
	}
	throw new Error( 'Failed to load sessions' );
}

export async function loadHistory( gatewayUrl: string, gatewayToken: string, sessionId: string ): Promise< ChatMessage[] > {
	const url = gatewayUrl + '/session/' + encodeURIComponent( sessionId ) + '/message?limit=50';

	const response = await fetch( url, {
		method: 'GET',
		headers: getOpenCodeHeaders( gatewayToken ),
	} );

	if ( response.ok ) {
		const data = await response.json();
		// OpenCode GET /session/:id/message returns an array of messages directly.
		return Array.isArray( data ) ? data : ( data.messages || [] );
	}
	throw new Error( 'Failed to load history' );
}

/**
 * Create a new session on the OpenCode server.
 */
export async function createSession( gatewayUrl: string, gatewayToken: string ): Promise< string | null > {
	try {
		const response = await fetch( gatewayUrl + '/session', {
			method: 'POST',
			headers: getOpenCodeHeaders( gatewayToken ),
			body: JSON.stringify( {} ),
		} );

		if ( response.ok ) {
			const data = await response.json();
			return data.id || null;
		}
	} catch {
		console.log( 'Failed to create OpenCode session' );
	}
	return null;
}

/**
 * Extract text reply from OpenCode response parts.
 *
 * OpenCode returns { info: {...}, parts: [{type: "text", content: "..."}] }.
 */
function extractReply( body: Record< string, unknown > ): string | undefined {
	const parts = ( body.parts || [] ) as Array< Record< string, string > >;

	for ( const part of parts ) {
		if ( part.type === 'text' && part.content ) {
			return part.content;
		}
	}

	// Fallback: try nested under result.
	const result = body.result as Record< string, unknown > | undefined;
	if ( result?.parts ) {
		for ( const part of result.parts as Array< Record< string, string > > ) {
			if ( part.type === 'text' && part.content ) {
				return part.content;
			}
		}
	}

	return undefined;
}

/**
 * Check if a session key is client-generated (not a real OpenCode session ID).
 */
function isClientGeneratedKey( key: string ): boolean {
	return key.startsWith( 'webchat-' );
}

export async function sendMessage(
	text: string,
	gatewayUrl: string,
	gatewayToken: string,
	sessionId?: string
): Promise< { reply?: string; status: number; error?: string; sessionId?: string } > {
	const messagePayload = JSON.stringify( {
		parts: [
			{
				type: 'text',
				text,
			},
		],
	} );

	// Client-generated keys (webchat-xxx) don't exist on the OpenCode server.
	// Create a real server-side session first.
	if ( ! sessionId || isClientGeneratedKey( sessionId ) ) {
		const newId = await createSession( gatewayUrl, gatewayToken );
		if ( ! newId ) {
			return {
				status: 502,
				error: 'Failed to create session',
			};
		}
		sessionId = newId;
	}

	let response = await fetch( gatewayUrl + '/session/' + encodeURIComponent( sessionId ) + '/message', {
		method: 'POST',
		headers: getOpenCodeHeaders( gatewayToken ),
		body: messagePayload,
	} );

	// If session not found (404), create a new one and retry.
	if ( response.status === 404 ) {
		const newId = await createSession( gatewayUrl, gatewayToken );
		if ( ! newId ) {
			return {
				status: 502,
				error: 'Failed to create session',
			};
		}
		sessionId = newId;

		response = await fetch( gatewayUrl + '/session/' + encodeURIComponent( sessionId ) + '/message', {
			method: 'POST',
			headers: getOpenCodeHeaders( gatewayToken ),
			body: messagePayload,
		} );
	}

	const status = response.status;

	if ( ! response.ok ) {
		const errorData = await response.json().catch( () => ( {} as Record< string, unknown > ) );
		const errorMsg = ( errorData.error as Record< string, string > )?.message || ( errorData.error as string ) || 'Failed to get response';
		return {
			status,
			error: errorMsg,
		};
	}

	const data = await response.json();
	const reply = extractReply( data );

	return {
		status,
		reply,
		sessionId,
	};
}