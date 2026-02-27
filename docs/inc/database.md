# Database

## Overview
Provides CRUD operations for Spawn customers and stores provisioning, billing, and credit metadata in the `wp_spawn_customers` table.

## Table
`{$wpdb->prefix}spawn_customers`

Schema (selected fields):
- `id` bigint unsigned primary key
- `user_id` bigint unsigned
- `email` varchar(255)
- `domain` varchar(255)
- `domain_type` varchar(20)
- `domain_expires_at` datetime
- `tier` varchar(50)
- `wants_website` tinyint(1)
- `hetzner_type` varchar(50)
- `hetzner_location` varchar(50)
- `stripe_customer` varchar(255)
- `stripe_subscription` varchar(255)
- `stripe_payment_method` varchar(255)
- `server_id` varchar(255)
- `server_ip` varchar(45)
- `agent_password` varchar(255)
- `status` varchar(50)
- `credit_balance` decimal(10,2)
- `auto_refill_enabled` tinyint(1)
- `auto_refill_threshold` decimal(10,2)
- `auto_refill_amount` decimal(10,2)
- `renewal_warnings_sent` text
- `domain_auto_renew` tinyint(1)
- `customer_region` varchar(10)

## Methods
- `get_table_name(): string` returns full table name.
- `create_tables(): void` creates the customers table.
- `create_customer(array $data): int|false` inserts a customer record.
- `update_customer(int $id, array $data): bool` updates a customer record.
- `get_customer(int $id): ?array` returns a customer by ID.
- `get_all_customers(string $order_by = 'created_at', string $order = 'DESC'): array` returns a list of customers.
- `get_customer_by_subscription(string $subscription_id): ?array`
- `get_customer_by_domain(string $domain): ?array`
- `get_customer_by_email(string $email): ?array`
- `get_customer_by_user_id(int $user_id): ?array`
- `get_customer_by_server_ip(string $ip): ?array`
- `update_tier(int $id, string $tier): bool`
- `update_wants_website(int $id, bool $wants_website): bool`
- `deduct_credits(int $id, float $amount): bool`
- `get_credit_balance(int $id): ?float`
- `add_credits(int $id, float $amount): bool`
- `update_auto_refill(int $id, bool $enabled, int $threshold = 100, int $amount = 1000): bool`
- `update_auto_refill_settings(int $id, bool $enabled, float $threshold = 5.00, float $amount = 10.00): bool`
- `get_auto_refill_settings(int $id): ?array`
- `get_customers_needing_refill(): array`
- `get_expiring_domains(int $days = 30): array`
- `renew_domain(int $id): bool`
- `update_payment_method(int $id, string $payment_method_id): bool`
- `get_payment_method(int $id): ?string`
- `schedule_deletion(int $id, int $grace_days = 7): bool`
- `get_customers_pending_deletion(): array`
- `get_cancelling_customers(): array`
- `mark_deleted(int $id): bool`
- `reactivate_customer(int $id): bool`
- `set_cloudflare_record_id(int $id, string $record_id): bool`
- `set_hetzner_server_id(int $id, string $server_id): bool`

## Usage
```php
$customer_id = \Spawn\Database::create_customer( [
	'email' => $email,
	'domain' => $domain,
	'tier' => 'starter',
	'wants_website' => true,
] );
```

```php
\Spawn\Database::update_customer( $customer_id, [
	'status' => 'active',
] );
```

## Table Creation
```php
$sql = "CREATE TABLE {$table_name} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	user_id bigint(20) unsigned DEFAULT NULL,
	email varchar(255) NOT NULL,
	status varchar(50) NOT NULL DEFAULT 'pending',
	created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id)
) {$charset_collate};";
```
