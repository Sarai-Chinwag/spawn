# User Role

## Overview
Defines the `spawn_customer` role and hides wp-admin for customers.

## Role
`spawn_customer` with capabilities:
- `read`
- `spawn_set_domain_auto_renew`

## Methods
- `init(): void` registers filters/actions to hide admin bar and redirect.
- `register_role(): void` adds the role and capability.
- `unregister_role(): void` removes the role.
- `hide_admin_bar( bool $show_admin_bar ): bool`
- `redirect_admin(): void`

## Hooks and Filters
- `show_admin_bar`
- `admin_init`
