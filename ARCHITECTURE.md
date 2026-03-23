# Spawn 2.0 Architecture

## Three-Layer Stack

Spawn is a commercial layer that sells access to Data Machine agents. The architecture has three distinct layers:

```
┌─────────────────────────────────────────────────────────────────────────┐
│ LAYER 1: SPAWN                                                          │
│ (Commercial Layer - WordPress Plugin)                                   │
│                                                                         │
│  Responsibilities:                                                      │
│  • Customer signup & authentication                                     │
│  • Subdomain provisioning (*.saraichinwag.com)                          │
│  • Billing & metering (Stripe integration)                              │
│  • Credit system & usage tracking                                       │
│  • Chat block UI (WordPress block using @extrachill/chat)               │
│  • Customer dashboard & management                                      │
│                                                                         │
│  Does NOT handle:                                                       │
│  • AI execution (delegates to DM)                                       │
│  • Chat session management (delegates to DM)                            │
│  • Tool execution (delegates to DM)                                     │
└─────────────────────────────────┬───────────────────────────────────────┘
                                  │
                                  │ Uses REST API
                                  ▼
┌─────────────────────────────────────────────────────────────────────────┐
│ LAYER 2: DATA MACHINE                                                   │
│ (Orchestration Layer - WordPress Plugin)                                │
│                                                                         │
│  Responsibilities:                                                      │
│  • Chat session lifecycle (create, continue, list, delete)              │
│  • Multi-turn conversation management                                   │
│  • Tool routing & execution                                             │
│  • Agent scoping (per-customer agent_id isolation)                      │
│  • REST API endpoints (/wp-json/datamachine/v1/chat)                    │
│  • Pipeline & flow execution                                            │
│  • Queue management                                                     │
│                                                                         │
│  Uses: ai-http-client (Composer package) to make AI HTTP requests       │
└─────────────────────────────────┬───────────────────────────────────────┘
                                  │
                                  │ HTTP requests via ai-http-client
                                  ▼
┌─────────────────────────────────────────────────────────────────────────┐
│ LAYER 3: AI PROVIDERS                                                   │
│ (External APIs)                                                         │
│                                                                         │
│  • OpenAI (GPT-4, GPT-4o, etc.)                                         │
│  • Anthropic (Claude 3.5 Sonnet, etc.)                                  │
│  • GitHub Copilot (optional, for free tier)                             │
│  • Future: Google, Grok, OpenRouter                                     │
│                                                                         │
│  API keys stored in DM settings (encrypted), never exposed to customers │
└─────────────────────────────────────────────────────────────────────────┘
```

## Key Boundaries

### Spawn → Data Machine
- Spawn **consumes** DM's REST API
- Spawn does not import DM classes directly
- Spawn passes `agent_id` to scope requests per customer
- DM handles all chat/session/tool complexity

### Data Machine → AI Providers
- DM uses `ai-http-client` Composer package (chubes4/ai-http-client)
- Makes direct HTTP requests to OpenAI/Anthropic APIs
- No intermediate server (OpenCode/Kimaki) in this architecture
- API keys stored in DM settings, managed by site admin

## Spawn's Value Proposition

Spawn doesn't build AI features — it **packages and sells** DM's AI features:

| Feature | Who Provides It | How Spawn Uses It |
|---------|----------------|-------------------|
| Chat UI | @extrachill/chat | WordPress block wrapper |
| Chat backend | Data Machine | REST API calls |
| Session management | Data Machine | DM handles entirely |
| Tool execution | Data Machine | DM handles entirely |
| AI models | OpenAI/Anthropic | Via DM's ai-http-client |
| Multi-tenancy | Data Machine | agent_id scoping |
| Subdomains | Spawn | WP multisite management |
| Billing | Spawn | Stripe + credit system |

## Agent Types Spawn Can Sell

### 1. Content Agent (Default Tier)
**What it does:**
- Writes blog posts, recipes, quizzes
- Generates featured images
- Optimizes for SEO
- Publishes directly to customer's site

**Tools available:**
- Content generation flows (DM Flow 29, 45, 48)
- Image generation
- SEO optimization
- Publishing tools

### 2. Coding Agent ("Spawn Pro" Tier)
**What it does:**
- Customizes customer's site
- "Add a dark mode toggle"
- "Create a custom recipe template"
- "Modify my CSS"

**Tools available:**
- File read/write (scoped to customer's site)
- Bash execution (sandboxed)
- WordPress admin operations
- Theme/plugin code editing

**Safety:**
- Filesystem scoped to customer's subdomain only
- Cannot access other customers' sites
- Cannot access Spawn main site
- Audit log of all changes

### 3. Marketing Agent (Future - "Spawn Growth" Tier)
**What it does:**
- "Generate Pinterest pins for my last 10 posts"
- "Write newsletter copy"
- "Create social media calendar"

**Tools available:**
- DM-Socials integration
- Image generation + templating
- Scheduling tools
- Analytics integration

### 4. Multi-Agent Teams (Future - "Spawn Studio" Tier)
**Workflow:**
1. Research Agent → gathers topic info
2. Writer Agent → creates draft
3. Editor Agent → reviews & improves
4. SEO Agent → optimizes
5. Publisher Agent → schedules & posts

Each agent is a separate `agent_id` with specific tool access.

## Customer Site Architecture

When a customer signs up:

```
customer.saraichinwag.com (subdomain site)
├── WordPress Core
├── Data Machine plugin (active)
├── Spawn plugin (active, thin client)
│   └── Chat block (uses @extrachill/chat)
└── Customer's content (posts, pages, media)
    
Customer interacts with:
- Chat UI on their site → DM REST API
- DM handles AI calls → OpenAI/Anthropic
- DM stores sessions → Database (isolated per agent_id)
- Spawn meters usage → Billing system
```

## No OpenCode Dependency

**Important:** Spawn 2.0 does NOT use OpenCode. The architecture is:

- **WordPress** → DM (PHP)
- **DM** → ai-http-client (PHP library via Composer)
- **ai-http-client** → OpenAI/Anthropic (direct HTTP)

OpenCode was part of Spawn 1.0's VPS-provisioning model. That model is deprecated.

## Comparison: Spawn 1.0 vs 2.0

| Aspect | Spawn 1.0 | Spawn 2.0 |
|--------|-----------|-----------|
| Infrastructure | VPS per customer | Subdomain on shared server |
| AI execution | LiteLLM proxy | DM's ai-http-client |
| Provisioning | External API call | WP multisite creation |
| Domain | Name.com registration | *.saraichinwag.com or BYOD |
| Chat | Custom controller | DM's /chat REST API |
| Session storage | Custom tables | DM's session system |
| Complexity | High | Low |
| Cost per customer | $10-50/month VPS | Near zero (shared) |

## Implementation Phases

### Phase 1: Content Agent (MVP)
- Subdomain provisioning
- Basic chat using @extrachill/chat
- Content generation flows
- Credit-based billing

### Phase 2: Coding Agent
- Sandboxed filesystem tools
- Code editing abilities
- "Pro" tier pricing
- Audit logging

### Phase 3: Multi-Agent
- Agent specialization
- Team workflows
- "Studio" tier pricing
- Advanced orchestration

## Summary

Spawn is a **reseller/tenant manager** for Data Machine:
- Spawn creates the customer account and subdomain
- DM provides all AI capabilities
- Spawn adds billing and usage metering on top
- Each customer gets isolated agent_id scoping
- No AI code in Spawn — it's all DM
