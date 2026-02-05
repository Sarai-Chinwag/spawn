# Changelog

All notable changes to the Spawn plugin will be documented in this file.

## Unreleased

- Add wants_website checkbox to checkout block for AI-only option

## [0.5.0] - 2025-02-05

### Added
- Google OAuth authentication for customer sign-in
- Admin settings for Google OAuth client ID/secret
- New REST endpoints: `/auth/google/configured`, `/auth/google`, `/auth/google/callback`
- "Sign in with Google" button on login block
- Domain renewal abilities (spawn_get_domain_renewal_info, spawn_renew_domain)
- Complete cancellation flow with 7-day grace period
- Cleanup class for VPS and DNS deletion after grace period
- Export-site ability for full/xml/database backups
- Database fields for cancellation tracking

### Changed
- Create single source of truth for tier configuration
- Update pricing: Starter $25/cpx21, Pro $50/cpx31, Business $100/cpx41

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
