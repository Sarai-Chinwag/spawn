<?php
/**
 * Cleanup operations for cancelled customers.
 *
 * Handles VPS deletion, DNS cleanup, and resource reclamation
 * after the cancellation grace period expires.
 *
 * @package Spawn
 */

declare(strict_types=1);

namespace Spawn;

/**
 * Handles cleanup of cancelled customer resources.
 */
class Cleanup {

	/**
	 * Grace period in days before resources are deleted.
	 */
	public const GRACE_PERIOD_DAYS = 7;

	/**
	 * Initialize cleanup hooks.
	 */
	public static function init(): void {
		// Register cron hook.
		add_action( 'spawn_process_deletions', array( __CLASS__, 'process_pending_deletions' ) );

		// Schedule cron if not already scheduled.
		if ( ! wp_next_scheduled( 'spawn_process_deletions' ) ) {
			wp_schedule_event( time(), 'hourly', 'spawn_process_deletions' );
		}
	}

	/**
	 * Process all customers pending deletion.
	 *
	 * Called by cron job hourly.
	 */
	public static function process_pending_deletions(): void {
		$customers = Database::get_customers_pending_deletion();

		foreach ( $customers as $customer ) {
			self::delete_customer_resources( $customer );
		}
	}

	/**
	 * Delete all resources for a customer.
	 *
	 * @param array $customer Customer data.
	 * @return bool Success.
	 */
	public static function delete_customer_resources( array $customer ): bool {
		$customer_id = (int) $customer['id'];
		$success     = true;

		error_log( sprintf( '[Spawn Cleanup] Processing deletion for customer #%d (%s)', $customer_id, $customer['domain'] ) );

		// Step 1: Delete VPS.
		if ( ! empty( $customer['provider_server_id'] ) || ! empty( $customer['server_id'] ) ) {
			$server_id  = $customer['provider_server_id'] ? $customer['provider_server_id'] : $customer['server_id'];
			$vps_result = self::delete_vps( $server_id );
			if ( ! $vps_result ) {
				error_log( sprintf( '[Spawn Cleanup] Failed to delete VPS %s for customer #%d', $server_id, $customer_id ) );
				$success = false;
			} else {
				error_log( sprintf( '[Spawn Cleanup] Deleted VPS %s for customer #%d', $server_id, $customer_id ) );
			}
		}

		// Step 2: Delete DNS record.
		if ( ! empty( $customer['cloudflare_record_id'] ) ) {
			$dns_result = self::delete_dns_record( $customer['cloudflare_record_id'] );
			if ( ! $dns_result ) {
				error_log( sprintf( '[Spawn Cleanup] Failed to delete DNS record for customer #%d', $customer_id ) );
				// Don't fail the whole operation for DNS issues.
			} else {
				error_log( sprintf( '[Spawn Cleanup] Deleted DNS record for customer #%d', $customer_id ) );
			}
		} elseif ( $customer['subdomain'] && ! empty( $customer['domain'] ) ) {
			// Try to delete by domain name if we don't have the record ID.
			$dns_result = self::delete_dns_record_by_name( $customer['domain'] );
			if ( $dns_result ) {
				error_log( sprintf( '[Spawn Cleanup] Deleted DNS record by name for customer #%d', $customer_id ) );
			}
		}

		// Step 3: Mark customer as deleted.
		if ( $success ) {
			Database::mark_deleted( $customer_id );
			error_log( sprintf( '[Spawn Cleanup] Customer #%d marked as deleted', $customer_id ) );

			// Send deletion confirmation email.
			self::send_deletion_email( $customer );
		}

		return $success;
	}

	/**
	 * Delete a Hetzner VPS.
	 *
	 * @param string $server_id Hetzner server ID.
	 * @return bool Success.
	 */
	private static function delete_vps( string $server_id ): bool {
		$hetzner_token = get_option( 'spawn_hetzner_api_token', '' );
		if ( empty( $hetzner_token ) ) {
			$hetzner_token = defined( 'SPAWN_HETZNER_API_TOKEN' ) ? SPAWN_HETZNER_API_TOKEN : '';
		}

		if ( empty( $hetzner_token ) ) {
			error_log( '[Spawn Cleanup] Hetzner API token not configured' );
			return false;
		}

		$response = wp_remote_request(
			"https://api.hetzner.cloud/v1/servers/{$server_id}",
			array(
				'method'  => 'DELETE',
				'headers' => array(
					'Authorization' => "Bearer {$hetzner_token}",
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( sprintf( '[Spawn Cleanup] Hetzner API error: %s', $response->get_error_message() ) );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		// 200 = deleted, 404 = already gone (both are success).
		return in_array( $code, array( 200, 204, 404 ), true );
	}

	/**
	 * Delete a Cloudflare DNS record by ID.
	 *
	 * @param string $record_id Cloudflare DNS record ID.
	 * @return bool Success.
	 */
	private static function delete_dns_record( string $record_id ): bool {
		$cf_token   = get_option( 'spawn_cloudflare_api_token', '' );
		$cf_zone_id = get_option( 'spawn_cloudflare_zone_id', '' );

		if ( empty( $cf_token ) ) {
			$cf_token = defined( 'SPAWN_CLOUDFLARE_API_TOKEN' ) ? SPAWN_CLOUDFLARE_API_TOKEN : '';
		}
		if ( empty( $cf_zone_id ) ) {
			$cf_zone_id = defined( 'SPAWN_CLOUDFLARE_ZONE_ID' ) ? SPAWN_CLOUDFLARE_ZONE_ID : '';
		}

		if ( empty( $cf_token ) || empty( $cf_zone_id ) ) {
			error_log( '[Spawn Cleanup] Cloudflare credentials not configured' );
			return false;
		}

		$response = wp_remote_request(
			"https://api.cloudflare.com/client/v4/zones/{$cf_zone_id}/dns_records/{$record_id}",
			array(
				'method'  => 'DELETE',
				'headers' => array(
					'Authorization' => "Bearer {$cf_token}",
					'Content-Type'  => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( sprintf( '[Spawn Cleanup] Cloudflare API error: %s', $response->get_error_message() ) );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return ! empty( $body['success'] );
	}

	/**
	 * Delete a Cloudflare DNS record by domain name.
	 *
	 * Used when we don't have the record ID stored.
	 *
	 * @param string $domain Full domain name.
	 * @return bool Success.
	 */
	private static function delete_dns_record_by_name( string $domain ): bool {
		$cf_token   = get_option( 'spawn_cloudflare_api_token', '' );
		$cf_zone_id = get_option( 'spawn_cloudflare_zone_id', '' );

		if ( empty( $cf_token ) ) {
			$cf_token = defined( 'SPAWN_CLOUDFLARE_API_TOKEN' ) ? SPAWN_CLOUDFLARE_API_TOKEN : '';
		}
		if ( empty( $cf_zone_id ) ) {
			$cf_zone_id = defined( 'SPAWN_CLOUDFLARE_ZONE_ID' ) ? SPAWN_CLOUDFLARE_ZONE_ID : '';
		}

		if ( empty( $cf_token ) || empty( $cf_zone_id ) ) {
			return false;
		}

		// First, find the record.
		$response = wp_remote_get(
			"https://api.cloudflare.com/client/v4/zones/{$cf_zone_id}/dns_records?" . http_build_query( array(
				'name' => $domain,
				'type' => 'A',
			) ),
			array(
				'headers' => array(
					'Authorization' => "Bearer {$cf_token}",
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['result'] ) ) {
			return true; // No record found, consider it success.
		}

		// Delete the record.
		$record_id = $body['result'][0]['id'];
		return self::delete_dns_record( $record_id );
	}

	/**
	 * Send deletion confirmation email to customer.
	 *
	 * @param array $customer Customer data.
	 */
	private static function send_deletion_email( array $customer ): void {
		$email   = $customer['email'];
		$domain  = $customer['domain'];
		$subject = sprintf( '[Spawn] Your site %s has been deleted', $domain );

		$message = sprintf(
			"Hello,\n\n" .
			"Your Spawn site at %s has been deleted as scheduled.\n\n" .
			"Your VPS and all associated data have been permanently removed.\n\n" .
			"If you exported your site before deletion, you can import it to any WordPress host.\n\n" .
			"Thank you for using Spawn. We hope to see you again!\n\n" .
			'- The Spawn Team',
			$domain
		);

		wp_mail( $email, $subject, $message );
	}

	/**
	 * Send cancellation confirmation with grace period info.
	 *
	 * @param array $customer Customer data.
	 */
	public static function send_cancellation_email( array $customer ): void {
		$email         = $customer['email'];
		$domain        = $customer['domain'];
		$deletion_date = $customer['scheduled_deletion_at'];
		$subject       = sprintf( '[Spawn] Cancellation confirmed for %s', $domain );

		$formatted_date = wp_date( 'F j, Y \a\t g:i A T', strtotime( $deletion_date ) );

		$message = sprintf(
			"Hello,\n\n" .
			"Your cancellation request for %s has been received.\n\n" .
			"IMPORTANT: Your site will be permanently deleted on %s.\n\n" .
			"Before that date, please export your site if you want to keep your content:\n" .
			"1. Log into your site's chat\n" .
			"2. Ask your AI: \"Export my site for download\"\n" .
			"3. Download the backup file\n\n" .
			"You can also access your files directly via SFTP - ask your AI for credentials.\n\n" .
			'Changed your mind? You can reactivate your subscription before the deletion date ' .
			"by visiting your account page or contacting support.\n\n" .
			'- The Spawn Team',
			$domain,
			$formatted_date
		);

		wp_mail( $email, $subject, $message );
	}

	/**
	 * Send reminder email before deletion.
	 *
	 * @param array $customer Customer data.
	 * @param int   $days_remaining Days until deletion.
	 */
	public static function send_deletion_reminder( array $customer, int $days_remaining ): void {
		$email         = $customer['email'];
		$domain        = $customer['domain'];
		$deletion_date = $customer['scheduled_deletion_at'];
		$subject       = sprintf( '[Spawn] %d days until %s is deleted', $days_remaining, $domain );

		$formatted_date = wp_date( 'F j, Y \a\t g:i A T', strtotime( $deletion_date ) );

		$message = sprintf(
			"Hello,\n\n" .
			"This is a reminder that your Spawn site %s will be permanently deleted in %d day(s).\n\n" .
			"Deletion date: %s\n\n" .
			"Please export your site NOW if you haven't already:\n" .
			"1. Log into your site's chat\n" .
			"2. Ask your AI: \"Export my site for download\"\n" .
			"3. Download the backup file\n\n" .
			"After deletion, your data cannot be recovered.\n\n" .
			"To keep your site, reactivate your subscription before the deletion date.\n\n" .
			'- The Spawn Team',
			$domain,
			$days_remaining,
			$formatted_date
		);

		wp_mail( $email, $subject, $message );
	}

	/**
	 * Unschedule the deletion cron on plugin deactivation.
	 */
	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( 'spawn_process_deletions' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'spawn_process_deletions' );
		}
	}
}
