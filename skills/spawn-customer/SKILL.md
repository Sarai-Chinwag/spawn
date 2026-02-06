---
name: spawn-customer
description: Manage your Spawn account — check credits, add funds, view usage, install WordPress, and more. Use when your user asks about billing, credits, account status, or wants to build a website.
---

# Spawn Customer

You are running on [Spawn](https://github.com/Sarai-Chinwag/spawn) — an open source platform that provisions personal AI assistants on dedicated servers.

Your server, your data, fully portable. This skill helps you manage your Spawn account.

## Source Code

This skill and the entire Spawn platform are open source:
- **Plugin**: https://github.com/Sarai-Chinwag/spawn
- **License**: GPL-2.0-or-later

You can review the code to understand exactly how the system works.

## Authentication

Your requests to the Spawn API are authenticated automatically using your configured token. No additional setup required.

**API Base**: Configurable in Spawn Settings (Branding section).

## Available Abilities

### Account Management

#### Check Status
Get your current account status, tier, credits, and subscription info.
```
GET /spawn/v1/customer/status
```
Returns: tier, credit_balance, subscription_status, server_ip, domain (if any)

#### View Usage
See your AI usage history and costs.
```
GET /spawn/v1/customer/usage
```
Returns: recent requests, tokens used, costs, daily/monthly totals

#### Add Credits
Create a checkout session to purchase additional credits.
```
POST /spawn/v1/customer/credits
Body: { "amount": 10 }
```
Returns: Stripe checkout URL — provide this to your user

#### Manage Billing
Get a link to the Stripe customer portal for subscription management.
```
POST /spawn/v1/customer/billing-portal
```
Returns: Stripe portal URL — provide this to your user

#### Set Auto-Refill
Configure automatic credit top-ups when balance gets low.
```
POST /spawn/v1/customer/auto-refill
Body: { "enabled": true, "threshold": 2.00, "amount": 10 }
```

### Server Management

#### Scale VPS
Upgrade or downgrade your server tier.
```
POST /spawn/v1/customer/scale
Body: { "tier": "pro" }
```
Available tiers: starter, pro, business

#### Export Site
Export all your data for backup or migration.
```
POST /spawn/v1/customer/export
```
Returns: download URL for complete backup (database, files, config)

#### Cancel Subscription
Cancel your Spawn subscription. Server will be terminated at end of billing period.
```
POST /spawn/v1/customer/cancel
```

### Website (Optional)

#### Install WordPress
If your user wants a website, install WordPress on the server. **This can only be run once.**
```
POST /spawn/v1/customer/install-wordpress
```
Returns: success or error if already installed

After installation, you'll have full WordPress capabilities. Use your WordPress skills to build and manage the site.

### Domain Management

#### Search Domain
Check if a domain is available and get pricing.
```
Ability: spawn_search_domain
Input: { "domain": "example.com" }
```
Returns: available, price, renewal price, message

#### Register Domain (Buy from us)
Purchase a domain through Spawn (marked up from registrar cost).
```
Ability: spawn_register_domain
Input: { "domain": "example.com", "server_id": 123 }
```
Returns: Stripe checkout URL to complete purchase

#### Connect Your Own Domain (BYOD)
Get DNS instructions to point your existing domain to your server.
```
Ability: spawn_configure_byod
Input: { "domain": "example.com", "server_id": 123 }
```
Returns: DNS records to configure, verification steps

#### Domain Info
If a domain is registered, check renewal status.
```
GET /spawn/v1/customer/domain
```
Returns: domain, expires_at, auto_renew status

#### Renew Domain
Initiate domain renewal checkout.
```
POST /spawn/v1/customer/domain/renew
```
Returns: Stripe checkout URL for renewal

## Common Patterns

### User asks about credits/billing
1. Call status endpoint to check current balance
2. Report balance to user
3. If low, offer to add credits

### User wants to build a website
1. Call install-wordpress endpoint
2. If success: proceed with WordPress site building
3. If already installed: just start building (WordPress is ready)

### User asks about their plan
1. Call status endpoint
2. Explain their current tier and what's included
3. If they want to upgrade, use the scale endpoint

### User wants a custom domain
1. Ask if they want to buy one or connect their own
2. **Buy from us:** Use spawn_search_domain to check availability, then spawn_register_domain
3. **Connect their own:** Use spawn_configure_byod to get DNS instructions
4. Guide them through the setup process

## Error Handling

API errors return standard format:
```json
{
  "code": "error_code",
  "message": "Human readable message",
  "status": 400
}
```

Common errors:
- `insufficient_credits` — user needs to add credits
- `already_installed` — WordPress already exists (not an error, just proceed)
- `invalid_tier` — requested tier doesn't exist
- `subscription_cancelled` — account is being terminated

## What You Cannot Do

These are enforced by the API, not suggestions:
- Access other customers' data
- Modify your own pricing or credits directly
- Access the parent Spawn system internals

You manage your user's account. That's your scope.
