# Renew Domain Ability

## Overview
Creates a Stripe checkout session for domain renewal.

## Class
`Spawn\Abilities\Ability_Renew_Domain`

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
  "checkout_url": "https://checkout.stripe.com/c/session/...",
  "session_id": "cs_...",
  "domain": "example.com",
  "renewal_price": 18.5
}
```

## Implementation
```php
$session = \StripeIntegration\StripeClient::create_checkout_session( [
	'mode' => 'payment',
	'line_items' => [
		[
			'price_data' => [
				'currency' => 'usd',
				'unit_amount' => $amount_cents,
			],
			'quantity' => 1,
		],
	],
] );
```
