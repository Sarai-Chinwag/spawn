# Name.com

## Overview
Wraps Name.com API operations for domain availability, renewal, and pricing.

## Configuration
- `spawn_namecom_username`
- `spawn_namecom_token`
- `spawn_namecom_test_mode` (boolean)

## Methods
- `check_availability(string $domain): array|WP_Error`
- `search(string $keyword, array $tlds = [ 'com', 'net', 'org' ]): array|WP_Error`
- `get_domain(string $domain): array|WP_Error`
- `renew(string $domain, int $years = 1): array|WP_Error`
- `get_renewal_price(string $domain): float|WP_Error`

## Example
```php
$result = \Spawn\Name_Com::check_availability( 'example.com' );
```

```php
$renewal = \Spawn\Name_Com::renew( 'example.com', 1 );
```
