# Set Auto-Refill Ability

## Overview
Configures auto-refill settings for a customer.

## Class
`Spawn\Abilities\Ability_Set_Auto_Refill`

## Method
- `execute( array $input ): array|WP_Error`

## Input
```json
{
  "customer_id": 123,
  "enabled": true,
  "threshold": 5.0,
  "amount": 10.0
}
```

## Output
```json
{
  "customer_id": 123,
  "enabled": true,
  "threshold": 5.0,
  "amount": 10.0,
  "message": "Auto-refill enabled. Will refill $10.00 when balance falls below $5.00."
}
```

## Validation
```php
if ( $new_threshold < 1.00 || $new_threshold > 100.00 ) {
	return new WP_Error( 'invalid_threshold', __( 'Threshold must be between $1 and $100.', 'spawn' ) );
}
```
