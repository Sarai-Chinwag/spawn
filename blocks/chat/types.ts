/**
 * TypeScript interfaces and type definitions for the chat block.
 *
 * @package Spawn
 */

export type SpawnChatState = 'unauthenticated' | 'no-credits' | 'chat' | 'credits-depleted' | 'provisioning';

export interface SpawnState {
	isAuthenticated: boolean;
	customerId: number;
	creditBalance: number;
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
}

export interface ChatContext {
	customer_id: number;
	domain: string;
	status: string;
	has_mobile: boolean;
	is_admin?: boolean;
	first_visit?: boolean;
	username: string;
}

export interface Session {
	sessionKey?: string;
	key?: string;
	displayName?: string;
	updatedAt?: string;
}

export interface ContentBlock {
	type: string;
	text?: string;
}

export interface ChatMessage {
	role: 'user' | 'assistant' | 'system';
	content: string | ContentBlock[];
}

export interface ChatSendResponse {
	reply?: string;
	error?: string;
	code?: string;
}

export interface SessionsResponse {
	sessions?: Session[];
}

export interface HistoryResponse {
	messages?: ChatMessage[];
}

export interface TitleResponse {
	title?: string;
}

export interface BalanceResponse {
	balance: number;
}

export interface PurchaseResponse {
	session_id?: string;
	checkout_url?: string;
	error?: string;
}

export interface SessionTitles {
	[ key: string ]: string;
}