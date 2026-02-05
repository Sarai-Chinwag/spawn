# Get Domain Renewal Info Ability

## Overview
Returns domain expiration details and renewal pricing with markup.

## Class
`Spawn\Abilities\Ability_Get_Domain_Renewal_Info`

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
  "renewable": true,
  "domain": "example.com",
  "expires_at": "2026-03-01 00:00:00",
  "expires_formatted": "March 1, 2026",
  "days_until_expiry": 24,
  "renewal_price": 18.5,
  "is_expiring_soon": true,
  "auto_renew_enabled": false
}
```

## Implementation
```php
$markup = (float) get_option( 'spawn_domain_markup', 1.5 );
$renewal_price = round( $renewal_price * $markup, 2 );
```
