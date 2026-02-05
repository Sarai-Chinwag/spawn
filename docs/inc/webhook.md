# Webhook

## Overview
Handles incoming webhooks for provisioning (Sweatpants) and Stripe events via the stripe-integration plugin.

## Routes

### POST /spawn/v1/webhook/provisioner
Processes Sweatpants callbacks.

Headers:
- `X-Spawn-Internal-Key` (string)
- `Authorization: Bearer <token>` (Sweatpants token)

```php
register_rest_route(
	'spawn/v1',
	'/webhook/provisioner',
	[
		'methods'  => 'POST',
		'callback' => [ __CLASS__, 'handle_provisioner_webhook' ],
	]
);
```

## Stripe Webhook Handlers
These are triggered by the stripe-integration plugin:
- `handle_checkout_completed( $session, $event )`
- `handle_invoice_paid( $invoice, $event )`
- `handle_payment_failed( $invoice, $event )`
- `handle_subscription_cancelled( $subscription, $event )`

## Methods
- `init(): void` registers routes and Stripe webhook hooks.
- `register_routes(): void` registers the provisioner endpoint.
- `verify_provisioner_webhook( WP_REST_Request $request ): bool|WP_Error` validates webhook headers.
- `handle_provisioner_webhook( WP_REST_Request $request ): WP_REST_Response|WP_Error` processes `provisioning_complete`.
- `handle_checkout_completed( $session, $event ): void` routes credit purchases, domain renewals, or subscription checkouts.
- `handle_invoice_paid( $invoice, $event ): void` marks subscription active and updates renewal time.
- `handle_payment_failed( $invoice, $event ): void` updates status to `payment_failed`.
- `handle_subscription_cancelled( $subscription, $event ): void` schedules deletion and sends cancellation email.

## Hooks and Filters
- `stripe_integration_webhook_checkout_session_completed`
- `stripe_integration_webhook_invoice_paid`
- `stripe_integration_webhook_invoice_payment_failed`
- `stripe_integration_webhook_customer_subscription_deleted`
