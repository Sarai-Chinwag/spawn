import apiFetch from '@wordpress/api-fetch';

document.addEventListener( 'DOMContentLoaded', function () {
	const blocks = document.querySelectorAll( '.wp-block-spawn-chat' );

	blocks.forEach( function ( block ) {
		const context = JSON.parse( block.dataset.context || '{}' );
		const messagesContainer = block.querySelector(
			'.wp-block-spawn-chat__messages'
		);
		const input = block.querySelector( '.wp-block-spawn-chat__input' );
		const sendBtn = block.querySelector( '.wp-block-spawn-chat__send' );
		const newConvoBtn = block.querySelector( '.wp-block-spawn-chat__new-convo' );
		const sessionIndicator = block.querySelector( '.wp-block-spawn-chat__session-id' );

		if ( ! messagesContainer || ! input || ! sendBtn ) {
			return;
		}

		let isLoading = false;

		const blockSessionKey = ( block.dataset.sessionKey || '' ).trim();

		// Session management.
		const SESSION_KEY_STORAGE = 'spawn_chat_session_key';
		const MESSAGES_STORAGE = 'spawn_chat_messages';

		// Load persisted messages or start fresh.
		function loadMessages() {
			try {
				const stored = localStorage.getItem( MESSAGES_STORAGE );
				return stored ? JSON.parse( stored ) : [];
			} catch ( e ) {
				return [];
			}
		}

		function saveMessages( msgs ) {
			try {
				localStorage.setItem( MESSAGES_STORAGE, JSON.stringify( msgs ) );
			} catch ( e ) {
				// Storage full or unavailable - ignore.
			}
		}

		const messages = loadMessages();

		function generateSessionKey() {
			return 'webchat-' + Date.now() + '-' + Math.random().toString( 36 ).substr( 2, 9 );
		}

		function getSessionKey() {
			if ( blockSessionKey ) {
				return blockSessionKey;
			}
			let key = localStorage.getItem( SESSION_KEY_STORAGE );
			if ( ! key ) {
				key = generateSessionKey();
				localStorage.setItem( SESSION_KEY_STORAGE, key );
			}
			return key;
		}

		function resetSession() {
			messages.length = 0;
			saveMessages( messages );
			if ( ! blockSessionKey ) {
				const newKey = generateSessionKey();
				localStorage.setItem( SESSION_KEY_STORAGE, newKey );
			}
			renderMessages();
			updateSessionIndicator();
			showWelcomeMessage();
		}

		function updateSessionIndicator() {
			if ( sessionIndicator ) {
				const key = getSessionKey();
				sessionIndicator.textContent = blockSessionKey
					? 'Session: set'
					: 'Session: ' + key.substr( -8 );
			}
		}

		let sessionKey = getSessionKey();
		updateSessionIndicator();

		function addMessage( role, content ) {
			messages.push( { role, content } );
			saveMessages( messages );
			renderMessages();
		}

		function renderMessages() {
			messagesContainer.innerHTML = messages
				.map(
					( msg ) =>
						`<div class="chat-message chat-message--${ msg.role }">
						<div class="chat-message__content">${ parseMarkdown(
							msg.content
						) }</div>
					</div>`
				)
				.join( '' );
			messagesContainer.scrollTop = messagesContainer.scrollHeight;
		}

		function escapeHtml( text ) {
			const div = document.createElement( 'div' );
			div.textContent = text;
			return div.innerHTML;
		}

		function parseMarkdown( text ) {
			// First escape HTML to prevent XSS
			let html = escapeHtml( text );

			// Code blocks (``` ... ```)
			html = html.replace( /```(\w*)\n?([\s\S]*?)```/g, ( match, lang, code ) => {
				return `<pre><code class="language-${ lang }">${ code.trim() }</code></pre>`;
			} );

			// Inline code (`code`)
			html = html.replace( /`([^`]+)`/g, '<code>$1</code>' );

			// Bold (**text** or __text__)
			html = html.replace( /\*\*([^*]+)\*\*/g, '<strong>$1</strong>' );
			html = html.replace( /__([^_]+)__/g, '<strong>$1</strong>' );

			// Italic (*text* or _text_)
			html = html.replace( /\*([^*]+)\*/g, '<em>$1</em>' );
			html = html.replace( /_([^_]+)_/g, '<em>$1</em>' );

			// Links [text](url)
			html = html.replace( /\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>' );

			// Line breaks
			html = html.replace( /\n/g, '<br>' );

			return html;
		}

		function showTypingIndicator() {
			const indicator = document.createElement( 'div' );
			indicator.className = 'chat-message chat-message--assistant chat-message--typing';
			indicator.innerHTML = `<div class="chat-message__content">
				<span class="typing-dot"></span>
				<span class="typing-dot"></span>
				<span class="typing-dot"></span>
			</div>`;
			indicator.id = 'typing-indicator';
			messagesContainer.appendChild( indicator );
			messagesContainer.scrollTop = messagesContainer.scrollHeight;
		}

		function hideTypingIndicator() {
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

		async function sendMessage() {
			const text = input.value.trim();
			if ( ! text || isLoading ) {
				return;
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
						sessionKey: getSessionKey(),
						context,
					},
				} );

				if ( response.reply ) {
					addMessage( 'assistant', response.reply );
				} else if ( response.error ) {
					addMessage(
						'system',
						'Error: ' + response.error
					);
				}
			} catch ( error ) {
				addMessage(
					'system',
					'Failed to send message. Please try again.'
				);
			}

			setLoading( false );
			input.focus();
		}

		function autoResizeInput() {
			input.style.height = 'auto';
			input.style.height =
				Math.min( input.scrollHeight, 150 ) + 'px';
		}

		function showWelcomeMessage() {
			// Only show system messages for error states, not fake assistant messages
			if ( context.status === 'failed' ) {
				addMessage(
					'system',
					"There was a problem setting up your website. We're looking into it and will email you shortly."
				);
			}
			// Otherwise start with empty chat - no fake welcome messages
		}

		// Event listeners.
		sendBtn.addEventListener( 'click', sendMessage );

		if ( newConvoBtn ) {
			newConvoBtn.addEventListener( 'click', resetSession );
		}

		input.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' && ! e.shiftKey ) {
				e.preventDefault();
				sendMessage();
			}
		} );

		input.addEventListener( 'input', autoResizeInput );

		// Render any persisted messages, or show welcome.
		if ( messages.length > 0 ) {
			renderMessages();
		} else {
			showWelcomeMessage();
		}
	} );
} );
