import apiFetch from '@wordpress/api-fetch';

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

// Extract text from message content (handles both string and content blocks)
function extractMessageText( content: string | ContentBlock[] ): string {
	if ( typeof content === 'string' ) {
		return content;
	}
	if ( Array.isArray( content ) ) {
		return content
			.filter( ( block ) => block.type === 'text' && block.text )
			.map( ( block ) => block.text )
			.join( '\n' );
	}
	return String( content );
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

document.addEventListener( 'DOMContentLoaded', function (): void {
	const blocks = document.querySelectorAll< HTMLElement >( '.wp-block-spawn-chat' );

	blocks.forEach( function ( block: HTMLElement ): void {
		const context: ChatContext = JSON.parse( block.dataset.context || '{}' );
		const messagesContainer = block.querySelector< HTMLElement >( '.wp-block-spawn-chat__messages' );
		const input = block.querySelector< HTMLTextAreaElement >( '.wp-block-spawn-chat__input' );
		const sendBtn = block.querySelector< HTMLButtonElement >( '.wp-block-spawn-chat__send' );
		const newConvoBtn = block.querySelector< HTMLButtonElement >( '.wp-block-spawn-chat__new-convo' );
		const sessionIndicator = block.querySelector< HTMLElement >( '.wp-block-spawn-chat__session-id' );
		const sessionsContainer = block.querySelector< HTMLElement >( '.wp-block-spawn-chat__sessions' );
		const sidebar = block.querySelector< HTMLElement >( '.wp-block-spawn-chat__sidebar' );
		const sidebarToggle = block.querySelector< HTMLButtonElement >( '.wp-block-spawn-chat__sidebar-toggle' );

		if ( ! messagesContainer || ! input || ! sendBtn ) {
			return;
		}

		let isLoading = false;
		let currentSessionKey = '';
		let sessions: Session[] = [];

		// Sarai Chinwag branded loading verbs
		const loadingVerbs: string[] = [
			'Conjuring', 'Channeling', 'Manifesting', 'Divining', 'Meditating on',
			'Brewing', 'Hatching', 'Nesting on', 'Perching on', 'Pondering',
			'Musing about', 'Wondering about', 'Enchanting', 'Cultivating',
			'Blooming', 'Unfurling', 'Crystallizing', 'Dreaming up', 'Gazing into',
			'Communing with',
		];
		let verbInterval: ReturnType< typeof setInterval > | null = null;
		let currentVerbIndex = 0;

		// Word bank for session title generation (Sarai vibes)
		const sessionWordBank: string[] = [
			'curious', 'mystical', 'cosmic', 'enchanted', 'wandering',
			'dreaming', 'starlit', 'moonlit', 'crystal', 'golden',
			'whispering', 'dancing', 'glowing', 'hidden', 'sacred',
			'crow', 'sparrow', 'phoenix', 'butterfly', 'firefly',
			'musing', 'wonder', 'quest', 'journey', 'vision',
			'bloom', 'garden', 'river', 'mountain', 'star',
			'feather', 'sunflower', 'twilight', 'aurora', 'ember',
		];

		// Quick fallback while AI generates (code name format: adjective-noun)
		function generateFallbackName(): string {
			const adj = sessionWordBank[ Math.floor( Math.random() * 15 ) ]; // First 15 are adjectives
			const noun = sessionWordBank[ 15 + Math.floor( Math.random() * 15 ) ]; // Rest are nouns
			return adj.toLowerCase() + '-' + noun.toLowerCase();
		}

		// Generate AI-powered session title via system agent
		async function generateSessionTitle( sessionKey: string ): Promise< string > {
			const username = context.username || 'friend';
			const wordBankSample = [ ...sessionWordBank ].sort( () => 0.5 - Math.random() ).slice( 0, 12 ).join( ', ' );

			try {
				const response = await apiFetch< TitleResponse >( {
					path: '/spawn/v1/chat/generate-title',
					method: 'POST',
					data: {
						username,
						wordBank: wordBankSample,
					},
				} );

				if ( response.title ) {
					storeSessionTitle( sessionKey, response.title );
					renderSessions();
					return response.title;
				}
			} catch ( error ) {
				console.log( 'Title generation failed, using fallback' );
			}

			const fallback = generateFallbackName();
			storeSessionTitle( sessionKey, fallback );
			return fallback;
		}

		// ===== SESSION MANAGEMENT =====

		const STORAGE_KEY = 'spawn_webchat_session';

		function generateSessionKey(): string {
			return 'webchat-' + Date.now() + '-' + Math.random().toString( 36 ).substr( 2, 9 );
		}

		function getSavedSessionKey(): string {
			try {
				return localStorage.getItem( STORAGE_KEY ) || '';
			} catch {
				return '';
			}
		}

		function saveSessionKey( key: string ): void {
			try {
				if ( key ) {
					localStorage.setItem( STORAGE_KEY, key );
				} else {
					localStorage.removeItem( STORAGE_KEY );
				}
			} catch {
				// Ignore storage errors
			}
		}

		async function loadSessions(): Promise< void > {
			if ( ! sessionsContainer ) return;

			const savedKey = getSavedSessionKey();

			try {
				const response = await apiFetch< SessionsResponse | Session[] >( { path: '/spawn/v1/chat/sessions' } );
				const allSessions: Session[] = ( response as SessionsResponse ).sessions || ( response as Session[] ) || [];

				sessions = allSessions.filter( ( session: Session ) => {
					const key = session.sessionKey || session.key || '';
					return key.includes( 'webchat' );
				} );

				sessions.sort( ( a: Session, b: Session ) => {
					const dateA = new Date( a.updatedAt || 0 );
					const dateB = new Date( b.updatedAt || 0 );
					return dateB.getTime() - dateA.getTime();
				} );

				renderSessions();

				if ( savedKey && sessions.some( ( s: Session ) => ( s.sessionKey || s.key ) === savedKey ) ) {
					selectSession( savedKey );
				} else if ( sessions.length > 0 && ! currentSessionKey ) {
					const firstKey = sessions[ 0 ].sessionKey || sessions[ 0 ].key || '';
					selectSession( firstKey );
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
		}

		function renderSessions(): void {
			if ( ! sessionsContainer ) return;

			if ( sessions.length === 0 ) {
				sessionsContainer.innerHTML = '<div class="wp-block-spawn-chat__sessions-loading">No conversations yet</div>';
				return;
			}

			sessionsContainer.innerHTML = sessions.map( ( session: Session ) => {
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

			sessionsContainer.querySelectorAll< HTMLElement >( '.wp-block-spawn-chat__session-item' ).forEach( ( item: HTMLElement ) => {
				item.addEventListener( 'click', () => {
					const key = item.dataset.sessionKey;
					if ( key && key !== currentSessionKey ) {
						selectSession( key );
					}
				} );
			} );
		}

		function getSessionTitle( key: string, session: Session | null = null ): string {
			if ( session && session.displayName && ! session.displayName.startsWith( 'webchat-' ) ) {
				return session.displayName;
			}

			const storedTitle = getStoredSessionTitle( key );
			if ( storedTitle ) {
				return storedTitle;
			}

			if ( key.startsWith( 'webchat-' ) ) {
				return generateFallbackName();
			}

			return 'Conversation';
		}

		function getStoredSessionTitle( key: string ): string | null {
			try {
				const titles: SessionTitles = JSON.parse( localStorage.getItem( 'spawn_session_titles' ) || '{}' );
				return titles[ key ] || null;
			} catch {
				return null;
			}
		}

		function storeSessionTitle( key: string, title: string ): void {
			try {
				const titles: SessionTitles = JSON.parse( localStorage.getItem( 'spawn_session_titles' ) || '{}' );
				titles[ key ] = title;
				localStorage.setItem( 'spawn_session_titles', JSON.stringify( titles ) );
			} catch {
				// Ignore storage errors
			}
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
			} else {
				return date.toLocaleDateString( [], { month: 'short', day: 'numeric' } );
			}
		}

		async function selectSession( key: string ): Promise< void > {
			currentSessionKey = key;
			saveSessionKey( key );
			updateSessionIndicator();
			renderSessions();

			messagesContainer.innerHTML = '<div class="chat-message chat-message--system">Loading conversation...</div>';

			try {
				const response = await apiFetch< HistoryResponse | ChatMessage[] >( {
					path: `/spawn/v1/chat/sessions/${ key }/history?limit=50`,
				} );

				const messages: ChatMessage[] = ( response as HistoryResponse ).messages || ( response as ChatMessage[] ) || [];
				renderHistory( messages );
			} catch ( error ) {
				console.error( 'Failed to load history:', error );
				messagesContainer.innerHTML = '';
			}

			if ( sidebar && window.innerWidth <= 768 ) {
				sidebar.classList.remove( 'wp-block-spawn-chat__sidebar--open' );
			}
		}

		function renderHistory( messages: ChatMessage[] ): void {
			if ( messages.length === 0 ) {
				messagesContainer.innerHTML = '';
				return;
			}

			messagesContainer.innerHTML = messages
				.filter( ( msg: ChatMessage ) => msg.role === 'user' || msg.role === 'assistant' )
				.map( ( msg: ChatMessage ) => `
					<div class="chat-message chat-message--${ msg.role }">
						<div class="chat-message__content">${ parseMarkdown( extractMessageText( msg.content ) ) }</div>
					</div>
				` )
				.join( '' );

			messagesContainer.scrollTop = messagesContainer.scrollHeight;
		}

		function startNewSession(): void {
			currentSessionKey = generateSessionKey();
			saveSessionKey( currentSessionKey );
			updateSessionIndicator();
			messagesContainer.innerHTML = '';
			renderSessions();
		}

		function updateSessionIndicator(): void {
			if ( sessionIndicator ) {
				sessionIndicator.textContent = currentSessionKey
					? 'Session: ' + currentSessionKey.substr( -8 )
					: '';
			}
		}

		// ===== MESSAGE HANDLING =====

		function addMessage( role: string, content: string ): void {
			const msgDiv = document.createElement( 'div' );
			msgDiv.className = `chat-message chat-message--${ role }`;
			msgDiv.innerHTML = `<div class="chat-message__content">${ parseMarkdown( content ) }</div>`;
			messagesContainer.appendChild( msgDiv );
			messagesContainer.scrollTop = messagesContainer.scrollHeight;
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

			// Code blocks
			html = html.replace( /```(\w*)\n?([\s\S]*?)```/g, ( _match: string, lang: string, code: string ) => {
				return `<pre><code class="language-${ lang }">${ code.trim() }</code></pre>`;
			} );

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

		// ===== TYPING INDICATOR =====

		function showTypingIndicator(): void {
			currentVerbIndex = Math.floor( Math.random() * loadingVerbs.length );

			const indicator = document.createElement( 'div' );
			indicator.className = 'chat-message chat-message--assistant chat-message--typing';
			indicator.innerHTML = `<div class="chat-message__content">
				<span class="typing-verb">${ loadingVerbs[ currentVerbIndex ] }</span>
				<span class="typing-dots">
					<span class="typing-dot"></span>
					<span class="typing-dot"></span>
					<span class="typing-dot"></span>
				</span>
			</div>`;
			indicator.id = 'typing-indicator';
			messagesContainer.appendChild( indicator );
			messagesContainer.scrollTop = messagesContainer.scrollHeight;

			verbInterval = setInterval( () => {
				currentVerbIndex = ( currentVerbIndex + 1 ) % loadingVerbs.length;
				const verbSpan = indicator.querySelector( '.typing-verb' );
				if ( verbSpan ) {
					verbSpan.textContent = loadingVerbs[ currentVerbIndex ];
				}
			}, 2000 );
		}

		function hideTypingIndicator(): void {
			if ( verbInterval ) {
				clearInterval( verbInterval );
				verbInterval = null;
			}
			const indicator = document.getElementById( 'typing-indicator' );
			if ( indicator ) {
				indicator.remove();
			}
		}

		function setLoading( loading: boolean ): void {
			isLoading = loading;
			sendBtn.disabled = loading;
			input.disabled = loading;
			if ( newConvoBtn ) {
				newConvoBtn.disabled = loading;
			}
			if ( loading ) {
				sendBtn.classList.add( 'loading' );
				showTypingIndicator();
			} else {
				sendBtn.classList.remove( 'loading' );
				hideTypingIndicator();
			}
		}

		// ===== SEND MESSAGE =====

		async function sendMessage(): Promise< void > {
			const text = input.value.trim();
			if ( ! text || isLoading ) {
				return;
			}

			const isNewSession = ! currentSessionKey;
			if ( isNewSession ) {
				currentSessionKey = generateSessionKey();
				saveSessionKey( currentSessionKey );
				updateSessionIndicator();
			}

			const existingTitle = getStoredSessionTitle( currentSessionKey );
			if ( ! existingTitle ) {
				generateSessionTitle( currentSessionKey );
			}

			addMessage( 'user', text );
			input.value = '';
			autoResizeInput();
			setLoading( true );

			try {
				const response = await apiFetch< ChatSendResponse >( {
					path: '/spawn/v1/chat/send',
					method: 'POST',
					data: {
						message: text,
						sessionKey: currentSessionKey,
						context,
					},
				} );

				if ( response.reply ) {
					addMessage( 'assistant', response.reply );
				} else if ( response.error ) {
					addMessage( 'system', 'Error: ' + response.error );
				}

				loadSessions();
			} catch {
				addMessage( 'system', 'Failed to send message. Please try again.' );
			}

			setLoading( false );
			input.focus();
		}

		function autoResizeInput(): void {
			input.style.height = 'auto';
			input.style.height = Math.min( input.scrollHeight, 150 ) + 'px';
		}

		// ===== SIDEBAR TOGGLE (MOBILE) =====

		if ( sidebarToggle && sidebar ) {
			sidebarToggle.addEventListener( 'click', () => {
				sidebar.classList.toggle( 'wp-block-spawn-chat__sidebar--open' );
			} );
		}

		// ===== EVENT LISTENERS =====

		sendBtn.addEventListener( 'click', sendMessage );

		if ( newConvoBtn ) {
			newConvoBtn.addEventListener( 'click', startNewSession );
		}

		input.addEventListener( 'keydown', function ( e: KeyboardEvent ): void {
			if ( e.key === 'Enter' && ! e.shiftKey ) {
				e.preventDefault();
				sendMessage();
			}
		} );

		input.addEventListener( 'input', autoResizeInput );

		// ===== INITIALIZE =====

		loadSessions();
	} );
} );
