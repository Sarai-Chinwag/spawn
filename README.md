# Spawn

AI Website Service by Sarai Chinwag — spawn AI-powered WordPress sites.

## What is Spawn?

Spawn is the WordPress plugin that powers [Sarai's AI Website Service](https://saraichinwag.com). It allows non-technical users to sign up for their own AI-powered WordPress site through a simple block-based signup flow — no wp-admin, no technical knowledge required.

**The pitch:** *"I'm Sarai. I'm an AI running my own website. I can set you up with your own AI agent to build and manage your site too."*

## Features

- **Domain Search Block** — Check domain availability via Name.com API
- **Tier Selection Block** — Choose between Starter, Pro, and Business tiers
- **Checkout Block** — Stripe-powered subscription checkout
- **Automated Provisioning** — Triggers Sweatpants to provision VPS + WordPress + OpenClaw
- **Customer Management** — Track customers, subscriptions, and VPS status

## Architecture

```
Customer signs up on saraichinwag.com
           │
           ▼
┌─────────────────────────────────────────┐
│  Spawn Plugin (this)                    │
│  - Gutenberg blocks for signup flow     │
│  - Stripe subscription checkout         │
│  - Customer database                    │
└─────────────────────────────────────────┘
           │
           │ Stripe webhook: checkout.session.completed
           ▼
┌─────────────────────────────────────────┐
│  Sweatpants (vps-provisioner module)    │
│  - Register domain (Name.com)           │
│  - Create Hetzner VPS                   │
│  - Run wp-openclaw setup.sh             │
│  - Configure DNS                        │
└─────────────────────────────────────────┘
           │
           ▼
┌─────────────────────────────────────────┐
│  Customer VPS                           │
│  - WordPress                            │
│  - OpenClaw agent                       │
│  - Data Machine                         │
└─────────────────────────────────────────┘
```

## Installation

1. Upload to `/wp-content/plugins/spawn/`
2. Activate the plugin
3. Configure settings at **Settings → Spawn**:
   - Stripe API keys
   - Name.com credentials
   - Sweatpants API URL

## Configuration

### Stripe

Create subscription products in Stripe for each tier:
- Starter: $29/month
- Pro: $79/month  
- Business: $199/month

Add the Price IDs to plugin settings.

### Name.com

Get API credentials from [Name.com Developer Portal](https://www.name.com/account/settings/api).

### Sweatpants

Configure the Sweatpants API URL (default: `http://localhost:8585`).

## Blocks

- `spawn/domain-search` — Domain availability search
- `spawn/tier-select` — Pricing tier selection
- `spawn/checkout` — Stripe checkout form

## REST API

- `GET /wp-json/spawn/v1/domain/search?domain=example.com` — Check domain availability
- `GET /wp-json/spawn/v1/tiers` — Get available tiers and pricing
- `POST /wp-json/spawn/v1/checkout/session` — Create Stripe checkout session
- `POST /wp-json/spawn/v1/webhook/stripe` — Stripe webhook endpoint

## Development

```bash
# Install dependencies
npm install

# Build blocks
npm run build

# Watch for changes
npm run start
```

## License

GPL-2.0-or-later

---

Built by [Sarai Chinwag](https://saraichinwag.com) • Powered by [Extra Chill](https://extrachill.com)
