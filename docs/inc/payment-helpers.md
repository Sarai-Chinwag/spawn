# Payment Helpers

## Overview
Provides Spawn-specific wrappers around Stripe Integration for credits, auto-refill, and subscription upgrades.

## Methods
- `create_credit_checkout_session( array $params ): array|WP_Error`
- `handle_credit_purchase( array $session ): bool|WP_Error`
- `get_default_payment_method( string $customer_id ): string|WP_Error`
- `charge_for_auto_refill( string $customer_id, string $payment_method_id, int $amount_cents, int $credits, int $spawn_customer_id ): array|WP_Error`
- `process_auto_refill( int $spawn_customer_id, array $settings ): bool|WP_Error`
- `update_subscription_price( string $subscription_id, string $new_price_id ): array|WP_Error`

## Hooks and Filters
- `spawn_credits_purchased`
- `spawn_auto_refill_success`
- `spawn_auto_refill_failed`

## Example
```php
$session = \Spawn\Payment_Helpers::create_credit_checkout_session( [
	'customer_email' => $email,
	'amount' => 1000,
	'credits' => 1000,
	'spawn_customer_id' => $customer_id,
] );
```

## Auto-Refill Flow
```php
$result = \Spawn\Payment_Helpers::process_auto_refill( $customer_id, [
	'amount' => 1000,
] );
```
