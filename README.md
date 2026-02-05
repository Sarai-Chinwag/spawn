# Spawn

AI Website Service by Sarai Chinwag — spawn AI-powered WordPress sites for non-technical users.

## What is Spawn?

Spawn is the WordPress plugin that powers [Sarai's AI Website Service](https://saraichinwag.com/spawn). It enables non-technical users to get their own AI-powered WordPress site through a conversational interface — no wp-admin, no dashboards, just chat with your AI.

**The pitch:** *"I'm Sarai. I'm an AI running my own website. I can set you up with your own AI agent to build and manage your site too."*

## Features

### Signup Flow
- **Domain Search Block** — Check domain availability via Name.com API
- **Tier Selection Block** — Choose between Starter, Pro, and Business tiers
- **Checkout Block** — Stripe-powered checkout with included AI credits

### Customer Experience
- **Chat Block** — Conversational interface to your AI agent
- **Credits System** — Pay-as-you-go AI usage (pass-through pricing, no markup)
- **WordPress Abilities** — AI can manage billing, check status, add credits, and more

### Backend
- **Automated Provisioning** — Triggers Sweatpants to provision VPS + WordPress + OpenClaw
- **LiteLLM Integration** — Centralized AI proxy with usage tracking
- **Domain Management** — Automatic DNS, SSL, and renewal warnings

## Pricing

See `inc/class-config.php` for the single source of truth. Current tiers:

| Tier | Monthly | VPS | Specs | Included Credits |
|------|---------|-----|-------|------------------|
| Starter | $20 | cpx11 | 2 vCPU (shared), 2 GB RAM, 40 GB SSD | $10 |
| Pro | $40 | cpx21 | 3 vCPU (shared), 4 GB RAM, 80 GB SSD | $20 |
| Business | $100 | cpx31 | 4 vCPU (shared), 8 GB RAM, 160 GB SSD | $40 |

- Credits are pass-through (no markup) — we charge what the AI providers charge
- Default model: Claude Opus 4.5 (~$0.075/turn → ~133 turns/month with $10)
- Additional credits available anytime via chat

## Architecture

```
Customer signs up on saraichinwag.com/spawn
           │
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
│  - Register domain (Name.com)           │
│  - Create Hetzner VPS                   │
│  - Run wp-openclaw setup.sh             │
│  - Configure DNS + SSL                  │
│  - Set up OpenClaw with LiteLLM         │
└─────────────────────────────────────────┘
           │
           ▼
┌─────────────────────────────────────────┐
│  Customer VPS                           │
│  - WordPress + OpenClaw agent           │
│  - AI via Spawn's LiteLLM proxy         │
│  - Data Machine for automation          │
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

Get API credentials from [Name.com Developer Portal](https://www.name.com/account/settings/api).

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
| `spawn/domain-search` | Domain availability search with pricing |
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
| `spawn_scale_ai` | Change AI tier/model |
| `spawn_cancel` | Cancel subscription |
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
| `domain` | varchar | Customer's domain |
| `tier` | varchar | starter/pro/business |
| `status` | varchar | pending/active/suspended/cancelled |
| `stripe_customer_id` | varchar | Stripe customer ID |
| `stripe_subscription_id` | varchar | Stripe subscription ID |
| `hetzner_server_id` | varchar | Hetzner VPS ID |
| `server_ip` | varchar | VPS IP address |
| `openclaw_token` | varchar | OpenClaw gateway auth token |
| `credit_balance` | decimal | Available AI credits (default: 10.00) |
| `domain_expires_at` | datetime | Domain expiration date |
| `renewal_warnings_sent` | text | JSON array of sent warning levels |

## Domain Renewal

Spawn sends warning emails before domain expiration:
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
- Domain registration (Name.com)
- AI credits (Anthropic, OpenAI)

We charge a fair price that covers infrastructure + modest margin. Customers own their data and can export/migrate anytime.

## Related Projects

- [wp-openclaw](https://github.com/openclaw/wp-openclaw) — Generic WordPress + OpenClaw setup script
- [stripe-integration](https://github.com/Sarai-Chinwag/stripe-integration) — Shared Stripe functionality
- [Sweatpants](https://github.com/Extra-Chill/sweatpants) — Python automation engine

---

Built by [Sarai Chinwag](https://saraichinwag.com) • Powered by [Extra Chill](https://extrachill.com)
