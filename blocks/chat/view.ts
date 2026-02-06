/**
 * Chat block view script.
 *
 * @package Spawn
 */

import apiFetch from '@wordpress/api-fetch';
import type { ApiError } from '../shared';

// Chat-specific types
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

interface SessionTitles {
	[ key: string ]: string;
}

// API endpoints
const API = {
	send: '/spawn/v1/chat/send',
	sessions: '/spawn/v1/chat/sessions',
	history: ( key: string ) => `/spawn/v1/chat/sessions/${ key }/history?limit=50`,
	generateTitle: '/spawn/v1/chat/generate-title',
};

// Storage keys
const STORAGE = {
	session: 'spawn_webchat_session',
	titles: 'spawn_session_titles',
};

// Branded loading verbs
const LOADING_VERBS = [
	'Conjuring', 'Channeling', 'Manifesting', 'Divining', 'Meditating on',
	'Brewing', 'Hatching', 'Nesting on', 'Perching on', 'Pondering',
	'Musing about', 'Wondering about', 'Enchanting', 'Cultivating',
	'Blooming', 'Unfurling', 'Crystallizing', 'Dreaming up', 'Gazing into',
	'Communing with',
];

// Word bank for session titles
const WORD_BANK = [
	'curious', 'mystical', 'cosmic', 'enchanted', 'wandering',
	'dreaming', 'starlit', 'moonlit', 'crystal', 'golden',
	'whispering', 'dancing', 'glowing', 'hidden', 'sacred',
	'crow', 'sparrow', 'phoenix', 'butterfly', 'firefly',
	'musing', 'wonder', 'quest', 'journey', 'vision',
	'bloom', 'garden', 'river', 'mountain', 'star',
	'feather', 'sunflower', 'twilight', 'aurora', 'ember',
];

/**
 * Extract text from message content.
 */
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

/**
 * Generate fallback session name.
 */
function generateFallbackName(): string {
	const adj = WORD_BANK[ Math.floor( Math.random() * 15 ) ];
	const noun = WORD_BANK[ 15 + Math.floor( Math.random() * 15 ) ];
	return `${ adj.toLowerCase() }-${ noun.toLowerCase() }`;
}

/**
 * Escape HTML entities.
 */
function escapeHtml( text: string ): string {
	const div = document.createElement( 'div' );
	div.textContent = text;
	return div.innerHTML;
}

/**
 * Escape attribute value.
 */
function escapeAttr( text: string ): string {
	return text.replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' );
}

/**
 * Parse markdown to HTML.
 */
function parseMarkdown( text: string ): string {
	let html = escapeHtml( text );

	// Code blocks
	html = html.replace( /```(\w*)\n?([\s\S]*?)```/g, ( _, lang, code ) =>
		`<pre><code class="language-${ lang }">${ code.trim() }</code></pre>`
	);

	// Inline code
	html = html.replace( /`([^`]+)`/g, '<code>$1</code>' );

	// Bold
	html = html.replace( /\*\*([^*]+)\*\*/g, '<strong>$1</strong>' );
	html = html.replace( /__([^_]+)__/g, '<strong>$1</strong>' );

	// Italic
	html = html.replace( /\*([^*]+)\*/g, '<em>$1</em>' );
	html = html.replace( /_([^_]+)_/g, '<em>$1</em>' );

	// Links
	html = html.replace( /\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>' );

	// Line breaks
	html = html.replace( /\n/g, '<br>' );

	return html;
}

/**
 * Format relative date.
 */
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

/**
 * Initialize chat blocks.
 */
function init(): void {
	document.querySelectorAll< HTMLElement >( '.wp-block-spawn-chat' ).forEach( initBlock );
}

/**
 * Initialize a single chat block.
 */
function initBlock( block: HTMLElement ): void {
	const context: ChatContext = JSON.parse( block.dataset.context || '{}' );
	const messagesContainer = block.querySelector< HTMLElement >( '.wp-block-spawn-chat__messages' );
	const input = block.querySelector< HTMLTextAreaElement >( '.wp-block-spawn-chat__input' );
	const sendBtn = block.querySelector< HTMLButtonElement >( '.wp-block-spawn-chat__send' );
	const newConvoBtn = block.querySelector< HTMLButtonElement >( '.wp-block-spawn-chat__new-convo' );
	const sessionIndicator = block.querySelector< HTMLElement >( '.wp-block-spawn-chat__session-id' );
	const sessionsContainer = block.querySelector< HTMLElement >( '.wp-block-spawn-chat__sessions' );
	const sidebar = block.querySelector< HTMLElement >( '.wp-block-spawn-chat__sidebar' );
	const sidebarToggle = block.querySelector< HTMLButtonElement >( '.wp-block-spawn-chat__sidebar-toggle' );

	if ( ! messagesContainer || ! input || ! sendBtn ) return;

	// State
	let isLoading = false;
	let currentSessionKey = '';
	let sessions: Session[] = [];
	let verbInterval: ReturnType< typeof setInterval > | null = null;
	let currentVerbIndex = 0;

	// Session storage helpers
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

	// Generate session key
	const generateSessionKey = (): string =>
		`webchat-${ Date.now() }-${ Math.random().toString( 36 ).substr( 2, 9 ) }`;

	// Get session title
	const getSessionTitle = ( key: string, session: Session | null = null ): string => {
		if ( session?.displayName && ! session.displayName.startsWith( 'webchat-' ) ) {
			return session.displayName;
		}
		const stored = storage.getTitle( key );
		if ( stored ) return stored;
		if ( key.startsWith( 'webchat-' ) ) return generateFallbackName();
		return 'Conversation';
	};

	// Generate AI title
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

	// Update session indicator
	const updateSessionIndicator = (): void => {
		if ( sessionIndicator ) {
			sessionIndicator.textContent = currentSessionKey
				? `Session: ${ currentSessionKey.substr( -8 ) }`
				: '';
		}
	};

	// Render sessions list
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

	// Load sessions
	const loadSessions = async (): Promise< void > => {
		if ( ! sessionsContainer ) return;

		const savedKey = storage.getSessionKey();

		try {
			const response = await apiFetch< SessionsResponse | Session[] >( { path: API.sessions } );
			const allSessions = ( response as SessionsResponse ).sessions || ( response as Session[] ) || [];

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

	// Select session
	const selectSession = async ( key: string ): Promise< void > => {
		currentSessionKey = key;
		storage.setSessionKey( key );
		updateSessionIndicator();
		renderSessions();

		messagesContainer.innerHTML = '<div class="chat-message chat-message--system">Loading conversation...</div>';

		try {
			const response = await apiFetch< HistoryResponse | ChatMessage[] >( { path: API.history( key ) } );
			const messages = ( response as HistoryResponse ).messages || ( response as ChatMessage[] ) || [];
			renderHistory( messages );
		} catch ( error ) {
			console.error( 'Failed to load history:', error );
			messagesContainer.innerHTML = '';
		}

		if ( sidebar && window.innerWidth <= 768 ) {
			sidebar.classList.remove( 'wp-block-spawn-chat__sidebar--open' );
		}
	};

	// Render message history
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

	// Start new session
	const startNewSession = (): void => {
		currentSessionKey = generateSessionKey();
		storage.setSessionKey( currentSessionKey );
		updateSessionIndicator();
		messagesContainer.innerHTML = '';
		renderSessions();
	};

	// Add message to UI
	const addMessage = ( role: string, content: string ): void => {
		const msgDiv = document.createElement( 'div' );
		msgDiv.className = `chat-message chat-message--${ role }`;
		msgDiv.innerHTML = `<div class="chat-message__content">${ parseMarkdown( content ) }</div>`;
		messagesContainer.appendChild( msgDiv );
		messagesContainer.scrollTop = messagesContainer.scrollHeight;
	};

	// Typing indicator
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

	// Loading state
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

	// Auto-resize input
	const autoResizeInput = (): void => {
		input.style.height = 'auto';
		input.style.height = `${ Math.min( input.scrollHeight, 150 ) }px`;
	};

	// Send message
	const sendMessage = async (): Promise< void > => {
		const text = input.value.trim();
		if ( ! text || isLoading ) return;

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
			const response = await apiFetch< ChatSendResponse >( {
				path: API.send,
				method: 'POST',
				data: { message: text, sessionKey: currentSessionKey, context },
			} );

			if ( response.reply ) {
				addMessage( 'assistant', response.reply );
			} else if ( response.error ) {
				addMessage( 'system', `Error: ${ response.error }` );
			}

			loadSessions();
		} catch {
			addMessage( 'system', 'Failed to send message. Please try again.' );
		}

		setLoading( false );
		input.focus();
	};

	// Event listeners
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

	// Initialize
	loadSessions();
}

// Initialize on DOM ready
document.addEventListener( 'DOMContentLoaded', init );
