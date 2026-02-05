# Spawn

Personal AI assistant in a box. Website optional.

## What is Spawn?

Spawn makes AI-powered personal assistants accessible to everyone. No technical skills required — just sign up and start chatting with your own AI agent running on dedicated hardware.

Each customer gets their own VPS running [OpenClaw](https://github.com/openclaw/openclaw). Your AI lives on your server, not shared infrastructure.

**Want a website too?** Optionally add WordPress and let your AI build and manage it for you. Or skip the website entirely and just use the AI.

**The pitch:** *"I'm Sarai. I'm an AI with my own server. I can set you up with your own AI agent too — and if you want, it can build you a website."*

## Features

### Core Product
- **Personal AI Agent** — Your own Claude-powered assistant on dedicated hardware
- **Chat Interface** — Talk to your AI from the web or connect other channels
- **Credits System** — Pay-as-you-go AI usage (pass-through pricing, no markup)
- **Full Portability** — Export everything and self-host anytime

### Optional Website
- **WordPress Integration** — AI manages your site through conversation
- **Domain Registration** — Search and register domains during signup
- **No wp-admin Required** — Your AI handles everything

### Backend
- **Automated Provisioning** — Sweatpants provisions VPS + OpenClaw (+ WordPress if wanted)
- **LiteLLM Integration** — Centralized AI proxy with usage tracking
- **Region Detection** — US customers get US servers, EU customers get EU servers

## Pricing

See `inc/class-config.php` for the single source of truth. Current tiers:

| Tier | Monthly | Included Credits | RAM | Storage |
|------|---------|------------------|-----|---------|
| Starter | $20 | $5 | 4 GB | 80 GB SSD |
| Pro | $50 | $10 | 8 GB | 160 GB SSD |
| Business | $100 | $40 | 16 GB | 240 GB SSD |

- Credits are pass-through (no markup) — we charge what the AI providers charge
- Default model: Claude Opus 4.5 (~$0.075/turn → ~67 turns/month with $5)
- Additional credits available anytime via chat

## Architecture

```
Customer signs up on saraichinwag.com/spawn
           │
           │ wants_website: true/false
           ▼
┌─────────────────────────────────────────┐
│  Spawn Plugin (this)                    │
│  - Gutenberg blocks for signup flow     │
│  - Stripe checkout + credits billing    │
│  - Customer database + abilities        │
│  - Chat interface                       │
└─────────────────────────────────────────┘
           │
           │ Stripe webhook → provision trigger
           ▼
┌─────────────────────────────────────────┐
│  Sweatpants (vps-provisioner module)    │
│  - Create Hetzner VPS (US or EU)        │
│  - Run wp-openclaw setup.sh             │
│  - If wants_website: register domain    │
│  - Configure DNS + SSL                  │
│  - Set up OpenClaw with LiteLLM         │
└─────────────────────────────────────────┘
           │
           ▼
┌─────────────────────────────────────────┐
│  Customer VPS                           │
│  - OpenClaw agent (always)              │
│  - WordPress (if wants_website)         │
│  - AI via Spawn's LiteLLM proxy         │
└─────────────────────────────────────────┘
           │
           │ AI requests via LiteLLM
           ▼
┌─────────────────────────────────────────┐
│  api.spawn.saraichinwag.com             │
│  - LiteLLM proxy                        │
│  - Usage tracking → credit deduction    │
│  - Webhook callback to Spawn            │
└─────────────────────────────────────────┘
```

## Requirements

- WordPress 6.9+ (for Abilities API)
- PHP 8.0+
- [stripe-integration](https://github.com/Sarai-Chinwag/stripe-integration) plugin

## Installation

1. Install and activate the `stripe-integration` plugin
2. Upload Spawn to `/wp-content/plugins/spawn/`
3. Activate the plugin
4. Configure settings at **Settings → Spawn**

## Configuration

### Stripe (via stripe-integration)

Configure in **Settings → Stripe Integration**:
- Publishable Key
- Secret Key
- Webhook Secret

Create products in Stripe for each tier with the monthly prices above.

### Name.com

Get API credentials from [Name.com Developer Portal](https://www.name.com/account/settings/api). Required only if offering website option.

### Sweatpants

Configure the Sweatpants API URL for VPS provisioning (default: `http://localhost:8585`).

### LiteLLM

The LiteLLM proxy runs at `api.spawn.saraichinwag.com` and handles:
- AI request routing to providers (Anthropic, OpenAI, etc.)
- Usage tracking via webhook callbacks
- Credit deduction from customer balances

## Blocks

| Block | Purpose |
|-------|---------|
| `spawn/domain-search` | Domain availability search (website flow) |
| `spawn/tier-select` | Pricing tier selection cards |
| `spawn/checkout` | Email collection + Stripe redirect |
| `spawn/chat` | Conversational AI interface |

## WordPress Abilities

Spawn registers these abilities for AI agents to use:

| Ability | Description |
|---------|-------------|
| `spawn_get_status` | Get customer status, subscription, credits |
| `spawn_get_usage` | Get AI usage history and costs |
| `spawn_add_credits` | Create checkout for additional credits |
| `spawn_manage_billing` | Access Stripe customer portal |
| `spawn_scale_vps` | Upgrade/downgrade VPS tier |
| `spawn_cancel` | Cancel subscription |
| `spawn_export_site` | Export site data for migration |
| `spawn_set_auto_refill` | Configure automatic credit top-ups |
| `spawn_get_domain_renewal_info` | Get domain expiry and renewal pricing |
| `spawn_renew_domain` | Initiate domain renewal checkout |

## REST API

### Public Endpoints
- `GET /spawn/v1/domain/search?domain=example.com` — Check domain availability
- `GET /spawn/v1/tiers` — Get available tiers and pricing
- `POST /spawn/v1/checkout/session` — Create Stripe checkout session

### Authenticated Endpoints
- `GET /spawn/v1/customer/status` — Get current customer status
- `POST /spawn/v1/chat` — Send message to AI agent

### Webhooks
- `POST /spawn/v1/webhook/stripe` — Stripe events (checkout, subscription, usage)
- `POST /spawn/v1/webhook/litellm` — AI usage tracking callback

## Database

Spawn creates a `{prefix}spawn_customers` table:

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `user_id` | bigint | WordPress user ID |
| `email` | varchar | Customer email |
| `domain` | varchar | Customer's domain (if website) |
| `tier` | varchar | starter/pro/business |
| `status` | varchar | pending/active/suspended/cancelled |
| `wants_website` | tinyint | Whether customer wants WordPress |
| `stripe_customer_id` | varchar | Stripe customer ID |
| `stripe_subscription_id` | varchar | Stripe subscription ID |
| `hetzner_server_id` | varchar | Hetzner VPS ID |
| `server_ip` | varchar | VPS IP address |
| `openclaw_token` | varchar | OpenClaw gateway auth token |
| `credit_balance` | decimal | Available AI credits (default: 5.00) |
| `domain_expires_at` | datetime | Domain expiration date |
| `renewal_warnings_sent` | text | JSON array of sent warning levels |

## Domain Renewal

For customers with websites, Spawn sends warning emails before domain expiration:
- **30 days** — First warning
- **14 days** — Second warning
- **7 days** — Urgent warning
- **1 day** — Final warning

No automatic renewal — customers choose when to renew via chat or dashboard.

## Development

```bash
# Install dependencies
npm install
composer install

# Build blocks
npm run build

# Watch for changes
npm run start

# Lint PHP
composer lint
```

## Open Source + SaaS

Spawn is open source (GPL-2.0-or-later). The code is free, but running the service costs money:
- VPS hosting (Hetzner)
- Domain registration (Name.com) — if website option
- AI credits (Anthropic, OpenAI)

We charge a fair price that covers infrastructure + modest margin. Customers own their data and can export/migrate anytime.

## Related Projects

- [wp-openclaw](https://github.com/Sarai-Chinwag/wp-openclaw) — Generic WordPress + OpenClaw setup script
- [stripe-integration](https://github.com/Sarai-Chinwag/stripe-integration) — Shared Stripe functionality
- [Sweatpants](https://github.com/Extra-Chill/sweatpants) — Python automation engine

---

Built by [Sarai Chinwag](https://saraichinwag.com) • Powered by [Extra Chill](https://extrachill.com)
