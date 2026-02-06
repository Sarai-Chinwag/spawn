<?php
/**
 * VPS Provisioning via Sweatpants.
 *
 * @package Spawn
 */

namespace Spawn;

use WP_Error;

/**
 * Handles VPS provisioning by triggering Sweatpants jobs.
 */
class Provisioner {

	/**
	 * Get Sweatpants configuration.
	 *
	 * @return array{url: string, token: string} Configuration.
	 */
	private static function get_config(): array {
		return [
			'url'   => get_option( 'spawn_sweatpants_url', 'http://127.0.0.1:8420' ),
			'token' => get_option( 'spawn_sweatpants_token', '' ),
		];
	}

	/**
	 * Trigger VPS provisioning.
	 *
	 * @param array $params Provisioning parameters.
	 * @return array|WP_Error Job info or error.
	 */
	public static function trigger( array $params ): array|WP_Error {
		$config = self::get_config();

		if ( empty( $config['url'] ) ) {
			error_log( '[Spawn Provisioner] Sweatpants URL not configured' );
			return new WP_Error(
				'sweatpants_not_configured',
				__( 'Sweatpants is not configured', 'spawn' ),
				[ 'status' => 500 ]
			);
		}

		// Determine if domain registration should be skipped.
		// Skip for subdomains or BYOD (bring your own domain).
		$is_subdomain              = $params['subdomain'] ?? false;
		$domain_type               = $params['domain_type'] ?? ( $is_subdomain ? 'subdomain' : 'register' );
		$skip_domain_registration  = $is_subdomain || 'byod' === $domain_type;

		// Check if Stripe is in test mode - use dry_run for provisioner.
		$stripe_settings = get_option( 'stripe_integration_settings', [] );
		$is_test_mode    = ! empty( $stripe_settings['test_mode'] );

		// Get server config based on tier and website preference.
		$tier          = $params['tier'] ?? 'starter';
		$wants_website = isset( $params['wants_website'] ) ? (bool) $params['wants_website'] : true;
		$server_config = Config::get_server_config( $tier, $wants_website );

		if ( ! $server_config ) {
			error_log( sprintf( '[Spawn Provisioner] Invalid tier: %s, falling back to starter', $tier ) );
			$tier          = 'starter';
			$server_config = Config::get_server_config( $tier, $wants_website );
		}

		// Build the job request.
		$job_data = [
			'module_id' => 'vps-provisioner',
			'inputs'    => [
				'customer_email'           => $params['customer_email'],
				'domain'                   => $params['domain'] ?? '',
				'subdomain'                => $is_subdomain,
				'tier'                     => $tier,
				'wants_website'            => $wants_website,
				'hetzner_type'             => $server_config['hetzner_type'],
				'hetzner_location'         => $server_config['location'],
				'site_title'               => $params['site_title'] ?? '',
				'skip_domain_registration' => $skip_domain_registration,
				'dry_run'                  => $is_test_mode,
			],
		];

		if ( $is_test_mode ) {
			error_log( '[Spawn Provisioner] Test mode active - using dry_run' );
		}

		error_log( sprintf(
			'[Spawn Provisioner] Triggering job for %s (tier: %s, wants_website: %s, server: %s @ %s)',
			$params['domain'] ?? 'no-domain',
			$tier,
			$wants_website ? 'yes' : 'no',
			$server_config['hetzner_type'],
			$server_config['location']
		) );

		// Make request to Sweatpants API.
		$args = [
			'method'  => 'POST',
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => wp_json_encode( $job_data ),
			'timeout' => 30,
		];

		// Add auth token if configured.
		if ( ! empty( $config['token'] ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $config['token'];
		}

		$response = wp_remote_request( $config['url'] . '/jobs', $args );

		if ( is_wp_error( $response ) ) {
			error_log( sprintf( '[Spawn Provisioner] Request failed: %s', $response->get_error_message() ) );
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 ) {
			$message = $body['error'] ?? __( 'Failed to create provisioning job', 'spawn' );
			error_log( sprintf( '[Spawn Provisioner] API error %d: %s', $code, $message ) );
			return new WP_Error(
				'provisioning_failed',
				$message,
				[ 'status' => $code ]
			);
		}

		$job_id = $body['job_id'] ?? $body['id'] ?? null;

		// Store job ID with customer record.
		if ( ! empty( $params['customer_id'] ) && ! empty( $job_id ) ) {
			Database::update_customer( $params['customer_id'], [
				'server_id' => 'job:' . $job_id,
			] );
			error_log( sprintf( '[Spawn Provisioner] Job %s linked to customer #%d', $job_id, $params['customer_id'] ) );
		}

		return [
			'job_id' => $job_id,
			'status' => $body['status'] ?? 'queued',
		];
	}

	/**
	 * Check provisioning job status.
	 *
	 * @param string $job_id Sweatpants job ID.
	 * @return array|WP_Error Job status or error.
	 */
	public static function get_status( string $job_id ): array|WP_Error {
		$config = self::get_config();

		if ( empty( $config['url'] ) ) {
			return new WP_Error(
				'sweatpants_not_configured',
				__( 'Sweatpants is not configured', 'spawn' ),
				[ 'status' => 500 ]
			);
		}

		$args = [
			'method'  => 'GET',
			'headers' => [],
			'timeout' => 30,
		];

		if ( ! empty( $config['token'] ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $config['token'];
		}

		$response = wp_remote_request( $config['url'] . '/jobs/' . $job_id, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 ) {
			$message = $body['error'] ?? __( 'Failed to get job status', 'spawn' );
			return new WP_Error(
				'status_check_failed',
				$message,
				[ 'status' => $code ]
			);
		}

		return $body;
	}

	/**
	 * Handle provisioning completion webhook from Sweatpants.
	 *
	 * @param array $data Webhook data.
	 * @return bool Success.
	 */
	public static function handle_completion( array $data ): bool {
		$domain              = $data['domain'] ?? '';
		$server_ip           = $data['server_ip'] ?? $data['vps_ip'] ?? '';
		$server_id           = $data['server_id'] ?? '';
		$openclaw_token      = $data['openclaw_token'] ?? '';
		$cloudflare_record_id = $data['cloudflare_record_id'] ?? '';
		$wp_admin_password   = $data['wp_admin_password'] ?? '';
		$success             = $data['success'] ?? false;

		if ( empty( $domain ) ) {
			error_log( '[Spawn Provisioner] Completion webhook missing domain' );
			return false;
		}

		$customer = Database::get_customer_by_domain( $domain );
		if ( ! $customer ) {
			error_log( sprintf( '[Spawn Provisioner] No customer found for domain: %s', $domain ) );
			return false;
		}

		error_log( sprintf(
			'[Spawn Provisioner] Completion received for customer #%d (%s): success=%s',
			$customer['id'],
			$domain,
			$success ? 'true' : 'false'
		) );

		if ( $success ) {
			$update_data = [
				'status' => 'active',
			];

			if ( ! empty( $server_ip ) ) {
				$update_data['server_ip'] = $server_ip;
			}

			if ( ! empty( $server_id ) ) {
				$update_data['server_id'] = $server_id;
				$update_data['hetzner_server_id'] = $server_id;
			}

			if ( ! empty( $openclaw_token ) ) {
				$update_data['openclaw_token'] = $openclaw_token;
			}

			if ( ! empty( $cloudflare_record_id ) ) {
				$update_data['cloudflare_record_id'] = $cloudflare_record_id;
			}

			Database::update_customer( (int) $customer['id'], $update_data );

			error_log( sprintf(
				'[Spawn Provisioner] Customer #%d activated: IP=%s',
				$customer['id'],
				$server_ip
			) );

			// Send welcome email with WordPress credentials.
			self::send_welcome_email( $customer['email'], $domain, $wp_admin_password );

			// Notify admin of successful purchase.
			self::send_admin_purchase_notification( $customer, $domain, $server_ip );

			// Fire action for other integrations.
			do_action( 'spawn_provisioning_complete', $customer['id'], $domain, $server_ip );
		} else {
			$error_message = $data['error'] ?? 'Unknown error';

			Database::update_customer( (int) $customer['id'], [
				'status' => 'failed',
			] );

			error_log( sprintf(
				'[Spawn Provisioner] Customer #%d provisioning failed: %s',
				$customer['id'],
				$error_message
			) );

			// Send failure notification to admin.
			self::send_failure_notification( $customer['email'], $domain, $error_message );

			// Fire action for other integrations.
			do_action( 'spawn_provisioning_failed', $customer['id'], $domain, $error_message );
		}

		return true;
	}

	/**
	 * Send welcome email to new customer.
	 *
	 * @param string $email  Customer email.
	 * @param string $domain Customer domain.
	 */
	private static function send_welcome_email( string $email, string $domain, string $wp_admin_password = '' ): void {
		$subject = sprintf(
			/* translators: %s: domain name */
			__( 'Your website %s is ready!', 'spawn' ),
			$domain
		);

		// Build credentials section if password provided.
		$credentials = '';
		if ( ! empty( $wp_admin_password ) ) {
			$credentials = sprintf(
				"\n\nWORDPRESS LOGIN:\n" .
				"Admin URL: https://%s/wp-admin/\n" .
				"Username: admin\n" .
				"Password: %s\n" .
				"(Save this password - you won't receive it again)\n",
				$domain,
				$wp_admin_password
			);
		}

		$chat_url = home_url( '/chat/' );

		$message = sprintf(
			__(
				"Great news! Your AI-powered website is now live.\n\n" .
				"YOUR WEBSITE: https://%1\$s\n" .
				"%2\$s\n" .
				"TALK TO YOUR AI:\n" .
				"Visit %3\$s to chat with your AI assistant.\n" .
				"Your AI can help you:\n" .
				"- Build pages and write content\n" .
				"- Install plugins and customize your site\n" .
				"- Answer questions about WordPress\n" .
				"- Export your site anytime\n\n" .
				"You own your website and all your data. You can export everything\n" .
				"and move to any host at any time - no lock-in.\n\n" .
				"Welcome aboard!\n" .
				"- Sarai @ Spawn",
				'spawn'
			),
			$domain,
			$credentials,
			$chat_url
		);

		wp_mail( $email, $subject, $message );
	}

	/**
	 * Send failure notification to admin.
	 *
	 * @param string $email   Customer email.
	 * @param string $domain  Customer domain.
	 * @param string $error   Error message.
	 */
	private static function send_failure_notification( string $email, string $domain, string $error ): void {
		$admin_email = get_option( 'admin_email' );

		$subject = sprintf(
			/* translators: %s: domain name */
			__( '[Spawn] Provisioning failed for %s', 'spawn' ),
			$domain
		);

		$message = sprintf(
			/* translators: 1: domain, 2: email, 3: error */
			__(
				"VPS provisioning failed.\n\n" .
				"Domain: %1\$s\n" .
				"Customer: %2\$s\n" .
				"Error: %3\$s\n\n" .
				"Please investigate and contact the customer.",
				'spawn'
			),
			$domain,
			$email,
			$error
		);

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Send admin notification of successful purchase.
	 *
	 * @param array  $customer Customer data.
	 * @param string $domain   Customer domain.
	 * @param string $server_ip Server IP address.
	 */
	private static function send_admin_purchase_notification( array $customer, string $domain, string $server_ip ): void {
		$admin_email = get_option( 'admin_email' );
		$tier        = $customer['vps_tier'] ?? 'unknown';

		$subject = sprintf(
			/* translators: %s: domain name */
			__( '🎉 New Spawn Purchase: %s', 'spawn' ),
			$domain
		);

		$message = sprintf(
			__(
				"A new customer has completed their Spawn purchase!\n\n" .
				"Domain: %1\$s\n" .
				"Email: %2\$s\n" .
				"Tier: %3\$s\n" .
				"Server IP: %4\$s\n" .
				"Customer ID: %5\$d\n\n" .
				"The customer has received their welcome email with login credentials.",
				'spawn'
			),
			$domain,
			$customer['email'],
			ucfirst( $tier ),
			$server_ip,
			$customer['id']
		);

		wp_mail( $admin_email, $subject, $message );
	}
}
