# Google OAuth

## Overview
Implements Google OAuth login and account creation for Spawn customers.

## Constants
- `GOOGLE_AUTH_URL`
- `GOOGLE_TOKEN_URL`
- `GOOGLE_USERINFO_URL`

## Configuration
- `spawn_google_client_id`
- `spawn_google_client_secret`

## Methods
- `is_configured(): bool` returns whether credentials are set.
- `get_auth_url(): string` builds the OAuth authorization URL.
- `exchange_code_for_token( string $code ): array|WP_Error`
- `get_user_info( string $access_token ): array|WP_Error`
- `find_or_create_user( array $google_user ): int|WP_Error`

## Example
```php
if ( \Spawn\Google_OAuth::is_configured() ) {
	$auth_url = \Spawn\Google_OAuth::get_auth_url();
}
```
