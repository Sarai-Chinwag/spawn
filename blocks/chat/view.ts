/**
 * Chat block view script.
 *
 * @package Spawn
 */

import apiFetch from '@wordpress/api-fetch';
import type { ApiError } from '../shared';

export type SpawnChatState = 'unauthenticated' | 'no-credits' | 'chat' | 'credits-depleted' | 'provisioning';

interface SpawnState {
	isAuthenticated: boolean;
	customerId: number;
	creditBalance: number;
	billingMode: 'managed' | 'byok';
	billingType: 'paid' | 'comped';
	username: string;
	domain: string;
	status: string;
	serverReady: boolean;
	loginUrl: string;
	registerUrl: string;
	lostPasswordUrl: string;
	purchaseUrl: string;
	brandName: string;
	brandLogoUrl: string;
	gatewayUrl: string;
	gatewayToken: string;
	chatMode: 'direct' | 'proxy';
}

interface ChatContext {
	customer_id: number;
	domain: string;
	status: string;
	has_mobile: boolean;
	is_admin?: boolean;
	first_visit?: boolean;
	username: string;
}

interface Session {
	sessionKey?: string;
	key?: string;
	displayName?: string;
	updatedAt?: string;
}

interface ContentBlock {
	type: string;
	text?: string;
}

interface ChatMessage {
	role: 'user' | 'assistant' | 'system';
	content: string | ContentBlock[];
}

interface ChatSendResponse {
	reply?: string;
	error?: string;
	code?: string;
}

interface SessionsResponse {
	sessions?: Session[];
}

interface HistoryResponse {
	messages?: ChatMessage[];
}

interface TitleResponse {
	title?: string;
}

interface BalanceResponse {
	balance: number;
}

interface PurchaseResponse {
	session_id?: string;
	checkout_url?: string;
	error?: string;
}

interface SessionTitles {
	[ key: string ]: string;
}

const API = {
	send: '/spawn/v1/chat/send',
	sessions: '/spawn/v1/chat/sessions',
	history: ( key: string ) => `/spawn/v1/chat/sessions/${ key }/history?limit=50`,
	generateTitle: '/spawn/v1/chat/generate-title',
	balance: '/spawn/v1/credits/balance',
	purchase: '/spawn/v1/credits/purchase',
};

const STORAGE = {
	session: 'spawn_webchat_session',
	titles: 'spawn_session_titles',
};

const LOADING_VERBS = [
	'Conjuring', 'Channeling', 'Manifesting', 'Divining', 'Meditating on',
	'Brewing', 'Hatching', 'Nesting on', 'Perching on', 'Pondering',
	'Musing about', 'Wondering about', 'Enchanting', 'Cultivating',
	'Blooming', 'Unfurling', 'Crystallizing', 'Dreaming up', 'Gazing into',
	'Communing with',
];

const WORD_BANK = [
	'curious', 'mystical', 'cosmic', 'enchanted', 'wandering',
	'dreaming', 'starlit', 'moonlit', 'crystal', 'golden',
	'whispering', 'dancing', 'glowing', 'hidden', 'sacred',
	'crow', 'sparrow', 'phoenix', 'butterfly', 'firefly',
	'musing', 'wonder', 'quest', 'journey', 'vision',
	'bloom', 'garden', 'river', 'mountain', 'star',
	'feather', 'sunflower', 'twilight', 'aurora', 'ember',
];

function extractMessageText( content: string | ContentBlock[] ): string {
	if ( typeof content === 'string' ) return content;
	if ( Array.isArray( content ) ) {
		return content
			.filter( ( block ) => block.type === 'text' && block.text )
			.map( ( block ) => block.text )
			.join( '\n' );
	}
	return String( content );
}

function generateFallbackName(): string {
	const adj = WORD_BANK[ Math.floor( Math.random() * 15 ) ];
	const noun = WORD_BANK[ 15 + Math.floor( Math.random() * 15 ) ];
	return `${ adj.toLowerCase() }-${ noun.toLowerCase() }`;
}

function escapeHtml( text: string ): string {
	const div = document.createElement( 'div' );
	div.textContent = text;
	return div.innerHTML;
}

function escapeAttr( text: string ): string {
	return text.replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' );
}

function parseMarkdown( text: string ): string {
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

function formatDate( dateStr: string ): string {
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

function init(): void {
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
			billingMode: 'managed',
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
			chatMode: 'proxy',
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

	let currentState: SpawnChatState = determineState( spawnState );
	let currentBalance = spawnState.creditBalance;

	function determineState( state: SpawnState ): SpawnChatState {
		if ( ! state.isAuthenticated ) {
			return 'unauthenticated';
		}
		if ( ! state.serverReady ) {
			return 'provisioning';
		}
		if ( state.billingMode === 'byok' || state.billingType === 'comped' || state.customerId === 0 ) {
			return 'chat';
		}
		if ( state.creditBalance <= 0 ) {
			return 'no-credits';
		}
		return 'chat';
	}

	function updateBalanceDisplay(): void {
		if ( balanceSpan ) {
			balanceSpan.textContent = `$${ currentBalance.toFixed( 2 ) }`;
			balanceSpan.classList.remove( 'wp-block-spawn-chat__balance--warning', 'wp-block-spawn-chat__balance--danger' );
			if ( currentBalance < 1 ) {
				balanceSpan.classList.add( 'wp-block-spawn-chat__balance--danger' );
			} else if ( currentBalance < 5 ) {
				balanceSpan.classList.add( 'wp-block-spawn-chat__balance--warning' );
			}
		}
	}

	async function fetchBalance(): Promise< number > {
		try {
			const response = await apiFetch< BalanceResponse >( { path: API.balance } );
			currentBalance = response.balance;
			updateBalanceDisplay();
			return currentBalance;
		} catch {
			return currentBalance;
		}
	}

	function renderUnauthenticated(): void {
		if ( ! messagesContainer || ! input || ! sendBtn ) return;

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
		if ( sessionsContainer ) sessionsContainer.innerHTML = '';
	}

	function renderNoCredits(): void {
		if ( ! messagesContainer ) return;

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
					const response = await apiFetch< PurchaseResponse >( {
						path: API.purchase,
						method: 'POST',
						data: { amount },
					} );
					if ( response.checkout_url ) {
						window.location.href = response.checkout_url;
					}
				} catch ( error ) {
					console.error( 'Purchase failed:', error );
				}
			} );
		} );

		if ( input ) input.style.display = 'none';
		if ( sendBtn ) sendBtn.style.display = 'none';
	}

	function renderProvisioning(): void {
		if ( ! messagesContainer ) return;

		messagesContainer.innerHTML = `
			<div class="wp-block-spawn-chat__provisioning">
				<p>Your website is still being set up! This usually takes a few minutes.</p>
				<p>I'll be fully operational once it's ready.</p>
			</div>
		`;

		if ( input ) input.disabled = true;
		if ( sendBtn ) sendBtn.disabled = true;
	}

	function renderCreditsDepleted(): void {
		if ( ! messagesContainer ) return;

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
						const response = await apiFetch< PurchaseResponse >( {
							path: API.purchase,
							method: 'POST',
							data: { amount },
						} );
						if ( response.checkout_url ) {
							window.location.href = response.checkout_url;
						}
					} catch ( error ) {
						console.error( 'Purchase failed:', error );
					}
				} );
			} );
		}

		if ( input ) input.disabled = true;
		if ( sendBtn ) sendBtn.disabled = true;
	}

	function renderChat(): void {
		if ( ! messagesContainer || ! input || ! sendBtn ) return;

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

	function renderState(): void {
		switch ( currentState ) {
			case 'unauthenticated':
				renderUnauthenticated();
				break;
			case 'no-credits':
				renderNoCredits();
				break;
			case 'provisioning':
				renderProvisioning();
				break;
			case 'credits-depleted':
				renderCreditsDepleted();
				break;
			case 'chat':
				renderChat();
				break;
		}
	}

	renderState();
	updateBalanceDisplay();

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

	if ( ! messagesContainer || ! input || ! sendBtn ) return;

	let isLoading = false;
	let currentSessionKey = '';
	let sessions: Session[] = [];
	let verbInterval: ReturnType< typeof setInterval > | null = null;
	let currentVerbIndex = 0;

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
		const wordBankSample = [ ...WORD_BANK ].sort( () => 0.5 - Math.random() ).slice( 0, 12 ).join( ', ' );

		try {
			const response = await apiFetch< TitleResponse >( {
				path: API.generateTitle,
				method: 'POST',
				data: { username, wordBank: wordBankSample },
			} );
			if ( response.title ) {
				storage.setTitle( sessionKey, response.title );
				renderSessions();
				return response.title;
			}
		} catch {
			console.log( 'Title generation failed, using fallback' );
		}

		const fallback = generateFallbackName();
		storage.setTitle( sessionKey, fallback );
		return fallback;
	};

	const updateSessionIndicator = (): void => {
		if ( sessionIndicator ) {
			sessionIndicator.textContent = currentSessionKey
				? `Session: ${ currentSessionKey.substr( -8 ) }`
				: '';
		}
	};

	const renderSessions = (): void => {
		if ( ! sessionsContainer ) return;

		if ( sessions.length === 0 ) {
			sessionsContainer.innerHTML = '<div class="wp-block-spawn-chat__sessions-loading">No conversations yet</div>';
			return;
		}

		sessionsContainer.innerHTML = sessions.map( ( session ) => {
			const key = session.sessionKey || session.key || '';
			const title = getSessionTitle( key, session );
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
				if ( key && key !== currentSessionKey ) selectSession( key );
			} );
		} );
	};

	const loadSessions = async (): Promise< void > => {
		if ( ! sessionsContainer ) return;

		const savedKey = storage.getSessionKey();

		try {
			let allSessions: Session[] = [];

			if ( spawnState.chatMode === 'direct' ) {
				const response = await fetch( spawnState.gatewayUrl + '/tools/invoke', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'Authorization': 'Bearer ' + spawnState.gatewayToken,
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
					allSessions = details.sessions || [];
				}
			} else {
				const response = await apiFetch< SessionsResponse | Session[] >( { path: API.sessions } );
				allSessions = ( response as SessionsResponse ).sessions || ( response as Session[] ) || [];
			}

			sessions = allSessions
				.filter( ( s ) => ( s.sessionKey || s.key || '' ).includes( 'webchat' ) )
				.sort( ( a, b ) => new Date( b.updatedAt || 0 ).getTime() - new Date( a.updatedAt || 0 ).getTime() );

			renderSessions();

			if ( savedKey && sessions.some( ( s ) => ( s.sessionKey || s.key ) === savedKey ) ) {
				selectSession( savedKey );
			} else if ( sessions.length > 0 && ! currentSessionKey ) {
				selectSession( sessions[ 0 ].sessionKey || sessions[ 0 ].key || '' );
			} else if ( ! currentSessionKey ) {
				startNewSession();
			}
		} catch ( error ) {
			console.error( 'Failed to load sessions:', error );
			sessionsContainer.innerHTML = '<div class="wp-block-spawn-chat__sessions-loading">Could not load chats</div>';
			if ( savedKey ) {
				currentSessionKey = savedKey;
				updateSessionIndicator();
				messagesContainer.innerHTML = '';
			} else if ( ! currentSessionKey ) {
				startNewSession();
			}
		}
	};

	const selectSession = async ( key: string ): Promise< void > => {
		currentSessionKey = key;
		storage.setSessionKey( key );
		updateSessionIndicator();
		renderSessions();

		messagesContainer.innerHTML = '<div class="chat-message chat-message--system">Loading conversation...</div>';

		try {
			let messages: ChatMessage[] = [];

			if ( spawnState.chatMode === 'direct' ) {
				const response = await fetch( spawnState.gatewayUrl + '/tools/invoke', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'Authorization': 'Bearer ' + spawnState.gatewayToken,
					},
					body: JSON.stringify( {
						tool: 'sessions_history',
						args: {
							sessionKey: key,
							limit: 50,
						},
					} ),
				} );

				if ( response.ok ) {
					const data = await response.json();
					// Gateway /tools/invoke returns { ok, result: { content, details } }
					const details = data.result?.details || data;
					messages = details.messages || [];
				}
			} else {
				const response = await apiFetch< HistoryResponse | ChatMessage[] >( { path: API.history( key ) } );
				messages = ( response as HistoryResponse ).messages || ( response as ChatMessage[] ) || [];
			}

			renderHistory( messages );
		} catch ( error ) {
			console.error( 'Failed to load history:', error );
			messagesContainer.innerHTML = '';
		}

		if ( sidebar && window.innerWidth <= 768 ) {
			sidebar.classList.remove( 'wp-block-spawn-chat__sidebar--open' );
		}
	};

	const renderHistory = ( messages: ChatMessage[] ): void => {
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
	};

	const startNewSession = (): void => {
		currentSessionKey = generateSessionKey();
		storage.setSessionKey( currentSessionKey );
		updateSessionIndicator();
		messagesContainer.innerHTML = '';
		renderSessions();
	};

	const addMessage = ( role: string, content: string ): void => {
		const msgDiv = document.createElement( 'div' );
		msgDiv.className = `chat-message chat-message--${ role }`;
		msgDiv.innerHTML = `<div class="chat-message__content">${ parseMarkdown( content ) }</div>`;
		messagesContainer.appendChild( msgDiv );
		messagesContainer.scrollTop = messagesContainer.scrollHeight;
	};

	const showTypingIndicator = (): void => {
		currentVerbIndex = Math.floor( Math.random() * LOADING_VERBS.length );

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

		verbInterval = setInterval( () => {
			currentVerbIndex = ( currentVerbIndex + 1 ) % LOADING_VERBS.length;
			const verbSpan = indicator.querySelector( '.typing-verb' );
			if ( verbSpan ) verbSpan.textContent = LOADING_VERBS[ currentVerbIndex ];
		}, 2000 );
	};

	const hideTypingIndicator = (): void => {
		if ( verbInterval ) {
			clearInterval( verbInterval );
			verbInterval = null;
		}
		document.getElementById( 'typing-indicator' )?.remove();
	};

	const setLoading = ( loading: boolean ): void => {
		isLoading = loading;
		sendBtn.disabled = loading;
		input.disabled = loading;
		if ( newConvoBtn ) newConvoBtn.disabled = loading;

		if ( loading ) {
			sendBtn.classList.add( 'loading' );
			showTypingIndicator();
		} else {
			sendBtn.classList.remove( 'loading' );
			hideTypingIndicator();
		}
	};

	const autoResizeInput = (): void => {
		input.style.height = 'auto';
		input.style.height = `${ Math.min( input.scrollHeight, 150 ) }px`;
	};

	const sendMessage = async (): Promise< void > => {
		const text = input.value.trim();
		if ( ! text || isLoading ) return;

		if ( spawnState.chatMode === 'direct' ) {
			if ( spawnState.billingMode === 'managed' && spawnState.billingType !== 'comped' && currentBalance <= 0 ) {
				currentState = 'credits-depleted';
				renderState();
				return;
			}
		}

		if ( ! currentSessionKey ) {
			currentSessionKey = generateSessionKey();
			storage.setSessionKey( currentSessionKey );
			updateSessionIndicator();
		}

		if ( ! storage.getTitle( currentSessionKey ) ) {
			generateSessionTitle( currentSessionKey );
		}

		addMessage( 'user', text );
		input.value = '';
		autoResizeInput();
		setLoading( true );

		try {
			let response: ChatSendResponse;

			if ( spawnState.chatMode === 'direct' ) {
				const headers: Record< string, string > = {
					'Content-Type': 'application/json',
					'Authorization': 'Bearer ' + spawnState.gatewayToken,
				};

				if ( currentSessionKey ) {
					headers[ 'x-openclaw-session-key' ] = currentSessionKey;
				}

				const chatResponse = await fetch( spawnState.gatewayUrl + '/v1/chat/completions', {
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

				if ( chatResponse.status === 402 || chatResponse.status === 403 ) {
					currentState = 'credits-depleted';
					await fetchBalance();
					renderState();
					setLoading( false );
					return;
				}

				if ( ! chatResponse.ok ) {
					const errorData = await chatResponse.json().catch( () => ( {} ) );
					if ( chatResponse.status === 0 ) {
						addMessage( 'system', 'Your agent is offline. It may be restarting.' );
					} else {
						addMessage( 'system', `Error: ${ errorData.error?.message || 'Failed to get response' }` );
					}
					setLoading( false );
					input.focus();
					return;
				}

				const chatData = await chatResponse.json();
				const reply = chatData.choices?.[ 0 ]?.message?.content;

				if ( reply ) {
					addMessage( 'assistant', reply );
					await fetchBalance();

					if ( currentBalance <= 0 && spawnState.billingMode === 'managed' ) {
						currentState = 'credits-depleted';
						renderState();
					}
				} else {
					addMessage( 'system', 'No response received from agent.' );
				}

				loadSessions();
				setLoading( false );
				input.focus();
				return;
			}

			response = await apiFetch< ChatSendResponse >( {
				path: API.send,
				method: 'POST',
				data: { message: text, sessionKey: currentSessionKey, context },
			} );

			if ( response.code === 'insufficient_credits' || ( response.error && ( response.error.toLowerCase().includes( 'credit' ) || response.error.toLowerCase().includes( 'insufficient' ) ) ) ) {
				currentState = 'credits-depleted';
				await fetchBalance();
				renderState();
				setLoading( false );
				return;
			}

			if ( response.reply ) {
				addMessage( 'assistant', response.reply );
				await fetchBalance();
			} else if ( response.error ) {
				addMessage( 'system', `Error: ${ response.error }` );
			}

			loadSessions();
		} catch ( error: unknown ) {
			const err = error as { code?: string; message?: string; status?: number };
			if ( err.status === 402 || ( err.message && err.message.toLowerCase().includes( 'credit' ) ) ) {
				currentState = 'credits-depleted';
				await fetchBalance();
				renderState();
			} else {
				addMessage( 'system', 'Failed to send message. Please try again.' );
			}
		}

		setLoading( false );
		input.focus();
	};

	sendBtn.addEventListener( 'click', sendMessage );
	newConvoBtn?.addEventListener( 'click', startNewSession );
	input.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Enter' && ! e.shiftKey ) {
			e.preventDefault();
			sendMessage();
		}
	} );
	input.addEventListener( 'input', autoResizeInput );

	if ( sidebarToggle && sidebar ) {
		sidebarToggle.addEventListener( 'click', () => {
			sidebar.classList.toggle( 'wp-block-spawn-chat__sidebar--open' );
		} );
	}

	loadSessions();
}

document.addEventListener( 'DOMContentLoaded', init );
