# Admin

## Overview
Adds the Spawn admin menu, renders settings and customer list, and registers plugin options.

## Settings
Registered options:
- `spawn_stripe_price_starter`
- `spawn_stripe_price_pro`
- `spawn_stripe_price_business`
- `spawn_namecom_username`
- `spawn_namecom_token`
- `spawn_sweatpants_url`
- `spawn_sweatpants_token`
- `spawn_agent_type`
- `spawn_agent_url`
- `spawn_agent_password`
- `spawn_google_client_id`
- `spawn_google_client_secret`

## Methods
- `init(): void` registers menus and settings.
- `add_menu(): void` adds Spawn admin menus.
- `register_settings(): void` registers settings sections and fields.
- `render_settings_page(): void` renders settings UI.
- `render_customers_page(): void` renders customer list.

## Example
```php
add_action( 'admin_menu', [ \Spawn\Admin::class, 'add_menu' ] );
```
