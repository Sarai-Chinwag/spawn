# Get Status Ability

## Overview
Returns customer subscription status, tier, and credit balance.

## Class
`Spawn\Abilities\Ability_Get_Status`

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
  "customer_id": 123,
  "email": "user@example.com",
  "domain": "example.com",
  "vps_tier": "cpx21",
  "status": "active",
  "server_ip": "203.0.113.10",
  "credit_balance": 12.34,
  "created_at": "2026-02-01 10:00:00"
}
```

## Implementation
```php
return [
	'customer_id'    => (int) $customer['id'],
	'email'          => $customer['email'],
	'domain'         => $customer['domain'],
	'vps_tier'       => $customer['vps_tier'],
	'status'         => $customer['status'],
	'server_ip'      => $customer['server_ip'],
	'credit_balance' => (float) $customer['credit_balance'],
	'created_at'     => $customer['created_at'],
];
```

## Notes
- Uses the current user when `customer_id` is not provided.
