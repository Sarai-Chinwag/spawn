# Spawn

**Deploy your own AI agent. Help others deploy theirs.**

## What is Spawn?

Spawn is a WordPress plugin for running an AI assistant service:

1. **SaaS Mode** — Run a managed AI assistant service for your customers
2. **Self-Spawn** — (Experimental) Install an AI agent on your own VPS

## SaaS Mode

Want to run your own AI assistant service? Spawn handles everything:

- **Automated provisioning** — Customer signs up, server spins up automatically
- **Billing integration** — Stripe subscriptions + credits system
- **Multi-tenant** — Each customer gets their own VPS and OpenClaw instance
- **Optional websites** — Customers can get a WordPress site managed by their AI

**Pricing tiers** (configurable in `inc/class-config.php`):

| Tier | Monthly | Included Credits | Server |
|------|---------|------------------|--------|
| Starter | $20 | $5 | 4GB RAM |
| Pro | $50 | $20 | 8GB RAM |
| Business | $100 | $40 | 16GB RAM |

**Requirements:**
- WordPress on a VPS (your control plane)
- Hetzner API token (for customer VPS provisioning)
- Stripe account (for billing)
- Cloudflare (for subdomain DNS)
- Name.com API (optional, for domain registration)

**Setup:**
1. Install & activate Spawn plugin
2. Configure Settings → Spawn with your API tokens
3. Set up Stripe products/prices
4. Create signup page with Spawn blocks
5. Customers sign up → automatic provisioning → they get their AI

## The Fractal Model

```
You install Spawn
├── Run SaaS: Provision agents for YOUR customers
│   └── They can install Spawn too
│       ├── Run SaaS: THEIR customers
│       └── ...and so on
└── Or self-spawn for yourself (experimental)
```

Spawn isn't just a product. It's infrastructure for an agent economy.

## Architecture

### SaaS Mode
```
┌─────────────────────────────────────────┐
│  Your Control Plane (WordPress + Spawn) │
│  ├── Signup flow (Gutenberg blocks)     │
│  ├── Stripe billing                     │
│  ├── Customer database                  │
│  └── Provisioning triggers              │
└─────────────────────────────────────────┘
           │
           ▼ Sweatpants vps-provisioner
┌─────────────────────────────────────────┐
│  Customer VPS (auto-provisioned)        │
│  ├── WordPress (optional)               │
│  ├── OpenClaw agent                     │
│  ├── Data Machine (if WordPress)        │
│  └── Their own AI, their own keys       │
└─────────────────────────────────────────┘
```

## Blocks

Spawn includes Gutenberg blocks for building your signup flow:

- `spawn/tier-select` — Pricing tier selector
- `spawn/checkout` — Email + Stripe checkout
- `spawn/domain-search` — Domain availability search
- `spawn/dashboard` — Customer dashboard
- `spawn/chat` — Chat with AI agent
- `spawn/login` — Customer login/register
- `spawn/account` — Account management

## Abilities API

Spawn exposes functionality via [wp-abilities-api](https://github.com/WordPress/wp-abilities-api):

- `spawn_get_status` — Customer subscription status
- `spawn_scale_vps` — Upgrade/downgrade tier
- `spawn_get_usage` — Usage statistics
- `spawn_cancel` — Cancel subscription
- `spawn_search_domain` — Check domain availability
- `spawn_register_domain` — Register a domain
- `spawn_configure_byod` — Configure bring-your-own-domain

---

## Self-Spawn (Experimental)

> ⚠️ **Experimental feature** with known limitations. See [open issues](https://github.com/Sarai-Chinwag/spawn/issues?q=is%3Aissue+is%3Aopen+label%3Aself-spawn).

For technical users who want to install OpenClaw on their own VPS via WordPress admin.

**Requirements:**
- WordPress on a **VPS or dedicated server** (shared hosting won't work)
- Shell access (shell_exec enabled in PHP)
- **Node.js v18+ already installed**
- Your own AI API key

**Known limitations:**
- Node.js must be pre-installed (can't be installed securely from WordPress)
- If you need SSH to install Node.js, you might as well use [wp-openclaw](https://github.com/Sarai-Chinwag/wp-openclaw) directly
- Does not work on shared hosting

**For most users:** SaaS mode or [wp-openclaw](https://github.com/Sarai-Chinwag/wp-openclaw) setup script are better options.

**Setup:**
1. Ensure Node.js v18+ is installed on your VPS
2. Install & activate Spawn plugin
3. Go to Settings → Spawn → "Self-Spawn" section
4. Click "Install OpenClaw"
5. Configure credentials via `openclaw configure` or install [wp-ai-client](https://github.com/WordPress/wp-ai-client)

---

## Development

```bash
# Clone
git clone https://github.com/Sarai-Chinwag/spawn.git
cd spawn

# Install dependencies (for development only)
npm install

# Build blocks
npm run build

# Watch for changes
npm run start
```

**Note:** No `composer install` needed — Spawn includes its own autoloader. Just upload the plugin folder and activate.

## License

GPL-2.0-or-later

---

*Built by [Sarai Chinwag](https://saraichinwag.com) for [Extra Chill](https://extrachill.com)*
