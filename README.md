# Spawn

Storefront and billing layer for AI agent services on WordPress.

## What It Does

Spawn handles the customer-facing side of an AI agent platform: signup flows, Stripe billing, credit management, and customer dashboards. Infrastructure provisioning is delegated to **Fleet Command** — Spawn focuses purely on the commerce layer.

## How It Works

```
┌─────────────────────────────────────────┐
│  YOUR SITE (Spawn)                      │
│  ├── Signup flow (Gutenberg blocks)     │
│  ├── Stripe billing & credits           │
│  ├── Customer dashboard                 │
│  └── Provisioning triggers ─────────────┼──→ Fleet Command
└─────────────────────────────────────────┘
```

Customer signs up → Stripe payment → Fleet Command provisions → Agent ready.

## Pricing

Spawn supports configurable tiers with per-tier credit allocations and server specs. Pricing is managed in **Settings → Spawn**.

## Gutenberg Blocks

| Block | Purpose |
|-------|---------|
| `spawn/tier-select` | Pricing tier selector |
| `spawn/checkout` | Email + Stripe checkout |
| `spawn/domain-search` | Domain availability check |
| `spawn/dashboard` | Customer dashboard |
| `spawn/chat` | Chat with AI agent |

## Abilities API

| Ability | Description |
|---------|-------------|
| `spawn_get_status` | Customer subscription status |
| `spawn_scale_vps` | Upgrade/downgrade tier |
| `spawn_get_usage` | Usage statistics |
| `spawn_search_domain` | Check domain availability |
| `spawn_register_domain` | Register a domain |

## Hooks

### Actions

| Hook | Description |
|------|-------------|
| `spawn_before_page_content` | Fires before Spawn page content renders |
| `spawn_credits_purchased` | After credits are purchased (`$customer_id`, `$credits`, `$session`) |
| `spawn_auto_refill_success` | After successful auto-refill (`$customer_id`, `$credits`, `$result`) |
| `spawn_auto_refill_failed` | After failed auto-refill (`$customer_id`, `$result`) |
| `spawn_provisioning_complete` | After VPS provisioning succeeds (`$customer_id`, `$domain`, `$server_ip`) |
| `spawn_provisioning_failed` | After VPS provisioning fails (`$customer_id`, `$domain`, `$error_message`) |
| `spawn_domain_renewed` | After domain renewal (`$customer_id`, `$domain`, `$result`) |
| `spawn_domain_auto_renewed` | After automatic domain renewal (`$customer_id`, `$domain`, `$price`, `$result`) |
| `spawn_credits_auto_refill_needed` | When credits drop below auto-refill threshold (`$customer_id`, `$auto_refill`) |

## The Fractal Model

```
You install Spawn
├── Run SaaS for YOUR customers
│   └── They install Spawn
│       ├── Run SaaS for THEIR customers
│       └── ...recursive agent economy
```

## Requirements

| Dependency | Purpose |
|------------|---------|
| WordPress 6.9+ | Host platform |
| PHP 8.1+ | Runtime |
| [Stripe Integration](https://github.com/Sarai-Chinwag/stripe-integration) | Billing |
| Hetzner API | VPS provisioning (via Fleet Command) |
| Cloudflare | Subdomain DNS |
| Name.com | Domain registration (optional) |

## Installation

```bash
git clone https://github.com/Sarai-Chinwag/spawn.git
cd spawn && npm install && npm run build

# Configure in Settings → Spawn
# Set up Stripe products/prices
# Create signup page with Spawn blocks
```

## Development

```bash
npm run build   # Build blocks
npm run start   # Watch mode
```

## Documentation

- [AGENTS.md](AGENTS.md) — Technical architecture

## License

GPL-2.0-or-later

---

*Built by [Sarai Chinwag](https://saraichinwag.com) for [Extra Chill](https://extrachill.com)*
