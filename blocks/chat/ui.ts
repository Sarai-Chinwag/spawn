/**
 * UI rendering functions for the chat block.
 *
 * @package Spawn
 */

import type { SpawnState, Session, ChatMessage } from './types';
import { escapeHtml, escapeAttr, parseMarkdown, extractMessageText, formatDate } from './utils';
import { purchaseCredits } from './api';
import { LOADING_VERBS } from './constants';

export function renderUnauthenticated( messagesContainer: HTMLElement, input: HTMLElement, sendBtn: HTMLElement, spawnState: SpawnState, newConvoBtn?: HTMLElement ): void {
	const brandLogo = spawnState.brandLogoUrl
		? `<img src="${ escapeAttr( spawnState.brandLogoUrl ) }" alt="${ escapeAttr( spawnState.brandName ) }" width="48" height="48" class="wp-block-spawn-chat__login-logo-img" />`
		: '';

	messagesContainer.innerHTML = `
		<div class="wp-block-spawn-chat__login">
			<div class="wp-block-spawn-chat__login-header">
				${ brandLogo }
				<h2 class="wp-block-spawn-chat__login-title">${ escapeHtml( spawnState.brandName ) }</h2>
				<p class="wp-block-spawn-chat__login-subtitle">Sign in to chat with your AI</p>
			</div>
			<form class="wp-block-spawn-chat__login-form" method="post" action="${ escapeAttr( spawnState.loginUrl ) }">
				<input type="hidden" name="redirect_to" value="${ escapeAttr( window.location.href ) }" />
				<div class="wp-block-spawn-chat__login-field">
					<label for="user_login">Email</label>
					<input type="text" name="log" id="user_login" required autocomplete="username" />
				</div>
				<div class="wp-block-spawn-chat__login-field">
					<label for="user_pass">Password</label>
					<input type="password" name="pwd" id="user_pass" required autocomplete="current-password" />
				</div>
				<button type="submit" class="wp-block-spawn-chat__login-submit">Sign In</button>
			</form>
			<div class="wp-block-spawn-chat__login-links">
				<a href="${ escapeAttr( spawnState.registerUrl ) }">Create an account</a>
				<span class="wp-block-spawn-chat__login-links-sep">|</span>
				<a href="${ escapeAttr( spawnState.lostPasswordUrl ) }">Forgot password?</a>
			</div>
		</div>
	`;

	input.style.display = 'none';
	sendBtn.style.display = 'none';
	if ( newConvoBtn ) newConvoBtn.style.display = 'none';
}

export function renderNoCredits( messagesContainer: HTMLElement ): void {
	messagesContainer.innerHTML = `
		<div class="wp-block-spawn-chat__no-credits">
			<div class="wp-block-spawn-chat__no-credits-header">
				<h2>Add Credits</h2>
				<p>Add credits to start chatting with your AI</p>
			</div>
			<div class="wp-block-spawn-chat__credit-buttons">
				<button class="wp-block-spawn-chat__credit-btn" data-amount="10">
					<span class="wp-block-spawn-chat__credit-btn-amount">$10</span>
					<span class="wp-block-spawn-chat__credit-btn-desc">1,000 credits</span>
				</button>
				<button class="wp-block-spawn-chat__credit-btn" data-amount="25">
					<span class="wp-block-spawn-chat__credit-btn-amount">$25</span>
					<span class="wp-block-spawn-chat__credit-btn-desc">3,000 credits <span class="wp-block-spawn-chat__credit-btn-bonus">(17% bonus)</span></span>
				</button>
				<button class="wp-block-spawn-chat__credit-btn" data-amount="50">
					<span class="wp-block-spawn-chat__credit-btn-amount">$50</span>
					<span class="wp-block-spawn-chat__credit-btn-desc">7,500 credits <span class="wp-block-spawn-chat__credit-btn-bonus">(50% bonus)</span></span>
				</button>
			</div>
		</div>
	`;

	messagesContainer.querySelectorAll< HTMLButtonElement >( '.wp-block-spawn-chat__credit-btn' ).forEach( ( btn ) => {
		btn.addEventListener( 'click', async () => {
			const amount = parseInt( btn.dataset.amount || '10', 10 );
			try {
				const response = await purchaseCredits( amount );
				if ( response.checkout_url ) {
					window.location.href = response.checkout_url;
				}
			} catch ( error ) {
				console.error( 'Purchase failed:', error );
			}
		} );
	} );
}

export function renderProvisioning( messagesContainer: HTMLElement, input: HTMLTextAreaElement, sendBtn: HTMLButtonElement ): void {
	messagesContainer.innerHTML = `
		<div class="wp-block-spawn-chat__provisioning">
			<p>Your website is still being set up! This usually takes a few minutes.</p>
			<p>I'll be fully operational once it's ready.</p>
		</div>
	`;

	input.disabled = true;
	sendBtn.disabled = true;
}

export function renderCreditsDepleted( messagesContainer: HTMLElement, input: HTMLTextAreaElement, sendBtn: HTMLButtonElement ): void {
	const existingMessage = messagesContainer.querySelector( '.wp-block-spawn-chat__credits-depleted-msg' );
	if ( ! existingMessage ) {
		const msgDiv = document.createElement( 'div' );
		msgDiv.className = 'wp-block-spawn-chat__credits-depleted-msg';
		msgDiv.innerHTML = `
			<div class="chat-message chat-message--system">
				<p>You've run out of credits</p>
			</div>
			<div class="wp-block-spawn-chat__credit-buttons wp-block-spawn-chat__credit-buttons--inline">
				<button class="wp-block-spawn-chat__credit-btn" data-amount="10">
					<span class="wp-block-spawn-chat__credit-btn-amount">$10</span>
					<span class="wp-block-spawn-chat__credit-btn-desc">1,000 credits</span>
				</button>
				<button class="wp-block-spawn-chat__credit-btn" data-amount="25">
					<span class="wp-block-spawn-chat__credit-btn-amount">$25</span>
					<span class="wp-block-spawn-chat__credit-btn-desc">3,000 credits</span>
				</button>
				<button class="wp-block-spawn-chat__credit-btn" data-amount="50">
					<span class="wp-block-spawn-chat__credit-btn-amount">$50</span>
					<span class="wp-block-spawn-chat__credit-btn-desc">7,500 credits</span>
				</button>
			</div>
		`;
		messagesContainer.appendChild( msgDiv );

		msgDiv.querySelectorAll< HTMLButtonElement >( '.wp-block-spawn-chat__credit-btn' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', async () => {
				const amount = parseInt( btn.dataset.amount || '10', 10 );
				try {
					const response = await purchaseCredits( amount );
					if ( response.checkout_url ) {
						window.location.href = response.checkout_url;
					}
				} catch ( error ) {
					console.error( 'Purchase failed:', error );
				}
			} );
		} );
	}

	input.disabled = true;
	sendBtn.disabled = true;
}

export function renderChat( messagesContainer: HTMLElement, input: HTMLTextAreaElement, sendBtn: HTMLButtonElement, newConvoBtn?: HTMLButtonElement ): void {
	input.style.display = '';
	input.disabled = false;
	sendBtn.style.display = '';
	sendBtn.disabled = false;
	if ( newConvoBtn ) newConvoBtn.style.display = '';

	const depletedMsg = messagesContainer.querySelector( '.wp-block-spawn-chat__credits-depleted-msg' );
	if ( depletedMsg ) {
		const successMsg = document.createElement( 'div' );
		successMsg.className = 'chat-message chat-message--system';
		successMsg.innerHTML = '<p>Credits added! You can continue chatting.</p>';
		messagesContainer.insertBefore( successMsg, depletedMsg );
		depletedMsg.remove();
	}

	messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

export function renderSessions( 
	sessionsContainer: HTMLElement, 
	sessions: Session[], 
	currentSessionKey: string, 
	onSessionClick: ( key: string ) => void,
	getTitle: ( key: string, session?: Session | null ) => string
): void {
	if ( sessions.length === 0 ) {
		sessionsContainer.innerHTML = '<div class="wp-block-spawn-chat__sessions-loading">No conversations yet</div>';
		return;
	}

	sessionsContainer.innerHTML = sessions.map( ( session ) => {
		const key = session.sessionKey || session.key || '';
		const title = getTitle( key, session );
		const date = session.updatedAt ? formatDate( session.updatedAt ) : '';
		const isActive = key === currentSessionKey;

		return `
			<div class="wp-block-spawn-chat__session-item${ isActive ? ' wp-block-spawn-chat__session-item--active' : '' }"
				 data-session-key="${ escapeAttr( key ) }">
				<div class="wp-block-spawn-chat__session-title">${ escapeHtml( title ) }</div>
				<div class="wp-block-spawn-chat__session-date">${ escapeHtml( date ) }</div>
			</div>
		`;
	} ).join( '' );

	sessionsContainer.querySelectorAll< HTMLElement >( '.wp-block-spawn-chat__session-item' ).forEach( ( item ) => {
		item.addEventListener( 'click', () => {
			const key = item.dataset.sessionKey;
			if ( key && key !== currentSessionKey ) onSessionClick( key );
		} );
	} );
}

export function renderHistory( messagesContainer: HTMLElement, messages: ChatMessage[] ): void {
	if ( messages.length === 0 ) {
		messagesContainer.innerHTML = '';
		return;
	}

	messagesContainer.innerHTML = messages
		.filter( ( msg ) => msg.role === 'user' || msg.role === 'assistant' )
		.map( ( msg ) => `
			<div class="chat-message chat-message--${ msg.role }">
				<div class="chat-message__content">${ parseMarkdown( extractMessageText( msg.content ) ) }</div>
			</div>
		` )
		.join( '' );

	messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

export function addMessage( messagesContainer: HTMLElement, role: string, content: string ): void {
	const msgDiv = document.createElement( 'div' );
	msgDiv.className = `chat-message chat-message--${ role }`;
	msgDiv.innerHTML = `<div class="chat-message__content">${ parseMarkdown( content ) }</div>`;
	messagesContainer.appendChild( msgDiv );
	messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

export function showTypingIndicator( messagesContainer: HTMLElement ): { interval: ReturnType< typeof setInterval >; currentVerbIndex: number } {
	let currentVerbIndex = Math.floor( Math.random() * LOADING_VERBS.length );

	const indicator = document.createElement( 'div' );
	indicator.className = 'chat-message chat-message--assistant chat-message--typing';
	indicator.id = 'typing-indicator';
	indicator.innerHTML = `<div class="chat-message__content">
		<span class="typing-verb">${ LOADING_VERBS[ currentVerbIndex ] }</span>
		<span class="typing-dots">
			<span class="typing-dot"></span>
			<span class="typing-dot"></span>
			<span class="typing-dot"></span>
		</span>
	</div>`;
	messagesContainer.appendChild( indicator );
	messagesContainer.scrollTop = messagesContainer.scrollHeight;

	const interval = setInterval( () => {
		currentVerbIndex = ( currentVerbIndex + 1 ) % LOADING_VERBS.length;
		const verbSpan = indicator.querySelector( '.typing-verb' );
		if ( verbSpan ) verbSpan.textContent = LOADING_VERBS[ currentVerbIndex ];
	}, 2000 );

	return { interval, currentVerbIndex };
}

export function hideTypingIndicator( interval: ReturnType< typeof setInterval > ): void {
	clearInterval( interval );
	document.getElementById( 'typing-indicator' )?.remove();
}

export function updateBalanceDisplay( balanceSpan: HTMLSpanElement, currentBalance: number ): void {
	balanceSpan.textContent = `$${ currentBalance.toFixed( 2 ) }`;
	balanceSpan.classList.remove( 'wp-block-spawn-chat__balance--warning', 'wp-block-spawn-chat__balance--danger' );
	if ( currentBalance < 1 ) {
		balanceSpan.classList.add( 'wp-block-spawn-chat__balance--danger' );
	} else if ( currentBalance < 5 ) {
		balanceSpan.classList.add( 'wp-block-spawn-chat__balance--warning' );
	}
}

export function updateSessionIndicator( sessionIndicator: HTMLElement, currentSessionKey: string ): void {
	sessionIndicator.textContent = currentSessionKey
		? `Session: ${ currentSessionKey.substr( -8 ) }`
		: '';
}