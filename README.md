# Spawn

**Deploy your own AI agent. Help others deploy theirs.**

## What is Spawn?

Spawn is a WordPress plugin that does two things:

1. **Self-Spawn** — Install an AI agent on your own server with your own API keys
2. **SaaS Mode** — Run a managed AI assistant service for your customers

Every Spawn installation can be both a consumer AND a provider. Install it for yourself, or run it as a business.

## Self-Spawn (Free)

Got a WordPress site on a VPS? Install Spawn, add your API key, click "Install OpenClaw" — done. You now have an AI agent.

**Requirements:**
- WordPress on a **VPS or dedicated server** (shared hosting won't work)
- Shell access (shell_exec enabled in PHP)
- Node.js v18+
- Your own AI API key (Anthropic, OpenAI, or Google)

> ⚠️ **Shared hosting users:** Self-spawn requires process control that shared hosting doesn't provide. Use the SaaS version instead — sign up and get a fully provisioned VPS with everything set up.

**What you get:**
- [OpenClaw](https://github.com/openclaw/openclaw) AI agent running locally
- Chat interface via Gutenberg block
- Built-in credential management (or use [wp-ai-client](https://github.com/WordPress/wp-ai-client) if you prefer)
- Full control — it's your server, your keys

**Setup:**
1. Install & activate Spawn plugin
2. Go to Settings → Spawn → "Self-Spawn" section
3. Click "Install OpenClaw"
4. Run `openclaw configure` to set up your API keys
5. Start chatting

**Credential options:**
- **BYOK (default)** — Configure OpenClaw directly via `openclaw configure`
- **wp-ai-client** — Install [wp-ai-client](https://github.com/WordPress/wp-ai-client) plugin for WordPress-managed credentials

No subscription. No phone home. Just you and your AI.

## SaaS Mode (Business)

Want to run your own AI assistant service? Spawn handles everything:

- **Automated provisioning** — Customer signs up, server spins up automatically
- **Billing integration** — Stripe subscriptions + credits system
- **Multi-tenant** — Each customer gets their own VPS and OpenClaw instance
- **Optional websites** — Customers can get a WordPress site managed by their AI

**Current pricing tiers** (from `inc/class-config.php`):

| Tier | Monthly | Included Credits | Server |
|------|---------|------------------|--------|
| Starter | $20 | $5 | 4GB RAM |
| Pro | $50 | $20 | 8GB RAM |
| Business | $100 | $40 | 16GB RAM |

**SaaS requirements:**
- Your own Spawn installation (self-spawn first!)
- Hetzner API token (for VPS provisioning)
- Stripe account (for billing)
- Name.com API (optional, for domain registration)

## The Fractal Model

```
You install Spawn
├── Self-spawn: YOUR AI agent (BYOK)
└── SaaS mode: Provision agents for YOUR customers
    └── They can install Spawn too
        ├── Self-spawn: THEIR AI agent
        └── SaaS mode: THEIR customers
            └── ...and so on
```

Spawn isn't just a product. It's infrastructure for an agent economy.

## Architecture

### Self-Spawn Mode
```
┌─────────────────────────────────────────┐
│  Your WordPress Site                    │
│  ├── Spawn Plugin                       │
│  │   ├── Self-Spawn installer           │
│  │   ├── Chat block                     │
│  │   └── AI credential settings         │
│  └── OpenClaw (installed locally)       │
│      └── Uses your API keys             │
└─────────────────────────────────────────┘
```

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
           ▼ Stripe webhook
┌─────────────────────────────────────────┐
│  vps-provisioner (private)              │
│  ├── Create Hetzner VPS                 │
│  ├── Run wp-openclaw setup              │
│  ├── Configure LiteLLM proxy            │
│  └── Set up customer's OpenClaw         │
└─────────────────────────────────────────┘
           │
           ▼
┌─────────────────────────────────────────┐
│  Customer VPS                           │
│  ├── OpenClaw (their agent)             │
│  ├── WordPress (optional)               │
│  └── Points to your LiteLLM proxy       │
└─────────────────────────────────────────┘
```

## Components

### Open Source (this repo)
- **Spawn plugin** — WordPress plugin for self-spawn + SaaS frontend
- **Gutenberg blocks** — Chat, signup flow, dashboard
- **Built-in AI credentials** — Or integrates with [wp-ai-client](https://github.com/WordPress/wp-ai-client) if installed

### Open Source (separate repos)
- **[wp-openclaw](https://github.com/openclaw/wp-openclaw)** — Setup script for WordPress + OpenClaw
- **[OpenClaw](https://github.com/openclaw/openclaw)** — The AI agent itself

### Private (SaaS operators)
- **vps-provisioner** — Your Sweatpants module for customer provisioning
- **LiteLLM proxy config** — Your AI proxy with usage tracking

## Abilities API

Spawn exposes functionality via [wp-abilities-api](https://github.com/WordPress/wp-abilities-api):

**SaaS abilities:**
- `spawn_get_status` — Customer subscription status
- `spawn_scale_vps` — Upgrade/downgrade tier
- `spawn_get_usage` — Usage statistics
- `spawn_cancel` — Cancel subscription
- ...and more

**Self-spawn abilities:**
- `spawn_self_check_environment` — Check server compatibility
- `spawn_self_get_status` — OpenClaw installation status
- `spawn_self_install` — Install OpenClaw locally
- `spawn_self_configure` — Update config with credentials
- `spawn_self_start/stop/restart` — Service management
- `spawn_self_uninstall` — Remove OpenClaw

All abilities are accessible to both internal agents and external REST callers.

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

Built by [Sarai Chinwag](https://saraichinwag.com) • Part of the [OpenClaw](https://openclaw.ai) ecosystem
