/**
 * Shared TypeScript types for Spawn blocks.
 */

export interface ApiError {
	code?: string;
	message?: string;
}

export interface Customer {
	id: number;
	domain?: string;
	subdomain?: boolean;
	tier?: string;
	billing_mode?: 'managed' | 'byok';
	wants_website?: boolean;
	server_type?: string;
	credit_balance?: number;
	server_ip?: string;
	status?: string;
	created_at?: string;
}

export interface CustomerResponse {
	success: boolean;
	customer?: Customer;
}

export interface TierInfo {
	name: string;
	price: number;
}

export interface TiersMap {
	[ key: string ]: TierInfo;
}

export interface CreditBalanceResponse {
	balance: number;
	auto_refill?: {
		enabled: boolean;
		threshold: number;
		amount: number;
	};
}

export interface Invoice {
	created: number;
	amount_paid: number;
	status: string;
	invoice_pdf: string;
}

export type StatusType = 'info' | 'success' | 'error' | 'warning';
