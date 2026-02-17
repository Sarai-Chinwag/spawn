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

export async function loadSessions( gatewayUrl: string, gatewayToken: string ): Promise< Session[] > {
	const response = await fetch( gatewayUrl + '/tools/invoke', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'Authorization': 'Bearer ' + gatewayToken,
		},
		body: JSON.stringify( {
			tool: 'sessions_list',
			args: {},
		} ),
	} );

	if ( response.ok ) {
		const data = await response.json();
		// Gateway /tools/invoke returns { ok, result: { content, details } }
		const details = data.result?.details || data;
		return details.sessions || [];
	}
	throw new Error( 'Failed to load sessions' );
}

export async function loadHistory( gatewayUrl: string, gatewayToken: string, sessionKey: string ): Promise< ChatMessage[] > {
	const response = await fetch( gatewayUrl + '/tools/invoke', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'Authorization': 'Bearer ' + gatewayToken,
		},
		body: JSON.stringify( {
			tool: 'sessions_history',
			args: {
				sessionKey: sessionKey,
				limit: 50,
			},
		} ),
	} );

	if ( response.ok ) {
		const data = await response.json();
		// Gateway /tools/invoke returns { ok, result: { content, details } }
		const details = data.result?.details || data;
		return details.messages || [];
	}
	throw new Error( 'Failed to load history' );
}

export async function sendMessage(
	text: string,
	gatewayUrl: string,
	gatewayToken: string,
	sessionKey?: string
): Promise< { reply?: string; status: number; error?: string } > {
	const headers: Record< string, string > = {
		'Content-Type': 'application/json',
		'Authorization': 'Bearer ' + gatewayToken,
	};

	if ( sessionKey ) {
		headers[ 'x-openclaw-session-key' ] = sessionKey;
	}

	const response = await fetch( gatewayUrl + '/v1/chat/completions', {
		method: 'POST',
		headers,
		body: JSON.stringify( {
			model: 'openclaw:main',
			messages: [
				{
					role: 'user',
					content: text,
				},
			],
		} ),
	} );

	const status = response.status;

	if ( ! response.ok ) {
		const errorData = await response.json().catch( () => ( {} ) );
		return {
			status,
			error: errorData.error?.message || 'Failed to get response',
		};
	}

	const data = await response.json();
	const reply = data.choices?.[ 0 ]?.message?.content;

	return {
		status,
		reply,
	};
}