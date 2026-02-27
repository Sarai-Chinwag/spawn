# REST API

## Overview
Registers `spawn/v1` REST endpoints for checkout, auth, customer accounts, credits, chat, and domain settings.

## Namespace
`spawn/v1`

## Constants
- `NAMESPACE` (`spawn/v1`)
- `EU_COUNTRIES` (array of ISO country codes)
- `ANTHROPIC_PRICING` (per MTok pricing map)

## Methods
- `init(): void`
- `register_routes(): void`
- `search_domain( WP_REST_Request $request ): WP_REST_Response|WP_Error`
- `create_checkout_session( WP_REST_Request $request ): WP_REST_Response|WP_Error`
- `get_tiers(): WP_REST_Response`
- `auth_login( WP_REST_Request $request ): WP_REST_Response|WP_Error`
- `auth_register( WP_REST_Request $request ): WP_REST_Response|WP_Error`
- `auth_me(): WP_REST_Response`
- `auth_logout(): WP_REST_Response`
- `auth_google_configured(): WP_REST_Response`
- `auth_google_start(): WP_REST_Response|WP_Error`
- `auth_google_callback( WP_REST_Request $request ): WP_REST_Response|WP_Error`
- `get_customer(): WP_REST_Response|WP_Error`
- `get_billing_portal(): WP_REST_Response|WP_Error`
- `upgrade_plan( WP_REST_Request $request ): WP_REST_Response|WP_Error`
- `toggle_website( WP_REST_Request $request ): WP_REST_Response|WP_Error`
- `cancel_subscription(): WP_REST_Response|WP_Error`
- `get_invoices(): WP_REST_Response|WP_Error`
- `get_credit_balance(): WP_REST_Response|WP_Error`
- `purchase_credits( WP_REST_Request $request ): WP_REST_Response|WP_Error`
- `deduct_credits( WP_REST_Request $request ): WP_REST_Response|WP_Error`
- `get_credit_packages(): WP_REST_Response`
- `get_auto_refill(): WP_REST_Response|WP_Error`
- `update_auto_refill_settings( WP_REST_Request $request ): WP_REST_Response|WP_Error`
- `update_auto_refill( WP_REST_Request $request ): WP_REST_Response|WP_Error`
- `verify_internal_request( WP_REST_Request $request ): bool|WP_Error`
- `chat_send( WP_REST_Request $request ): WP_REST_Response|WP_Error`
- `chat_sessions_list(): WP_REST_Response|WP_Error`
- `chat_session_history( WP_REST_Request $request ): WP_REST_Response|WP_Error`
- `chat_generate_title( WP_REST_Request $request ): WP_REST_Response`
- `verify_litellm_callback( WP_REST_Request $request ): bool|WP_Error`
- `litellm_callback( WP_REST_Request $request ): WP_REST_Response`
- `can_set_domain_auto_renew(): bool|WP_Error`
- `get_domain_auto_renew(): WP_REST_Response|WP_Error`
- `update_domain_auto_renew( WP_REST_Request $request ): WP_REST_Response|WP_Error`
- `process_domain_renewal_payment( int $customer_id, string $domain ): bool|WP_Error`

## Routes

### GET /domain/search
Search for domain availability.

Parameters:
- `domain` (string, required)

```php
register_rest_route(
	self::NAMESPACE,
	'/domain/search',
	[
		'methods'  => 'GET',
		'callback' => [ __CLASS__, 'search_domain' ],
	]
);
```

### POST /checkout/session
Create a Stripe checkout session for subscription signup.

Parameters:
- `email` (string, required, email)
- `tier` (string, enum: starter, pro, business)
- `wants_website` (boolean)
- `domain` (string)
- `domain_type` (string, enum: subdomain, register, byod)
- `domain_price` (number)

### GET /tiers
Return public tier data.

### POST /auth/login
Login with email/password.

Parameters:
- `email` (string, required)
- `password` (string, required)

### POST /auth/register
Register a new user and set `spawn_customer` role.

Parameters:
- `email` (string, required)
- `password` (string, required, minLength: 8)

### GET /auth/me
Return current user info if logged in.

### POST /auth/logout
Logout current user.

### GET /auth/google/configured
Return whether Google OAuth is configured.

### GET /auth/google
Start Google OAuth flow.

### GET /auth/google/callback
Handle Google OAuth callback.

### GET /customer/me
Return current customer record.

### GET /customer/billing-portal
Return Stripe billing portal URL.

### POST /customer/upgrade
Upgrade subscription tier.

Parameters:
- `tier` (string, required)

### POST /customer/toggle-website
Update `wants_website` preference.

Parameters:
- `wants_website` (boolean, required)

### POST /customer/cancel
Cancel subscription (Stripe) and mark cancelled.

### GET /customer/invoices
Return Stripe invoices for current customer.

### GET /credits/balance
Return current credit balance and auto-refill settings.

### POST /credits/purchase
Create checkout session to purchase credits.

Parameters:
- `amount` (integer, required, minimum 10)

### POST /credits/deduct
Internal credit deduction endpoint.

Parameters:
- `customer_id` (integer, required)
- `amount` (number, required)
- `reason` (string, default: api_call)

### GET /credits/packages
Return available credit packages.

### GET /account/auto-refill
Return auto-refill settings (dollar-based).

### POST /account/auto-refill
Update auto-refill settings (dollar-based).

Parameters:
- `enabled` (boolean, required)
- `threshold` (number, default 5.00)
- `amount` (number, default 10.00)

### POST /credits/auto-refill
Legacy auto-refill endpoint (credit-based).

Parameters:
- `enabled` (boolean, required)
- `threshold` (integer, default 100)
- `amount` (integer, default 1000)

### POST /litellm/callback
LiteLLM usage callback for credit deduction.

```php
$credits_to_deduct = $total_cost * 100;
$credits_to_deduct = max( 0.01, round( $credits_to_deduct, 2 ) );
```

### POST /chat/send
Send a chat message to the customer's AI.

Parameters:
- `message` (string, required)
- `sessionKey` (string)
- `context` (object)

### GET /chat/sessions
List chat sessions from the customer's OpenCode server.

### GET /chat/sessions/{sessionKey}/history
Return chat session history.

Parameters:
- `sessionKey` (string, required)
- `limit` (integer, default 50)

### POST /chat/generate-title
Generate a session title via Data Machine if available.

Parameters:
- `username` (string, default: friend)
- `wordBank` (string)

### GET /account/domain-auto-renew
Return domain auto-renew settings.

### POST /account/domain-auto-renew
Update domain auto-renew settings.

Parameters:
- `enabled` (boolean, required)

## Authentication
- Public: `/domain/search`, `/checkout/session`, `/tiers`, auth and Google OAuth start/callback, `/credits/packages`.
- Logged in: customer, credits (except packages), chat, account routes.
- Internal: `/credits/deduct` uses `X-Spawn-Internal-Key` header.

## Hooks and Filters
- `spawn_credits_auto_refill_needed` fires when credits drop below threshold.
- `spawn_domain_renewed` fires after a paid renewal completes.

## Examples
```php
register_rest_route(
	self::NAMESPACE,
	'/credits/deduct',
	[
		'methods'             => 'POST',
		'callback'            => [ __CLASS__, 'deduct_credits' ],
		'permission_callback' => [ __CLASS__, 'verify_internal_request' ],
	]
);
```

```php
return new WP_REST_Response( Config::get_public_tiers() );
```
