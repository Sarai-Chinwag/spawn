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
		const messages = [];

		// Session management.
		const SESSION_KEY_STORAGE = 'spawn_chat_session_key';

		function generateSessionKey() {
			return 'webchat-' + Date.now() + '-' + Math.random().toString( 36 ).substr( 2, 9 );
		}

		function getSessionKey() {
			let key = localStorage.getItem( SESSION_KEY_STORAGE );
			if ( ! key ) {
				key = generateSessionKey();
				localStorage.setItem( SESSION_KEY_STORAGE, key );
			}
			return key;
		}

		function resetSession() {
			const newKey = generateSessionKey();
			localStorage.setItem( SESSION_KEY_STORAGE, newKey );
			messages.length = 0;
			renderMessages();
			updateSessionIndicator();
			showWelcomeMessage();
		}

		function updateSessionIndicator() {
			if ( sessionIndicator ) {
				const key = getSessionKey();
				sessionIndicator.textContent = 'Session: ' + key.substr( -8 );
			}
		}

		let sessionKey = getSessionKey();
		updateSessionIndicator();

		function addMessage( role, content ) {
			messages.push( { role, content } );
			renderMessages();
		}

		function renderMessages() {
			messagesContainer.innerHTML = messages
				.map(
					( msg ) =>
						`<div class="chat-message chat-message--${ msg.role }">
						<div class="chat-message__content">${ escapeHtml(
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
			return div.innerHTML.replace( /\n/g, '<br>' );
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
			} else {
				sendBtn.classList.remove( 'loading' );
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
			// Welcome message based on context.
			if ( context.is_admin ) {
				addMessage(
					'assistant',
					"Hey! 👋 What can I help you with?"
				);
			} else if ( context.status === 'provisioning' ) {
				addMessage(
					'assistant',
					"Hi! Your website is being set up right now. This usually takes a few minutes. I'll let you know when it's ready!"
				);
			} else if ( context.status === 'active' && ! context.has_mobile ) {
				addMessage(
					'assistant',
					`Hi! Your website at ${ context.domain } is live! 🎉\n\nWant to message me from your phone? I can walk you through setting up Telegram or Discord in about 5 minutes.`
				);
			} else if ( context.status === 'active' ) {
				addMessage(
					'assistant',
					`Hey! How can I help with ${ context.domain } today?`
				);
			} else if ( context.status === 'failed' ) {
				addMessage(
					'system',
					"There was a problem setting up your website. We're looking into it and will email you shortly."
				);
			} else {
				addMessage(
					'assistant',
					"Hi! How can I help you today?"
				);
			}
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

		// Show initial welcome.
		showWelcomeMessage();
	} );
} );
