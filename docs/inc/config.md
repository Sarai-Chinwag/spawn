# Config

## Overview
Defines tier pricing, VPS specs, and location rules. This class is the single source of truth for tier data.

## Constants
`DEFAULT_STARTER_CREDITS` (float): Default credits for new customers.

## Methods
- `get_tiers(): array` returns tier definitions, including Stripe price IDs and Hetzner types.
- `get_tier(string $tier_id): ?array` returns a single tier definition.
- `get_tier_ids(): array` returns valid tier IDs.
- `get_hetzner_type(string $tier_id, bool $wants_website = true, string $customer_region = 'us'): ?string` returns the Hetzner type for a tier/location.
- `get_server_location(bool $wants_website, string $customer_region = 'us'): string` returns `ash` or `fsn1` based on region rules.
- `get_server_config(string $tier_id, bool $wants_website, string $customer_region = 'us'): ?array` returns VPS config data used for provisioning.
- `get_included_credits(string $tier_id): float` returns credits for a tier.
- `get_public_tiers(): array` returns tier data safe for public API responses.
- `get_tier_by_price(int $price): ?string` returns tier ID for a monthly price.
- `get_margin_breakdown(string $tier_id, bool $wants_website): ?array` returns pricing/margin breakdown.

## Data Sources
- `spawn_stripe_prices` option provides Stripe price IDs (keys: `vps_starter`, `vps_pro`, `vps_business`).

## Usage
```php
$tiers = \Spawn\Config::get_tiers();
$server_config = \Spawn\Config::get_server_config( 'starter', true, 'us' );
```

```php
$public = \Spawn\Config::get_public_tiers();
```

## Example Tier Definition
```php
'starter' => [
	'name'            => __( 'Starter', 'spawn' ),
	'price'           => 20,
	'description'     => __( 'Perfect for personal use', 'spawn' ),
	'stripe_price_id' => $prices['vps_starter'] ?? '',
	'hetzner_type_us' => 'cpx21',
	'hetzner_type_eu' => 'cpx22',
	'ram_gb'          => 4,
	'disk_gb'         => 80,
];
```
