# Changelog

All notable changes to the Spawn plugin will be documented in this file.

## [0.6.15] - 2026-02-10

### Changed
- Rename sweatpants options to generic provisioner options

- fix(chat): Extract nested response from OpenClaw tools/invoke - non-admin customers can now load chat history on refresh
- Add spawn/send-message, spawn/grant-support-access, and spawn/revoke-support-access abilities for customer-agent communication
- Add domain_registration webhook handler for existing customer domain purchases

### Removed
- Remove shield_router_ip from plugin (handled by provisioner)

## 0.6.10 - 2026-02-06

### Fixed
- Prevent duplicate customer records when user checks out twice with same email

## 0.6.9 - 2026-02-06

- Restructure README: SaaS mode first, self-spawn moved to bottom as experimental

## 0.6.8 - 2026-02-06

- Mark self-spawn as experimental with documented limitations
- Created GitHub issues #1 and #2 to track known problems

## 0.6.7 - 2026-02-06

- Update README: clarify self-spawn requires VPS, not shared hosting

## 0.6.6 - 2026-02-06

- Remove duplicate AI credential fields from Spawn settings (BYOK approach)
- Users configure OpenClaw directly or use wp-ai-client

## 0.6.5 - 2026-02-06

- Update README: document self-spawn credential options, remove bundled wp-ai-client references

## 0.6.4 - 2026-02-06

- Remove wp-ai-client from composer.json to prevent conflicts with plugin version

## 0.6.3 - 2026-02-06

- Make wp-ai-client optional: add AI credential settings directly to Spawn as fallback

## 0.6.2 - 2026-02-06

- Make Stripe plugin optional: remove hard dependency, gracefully handle missing Stripe in REST endpoints
- BYOD fix: pass domain_type to provisioner for bring-your-own-domain support

## 0.6.1 - 2026-02-06

- Fix documentation drift: README pricing (Pro=$20), add missing abilities/blocks, sync version constant, update cost-breakdown with final decisions
- Fix Pro tier credits: $10 → $20 (Config class was wrong, README was right)
- Make plugin fully configurable: add Branding class with settings for subdomain suffix, brand name, logo URL, and API base URL; remove all hardcoded saraichinwag.com references from runtime code
- Update README: document Branding settings, remove hardcoded domain references from architecture diagram
- fix: pass domain_type to provisioner for BYOD support

## [0.6.0] - 2026-02-06

### Added
- Password reset system with REST endpoints (`/auth/forgot-password`, `/auth/reset-password`)
- wp-login.php redirect for Spawn customers (always sent to `/spawn/login/`)
- Auth and Chat controller classes for better code organization

### Changed
- Moved dashboard API endpoints to Auth_Controller
- Organized chat functionality into Chat_Controller class
