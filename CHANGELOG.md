## Unreleased

### Added
- Comp billing system: `billing_type` field, `spawn_billing_types` filter, `spawn_comp_customer` ability
- Self-contained chat block with state machine (auth, credits, chat, provisioning)
- Direct mode: chat block talks directly to customer gateway over HTTPS
- Domain migration trigger + auto-refund on registration failure
- Admin comp endpoint (`POST /spawn/v1/admin/comp`)
- Automated daily backups to all tier feature cards

### Changed
- Modularize chat block into focused TypeScript modules
- Refactor chat block from composable inner blocks to single smart block

### Removed
- BYOK billing mode infrastructure
- Proxy chat mode (direct-only now)
- Chat storefront inner blocks (auth-gate, credit-balance, credit-purchase)
