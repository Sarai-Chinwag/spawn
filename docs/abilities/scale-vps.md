# Scale VPS Ability

## Overview
Resizes a customer's Hetzner VPS via the hcloud CLI.

## Class
`Spawn\Abilities\Ability_Scale_VPS`

## Method
- `execute( array $input ): array|WP_Error`

## Input
```json
{
  "customer_id": 123,
  "new_tier": "cpx31"
}
```

## Output
```json
{
  "success": true,
  "new_tier": "cpx31",
  "server_id": "123456",
  "effective_date": "2026-02-01 10:00:00",
  "note": "Server resize initiated. May require reboot."
}
```

## Execution
```php
$command = sprintf(
	'HCLOUD_TOKEN=%s /usr/local/bin/hcloud server change-type %s %s --keep-disk 2>&1',
	escapeshellarg( getenv( 'HETZNER_API_TOKEN' ) ?: '' ),
	escapeshellarg( $server_id ),
	escapeshellarg( $new_tier )
);
```

## Notes
- Valid tiers are provided by `\Spawn\Config::get_valid_hetzner_types()`.
