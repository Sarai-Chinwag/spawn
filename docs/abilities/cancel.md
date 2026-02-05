# Cancel Ability

## Overview
Cancels a subscription and schedules deletion after the grace period.

## Class
`Spawn\Abilities\Ability_Cancel`

## Method
- `execute( array $input ): array|WP_Error`

## Input
```json
{
  "customer_id": 123,
  "reason": "No longer needed",
  "confirm": true
}
```

## Output
```json
{
  "success": true,
  "status": "cancellation_scheduled",
  "scheduled_deletion": "2026-02-08 10:00:00",
  "grace_period_days": 7,
  "message": "Your subscription has been cancelled. Your site will remain accessible until ...",
  "export_instructions": {
    "methods": [
      { "name": "Full Site Backup", "command": "export-site" }
    ]
  },
  "can_reactivate": true
}
```

## Notes
- Returns a confirmation payload when `confirm` is false.

## Implementation
```php
if ( ! $confirm ) {
	return [
		'status' => 'confirmation_required',
		'grace_period_days' => \Spawn\Cleanup::GRACE_PERIOD_DAYS,
		'export_instructions' => self::get_export_instructions(),
	];
}
```
