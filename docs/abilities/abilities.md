# Abilities

## Overview
Registers the Spawn abilities category and all abilities exposed through the Abilities API.

## Category
- `spawn` labeled “Spawn Service”.

## Registration
Abilities are registered via `wp_register_ability` on `wp_abilities_api_init`.

## Abilities
- `spawn_get_status` → `Ability_Get_Status::execute`
- `spawn_scale_vps` → `Ability_Scale_VPS::execute`
- `spawn_add_credits` → `Ability_Add_Credits::execute`
- `spawn_get_usage` → `Ability_Get_Usage::execute`
- `spawn_cancel` → `Ability_Cancel::execute`
- `spawn_export_site` → `Ability_Export_Site::execute`
- `spawn_manage_billing` → `Ability_Manage_Billing::execute`
- `spawn_set_auto_refill` → `Ability_Set_Auto_Refill::execute`
- `spawn_get_domain_renewal_info` → `Ability_Get_Domain_Renewal_Info::execute`
- `spawn_renew_domain` → `Ability_Renew_Domain::execute`

## Permissions
- `check_customer_permission( array $input ): bool` allows admins, or current user if they own the customer.
- `check_admin_permission( array $input ): bool` allows admins only.

## Example
```php
wp_register_ability( 'spawn_get_status', [
	'callback' => [ \Spawn\Abilities\Ability_Get_Status::class, 'execute' ],
] );
```
