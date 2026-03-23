/**
 * Spawn Chat Block - View Script
 *
 * Renders the chat interface on the frontend using @extrachill/chat.
 *
 * @package Spawn\Blocks
 */

import { createRoot } from 'react-dom/client';
import { Chat } from '@extrachill/chat';
import apiFetch from '@wordpress/api-fetch';

// Import styles.
import './style.css';

// Type for window.spawnChatConfig.
declare global {
	interface Window {
		spawnChatConfig?: {
			agentId: number;
			welcomeMessage: string;
			placeholder: string;
			showSessions: boolean;
			logoUrl: string;
			customerId: number;
			siteId: number;
		};
	}
}

// Find all chat containers and initialize.
document.addEventListener( 'DOMContentLoaded', () => {
	const containers = document.querySelectorAll< HTMLElement >(
		'#spawn-chat-root'
	);

	containers.forEach( ( container ) => {
		// Get config from data attribute.
		const configAttr = container.dataset.config;
		if ( ! configAttr ) {
			console.error( 'Spawn Chat: No config found' );
			return;
		}

		const config = JSON.parse( configAttr );

		// Tool name mappings for better UI display.
		const toolNames: Record< string, string > = {
			search_domain: __( 'Search Domain', 'spawn' ),
			create_site: __( 'Create Site', 'spawn' ),
			get_usage: __( 'Get Usage', 'spawn' ),
			manage_billing: __( 'Manage Billing', 'spawn' ),
			get_status: __( 'Check Status', 'spawn' ),
			scale_vps: __( 'Scale Server', 'spawn' ),
			add_credits: __( 'Add Credits', 'spawn' ),
			get_customer_credits: __( 'Get Credits', 'spawn' ),
		};

		// Empty state with Spawn branding.
		const emptyState = (
			<div className="spawn-chat-welcome">
				<div className="spawn-chat-logo">
					{ config.logoUrl && (
						<img src={ config.logoUrl } alt="Spawn" />
					) }
				</div>
				<p>{ config.welcomeMessage }</p>
			</div>
		);

		// Processing label for multi-turn.
		const processingLabel = ( turnCount: number ) => {
			return sprintf(
				/* translators: %d: turn number */
				__( 'Thinking... (turn %d)', 'spawn' ),
				turnCount
			);
		};

		// Create root and render.
		const root = createRoot( container );
		root.render(
			<Chat
				basePath="/wp-json/datamachine/v1/chat"
				fetchFn={ apiFetch }
				agentId={ config.agentId }
				placeholder={ config.placeholder }
				showSessions={ config.showSessions }
				emptyState={ emptyState }
				toolNames={ toolNames }
				processingLabel={ processingLabel }
				allowAttachments={ true }
				acceptFileTypes="image/*,video/*,.pdf,.txt,.md"
			/>
		);
	} );
} );

// Helper function for translations.
function __( text: string, domain: string ): string {
	// @ts-ignore - wp.i18n is available in WordPress.
	if ( typeof wp !== 'undefined' && wp.i18n ) {
		return wp.i18n.__( text, domain );
	}
	return text;
}

// Helper function for sprintf.
function sprintf( format: string, ...args: any[] ): string {
	// @ts-ignore - wp.i18n is available in WordPress.
	if ( typeof wp !== 'undefined' && wp.i18n && wp.i18n.sprintf ) {
		return wp.i18n.sprintf( format, ...args );
	}
	// Simple fallback.
	return format.replace( /%d/g, () => String( args.shift() ) );
}
