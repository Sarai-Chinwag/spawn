/**
 * Main chat block orchestration.
 *
 * @package Spawn
 */

import type { SpawnState, SpawnChatState, ChatContext, Session, SessionTitles } from './types';
import { STORAGE } from './constants';
import { generateFallbackName } from './utils';
import { determineState } from './state';
import {
	renderUnauthenticated,
	renderNoCredits,
	renderProvisioning,
	renderCreditsDepleted,
	renderChat,
	renderSessions,
	renderHistory,
	addMessage,
	showTypingIndicator,
	hideTypingIndicator,
	updateBalanceDisplay,
	updateSessionIndicator,
} from './ui';
import {
	fetchBalance,
	generateTitle,
	loadSessions,
	loadHistory,
	sendMessage,
} from './api';

export function init(): void {
	document.querySelectorAll< HTMLElement >( '.wp-block-spawn-chat' ).forEach( initBlock );
}

function initBlock( block: HTMLElement ): void {
	const stateJson = block.dataset.spawnState || '{}';
	let spawnState: SpawnState;

	try {
		spawnState = JSON.parse( stateJson );
	} catch {
		spawnState = {
			isAuthenticated: false,
			customerId: 0,
			creditBalance: 0,
			billingType: 'paid',
			username: '',
			domain: '',
			status: '',
			serverReady: true,
			loginUrl: '',
			registerUrl: '',
			lostPasswordUrl: '',
			purchaseUrl: '',
			brandName: 'Spawn',
			brandLogoUrl: '',
			gatewayUrl: '',
			gatewayToken: '',
		};
	}

	const messagesContainer = block.querySelector< HTMLElement >( '.wp-block-spawn-chat__messages' );
	const input = block.querySelector< HTMLTextAreaElement >( '.wp-block-spawn-chat__input' );
	const sendBtn = block.querySelector< HTMLButtonElement >( '.wp-block-spawn-chat__send' );
	const newConvoBtn = block.querySelector< HTMLButtonElement >( '.wp-block-spawn-chat__new-convo' );
	const sessionIndicator = block.querySelector< HTMLElement >( '.wp-block-spawn-chat__session-id' );
	const sessionsContainer = block.querySelector< HTMLElement >( '.wp-block-spawn-chat__sessions' );
	const sidebar = block.querySelector< HTMLElement >( '.wp-block-spawn-chat__sidebar' );
	const sidebarToggle = block.querySelector< HTMLButtonElement >( '.wp-block-spawn-chat__sidebar-toggle' );
	const balanceSpan = block.querySelector< HTMLSpanElement >( '.wp-block-spawn-chat__balance' );

	if ( ! messagesContainer || ! input || ! sendBtn ) return;

	let currentState: SpawnChatState = determineState( spawnState );
	let currentBalance = spawnState.creditBalance;

	function renderState(): void {
		switch ( currentState ) {
			case 'unauthenticated':
				renderUnauthenticated( messagesContainer, input, sendBtn, spawnState, newConvoBtn );
				break;
			case 'no-credits':
				renderNoCredits( messagesContainer );
				input.style.display = 'none';
				sendBtn.style.display = 'none';
				break;
			case 'provisioning':
				renderProvisioning( messagesContainer, input, sendBtn );
				break;
			case 'credits-depleted':
				renderCreditsDepleted( messagesContainer, input, sendBtn );
				break;
			case 'chat':
				renderChat( messagesContainer, input, sendBtn, newConvoBtn );
				break;
		}
	}

	async function updateBalance(): Promise< void > {
		try {
			currentBalance = await fetchBalance();
			if ( balanceSpan ) {
				updateBalanceDisplay( balanceSpan, currentBalance );
			}
		} catch {
			// Balance update failed, keep existing value
		}
	}

	renderState();
	if ( balanceSpan ) {
		updateBalanceDisplay( balanceSpan, currentBalance );
	}

	if ( currentState !== 'chat' ) {
		return;
	}

	const context: ChatContext = {
		customer_id: spawnState.customerId,
		domain: spawnState.domain,
		status: spawnState.status,
		has_mobile: false,
		is_admin: spawnState.customerId === 0,
		username: spawnState.username,
	};

	let isLoading = false;
	let currentSessionKey = '';
	let sessions: Session[] = [];
	let typingInterval: ReturnType< typeof setInterval > | null = null;

	const storage = {
		getSessionKey: (): string => {
			try { return localStorage.getItem( STORAGE.session ) || ''; }
			catch { return ''; }
		},
		setSessionKey: ( key: string ): void => {
			try {
				if ( key ) localStorage.setItem( STORAGE.session, key );
				else localStorage.removeItem( STORAGE.session );
			} catch {}
		},
		getTitle: ( key: string ): string | null => {
			try {
				const titles: SessionTitles = JSON.parse( localStorage.getItem( STORAGE.titles ) || '{}' );
				return titles[ key ] || null;
			} catch { return null; }
		},
		setTitle: ( key: string, title: string ): void => {
			try {
				const titles: SessionTitles = JSON.parse( localStorage.getItem( STORAGE.titles ) || '{}' );
				titles[ key ] = title;
				localStorage.setItem( STORAGE.titles, JSON.stringify( titles ) );
			} catch {}
		},
	};

	const generateSessionKey = (): string =>
		`webchat-${ Date.now() }-${ Math.random().toString( 36 ).substr( 2, 9 ) }`;

	const getSessionTitle = ( key: string, session: Session | null = null ): string => {
		if ( session?.displayName && ! session.displayName.startsWith( 'webchat-' ) ) {
			return session.displayName;
		}
		const stored = storage.getTitle( key );
		if ( stored ) return stored;
		if ( key.startsWith( 'webchat-' ) ) return generateFallbackName();
		return 'Conversation';
	};

	const generateSessionTitle = async ( sessionKey: string ): Promise< string > => {
		const username = context.username || 'friend';

		try {
			const title = await generateTitle( username );
			storage.setTitle( sessionKey, title );
			if ( sessionsContainer ) {
				renderSessions( sessionsContainer, sessions, currentSessionKey, selectSession, getSessionTitle );
			}
			return title;
		} catch {
			const fallback = generateFallbackName();
			storage.setTitle( sessionKey, fallback );
			return fallback;
		}
	};

	const loadSessionsData = async (): Promise< void > => {
		if ( ! sessionsContainer ) return;

		const savedKey = storage.getSessionKey();

		try {
			const allSessions = await loadSessions( spawnState.gatewayUrl, spawnState.gatewayToken );

			sessions = allSessions
				.sort( ( a, b ) => new Date( b.updatedAt || 0 ).getTime() - new Date( a.updatedAt || 0 ).getTime() );

			renderSessions( sessionsContainer, sessions, currentSessionKey, selectSession );

			if ( savedKey && sessions.some( ( s ) => ( s.id || s.sessionKey || s.key ) === savedKey ) ) {
				selectSession( savedKey );
			} else if ( sessions.length > 0 && ! currentSessionKey ) {
				selectSession( sessions[ 0 ].id || sessions[ 0 ].sessionKey || sessions[ 0 ].key || '' );
			} else if ( ! currentSessionKey ) {
				startNewSession();
			}
		} catch ( error ) {
			console.error( 'Failed to load sessions:', error );
			sessionsContainer.innerHTML = '<div class="wp-block-spawn-chat__sessions-loading">Could not load chats</div>';
			if ( savedKey ) {
				currentSessionKey = savedKey;
				if ( sessionIndicator ) {
					updateSessionIndicator( sessionIndicator, currentSessionKey );
				}
				messagesContainer.innerHTML = '';
			} else if ( ! currentSessionKey ) {
				startNewSession();
			}
		}
	};

	const selectSession = async ( key: string ): Promise< void > => {
		currentSessionKey = key;
		storage.setSessionKey( key );
		if ( sessionIndicator ) {
			updateSessionIndicator( sessionIndicator, currentSessionKey );
		}
		if ( sessionsContainer ) {
			renderSessions( sessionsContainer, sessions, currentSessionKey, selectSession, getSessionTitle );
		}

		messagesContainer.innerHTML = '<div class="chat-message chat-message--system">Loading conversation...</div>';

		try {
			const messages = await loadHistory( spawnState.gatewayUrl, spawnState.gatewayToken, key );
			renderHistory( messagesContainer, messages );
		} catch ( error ) {
			console.error( 'Failed to load history:', error );
			messagesContainer.innerHTML = '';
		}

		if ( sidebar && window.innerWidth <= 768 ) {
			sidebar.classList.remove( 'wp-block-spawn-chat__sidebar--open' );
		}
	};

	const startNewSession = (): void => {
		currentSessionKey = generateSessionKey();
		storage.setSessionKey( currentSessionKey );
		if ( sessionIndicator ) {
			updateSessionIndicator( sessionIndicator, currentSessionKey );
		}
		messagesContainer.innerHTML = '';
		if ( sessionsContainer ) {
			renderSessions( sessionsContainer, sessions, currentSessionKey, selectSession, getSessionTitle );
		}
	};

	const setLoading = ( loading: boolean ): void => {
		isLoading = loading;
		sendBtn.disabled = loading;
		input.disabled = loading;
		if ( newConvoBtn ) newConvoBtn.disabled = loading;

		if ( loading ) {
			sendBtn.classList.add( 'loading' );
			const { interval } = showTypingIndicator( messagesContainer );
			typingInterval = interval;
		} else {
			sendBtn.classList.remove( 'loading' );
			if ( typingInterval ) {
				hideTypingIndicator( typingInterval );
				typingInterval = null;
			}
		}
	};

	const autoResizeInput = (): void => {
		input.style.height = 'auto';
		input.style.height = `${ Math.min( input.scrollHeight, 150 ) }px`;
	};

	const handleSendMessage = async (): Promise< void > => {
		const text = input.value.trim();
		if ( ! text || isLoading ) return;

		if ( spawnState.billingType !== 'comped' && currentBalance <= 0 ) {
			currentState = 'credits-depleted';
			renderState();
			return;
		}

		if ( ! currentSessionKey ) {
			currentSessionKey = generateSessionKey();
			storage.setSessionKey( currentSessionKey );
			if ( sessionIndicator ) {
				updateSessionIndicator( sessionIndicator, currentSessionKey );
			}
		}

		if ( ! storage.getTitle( currentSessionKey ) ) {
			generateSessionTitle( currentSessionKey );
		}

		addMessage( messagesContainer, 'user', text );
		input.value = '';
		autoResizeInput();
		setLoading( true );

		try {
			const result = await sendMessage( text, spawnState.gatewayUrl, spawnState.gatewayToken, currentSessionKey );

			// Update session key if the server assigned a new session ID.
			if ( result.sessionId && result.sessionId !== currentSessionKey ) {
				currentSessionKey = result.sessionId;
				storage.setSessionKey( currentSessionKey );
				if ( sessionIndicator ) {
					updateSessionIndicator( sessionIndicator, currentSessionKey );
				}
			}

			if ( result.status === 402 || result.status === 403 ) {
				currentState = 'credits-depleted';
				await updateBalance();
				renderState();
				setLoading( false );
				return;
			}

			if ( result.status === 0 ) {
				addMessage( messagesContainer, 'system', 'Your agent is offline. It may be restarting.' );
			} else if ( result.reply ) {
				addMessage( messagesContainer, 'assistant', result.reply );
				await updateBalance();

				if ( currentBalance <= 0 && spawnState.billingType !== 'comped' ) {
					currentState = 'credits-depleted';
					renderState();
				}
			} else {
				addMessage( messagesContainer, 'system', result.error || 'No response received from agent.' );
			}

			loadSessionsData();
		} catch ( error: unknown ) {
			const err = error as { status?: number; message?: string };
			if ( err.status === 402 || ( err.message && err.message.toLowerCase().includes( 'credit' ) ) ) {
				currentState = 'credits-depleted';
				await updateBalance();
				renderState();
			} else {
				addMessage( messagesContainer, 'system', 'Failed to send message. Please try again.' );
			}
		}

		setLoading( false );
		input.focus();
	};

	sendBtn.addEventListener( 'click', handleSendMessage );
	newConvoBtn?.addEventListener( 'click', startNewSession );
	input.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Enter' && ! e.shiftKey ) {
			e.preventDefault();
			handleSendMessage();
		}
	} );
	input.addEventListener( 'input', autoResizeInput );

	if ( sidebarToggle && sidebar ) {
		sidebarToggle.addEventListener( 'click', () => {
			sidebar.classList.toggle( 'wp-block-spawn-chat__sidebar--open' );
		} );
	}

	loadSessionsData();
}