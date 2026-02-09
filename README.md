# Spawn

Deploy your own AI agent. Help others deploy theirs.

## What It Does

Spawn turns WordPress into an AI assistant service platform:

- **SaaS mode** — Provision and bill AI agents for your customers
- **Auto-scaling** — Each customer gets their own VPS and OpenClaw instance
- **Billing** — Stripe subscriptions with credits system
- **Optional websites** — Customers can get AI-managed WordPress sites

## How It Works

```
┌─────────────────────────────────────────┐
│  YOUR SITE (Control Plane)              │
│  ├── Signup flow (Gutenberg blocks)     │
│  ├── Stripe billing                     │
│  └── Provisioning triggers              │
└───────────────┬─────────────────────────┘
                │
                ▼ vps-provisioner module
┌─────────────────────────────────────────┐
│  CUSTOMER VPS (Auto-provisioned)        │
│  ├── OpenClaw agent                     │
│  ├── WordPress (optional)               │
│  └── Their AI, their keys               │
└─────────────────────────────────────────┘
```

Customer signs up → Stripe payment → VPS spins up → Agent ready in minutes.

## Pricing Tiers

| Tier | Monthly | Credits | Server |
|------|---------|---------|--------|
| Starter | $20 | $5 | 4GB RAM |
| Pro | $50 | $20 | 8GB RAM |
| Business | $100 | $40 | 16GB RAM |

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

## The Fractal Model

```
You install Spawn
├── Run SaaS for YOUR customers
│   └── They install Spawn
│       ├── Run SaaS for THEIR customers
│       └── ...recursive agent economy
```

## Requirements

| Service | Purpose |
|---------|---------|
| WordPress on VPS | Control plane |
| Hetzner API | Customer VPS provisioning |
| Stripe | Billing |
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

## Self-Spawn (Experimental)

> ⚠️ For technical users only. Requires Node.js pre-installed.

For most users: Use SaaS mode or [wp-openclaw](https://github.com/Sarai-Chinwag/wp-openclaw) setup script instead.

## Documentation

- [AGENTS.md](AGENTS.md) — Technical architecture

## License

GPL-2.0-or-later

---

*Built by [Sarai Chinwag](https://saraichinwag.com) for [Extra Chill](https://extrachill.com)*
