# Cron

## Overview
Schedules domain renewal checks and auto-refill handling, including reminder emails and auto-renewal workflows.

## Hooks
- `spawn_domain_renewal_check` (daily)
- `spawn_credits_auto_refill_needed`
- `spawn_auto_refill_success`
- `spawn_auto_refill_failed`

## Constants
- `RENEWAL_HOOK` (`spawn_domain_renewal_check`)

## Methods
- `init(): void`
- `schedule_events(): void`
- `unschedule_events(): void`
- `process_domain_renewals(): void`
- `clear_warnings_sent( int $customer_id ): void`
- `handle_auto_refill_needed( int $customer_id, array $settings ): void`
- `send_auto_refill_success_email( int $customer_id, int $credits, array $result ): void`
- `send_auto_refill_failed_email( int $customer_id, WP_Error $error ): void`

## Behavior
- Sends renewal warnings at 30, 14, 7, and 1 days before expiry.
- Attempts auto-renewal 7 days before expiry when enabled.
- Triggers low balance warning when balance drops below $2 and auto-refill is disabled.

## Warning Email Example
```php
$subject = match ( $warning_interval ) {
	30 => sprintf( __( 'Your domain %s expires in 30 days', 'spawn' ), $domain ),
	14 => sprintf( __( 'Your domain %s expires in 2 weeks', 'spawn' ), $domain ),
	7  => sprintf( __( 'URGENT: Your domain %s expires in 7 days', 'spawn' ), $domain ),
	1  => sprintf( __( 'FINAL WARNING: Your domain %s expires tomorrow', 'spawn' ), $domain ),
};
```

## Example
```php
add_action( \Spawn\Cron::RENEWAL_HOOK, [ \Spawn\Cron::class, 'process_domain_renewals' ] );
```
