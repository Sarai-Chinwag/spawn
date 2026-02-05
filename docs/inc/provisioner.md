# Provisioner

## Overview
Triggers VPS provisioning jobs in Sweatpants and processes completion callbacks.

## Configuration
- `spawn_sweatpants_url` (default: `http://127.0.0.1:8420`)
- `spawn_sweatpants_token`

## Methods
- `trigger(array $params): array|WP_Error` submits a `vps-provisioner` job.
- `get_status(string $job_id): array|WP_Error` retrieves job status.
- `handle_completion(array $data): bool` updates customer records after provisioning.

## Completion Handling
```php
if ( $success ) {
	$update_data = [ 'status' => 'active' ];
	if ( ! empty( $server_ip ) ) {
		$update_data['server_ip'] = $server_ip;
	}
	Database::update_customer( (int) $customer['id'], $update_data );
	do_action( 'spawn_provisioning_complete', $customer['id'], $domain, $server_ip );
}
```

## Provisioning Job Inputs
Payload sent to Sweatpants `/jobs`:
```php
$job_data = [
	'module_id' => 'vps-provisioner',
	'inputs'    => [
		'customer_email' => $params['customer_email'],
		'domain' => $params['domain'] ?? '',
		'subdomain' => $is_subdomain,
		'tier' => $tier,
		'wants_website' => $wants_website,
		'hetzner_type' => $server_config['hetzner_type'],
		'hetzner_location' => $server_config['location'],
		'skip_domain_registration' => $skip_domain_registration,
		'dry_run' => $is_test_mode,
	],
];
```

## Hooks and Filters
- `spawn_provisioning_complete` fires on success.
- `spawn_provisioning_failed` fires on failure.
