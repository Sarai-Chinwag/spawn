# Add Credits Ability

## Overview
Adds credits to a customer's balance.

## Class
`Spawn\Abilities\Ability_Add_Credits`

## Method
- `execute( array $input ): array|WP_Error`

## Input
```json
{
  "customer_id": 123,
  "amount": 15.5
}
```

## Output
```json
{
  "success": true,
  "added": 15.5,
  "new_balance": 42.75
}
```

## Implementation
```php
$success = \Spawn\Database::add_credits( (int) $customer['id'], $amount );
```
