# Spawn Multi-Tenant Docker Setup

## Architecture

Each Spawn tenant runs in an isolated Docker environment:

```
Host Server (e.g. Hetzner CX41)
├── Host nginx (reverse proxy, SSL termination)
│   └── Routes *.spawn.site → correct tenant container
├── Tenant: customer-a
│   ├── nginx (static files + PHP proxy)
│   ├── wordpress (PHP-FPM + Data Machine + Spawn plugin)
│   └── mariadb (isolated database)
├── Tenant: customer-b
│   └── (same structure)
└── Plasma Shield (host-level network isolation)
```

## Quick Start (Single Tenant)

```bash
cd docker/
cp .env.example .env
# Edit .env with real passwords and config

# Build and start
docker compose up -d

# Install WordPress
docker compose exec wordpress wp core install \
    --url="http://localhost:8080" \
    --title="My Site" \
    --admin_user=admin \
    --admin_password=changeme \
    --admin_email=admin@example.com \
    --allow-root

# Activate plugins
docker compose exec wordpress wp plugin activate data-machine spawn --allow-root
```

## Resource Estimates

Per tenant (idle):
- WordPress + PHP-FPM: ~100-200MB RAM
- MariaDB: ~50-100MB RAM
- nginx: ~5-10MB RAM
- **Total: ~200-350MB RAM per tenant**

A 16GB server can comfortably host **20-30 tenants**.

## Agent Runtime

The agent runtime is designed as a swappable layer. Currently placeholder.
Candidates:
- **OpenCode** — general coding agent (current plan)
- **wp-agent** — WordPress-native runtime (future)

Configure via `SPAWN_AGENT_RUNTIME` and `SPAWN_AGENT_ENABLED` env vars.

## Adding a New Tenant

1. Create a directory for the tenant (or use a management script)
2. Copy `.env.example` → `.env` with tenant-specific values
3. Set a unique `TENANT_PORT`
4. `docker compose up -d`
5. Configure host nginx to route the subdomain to the tenant port
6. Run WordPress installation

## Related Issues

- #16 — Architecture decision
- #17 — This Dockerfile
- #18 — First migration test (Carrie-Ann)
- #19 — Subdomain routing
- #22 — Agent runtime in containers
