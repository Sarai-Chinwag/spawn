# Manage Billing Ability

## Overview
Returns the Stripe customer portal URL.

## Class
`Spawn\Abilities\Ability_Manage_Billing`

## Method
- `execute( array $input ): array|WP_Error`

## Input
```json
{
  "customer_id": 123
}
```

## Output
```json
{
  "success": true,
  "portal_url": "https://billing.stripe.com/session/..."
}
```

## Implementation
```php
$portal = \StripeIntegration\StripeClient::create_billing_portal_session(
	$customer['stripe_customer'],
	home_url( '/spawn/dashboard/' )
);
```
