# Spawn Dashboard + Multi-Server Plan

## Overview

Evolving Spawn from 1:1 (user:server) to 1:many. Single user can have multiple AI spawns, each scaling independently. Shared credit pool with per-spawn usage tracking.

## Schema Changes

### Current: spawn_customers
Single table with everything - user info, server info, domain info all in one row.

### Target: Normalized Tables

```sql
-- User billing/account info (or could use wp_users + usermeta)
-- For now, keep spawn_customers as the "user" record

-- Servers (one per spawn)
CREATE TABLE {prefix}spawn_servers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) DEFAULT '',
    tier VARCHAR(50) NOT NULL DEFAULT 'starter',
    hetzner_server_id VARCHAR(100),
    hetzner_type VARCHAR(50),
    server_ip VARCHAR(45),
    server_location VARCHAR(10) DEFAULT 'ash',
    openclaw_token VARCHAR(255),
    has_wordpress TINYINT(1) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
);

-- Domains (can be assigned to servers or unassigned)
CREATE TABLE {prefix}spawn_domains (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    server_id BIGINT UNSIGNED DEFAULT NULL,
    domain VARCHAR(255) NOT NULL,
    registrar VARCHAR(50) DEFAULT 'namecom',
    registered_at DATETIME,
    expires_at DATETIME,
    auto_renew TINYINT(1) DEFAULT 0,
    dns_configured TINYINT(1) DEFAULT 0,
    ssl_configured TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_domain (domain),
    INDEX idx_user_id (user_id),
    INDEX idx_server_id (server_id)
);

-- Usage tracking per server per period
CREATE TABLE {prefix}spawn_usage (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    server_id BIGINT UNSIGNED NOT NULL,
    credits_used DECIMAL(10,4) DEFAULT 0,
    requests_count INT UNSIGNED DEFAULT 0,
    tokens_input BIGINT UNSIGNED DEFAULT 0,
    tokens_output BIGINT UNSIGNED DEFAULT 0,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_server_period (server_id, period_start),
    INDEX idx_user_id (user_id)
);
```

### Migration Strategy
1. Create new tables
2. For each spawn_customers row:
   - Keep user data in spawn_customers (id, user_id, email, stripe_*, credit_balance)
   - Insert server data into spawn_servers
   - Insert domain data into spawn_domains (if domain exists)
3. Remove migrated columns from spawn_customers
4. Rename spawn_customers → spawn_users (optional, could keep name)

## Dashboard Structure

### URL: /spawn/dashboard/

### Tabs
1. **Overview** (default)
   - Credit balance (big number)
   - Usage chart (last 30 days)
   - Quick stats (servers count, domains count)
   - Notifications (low credits, renewals coming)
   - Quick actions (Add Credits, Spawn New AI)

2. **Servers**
   - Card per server showing:
     - Name/subdomain
     - Status badge (active/starting/stopped)
     - Tier + specs
     - Health (CPU/RAM/Disk if available)
     - Actions: Chat, Scale, Restart, Settings
   - "Spawn New AI" button
   - Empty state: "You don't have any AI spawns yet"

3. **Domains**
   - Table of domains:
     - Domain name
     - Assigned server (dropdown to reassign)
     - Expires date
     - Auto-renew toggle
     - Actions: DNS settings, Remove
   - "Register Domain" button → domain-search modal
   - Empty state: "No domains yet. Your spawns use subdomains by default."

### Implementation
- Dashboard is a Gutenberg block or classic page template
- Tabs could be:
  - Query param: `/spawn/dashboard/?tab=servers`
  - Or anchor links with JS tab switching
  - Or separate Gutenberg blocks that read the tab state

## REST API Updates

### New Endpoints

```
GET  /spawn/v1/servers              - List user's servers
POST /spawn/v1/servers              - Create new server (triggers provisioning)
GET  /spawn/v1/servers/{id}         - Get server details + health
POST /spawn/v1/servers/{id}/scale   - Scale server tier
POST /spawn/v1/servers/{id}/restart - Restart server
DELETE /spawn/v1/servers/{id}       - Delete server

GET  /spawn/v1/domains              - List user's domains
POST /spawn/v1/domains/search       - Search domain availability
POST /spawn/v1/domains              - Register new domain
PUT  /spawn/v1/domains/{id}         - Update domain (auto-renew, server assignment)
DELETE /spawn/v1/domains/{id}       - Release domain

GET  /spawn/v1/usage                - Get usage summary
GET  /spawn/v1/usage/{server_id}    - Get usage for specific server
```

### Updated Endpoints

```
GET /spawn/v1/customer/status
- Now includes: servers array, domains array, total credits

POST /spawn/v1/checkout/session
- Add: server_id (for scaling existing)
- Add: type (new_server | credits | domain_registration | domain_renewal)
```

## Abilities Updates

### New Abilities
- `spawn_list_servers` - List all user's spawns
- `spawn_get_server_status` - Detailed status for one server
- `spawn_create_server` - Spawn a new AI (triggers checkout)
- `spawn_register_domain` - Search + register domain
- `spawn_list_domains` - List user's domains
- `spawn_assign_domain` - Assign domain to server

### Updated Abilities
- `spawn_get_status` - Include servers/domains arrays
- `spawn_scale_vps` - Accept server_id param
- `spawn_install_wordpress` - Accept server_id param
- `spawn_export_site` - Accept server_id param

## Phased Implementation

### Phase 1: Schema (Agent 1)
1. Write migration class
2. Create new tables
3. Migrate existing data
4. Update Database class with new methods
5. Test migration rollback

### Phase 2: REST API (Agent 2)
1. Add new server endpoints
2. Add new domain endpoints
3. Update existing endpoints
4. Add Hetzner API calls for server health
5. Test all endpoints

### Phase 3: Dashboard UI (Agent 3)
1. Create dashboard page template
2. Build tab navigation
3. Build Overview tab content
4. Build Servers tab + server cards
5. Build Domains tab + domain table
6. Style with Tailwind/theme styles

### Phase 4: Abilities (Agent 4)
1. Create new ability classes
2. Update existing abilities
3. Update spawn-customer skill documentation
4. Test ability registration

### Phase 5: Integration
1. Wire dashboard to REST API
2. Test full flows (new server, new domain, scale, etc.)
3. Update provisioner for multi-server support
4. Test checkout flows

## Files to Create/Modify

### New Files
- `inc/class-migration.php` - Schema migration
- `inc/rest/class-servers-controller.php` - Server REST endpoints
- `inc/rest/class-domains-controller.php` - Domain REST endpoints
- `blocks/dashboard/` - Dashboard block
- `blocks/server-card/` - Server card component
- `blocks/domain-table/` - Domain table component
- `inc/abilities/class-ability-list-servers.php`
- `inc/abilities/class-ability-register-domain.php`
- etc.

### Modified Files
- `inc/class-database.php` - New table methods
- `inc/class-rest-api.php` - Register new controllers
- `inc/class-config.php` - Multi-server config
- `inc/abilities/class-abilities.php` - Register new abilities
- `skills/spawn-customer/SKILL.md` - Document new abilities
- All existing ability classes - Add server_id support

## Success Criteria

1. User can have multiple spawns, each on own VPS
2. User can register multiple domains
3. User can assign domains to servers
4. Dashboard shows all servers with health
5. Dashboard shows all domains with renewal info
6. Single credit pool with per-server usage tracking
7. All existing flows still work (backwards compatible)
