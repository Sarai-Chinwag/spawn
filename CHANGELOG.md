# Changelog

All notable changes to the Spawn plugin will be documented in this file.

## Unreleased

- Add domain renewal abilities (spawn_get_domain_renewal_info, spawn_renew_domain)
- Create single source of truth for tier configuration

## [0.2.0] - 2025-02-09

### Changed
- **BREAKING**: Now requires `stripe-integration` plugin for Stripe functionality
- Refactored all Stripe API calls to use shared `StripeIntegration\StripeClient`
- Moved Stripe API key settings to stripe-integration plugin (Price IDs remain in Spawn settings)
- Webhooks now handled via stripe-integration hooks instead of dedicated endpoint
- Requires WordPress 6.9+ and PHP 8.1+

### Added
- `Payment_Helpers` class for Spawn-specific payment operations (credit purchases, auto-refill)
- Source metadata on all Stripe transactions for filtering in shared webhook handler

### Removed
- `class-stripe.php` - All Stripe API calls now go through stripe-integration plugin
- Duplicate webhook endpoint at `/spawn/v1/webhook/stripe` (use stripe-integration's endpoint)
- Stripe API key settings from Spawn admin (moved to stripe-integration)

## [0.1.0] - 2025-01-15

### Added
- Initial release
- Stripe integration for subscriptions and credit purchases
- VPS provisioning via Sweatpants
- Customer dashboard and billing management
- WordPress Abilities API integration
