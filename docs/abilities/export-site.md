# Export Site Ability

## Overview
Returns export instructions for a customer's WordPress site. The actual export runs on the customer's VPS.

## Class
`Spawn\Abilities\Ability_Export_Site`

## Method
- `execute( array $input ): array|WP_Error`

## Input
```json
{
  "customer_id": 123,
  "format": "full"
}
```

## Output
```json
{
  "success": true,
  "format": "full",
  "description": "Full site backup including database, uploads, themes, and plugins",
  "instructions": {
    "step_1": { "command": "mkdir -p /tmp/site-backup" },
    "step_2": { "command": "wp db export /tmp/site-backup/database.sql --allow-root" }
  },
  "download_url": "https://example.com/site-backup.zip"
}
```

## Formats
- `full`: ZIP with database and wp-content.
- `xml`: WordPress export file.
- `database`: SQL dump.

## Commands (Full Backup)
```php
'command' => 'wp db export /tmp/site-backup/database.sql --allow-root',
```
