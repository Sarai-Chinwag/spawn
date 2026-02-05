# Get Usage Ability

## Overview
Returns usage period bounds, credit balance, and auto-refill flag.

## Class
`Spawn\Abilities\Ability_Get_Usage`

## Method
- `execute( array $input ): array|WP_Error`

## Input
```json
{
  "customer_id": 123,
  "period": "current"
}
```

## Output
```json
{
  "customer_id": 123,
  "credit_balance": 12.34,
  "auto_refill": true,
  "bandwidth_mb": 0,
  "storage_mb": 0,
  "period_start": "2026-02-01 00:00:00",
  "period_end": "2026-02-28 23:59:59",
  "period": "current"
}
```

## Period Calculation
```php
if ( 'current' === $period ) {
	$period_start = gmdate( 'Y-m-01 00:00:00' );
	$period_end   = gmdate( 'Y-m-t 23:59:59' );
} else {
	$period_start = gmdate( 'Y-m-01 00:00:00', strtotime( 'first day of last month' ) );
	$period_end   = gmdate( 'Y-m-t 23:59:59', strtotime( 'last day of last month' ) );
}
```
