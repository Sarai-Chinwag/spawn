# Cleanup

## Overview
Deletes VPS and DNS resources after cancellation grace period and sends notification emails.

## Constants
- `GRACE_PERIOD_DAYS` (7)

## Hooks
- `spawn_process_deletions` (hourly)

## Methods
- `init(): void` schedules hourly deletion job.
- `process_pending_deletions(): void` deletes pending customers.
- `delete_customer_resources( array $customer ): bool`
- `send_cancellation_email( array $customer ): void`
- `send_deletion_reminder( array $customer, int $days_remaining ): void`
- `deactivate(): void` unschedules cron.

## Deletion Workflow
```php
if ( ! empty( $customer['hetzner_server_id'] ) || ! empty( $customer['server_id'] ) ) {
	$server_id = $customer['hetzner_server_id'] ?: $customer['server_id'];
	self::delete_vps( $server_id );
}
```

## Example
```php
add_action( 'spawn_process_deletions', [ \Spawn\Cleanup::class, 'process_pending_deletions' ] );
```
