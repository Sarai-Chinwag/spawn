# Configuration

## Overview
Describes Spawn plugin options used by provisioning, billing, domains, and auth.

## Options
- `spawn_stripe_prices` (array) Stripe price IDs keyed by `vps_starter`, `vps_pro`, `vps_business`.
- `spawn_internal_api_key` (string) Internal REST auth key.
- `spawn_domain_markup` (float) Domain price markup multiplier.
- `spawn_litellm_callback_secret` (string) Bearer token for LiteLLM callbacks.
- `spawn_sweatpants_url` (string) Sweatpants API URL.
- `spawn_sweatpants_token` (string) Sweatpants API token.
- `spawn_namecom_username` (string)
- `spawn_namecom_token` (string)
- `spawn_namecom_test_mode` (bool)
- `spawn_openclaw_gateway_url` (string) Control-plane gateway URL.
- `spawn_openclaw_token` (string) Control-plane gateway token.
- `spawn_google_client_id` (string)
- `spawn_google_client_secret` (string)
- `spawn_cloudflare_api_token` (string)
- `spawn_cloudflare_zone_id` (string)
- `spawn_hetzner_api_token` (string)

## Usage
```php
$markup = (float) get_option( 'spawn_domain_markup', 1.5 );
```
