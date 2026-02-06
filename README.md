# Spawn

Personal AI assistant in a box. Website optional.

## What is Spawn?

Spawn makes AI-powered personal assistants accessible to everyone. No technical skills required — just sign up and start chatting with your own AI agent running on dedicated hardware.

Each customer gets their own server running [OpenClaw](https://github.com/openclaw/openclaw). Your AI lives on your server, not shared infrastructure.

**Want a website too?** Check the box during signup and your AI will build and manage it for you. Or skip the website entirely and just use the AI.

## Features

### Core Product
- **Personal AI Agent** — Your own Claude-powered assistant on dedicated hardware
- **Chat Interface** — Talk to your AI from the web or connect other channels
- **Credits System** — Pay-as-you-go AI usage (pass-through pricing, no markup)
- **Full Portability** — Export everything and self-host anytime

### Optional Website
- **WordPress Integration** — AI manages your site through conversation
- **Custom Domain** — Add your own domain anytime via dashboard (or use free subdomain)
- **No wp-admin Required** — Your AI handles everything

### Backend
- **Automated Provisioning** — Sweatpants provisions server + OpenClaw (+ WordPress if wanted)
- **LiteLLM Integration** — Centralized AI proxy with usage tracking
- **Region Detection** — US customers get US servers, EU customers get EU servers

## Pricing

See `inc/class-config.php` for the single source of truth. Current tiers:

| Tier | Monthly | Included Credits |
|------|---------|------------------|
| Starter | $20 | $5 |
| Pro | $50 | $20 |
| Business | $100 | $40 |

- Credits are pass-through (no markup) — we charge what the AI providers charge
- Additional credits available anytime via chat

## Signup Flow

1. **Choose your plan** — Pick a tier based on how much AI power you need
2. **Complete checkout** — Enter email, optionally check "Include a free website", pay via Stripe
3. **Start chatting** — Your AI is ready on a free subdomain

Custom domains can be added later through the dashboard.

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
│  - Configure subdomain + SSL            │
│  - Set up OpenClaw with LiteLLM         │
│  - If wants_website: install WordPress  │
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

Get API credentials from [Name.com Developer Portal](https://www.name.com/account/settings/api). Required for custom domain registration.

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
| `spawn/tier-select` | Pricing tier selection cards |
| `spawn/checkout` | Email collection + website toggle + Stripe redirect |
| `spawn/dashboard` | Customer dashboard (credits, domains, settings) |
| `spawn/chat` | Conversational AI interface |

## WordPress Abilities

Spawn registers these abilities for AI agents to use:

| Ability | Description |
|---------|-------------|
| `spawn_get_status` | Get customer status, subscription, credits |
| `spawn_get_usage` | Get AI usage history and costs |
| `spawn_add_credits` | Create checkout for additional credits |
| `spawn_manage_billing` | Access Stripe customer portal |
| `spawn_scale_vps` | Upgrade/downgrade tier |
| `spawn_cancel` | Cancel subscription |
| `spawn_export_site` | Export site data for migration |
| `spawn_set_auto_refill` | Configure automatic credit top-ups |
| `spawn_get_domain_renewal_info` | Get domain expiry and renewal pricing |
| `spawn_renew_domain` | Initiate domain renewal checkout |

## REST API

### Public Endpoints
- `GET /spawn/v1/tiers` — Get available tiers and pricing
- `POST /spawn/v1/checkout/session` — Create Stripe checkout session
- `GET /spawn/v1/domain/search?domain=example.com` — Check domain availability (for dashboard)

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
| `domain` | varchar | Customer's domain (if any) |
| `tier` | varchar | starter/pro/business |
| `status` | varchar | pending/active/suspended/cancelled |
| `wants_website` | tinyint | Whether customer has WordPress |
| `stripe_customer_id` | varchar | Stripe customer ID |
| `stripe_subscription_id` | varchar | Stripe subscription ID |
| `hetzner_server_id` | varchar | Hetzner server ID |
| `server_ip` | varchar | Server IP address |
| `openclaw_token` | varchar | OpenClaw gateway auth token |
| `credit_balance` | decimal | Available AI credits |
| `domain_expires_at` | datetime | Domain expiration date |

## Domain Management

Domains are managed post-signup through the dashboard:
- **Register** — Buy a domain through us (marked up from Name.com)
- **Bring your own** — Point your existing domain via DNS

For customers with domains, Spawn sends renewal warning emails at 30, 14, 7, and 1 day before expiration.

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
- Server hosting (Hetzner)
- AI credits (Anthropic, OpenAI)
- Domain registration (Name.com) — optional

We charge a fair price that covers infrastructure + modest margin. Customers own their data and can export/migrate anytime.

## Related Projects

- [wp-openclaw](https://github.com/Sarai-Chinwag/wp-openclaw) — Generic WordPress + OpenClaw setup script
- [stripe-integration](https://github.com/Sarai-Chinwag/stripe-integration) — Shared Stripe functionality
- [Sweatpants](https://github.com/Extra-Chill/sweatpants) — Python automation engine

---

Built by [Sarai Chinwag](https://saraichinwag.com) • Powered by [Extra Chill](https://extrachill.com)
