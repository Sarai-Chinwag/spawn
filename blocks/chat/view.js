import apiFetch from '@wordpress/api-fetch';

document.addEventListener( 'DOMContentLoaded', function () {
	const blocks = document.querySelectorAll( '.wp-block-spawn-chat' );

	blocks.forEach( function ( block ) {
		const context = JSON.parse( block.dataset.context || '{}' );
		const messagesContainer = block.querySelector( '.wp-block-spawn-chat__messages' );
		const input = block.querySelector( '.wp-block-spawn-chat__input' );
		const sendBtn = block.querySelector( '.wp-block-spawn-chat__send' );
		const newConvoBtn = block.querySelector( '.wp-block-spawn-chat__new-convo' );
		const sessionIndicator = block.querySelector( '.wp-block-spawn-chat__session-id' );
		const sessionsContainer = block.querySelector( '.wp-block-spawn-chat__sessions' );
		const sidebar = block.querySelector( '.wp-block-spawn-chat__sidebar' );
		const sidebarToggle = block.querySelector( '.wp-block-spawn-chat__sidebar-toggle' );

		if ( ! messagesContainer || ! input || ! sendBtn ) {
			return;
		}

		let isLoading = false;
		let currentSessionKey = '';
		let sessions = [];

		// Sarai Chinwag branded loading verbs
		const loadingVerbs = [
			'Conjuring', 'Channeling', 'Manifesting', 'Divining', 'Meditating on',
			'Brewing', 'Hatching', 'Nesting on', 'Perching on', 'Pondering',
			'Musing about', 'Wondering about', 'Enchanting', 'Cultivating',
			'Blooming', 'Unfurling', 'Crystallizing', 'Dreaming up', 'Gazing into',
			'Communing with',
		];
		let verbInterval = null;
		let currentVerbIndex = 0;

		// Word bank for session title generation (Sarai vibes)
		const sessionWordBank = [
			'curious', 'mystical', 'cosmic', 'enchanted', 'wandering',
			'dreaming', 'starlit', 'moonlit', 'crystal', 'golden',
			'whispering', 'dancing', 'glowing', 'hidden', 'sacred',
			'crow', 'sparrow', 'phoenix', 'butterfly', 'firefly',
			'musing', 'wonder', 'quest', 'journey', 'vision',
			'bloom', 'garden', 'river', 'mountain', 'star',
			'feather', 'sunflower', 'twilight', 'aurora', 'ember',
		];

		// Quick fallback while AI generates
		function generateFallbackName() {
			const adj = sessionWordBank[ Math.floor( Math.random() * 15 ) ]; // First 15 are adjectives
			const noun = sessionWordBank[ 15 + Math.floor( Math.random() * 15 ) ]; // Rest are nouns
			return adj.charAt( 0 ).toUpperCase() + adj.slice( 1 ) + ' ' + noun.charAt( 0 ).toUpperCase() + noun.slice( 1 );
		}

		// Generate AI-powered session title via system agent
		async function generateSessionTitle( sessionKey ) {
			const username = context.username || 'friend';
			const wordBankSample = sessionWordBank.sort( () => 0.5 - Math.random() ).slice( 0, 12 ).join( ', ' );

			try {
				const response = await apiFetch( {
					path: '/spawn/v1/chat/generate-title',
					method: 'POST',
					data: {
						username,
						wordBank: wordBankSample,
					},
				} );

				if ( response.title ) {
					storeSessionTitle( sessionKey, response.title );
					renderSessions(); // Update UI
					return response.title;
				}
			} catch ( error ) {
				console.log( 'Title generation failed, using fallback' );
			}

			// Fallback to random combo
			const fallback = generateFallbackName();
			storeSessionTitle( sessionKey, fallback );
			return fallback;
		}

		// ===== SESSION MANAGEMENT =====

		// Storage key for persisting current session
		const STORAGE_KEY = 'spawn_webchat_session';

		function generateSessionKey() {
			return 'webchat-' + Date.now() + '-' + Math.random().toString( 36 ).substr( 2, 9 );
		}

		function getSavedSessionKey() {
			try {
				return localStorage.getItem( STORAGE_KEY ) || '';
			} catch ( e ) {
				return '';
			}
		}

		function saveSessionKey( key ) {
			try {
				if ( key ) {
					localStorage.setItem( STORAGE_KEY, key );
				} else {
					localStorage.removeItem( STORAGE_KEY );
				}
			} catch ( e ) {
				// Ignore storage errors
			}
		}

		async function loadSessions() {
			if ( ! sessionsContainer ) return;

			// Check for a persisted session first
			const savedKey = getSavedSessionKey();

			try {
				const response = await apiFetch( { path: '/spawn/v1/chat/sessions' } );
				let allSessions = response.sessions || response || [];

				// Filter to only show webchat sessions (not Discord, Telegram, etc.)
				sessions = allSessions.filter( ( session ) => {
					const key = session.sessionKey || session.key || '';
					return key.includes( 'webchat' );
				} );

				// Sort by updatedAt descending (most recent first)
				sessions.sort( ( a, b ) => {
					const dateA = new Date( a.updatedAt || 0 );
					const dateB = new Date( b.updatedAt || 0 );
					return dateB - dateA;
				} );

				renderSessions();

				// Priority: 1) Saved session, 2) Most recent webchat, 3) New session
				if ( savedKey && sessions.some( ( s ) => ( s.sessionKey || s.key ) === savedKey ) ) {
					selectSession( savedKey );
				} else if ( sessions.length > 0 && ! currentSessionKey ) {
					const firstKey = sessions[ 0 ].sessionKey || sessions[ 0 ].key;
					selectSession( firstKey );
				} else if ( ! currentSessionKey ) {
					startNewSession();
				}
			} catch ( error ) {
				console.error( 'Failed to load sessions:', error );
				sessionsContainer.innerHTML = '<div class="wp-block-spawn-chat__sessions-loading">Could not load chats</div>';
				// Restore saved session or start new
				if ( savedKey ) {
					currentSessionKey = savedKey;
					updateSessionIndicator();
					messagesContainer.innerHTML = '';
				} else if ( ! currentSessionKey ) {
					startNewSession();
				}
			}
		}

		function renderSessions() {
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

			// Add click handlers
			sessionsContainer.querySelectorAll( '.wp-block-spawn-chat__session-item' ).forEach( ( item ) => {
				item.addEventListener( 'click', () => {
					const key = item.dataset.sessionKey;
					if ( key && key !== currentSessionKey ) {
						selectSession( key );
					}
				} );
			} );
		}

		function getSessionTitle( key, session = null ) {
			// Use stored title if available
			if ( session && session.displayName && ! session.displayName.startsWith( 'webchat-' ) ) {
				return session.displayName;
			}

			// Check localStorage for custom title
			const storedTitle = getStoredSessionTitle( key );
			if ( storedTitle ) {
				return storedTitle;
			}

			// Fallback for webchat sessions (AI title will be generated async)
			if ( key.startsWith( 'webchat-' ) ) {
				return generateFallbackName();
			}

			return 'Conversation';
		}

		function getStoredSessionTitle( key ) {
			try {
				const titles = JSON.parse( localStorage.getItem( 'spawn_session_titles' ) || '{}' );
				return titles[ key ] || null;
			} catch ( e ) {
				return null;
			}
		}

		function storeSessionTitle( key, title ) {
			try {
				const titles = JSON.parse( localStorage.getItem( 'spawn_session_titles' ) || '{}' );
				titles[ key ] = title;
				localStorage.setItem( 'spawn_session_titles', JSON.stringify( titles ) );
			} catch ( e ) {
				// Ignore storage errors
			}
		}

		function formatDate( dateStr ) {
			const date = new Date( dateStr );
			const now = new Date();
			const diffMs = now - date;
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

		async function selectSession( key ) {
			currentSessionKey = key;
			saveSessionKey( key ); // Persist across page loads
			updateSessionIndicator();
			renderSessions(); // Update active state

			// Load history from OpenClaw
			messagesContainer.innerHTML = '<div class="chat-message chat-message--system">Loading conversation...</div>';

			try {
				const response = await apiFetch( {
					path: `/spawn/v1/chat/sessions/${ encodeURIComponent( key ) }/history?limit=50`,
				} );

				const messages = response.messages || response || [];
				renderHistory( messages );
			} catch ( error ) {
				console.error( 'Failed to load history:', error );
				messagesContainer.innerHTML = '';
			}

			// Close sidebar on mobile
			if ( sidebar && window.innerWidth <= 768 ) {
				sidebar.classList.remove( 'wp-block-spawn-chat__sidebar--open' );
			}
		}

		function renderHistory( messages ) {
			if ( messages.length === 0 ) {
				messagesContainer.innerHTML = '';
				return;
			}

			messagesContainer.innerHTML = messages
				.filter( ( msg ) => msg.role === 'user' || msg.role === 'assistant' )
				.map( ( msg ) => `
					<div class="chat-message chat-message--${ msg.role }">
						<div class="chat-message__content">${ parseMarkdown( msg.content || '' ) }</div>
					</div>
				` )
				.join( '' );

			messagesContainer.scrollTop = messagesContainer.scrollHeight;
		}

		function startNewSession() {
			currentSessionKey = generateSessionKey();
			saveSessionKey( currentSessionKey ); // Persist across page loads
			updateSessionIndicator();
			messagesContainer.innerHTML = '';
			renderSessions();
		}

		function updateSessionIndicator() {
			if ( sessionIndicator ) {
				sessionIndicator.textContent = currentSessionKey
					? 'Session: ' + currentSessionKey.substr( -8 )
					: '';
			}
		}

		// ===== MESSAGE HANDLING =====

		function addMessage( role, content ) {
			const msgDiv = document.createElement( 'div' );
			msgDiv.className = `chat-message chat-message--${ role }`;
			msgDiv.innerHTML = `<div class="chat-message__content">${ parseMarkdown( content ) }</div>`;
			messagesContainer.appendChild( msgDiv );
			messagesContainer.scrollTop = messagesContainer.scrollHeight;
		}

		function escapeHtml( text ) {
			const div = document.createElement( 'div' );
			div.textContent = text;
			return div.innerHTML;
		}

		function escapeAttr( text ) {
			return text.replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' );
		}

		function parseMarkdown( text ) {
			let html = escapeHtml( text );

			// Code blocks
			html = html.replace( /```(\w*)\n?([\s\S]*?)```/g, ( match, lang, code ) => {
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

		function showTypingIndicator() {
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

		function hideTypingIndicator() {
			if ( verbInterval ) {
				clearInterval( verbInterval );
				verbInterval = null;
			}
			const indicator = document.getElementById( 'typing-indicator' );
			if ( indicator ) {
				indicator.remove();
			}
		}

		function setLoading( loading ) {
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

		async function sendMessage() {
			const text = input.value.trim();
			if ( ! text || isLoading ) {
				return;
			}

			// Ensure we have a session
			const isNewSession = ! currentSessionKey;
			if ( isNewSession ) {
				currentSessionKey = generateSessionKey();
				saveSessionKey( currentSessionKey );
				updateSessionIndicator();
			}

			// For new sessions, generate AI title (async, doesn't block)
			const existingTitle = getStoredSessionTitle( currentSessionKey );
			if ( ! existingTitle ) {
				generateSessionTitle( currentSessionKey ); // Fire and forget
			}

			addMessage( 'user', text );
			input.value = '';
			autoResizeInput();
			setLoading( true );

			try {
				const response = await apiFetch( {
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

				// Refresh session list (new session might have been created)
				loadSessions();
			} catch ( error ) {
				addMessage( 'system', 'Failed to send message. Please try again.' );
			}

			setLoading( false );
			input.focus();
		}

		function autoResizeInput() {
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

		input.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' && ! e.shiftKey ) {
				e.preventDefault();
				sendMessage();
			}
		} );

		input.addEventListener( 'input', autoResizeInput );

		// ===== INITIALIZE =====

		// Load sessions from OpenClaw
		loadSessions();
	} );
} );
