# Spawn Plugin Audit Results

**Date:** 2026-02-13
**Context:** Post-cleanup audit after removing self-spawn, wp-ai-client bridge, renaming hetzner columns, removing included credits from tiers.

---

## 🔴 Critical Issues

### 1. Tests expect old `included_credits` values (WILL FAIL)

Config now has `included_credits: 0` for all tiers, but tests assert the old values:

- **tests/ConfigTest.php:113-119** — `test_included_credits()` asserts starter=5.00, pro=10.00, business=40.00 but all are now 0
- **tests/ConfigTest.php:125-129** — `test_get_included_credits_helper()` same issue
- **tests/WebhookTest.php:73-80** — `test_checkout_sets_correct_credits()` expects starter=$5, pro=$10, business=$40

### 2. `Config::get_included_credits()` returns wrong default

- **inc/class-config.php:188** — When tier has `included_credits: 0`, this returns 0. But for invalid tiers, falls back to `DEFAULT_STARTER_CREDITS` ($5.00). This fallback is misleading now that tiers intentionally have 0 credits.

### 3. Duplicate `server_type` property in shared types

- **blocks/shared/types.ts:22 and :26** — `server_type` is declared twice in the `Customer` interface. TypeScript will use the last one, but this is a bug/oversight.

---

## 🟡 Moderate Issues

### 4. Frontend TIERS config hardcodes US-only VPS types

- **blocks/account/view.ts:54-58** — `TIERS` maps only to US types (`cpx21/cpx31/cpx41`). EU customers with `cpx22/cpx32/cpx42` will see mismatched info. The `vps` field isn't even used for display currently, but it's a consistency risk.

### 5. Frontend `TierInfo` type includes `included_credits` but API doesn't return it

- **blocks/shared/types.ts:33** — `TierInfo.included_credits` exists in the TS type
- **inc/class-config.php:226-236** — `get_public_tiers()` does NOT include `included_credits` in response
- Not currently breaking anything since nothing reads it from the API response, but it's misleading.

### 6. Legacy credit packages still use "credits" units, not dollars

- **inc/class-rest-api.php:2363-2394** — `get_credit_packages_config()` returns packages with `credits: 1000/3000/7500` and `price: 10/25/50`. But the DB `credit_balance` is in dollars. The `purchase_credits` method (line 1673) converts `$amount * 100` to credits — this entire credit-package system is inconsistent with the dollar-based balance.

### 7. Docblock says "Hetzner server ID" but method is generic

- **inc/class-database.php:481-482** — `set_provider_server_id()` docblock says "Store Hetzner server ID" — should say "Store provider server ID"

### 8. `class-rest-api.php` is 3616 lines — too large

Should be split. Natural boundaries:
- Auth endpoints → `controllers/class-auth-controller.php` (already partially done)
- Chat endpoints → `controllers/class-chat-controller.php` (already partially done)  
- Credit/billing endpoints → new controller
- Server/domain CRUD endpoints → new controller
- LiteLLM callback endpoints → new controller

### 9. Two `update_auto_refill` methods with different units

- **inc/class-database.php:398** — `update_auto_refill()` uses credit-based integers
- **inc/class-database.php:416** — `update_auto_refill_settings()` uses dollar-based floats
- **inc/class-rest-api.php** has both `/credits/auto-refill` (legacy) and `/account/auto-refill` (new)
- The legacy one should be removed or deprecated more clearly

---

## 🟢 Clean (No Issues Found)

### Dead Code / Unused References
- ✅ No references to `Self_Spawn`, `Environment_Detector`, `WP_AI_Client_Bridge`, `Ability_Self_Spawn`
- ✅ No references to `hetzner_type`, `hetzner_server_id`, `hetzner_location`, `vps_tier` in PHP/TS source
- ✅ Autoloader (`inc/autoload.php`) is generic/dynamic — no hardcoded file references to deleted classes
- ✅ `spawn.php` has clean comment noting self-spawn removal

### Autoloading
- ✅ Dynamic autoloader handles all namespaced classes correctly
- ✅ No stale require/include statements

### Security
- ✅ REST endpoints use `is_user_logged_in` or custom permission callbacks
- ✅ Internal endpoints use API key verification (`verify_internal_request`)
- ✅ Inputs sanitized via `sanitize_text_field`, `sanitize_email`, `absint`
- ✅ SQL queries use `$wpdb->prepare()` throughout
- ✅ Google OAuth validates nonce on callback
- ✅ Order column in `get_all_customers` is allowlisted

### DB Schema Consistency
- ✅ Schema uses `server_type`, `server_location`, `provider_server_id` consistently
- ✅ Migration function (`migrate_column_names`) handles old→new column renames
- ✅ `create_customer()` correctly uses `Config::get_server_config()` for server type/location

---

## 💡 Suggestions (Low Priority)

1. **Remove legacy auto-refill endpoint** — `/credits/auto-refill` is superseded by `/account/auto-refill`
2. **Remove `Database::update_auto_refill()`** — legacy credit-unit version, keep only `update_auto_refill_settings()`
3. **Move credit packages to Config** — `get_credit_packages_config()` in REST_API is hardcoded; should be in Config class
4. **Reconcile credits model** — Either credit packages should return dollar amounts, or document the conversion clearly
5. **Add `vcpu` to public tiers** — `get_public_tiers()` omits it but it would be useful for the tier-select block
6. **`ANTHROPIC_PRICING` constant in REST_API** — Should be in Config for single-source-of-truth
7. **`EU_COUNTRIES` constant in REST_API** — Could move to Config
8. **`get_domain_markup()` reads from options** — Good pattern, but the default 1.5x is hardcoded in the method. Consider Config.
